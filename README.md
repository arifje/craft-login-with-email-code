# Login With Email Code

Login With Email Code is a Craft CMS plugin for passwordless login. Existing active, unlocked users can request a short email code or a magic link, then log in without entering their username and password.

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
- `requestCooldownSeconds` limits how frequently a new token is generated for the same user and flow, including after successful redemption. Default: `60`.
- `invalidateExistingTokens` invalidates older unused tokens for the same user and login method when a new one is generated. Default: `true`.
- `rememberMeDuration` sets the login duration in seconds. Default: `0` for a normal session login.
- `successRedirect` is the fallback redirect after a successful login.
- `failureRedirect` is the fallback redirect after an invalid code or link.
- `codeEmailSubject`, `codeEmailBody`, `magicLinkEmailSubject`, and `magicLinkEmailBody` define the default Craft system-message content for outgoing emails.

Emails are sent through Craft system messages, so Craft's default/custom email template is used. The default subject/body text is translated for English and Dutch when the email is rendered, based on the recipient's preferred language or the current site mail language. After installation, the generated messages can also be customized from Craft's System Messages utility.

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

The emailed magic link opens a confirmation page showing the account email. Opening it does not consume the token or sign the visitor in. The visitor must submit the CSRF-protected confirmation form to sign in. The page prevents framing and sends no-cache and no-referrer headers.

## Two-step verification

On Craft 5, users with an active two-step verification method are sent to Craft's native verification screen after proving their email credential. A session is created only after the second factor succeeds. Craft's `disable2fa` configuration is respected. Craft 4 continues to use its normal session login API.

JSON clients must handle this HTTP 200 response before treating the request as a successful login:

```json
{
  "success": false,
  "requiresTwoFactor": true,
  "redirect": "https://example.com/actions/users/auth-form"
}
```

Navigate the browser to the returned `redirect`, including in app contexts. After verification, Craft returns the user to the configured login destination. The bundled Vue example handles this response. Third-party authentication extensions on Craft 4 are not integrated by this plugin.

## Vue Example

An adapted UIkit/Vue login component is included at `examples/LoginRegister.vue`. It keeps the existing password login, registration, and password-reset flows, and adds:

- email-code request and verification via `/actions/login-with-email-code/auth/request-code` and `/actions/login-with-email-code/auth/verify-code`
- magic-link requests via `/actions/login-with-email-code/auth/request-magic-link`
- JSON response handling and redirect support for web/app contexts

The example assumes `axios`, UIkit, the local `LoadingIndicator.vue` component, and Craft CSRF globals named `window.csrfTokenName` and `window.csrfTokenValue`.

## Notes

- Only existing active, unlocked users can log in. Users required to reset their password must complete that reset first.
- The request actions always show generic success messaging, even when the email address is unknown.
- Tokens are stored hashed and are consumed when the email credential is verified, before session creation or any second-factor challenge.
- A valid code or magic link is a login credential. Keep expiry short and make sure your site uses HTTPS.

## Security and storage

- Issuance and redemption use the same Craft mutex key per user and login method. Keep Craft's mutex configured with a shared backend on multi-server installations; the default database-backed driver is suitable. Lock acquisition failure fails closed.
- Token replacement is transactional. Email delivery happens after the token transaction commits; a delivery failure still counts toward the request cooldown.
- Expired tokens are deleted during later token issuance once their request cooldown has also ended. There is no background cleanup job. User deletion cascades to their tokens.
- Settings use Craft's standard plugin settings permissions. Public authentication actions intentionally allow anonymous requests; POST actions retain Craft's CSRF protection.

## Development

Run the isolated security suite inside an existing Craft 4 or Craft 5 PHP container with this checkout mounted:

```bash
CRAFT_VENDOR_AUTOLOAD=/path/to/craft/vendor/autoload.php php tests/security.php
```

It requires PDO SQLite and `proc_open`, uses disposable files under the container's temporary directory, and sends no email. It exercises concurrent issuance/redemption, attempt limits, cooldowns, account restrictions, CSRF rejection, and the Craft 5 second-factor handoff using test users and a test session. It does not replace end-to-end testing of a real authenticator and mail delivery.

To bootstrap an existing Craft test installation and render the confirmation page with its native Twig environment:

```bash
CRAFT_TEST_ROOT=/path/to/craft php tests/native-smoke.php
```

## Upgrading to 1.0.5

No schema migration or settings changes are required. Existing unexpired magic links open the new confirmation page. Deploy all application instances together so old workers cannot bypass the new locking behavior.

Update custom JSON clients for `requiresTwoFactor` before deployment. Custom magic-link integrations must use the confirmation flow rather than expecting a GET to create a session. Existing locked users and users required to reset their passwords can no longer use passwordless login until those restrictions are resolved.
