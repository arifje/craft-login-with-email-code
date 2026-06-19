<?php

namespace arifje\loginwithemailcode;

use arifje\loginwithemailcode\models\Settings;
use arifje\loginwithemailcode\services\Tokens;
use Craft;
use craft\base\Model;
use craft\base\Plugin;

class LoginWithEmailCode extends Plugin
{
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
}
