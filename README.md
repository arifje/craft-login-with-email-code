# Login With Email Code

Login With Email Code is a Craft CMS plugin for passwordless login. Existing active users can request a short email code or a magic link, then log in without entering their username and password.

The same 1.x line supports Craft CMS 4 and 5.

## Requirements

- Craft CMS 4.0 or 5.0+
- PHP 8.0.2+
- A working Craft mailer configuration

## Installation

```bash
composer require arifje/craft-login-with-email-code
php craft plugin/install login-with-email-code
```

## Configuration

Settings can be managed in the Craft control panel or overridden with `config/login-with-email-code.php`.

```php
<?php

return [
    'codeExpiryMinutes' => 10,
    'magicLinkExpiryMinutes' => 15,
    'codeLength' => 6,
    'maxAttempts' => 5,
    'requestCooldownSeconds' => 60,
    'successRedirect' => '/account',
    'failureRedirect' => '/login',
];
```

Available settings:

- `allowEmailCodes` enables email-code login. Default: `true`.
- `allowMagicLinks` enables magic-link login. Default: `true`.
- `codeLength` sets the numeric login code length. Default: `6`.
- `codeExpiryMinutes` sets how long email codes remain valid. Default: `10`.
- `magicLinkExpiryMinutes` sets how long magic links remain valid. Default: `15`.
- `maxAttempts` limits attempts per token before it is effectively unusable. Default: `5`.
- `requestCooldownSeconds` limits how frequently a new token is generated for the same user and flow. Default: `60`.
- `invalidateExistingTokens` invalidates older unused tokens for the same user and login method when a new one is generated. Default: `true`.
- `rememberMeDuration` sets the login duration in seconds. Default: `0` for a normal session login.
- `successRedirect` is the fallback redirect after a successful login.
- `failureRedirect` is the fallback redirect after an invalid code or link.
- `codeEmailSubject`, `codeEmailBody`, `magicLinkEmailSubject`, and `magicLinkEmailBody` define the default Craft system-message content for outgoing emails.

Emails are sent through Craft system messages, so Craft's default/custom email template is used. The default subject/body text is translated for English and Dutch, based on Craft's active mail language. After installation, the generated messages can also be customized from Craft's System Messages utility.

Default email content supports these placeholders:

- `{siteName}`
- `{email}`
- `{code}` for code emails
- `{link}` for magic-link emails
- `{expires}` in minutes

## Email Code Flow

Create a form that requests a code:

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('login-with-email-code/auth/request-code') }}
    {{ redirectInput('/login/check-email') }}

    <label for="email">Email</label>
    <input id="email" type="email" name="email" autocomplete="email" required>

    <button type="submit">Send login code</button>
</form>
```

Create a form that verifies the code:

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('login-with-email-code/auth/verify-code') }}
    {{ redirectInput('/account') }}

    <label for="email">Email</label>
    <input id="email" type="email" name="email" autocomplete="email" required>

    <label for="code">Code</label>
    <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required>

    <button type="submit">Log in</button>
</form>
```

## Magic Link Flow

Create a form that requests a magic link:

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('login-with-email-code/auth/request-magic-link') }}
    {{ redirectInput('/login/check-email') }}

    <input type="hidden" name="loginRedirect" value="/account">

    <label for="email">Email</label>
    <input id="email" type="email" name="email" autocomplete="email" required>

    <button type="submit">Send magic link</button>
</form>
```

The emailed magic link points at the plugin action and logs the user in when the token is valid.

## Vue Example

An adapted UIkit/Vue login component is included at `examples/LoginRegister.vue`. It keeps the existing password login, registration, and password-reset flows, and adds:

- email-code request and verification via `/actions/login-with-email-code/auth/request-code` and `/actions/login-with-email-code/auth/verify-code`
- magic-link requests via `/actions/login-with-email-code/auth/request-magic-link`
- JSON response handling and redirect support for web/app contexts

The example assumes `axios`, UIkit, the local `LoadingIndicator.vue` component, and Craft CSRF globals named `window.csrfTokenName` and `window.csrfTokenValue`.

## Notes

- Only existing active users can log in.
- The request actions always show generic success messaging, even when the email address is unknown.
- Tokens are stored hashed and are marked used after successful login.
- A valid code or magic link is a login credential. Keep expiry short and make sure your site uses HTTPS.
