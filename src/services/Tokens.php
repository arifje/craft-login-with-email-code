<?php

namespace arifje\loginwithemailcode\services;

use arifje\loginwithemailcode\LoginWithEmailCode;
use arifje\loginwithemailcode\models\LoginResult;
use Craft;
use craft\base\Component;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\Template;
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

        if (!$user) {
            return false;
        }

        $code = $this->generateNumericCode((int)$settings->codeLength);
        if (!$this->issueToken((int)$user->id, self::TYPE_CODE, $code, (int)$settings->codeExpiryMinutes, $redirect)) {
            return false;
        }

        return $this->sendEmail($user, LoginWithEmailCode::CODE_EMAIL_KEY, [
            'code' => $code,
            'expires' => (string)$settings->codeExpiryMinutes,
            'email' => (string)$user->email,
            'siteName' => Craft::$app->getSystemName(),
        ]);
    }

    public function sendMagicLink(string $email, ?string $redirect = null): bool
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $user = $this->findLoginableUser($email);

        if (!$user) {
            return false;
        }

        $token = $this->generateMagicToken();
        if (!$this->issueToken((int)$user->id, self::TYPE_MAGIC_LINK, $token['verifier'], (int)$settings->magicLinkExpiryMinutes, $redirect, $token['selector'])) {
            return false;
        }

        $link = UrlHelper::actionUrl('login-with-email-code/auth/magic-link', [
            'loginToken' => $token['selector'] . ':' . $token['verifier'],
        ]);

        return $this->sendEmail($user, LoginWithEmailCode::MAGIC_LINK_EMAIL_KEY, [
            'link' => Template::raw($link),
            'expires' => (string)$settings->magicLinkExpiryMinutes,
            'email' => (string)$user->email,
            'siteName' => Craft::$app->getSystemName(),
        ]);
    }

    public function consumeCode(string $email, string $code): ?LoginResult
    {
        $user = $this->findLoginableUser($email);
        $code = trim($code);

        if (!$user || $code === '') {
            return null;
        }

        return $this->withTokenLock((int)$user->id, self::TYPE_CODE, fn() => $this->consumeCodeForUser((int)$user->id, $code));
    }

    private function consumeCodeForUser(int $userId, string $code): ?LoginResult
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();
        $user = Craft::$app->getUsers()->getUserById($userId);
        if (!$user || !$this->isLoginableUser($user)) {
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
                return $this->markUsed((int)$row['id'])
                    ? new LoginResult($user, $this->normalizeSiteRedirect($row['redirect'] ?? null))
                    : null;
            }

            $this->incrementAttempts((int)$row['id']);
        }

        return null;
    }

    public function consumeMagicLink(string $token): ?LoginResult
    {
        $parts = $this->magicLinkParts($token);
        if ($parts === null) {
            return null;
        }

        // Only discover the lock key here. Re-read and verify under the same lock as issuance.
        $row = $this->findMagicLink($parts[0]);
        if (!$row) {
            return null;
        }

        return $this->withTokenLock((int)$row['userId'], self::TYPE_MAGIC_LINK, function () use ($parts): ?LoginResult {
            $row = $this->findMagicLink($parts[0]);
            if (!$row) {
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

            return $this->markUsed((int)$row['id'])
                ? new LoginResult($user, $this->normalizeSiteRedirect($row['redirect'] ?? null))
                : null;
        });
    }

    /**
     * Preview a valid link without consuming it or creating a session.
     * The POST confirmation must independently verify the token again.
     */
    public function previewMagicLink(string $token): ?LoginResult
    {
        $parts = $this->magicLinkParts($token);
        if ($parts === null) {
            return null;
        }

        $row = $this->findMagicLink($parts[0]);
        if (!$row || !Craft::$app->getSecurity()->validatePassword($parts[1], (string)$row['verifierHash'])) {
            return null;
        }

        $user = Craft::$app->getUsers()->getUserById((int)$row['userId']);
        return $user && $this->isLoginableUser($user) ? new LoginResult($user) : null;
    }

    private function magicLinkParts(string $token): ?array
    {
        if (!preg_match('/\A([a-f0-9]{18}):([A-Za-z0-9_-]{43})\z/', trim($token), $matches)) {
            return null;
        }

        return [$matches[1], $matches[2]];
    }

    private function findMagicLink(string $selector): array|false
    {
        return (new Query())
            ->from(self::TABLE)
            ->where(['selector' => $selector, 'type' => self::TYPE_MAGIC_LINK, 'usedAt' => null])
            ->andWhere(['>', 'expiresAt', $this->nowForDb()])
            ->andWhere(['<', 'attempts', LoginWithEmailCode::$plugin->getSettings()->maxAttempts])
            ->one();
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

    public function isLoginableUser(User $user): bool
    {
        return $user->email && $user->getStatus() === User::STATUS_ACTIVE
            && !$user->locked && !$user->passwordResetRequired;
    }

    private function withTokenLock(int $userId, string $type, callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();
        $name = "login-with-email-code:$userId:$type";
        if (!$mutex->acquire($name, 5)) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $mutex->release($name);
        }
    }

    private function issueToken(int $userId, string $type, string $verifier, int $expiryMinutes, ?string $redirect, ?string $selector = null): bool
    {
        return (bool)$this->withTokenLock($userId, $type, function () use ($userId, $type, $verifier, $expiryMinutes, $redirect, $selector): bool {
            $user = Craft::$app->getUsers()->getUserById($userId);
            if (!$user || !$this->isLoginableUser($user) || $this->isRequestThrottled($userId, $type)) {
                return false;
            }

            Craft::$app->getDb()->transaction(function () use ($userId, $type, $verifier, $expiryMinutes, $redirect, $selector): void {
                $this->createToken($userId, $type, $verifier, $expiryMinutes, $redirect, $selector);
            });
            return true;
        });
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
            ])
            ->andWhere(['>', 'dateCreated', Db::prepareDateForDb($threshold)])
            ->exists();
    }

    private function purgeExpiredTokens(): void
    {
        $seconds = max(0, LoginWithEmailCode::$plugin->getSettings()->requestCooldownSeconds);
        $threshold = (new DateTime('now', new DateTimeZone('UTC')))->modify('-' . $seconds . ' seconds');

        Craft::$app->getDb()->createCommand()
            ->delete(self::TABLE, ['and',
                ['<', 'expiresAt', $this->nowForDb()],
                ['<=', 'dateCreated', Db::prepareDateForDb($threshold)],
            ])
            ->execute();
    }

    private function markUsed(int $id): bool
    {
        $now = $this->nowForDb();

        return Craft::$app->getDb()->createCommand()
            ->update(self::TABLE, [
                'usedAt' => $now,
                'dateUpdated' => $now,
            ], ['and',
                ['id' => $id, 'usedAt' => null],
                ['>', 'expiresAt', $now],
                ['<', 'attempts', LoginWithEmailCode::$plugin->getSettings()->maxAttempts],
            ])
            ->execute() === 1;
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
     * @param array<string, mixed> $variables
     */
    private function sendEmail(User $user, string $messageKey, array $variables): bool
    {
        try {
            return Craft::$app->getMailer()
                ->composeFromKey($messageKey, $variables)
                ->setTo($user)
                ->send();
        } catch (Throwable $exception) {
            Craft::error('Could not send passwordless login email: ' . $exception->getMessage(), __METHOD__);

            return false;
        }
    }
}
