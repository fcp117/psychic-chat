# Security hardening — October 11, 2026

- Chat sending: 30 attempts/minute per authenticated account, independent of heartbeat/report quotas.
- Attachments: 5 MB per file; 50 MB per rolling 24 hours and 250 MB retained per sender across conversations. Both clients and coaches are covered. Configure byte allowances in `config/chat.php`.
- Existing attachment rows with unknown sizes conservatively count as 5 MB each. No existing messages or files are removed. Text remains available at the upload limit.
- Upload quota checks run while participant rows are locked. Failed transactions remove newly stored files.
- Chat history initially loads the latest 50 messages, with a participant-only, rate-limited older-message endpoint and a compact Load earlier messages button.
- Password recovery uses a neutral response for unknown addresses, successful requests, cooldowns and delivery errors; five requests/minute per guest IP.
- Development account/chat seeders refuse non-local/non-testing environments before writing data. Existing local accounts are untouched.
- PayMongo callback now has an exact CSRF exemption and a fixed server-side provider. Signature verification remains required. Sandbox settings and live-key rejection are unchanged.

## Deployment

Run the new `2026_10_11_000001_add_attachment_bytes_to_messages` migration before serving the updated code, and rebuild frontend assets. The migration has already been applied locally. Do not run development seeders in production.

These changes do not enable live payments. Client credentials and a separately verified live-payment implementation are still required before taking real payments.

## Verification

Standalone isolated checks: `tests/SecurityHardeningCheck.php`, `tests/ConversationToolsCheck.php`, `tests/PaymentWorkflowCheck.php`. They use temporary SQLite data; payment tests use fixtures, not provider network calls. Security checks exercise the actual HTTP webhook route with CSRF enabled. Additional PHPUnit regression cases are in `tests/Feature/SecurityHardeningTest.php` for environments with PHPUnit installed.

This is targeted hardening, not a complete penetration test. Production MySQL concurrency, VPS/firewall settings, backups, dependency advisories and external PayMongo delivery still need deployment verification.
