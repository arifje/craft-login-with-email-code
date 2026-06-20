<?php

namespace arifje\loginwithemailcode;

use arifje\loginwithemailcode\models\Settings;
use arifje\loginwithemailcode\services\Tokens;
use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterEmailMessagesEvent;
use craft\helpers\App;
use craft\services\SystemMessages;
use yii\base\Event;

class LoginWithEmailCode extends Plugin
{
    public const CODE_EMAIL_KEY = 'login_with_email_code_code';
    public const MAGIC_LINK_EMAIL_KEY = 'login_with_email_code_magic_link';

    public static ?LoginWithEmailCode $plugin = null;

    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->setComponents([
            'tokens' => Tokens::class,
        ]);

        $this->registerSystemMessages();
    }

    public function getTokens(): Tokens
    {
        return $this->get('tokens');
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('login-with-email-code/_settings.twig', [
            'settings' => $this->getSettings(),
        ]);
    }

    private function registerSystemMessages(): void
    {
        Event::on(
            SystemMessages::class,
            SystemMessages::EVENT_REGISTER_MESSAGES,
            function(RegisterEmailMessagesEvent $event): void {
                /** @var Settings $settings */
                $settings = $this->getSettings();

                $event->messages[] = [
                    'key' => self::CODE_EMAIL_KEY,
                    'heading' => Craft::t('login-with-email-code', 'When someone requests a login code'),
                    'subject' => $this->systemMessageTemplate((string)$settings->codeEmailSubject),
                    'body' => $this->systemMessageTemplate((string)$settings->codeEmailBody),
                ];

                $event->messages[] = [
                    'key' => self::MAGIC_LINK_EMAIL_KEY,
                    'heading' => Craft::t('login-with-email-code', 'When someone requests a magic login link'),
                    'subject' => $this->systemMessageTemplate((string)$settings->magicLinkEmailSubject),
                    'body' => $this->systemMessageTemplate((string)$settings->magicLinkEmailBody),
                ];
            }
        );
    }

    private function systemMessageTemplate(string $template): string
    {
        return strtr(Craft::t('login-with-email-code', App::parseEnv($template)), [
            '{siteName}' => '{{ siteName }}',
            '{email}' => '{{ email }}',
            '{code}' => '{{ code }}',
            '{link}' => '{{ link }}',
            '{expires}' => '{{ expires }}',
        ]);
    }
}
