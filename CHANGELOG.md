# Changelog

## 1.0.5 - 2026-10-07

- Require Craft 5's native two-step verification after email-code or magic-link verification when the user has an active second factor.
- Serialize token issuance and redemption with Craft's mutex service to enforce cooldowns, attempt limits, and single use under concurrent requests.
- Reject locked accounts and accounts requiring a password reset when issuing or redeeming login credentials.
- Require an explicit, CSRF-protected confirmation showing the account email before magic-link login; opening the email link no longer consumes it.
- Keep request cooldowns in effect after token use, and retain expired token records until their cooldown has also ended.
- Update the Vue example to follow the two-step verification redirect, including app contexts.

## 1.0.4 - 2026-06-20

- Render translated default email subjects and bodies in the recipient/site mail language.

## 1.0.3 - 2026-06-20

- Added translated default email subjects and bodies for English and Dutch system-message emails.

## 1.0.2 - 2026-06-20

- Send login code and magic link emails through Craft system messages so Craft's default/custom email template is used.

## 1.0.1 - 2026-06-19

- Fixed Craft 4 Composer plugin metadata so the Craft plugin installer can determine the plugin class.
- Fixed magic links to avoid Craft's reserved `token` query parameter.

## 1.0.0 - 2026-06-19

- Initial release.
- Added email-code login for existing active Craft users.
- Added magic-link login for existing active Craft users.
- Added configurable expiry, attempts, request cooldown, redirect, and email templates.
