<?php

namespace arifje\loginwithemailcode\models;

use craft\base\Model;

class Settings extends Model
{
    public bool $allowEmailCodes = true;
    public bool $allowMagicLinks = true;
    public int $codeLength = 6;
    public int $codeExpiryMinutes = 10;
    public int $magicLinkExpiryMinutes = 15;
    public int $maxAttempts = 5;
    public int $requestCooldownSeconds = 60;
    public bool $invalidateExistingTokens = true;
    public int $rememberMeDuration = 0;
    public string $successRedirect = '';
    public string $failureRedirect = '';
    public string $codeEmailSubject = 'Your login code';
    public string $codeEmailBody = "Your login code is {code}.\n\nThis code expires in {expires} minutes.";
    public string $magicLinkEmailSubject = 'Your login link';
    public string $magicLinkEmailBody = "Log in with this secure link:\n\n{link}\n\nThis link expires in {expires} minutes.";

    public function rules(): array
    {
        return [
            [['allowEmailCodes', 'allowMagicLinks', 'invalidateExistingTokens'], 'boolean'],
            [['codeLength'], 'integer', 'min' => 4, 'max' => 12],
            [['codeExpiryMinutes', 'magicLinkExpiryMinutes'], 'integer', 'min' => 1, 'max' => 1440],
            [['maxAttempts'], 'integer', 'min' => 1, 'max' => 50],
            [['requestCooldownSeconds', 'rememberMeDuration'], 'integer', 'min' => 0],
            [['successRedirect', 'failureRedirect'], 'string', 'max' => 2048],
            [['codeEmailSubject', 'codeEmailBody', 'magicLinkEmailSubject', 'magicLinkEmailBody'], 'string'],
        ];
    }
}
