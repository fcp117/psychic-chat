# Staging readiness

Staging is a production-like safety check, not a place for real payments or personal data. Use a separate database, domain, Resend key, Reverb application credentials, and Together key.

## Required environment

Set `APP_ENV=staging`, `APP_DEBUG=false`, a unique `APP_KEY`, an HTTPS `APP_URL`, `DB_CONNECTION=mysql`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, and `SESSION_SECURE_COOKIE=true`.

Set `MAIL_MAILER=resend` only after the staging sending domain is verified. Keep `STRIPE_ENABLED=false`, `PAYPAL_ENABLED=false`, `MAYA_ENABLED=false`, and `GCASH_ENABLED=false`: this release intentionally blocks all sandbox checkout in production and should not be used for real money.

For Reverb behind HTTPS, use `REVERB_SCHEME=https`, port `443`, and the public staging hostname for both `REVERB_HOST` and `VITE_REVERB_HOST`. Set `REVERB_ALLOWED_ORIGINS=https://your-staging-domain`; production intentionally accepts no WebSocket origin until this is set.

## Deployment order

1. Back up the staging database.
2. Install dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader`.
3. Install frontend dependencies with `npm ci` and build using `npm run build`.
4. Run `php artisan migrate --force`.
5. Run `php artisan optimize`.
6. Restart the queue worker, scheduler, and Reverb processes.
7. Run the acceptance checks below. Never use `migrate:fresh` outside a disposable local database.

## Acceptance checks

- Register two test accounts and verify their email OTPs.
- Apply for counselor access; approve it as an administrator.
- Request, accept, send messages in, and end a chat from two browsers/devices.
- Confirm billing, inactivity handling, notifications, photo upload, profile changes, password reset, forecasts, and admin credit adjustments.
- Check Isla: website answers, five educational-library requests, personal-reading refusal, and Together failure fallback.
- Confirm a scheduled `readings:settle` run and a `payments:reconcile` run complete without errors.

## Commands to validate locally

```powershell
php artisan config:clear
php artisan test
npm run build
php artisan route:list
php tests/StagingReadinessCheck.php
```

The standalone workflow checks in `tests/` are safe local fixtures; they do not call a live payment provider.
