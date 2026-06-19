<?php

namespace arifje\loginwithemailcode\services;

use arifje\loginwithemailcode\LoginWithEmailCode;
use arifje\loginwithemailcode\models\LoginResult;
use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\App;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use DateTime;
use DateTimeZone;
use Throwable;
use yii\db\Expression;
use yii\db\Query;

class Tokens extends Component
{
    private const TABLE = '{{%loginwithemailcode_tokens}}';
    private const TYPE_CODE = 'code';
    private const TYPE_MAGIC_LINK = 'magic_link';

    public function sendLoginCode(string $email, ?string $redirect = null): bool
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $user = $this->findLoginableUser($email);

        if (!$user || $this->isRequestThrottled((int)$user->id, self::TYPE_CODE)) {
            return false;
        }

        $code = $this->generateNumericCode((int)$settings->codeLength);
        $this->createToken((int)$user->id, self::TYPE_CODE, $code, (int)$settings->codeExpiryMinutes, $redirect);

        return $this->sendEmail(
            $user,
            $this->renderTemplate((string)$settings->codeEmailSubject, [
                'code' => $code,
                'expires' => (string)$settings->codeExpiryMinutes,
                'email' => (string)$user->email,
            ]),
            $this->renderTemplate((string)$settings->codeEmailBody, [
                'code' => $code,
                'expires' => (string)$settings->codeExpiryMinutes,
                'email' => (string)$user->email,
            ])
        );
    }

    public function sendMagicLink(string $email, ?string $redirect = null): bool
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $user = $this->findLoginableUser($email);

        if (!$user || $this->isRequestThrottled((int)$user->id, self::TYPE_MAGIC_LINK)) {
            return false;
        }

        $token = $this->generateMagicToken();
        $this->createToken((int)$user->id, self::TYPE_MAGIC_LINK, $token['verifier'], (int)$settings->magicLinkExpiryMinutes, $redirect, $token['selector']);

        $link = UrlHelper::actionUrl('login-with-email-code/auth/magic-link', [
            'loginToken' => $token['selector'] . ':' . $token['verifier'],
        ]);

        return $this->sendEmail(
            $user,
            $this->renderTemplate((string)$settings->magicLinkEmailSubject, [
                'link' => $link,
                'expires' => (string)$settings->magicLinkExpiryMinutes,
                'email' => (string)$user->email,
            ]),
            $this->renderTemplate((string)$settings->magicLinkEmailBody, [
                'link' => $link,
                'expires' => (string)$settings->magicLinkExpiryMinutes,
                'email' => (string)$user->email,
            ])
        );
    }

    public function consumeCode(string $email, string $code): ?LoginResult
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $user = $this->findLoginableUser($email);
        $code = trim($code);

        if (!$user || $code === '') {
            return null;
        }

        $rows = (new Query())
            ->from(self::TABLE)
            ->where([
                'userId' => (int)$user->id,
                'type' => self::TYPE_CODE,
                'usedAt' => null,
            ])
            ->andWhere(['>', 'expiresAt', $this->nowForDb()])
            ->orderBy(['id' => SORT_DESC])
            ->all();

        foreach ($rows as $row) {
            if ((int)$row['attempts'] >= (int)$settings->maxAttempts) {
                continue;
            }

            if (Craft::$app->getSecurity()->validatePassword($code, (string)$row['verifierHash'])) {
                $this->markUsed((int)$row['id']);

                return new LoginResult($user, $this->normalizeSiteRedirect($row['redirect'] ?? null));
            }

            $this->incrementAttempts((int)$row['id']);
        }

        return null;
    }

    public function consumeMagicLink(string $token): ?LoginResult
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $parts = explode(':', trim($token), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        $row = (new Query())
            ->from(self::TABLE)
            ->where([
                'selector' => $parts[0],
                'type' => self::TYPE_MAGIC_LINK,
                'usedAt' => null,
            ])
            ->andWhere(['>', 'expiresAt', $this->nowForDb()])
            ->one();

        if (!$row || (int)$row['attempts'] >= (int)$settings->maxAttempts) {
            return null;
        }

        if (!Craft::$app->getSecurity()->validatePassword($parts[1], (string)$row['verifierHash'])) {
            $this->incrementAttempts((int)$row['id']);

            return null;
        }

        $user = Craft::$app->getUsers()->getUserById((int)$row['userId']);
        if (!$user || !$this->isLoginableUser($user)) {
            return null;
        }

        $this->markUsed((int)$row['id']);

        return new LoginResult($user, $this->normalizeSiteRedirect($row['redirect'] ?? null));
    }

    public function normalizeSiteRedirect(?string $url): ?string
    {
        $url = trim((string)$url);

        if ($url === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $url)) {
            return null;
        }

        return $url;
    }

    private function findLoginableUser(string $email): ?User
    {
        $email = trim($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $user = Craft::$app->getUsers()->getUserByUsernameOrEmail($email);

        return $user && $this->isLoginableUser($user) ? $user : null;
    }

    private function isLoginableUser(User $user): bool
    {
        return $user->email && $user->getStatus() === User::STATUS_ACTIVE;
    }

    private function createToken(int $userId, string $type, string $verifier, int $expiryMinutes, ?string $redirect = null, ?string $selector = null): void
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $this->purgeExpiredTokens();

        if ($settings->invalidateExistingTokens) {
            Craft::$app->getDb()->createCommand()
                ->delete(self::TABLE, [
                    'userId' => $userId,
                    'type' => $type,
                    'usedAt' => null,
                ])
                ->execute();
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));
        $expiresAt = (clone $now)->modify('+' . max(1, $expiryMinutes) . ' minutes');

        Craft::$app->getDb()->createCommand()
            ->insert(self::TABLE, [
                'userId' => $userId,
                'type' => $type,
                'selector' => $selector ?: bin2hex(random_bytes(12)),
                'verifierHash' => Craft::$app->getSecurity()->generatePasswordHash($verifier),
                'redirect' => $this->normalizeSiteRedirect($redirect),
                'expiresAt' => Db::prepareDateForDb($expiresAt),
                'attempts' => 0,
                'dateCreated' => Db::prepareDateForDb($now),
                'dateUpdated' => Db::prepareDateForDb($now),
                'uid' => StringHelper::UUID(),
            ])
            ->execute();
    }

    private function generateNumericCode(int $length): string
    {
        $length = max(4, min(12, $length));
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string)random_int(0, 9);
        }

        return $code;
    }

    /**
     * @return array{selector:string, verifier:string}
     */
    private function generateMagicToken(): array
    {
        return [
            'selector' => bin2hex(random_bytes(9)),
            'verifier' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
        ];
    }

    private function isRequestThrottled(int $userId, string $type): bool
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $seconds = max(0, (int)$settings->requestCooldownSeconds);

        if ($seconds === 0) {
            return false;
        }

        $threshold = (new DateTime('now', new DateTimeZone('UTC')))->modify('-' . $seconds . ' seconds');

        return (new Query())
            ->from(self::TABLE)
            ->where([
                'userId' => $userId,
                'type' => $type,
                'usedAt' => null,
            ])
            ->andWhere(['>', 'dateCreated', Db::prepareDateForDb($threshold)])
            ->exists();
    }

    private function purgeExpiredTokens(): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete(self::TABLE, ['<', 'expiresAt', $this->nowForDb()])
            ->execute();
    }

    private function markUsed(int $id): void
    {
        $now = $this->nowForDb();

        Craft::$app->getDb()->createCommand()
            ->update(self::TABLE, [
                'usedAt' => $now,
                'dateUpdated' => $now,
            ], ['id' => $id])
            ->execute();
    }

    private function incrementAttempts(int $id): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(self::TABLE, [
                'attempts' => new Expression('[[attempts]] + 1'),
                'dateUpdated' => $this->nowForDb(),
            ], ['id' => $id])
            ->execute();
    }

    private function nowForDb(): string
    {
        return Db::prepareDateForDb(new DateTime('now', new DateTimeZone('UTC')));
    }

    /**
     * @param array<string, string> $variables
     */
    private function renderTemplate(string $template, array $variables): string
    {
        $variables = array_merge([
            'siteName' => Craft::$app->getSystemName(),
        ], $variables);

        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{' . $key . '}'] = $value;
        }

        return strtr(App::parseEnv($template), $replace);
    }

    private function sendEmail(User $user, string $subject, string $body): bool
    {
        try {
            return Craft::$app->getMailer()
                ->compose()
                ->setTo([(string)$user->email => $this->friendlyName($user)])
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } catch (Throwable $exception) {
            Craft::error('Could not send passwordless login email: ' . $exception->getMessage(), __METHOD__);

            return false;
        }
    }

    private function friendlyName(User $user): string
    {
        if (method_exists($user, 'getFriendlyName')) {
            $name = trim((string)$user->getFriendlyName());

            if ($name !== '') {
                return $name;
            }
        }

        return (string)$user->email;
    }
}
