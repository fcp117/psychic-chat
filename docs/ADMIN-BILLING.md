# Administration and reading credits

Sign in with an administrator account and open **Admin Settings**. The navigation and server both enforce roles. New registrations are Users. Admins can approve Counselors, assign roles, suspend accounts, change pricing, add/remove credits with a reason, refund completed readings, review sessions and audit history, and draft or schedule forecasts.

Defaults: 60 credits/hour, 1-credit minimum, 30-second disconnect timeout. These are editable. Existing counselors retain their converted minute rates as hourly overrides; clear the override to use the default. Earnings are credits, not cash or payouts. Payment processing is not included.

## Running locally

Keep each command running in its own terminal at the project root:

```powershell
php artisan serve
php artisan reverb:start
php artisan schedule:work
```

Open http://127.0.0.1:8000. The scheduler checks abandoned readings every ten seconds; it is required for automatic cleanup when browsers close. Build frontend changes with `npm run build`, or use `npm run dev` while editing.

## Billing rules

Users agree to the counselor's hourly price before requesting a reading. Charging starts only on counselor acceptance. Each reading keeps its agreed price and disconnect timeout. Notification preferences are per counselor; a changed price requires renewed agreement even when the popup was disabled.

Balances use integer units (3,600 units per credit). Charges use elapsed whole seconds and the agreed hourly price. Both chat browsers poll every five seconds. Presence reflects recent pointer, keyboard, touch, or scroll interaction on the visible chat page. Polling alone does not reset activity. The default 30-second inactivity/disconnect limit remains editable in Admin Settings. Normal settlement bills only time confirmed by both participants. Disconnect grace is unbilled. Explicit ending while connected bills through the end time. Insufficient funds close the reading without allowing a negative balance. Pending requests expire after ten minutes.

Every deduction, admin adjustment, and refund is recorded. Refunds reduce net counselor earnings. A full refund returns any fractional remainder. Use Admin Settings to adjust balances; do not directly increment the legacy available_credits database column. Account deletion is prevented where needed to preserve transaction/session history.

The upgrade preserves existing balances and archives old open readings as unbilled history. A SQLite backup was created before applying the upgrade in storage/app/private/backups. Restoring that backup discards subsequent changes; this financial migration deliberately refuses destructive rollback.

## Verification

```powershell
php tests/BillingWorkflowCheck.php
npm run build
```

The standalone integration check creates and removes a temporary SQLite database. It checks consent, acceptance, exact charging, rate snapshots, repeated settlement, disconnects, zero balance, refunds, role restrictions and HTTP pages. It never uses the live database for fixtures or billing checks. Browser appearance and simultaneous real browser connections still require a manual check.

## Persistent conversations and assistant placeholder

Chat shows one conversation per User/Counselor pair. Existing messages are grouped automatically, with a stable URL using the first reading ID. Continuing creates a fresh pending billing segment internally while preserving the visible conversation. This keeps agreed rates, earnings, and refunds auditable. Administrative session records remain separate by accepted reading.

Automatic end notices are stored once in the conversation. The user can request to continue from that same page. Counselors receive private Reverb notifications throughout the signed-in site; a ten-second polling fallback also checks requests. Charging resumes only after acceptance. The chat API omits billing totals for counselors; Earnings remains the place for those totals. Users see remaining credits directly above the reply bar.

The responsive Assistant panel is a UI placeholder only. It has no RAG integration, API call for assistant messages, or message submission. The home hero adapts to viewport size with cover cropping and an adjustable focal point in resources/css/app.css.
