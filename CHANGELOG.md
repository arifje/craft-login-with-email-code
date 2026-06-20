# Changelog

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
