# Counselor applications and recovery

## User workflow

A verified User account opens Become a Counselor beside the profile controls (or from mobile navigation). The application collects introduction, specialties, languages, years of experience, qualifications, and typical availability. Qualifications are private to the applicant and administrators. The remaining profile fields may be displayed after approval, as explained by the consent checkbox.

Only one application per account is stored. Pending applications cannot be duplicated. Declined applications can be edited and resubmitted; submission and review actions are audited. Approval requires an active, verified User with no pending or active reading. Approval changes the role to Counselor, sets approval, and optionally sets an hourly override. It does not change the user's existing balance.

Admin Settings > Counselor Applications lists pending reviews first. An admin selects approve or decline, supplies feedback, and confirms. Applicants receive an in-app notification. No approval emails or external messages are sent. Users cannot review applications or assign themselves roles.

Counselor profile links appear in Find Psychic. Profiles contain public application fields only; legacy counselors without an application show a clear introduction placeholder. Applications do not collect identity documents, payment details, or uploads. Availability is descriptive, not a booking calendar. Approval is an administrative decision, not a claim of verified professional licensing.

## Recovery features

- OTP page: server-derived 60-second resend countdown; email correction requires the current password and invalidates the previous address's code. Existing 10-minute expiry and five-attempt limits remain.
- Chat: connection status is distinct from paid reading state. Failed sends preserve text. Retrying reuses a UUID; the backend returns the existing message and rejects a changed payload. A saved message acknowledgement can be recovered after a reading ends.
- Payments: status cards distinguish pending, failed, cancelled, expired, setup failure and paid. Return-query flags are advisory only; verified provider responses decide payment state. Sandbox restrictions remain.
- Errors: branded 403/404/419/429/500/503 pages; failed Inertia mutations return validation-style errors to preserve forms instead of reporting success. JSON errors use safe messages. Global offline, loading, success and recovery notices are included.
- Notifications: reading requests, acceptance/end, low balances, paid credits and application decisions. The bell polls every 30 seconds and on window focus. Existing live counselor request prompts remain. Notifications are scoped to their owner; marking read cannot touch another user's entries.
- System Health: records reportable server exception class, named route, method and timestamp; at most one per class/route each minute. No request payload, exception text or credentials are stored in this table. This local record is not an external alerting or uptime-monitoring service.
- UI: public introduction for guests, shared page headers/notices, mobile navigation and dialog focus restoration. Logged-in verified users still see Home.

## Apply on another checkout

Back up the database, install locked dependencies, run `php artisan migrate`, and run `npm run build`. Clear route/config caches when needed. Restart long-running Reverb, scheduler and queue processes after deployment so they load the changed application code. Configure each environment's mail and payment secrets locally; do not commit `.env`.

## Verification

`php tests/RecoveryWorkflowCheck.php` uses temporary SQLite and fake email/broadcasting. Covers application permissions, duplicates, rejection/resubmission, open-reading protection, approval and audit, notification ownership, profile privacy, duplicate-message recovery, password-protected email correction, and error responses. Existing AuthWorkflowCheck, BillingWorkflowCheck and PaymentWorkflowCheck provide regression coverage. No real payments or outgoing emails are part of these checks.

External error alerts, counselor payouts, public document uploads and live payment activation are not configured by this change.