# Changelog

## 1.0.1 - 2026-06-19

- Fixed Craft 4 Composer plugin metadata so the Craft plugin installer can determine the plugin class.
- Fixed magic links to avoid Craft's reserved `token` query parameter.

## 1.0.0 - 2026-06-19

- Initial release.
- Added email-code login for existing active Craft users.
- Added magic-link login for existing active Craft users.
- Added configurable expiry, attempts, request cooldown, redirect, and email templates.
