# Lists 4 and 5 — booking and privacy workflows

## User-facing entry points

- Bookings (signed-in, verified accounts): clients select a coach, 5/10/20/30/60-minute duration and UTC-backed time slot. Times display in their selected IANA time zone. Coaches add dated office-hour windows in their chosen office time zone (default Asia/Manila), or remove unbooked windows. No window means unavailable. Admin Settings has Appointments and Privacy requests entries.
- Profile → Privacy & account closure: password-confirmed account-closure requests, or transcript/attachment deletion requests. Admin review is required. A specific conversation ID is optional. Only the member's own conversations can be requested. Requests are paginated; admins see requests, not unrestricted transcript content.

## Reservations and readings

- Reservations hold actual purchase-lot units, without debiting them. Minutes must remain valid past the protected appointment window. Other bookings, instant readings and administrative deductions cannot spend held minutes. Client and coach rows serialize overlapping booking attempts. Slots require at least one hour's notice and are offered up to 90 days ahead.
- Ten calendar minutes are protected after the reading duration. This implementation buffer allows the approved ten-minute late-start grace without double-booking the next customer. Join at or after the start, up to ten minutes afterwards. Clients request the reading; coaches accept while the client remains connected. Merely booking or checking in never starts billing.
- A booked reading consumes only its held lots and ends at its agreed duration measured from actual acceptance. Unused reservation units are released, keeping their original expiry. No automatic overtime charge or cash payment. Start another explicitly approved reading for extra time. Instant readings are capped before either participant's next reserved appointment and cannot start inside an occupied appointment window.
- Reschedule or cancel at least one hour before the start for free. Rescheduling atomically replaces the original hold/appointment and cannot switch coaches. Coach/admin cancellation releases the reservation without penalty. Later client cancellations require review and do not automatically spend minutes.
- After ten minutes with no accepted session, bookings enter review. Admins may waive and release. A client no-show penalty requires a recorded coach check-in and no recorded client check-in. Review is one-time, audited and notified. Each purchased lot contributes 50% on its first approved no-show and 100% after a prior penalized no-show on the same purchase. Mixed-package portions are computed separately. Non-purchase balances contribute 50%; there is no invented cash earning for coaches. The admin sees the breakdown before approval. Expired held minutes cannot incur a new penalty.
- Coach check-in is evidence of a timestamp, not continuous attendance. Admins must investigate disputes before applying penalties. Penalty previews can change if a different booking from the same package is reviewed first; review runs again under the client lock.
- Rainbow-only mode governs new bookings and joining. If visibility changes for an already-booked coach, clients/admins can cancel; no balance is forfeited automatically.

## Transcript retention

- Only `messages` and their private attachment files are purged. Chat-session rows, purchase records, ledgers, support threads/messages, private coach notes and moderation/audit records are preserved.
- Retention is keyed to the client, not the coach's last activity. Clock: latest paid session start or confirmed purchase; the first remaining message establishes the baseline for a free-message-only account, and ensures newly retained messages never receive immediate deletion.
- During month 23, email and in-app notices give at least 30 days. Deletion occurs no earlier than month 24 AND 30 days after a successful mail-send attempt. Legacy overdue transcripts receive a fresh notice. Active/pending readings defer retention. Renewed paid activity resets the notice. New messages after a notice are excluded from that notice's snapshot.
- Failed email sends defer deletion. Production log/array mail transports refuse to start notices. Successful transport acceptance is not proof the member read the email; monitor delivery/bounces operationally. Set APP_URL to the correct deployment host before enabling scheduled mail.
- Attachment removal uses a durable private-file deletion queue. If storage is unavailable, file deletion retries on the next retention run. Database messages are removed first, making downloads inaccessible in the application. Backups and already exported copies are outside this process and require their own business-approved retention policy.
- Manual transcript requests snapshot message IDs when submitted, so admin approval does not delete messages written afterwards. Shared messages disappear for both participants; private notes do not appear in these requests or transcripts.

## Account closure

- Members explicitly acknowledge cancellation of bookings, unused-minute forfeiture and irreversible chat deletion, with their current password. Admins review and record a reason. Active readings and unresolved provider checkouts block approval. Admin accounts require a separate ownership-transfer process.
- Approved closure cancels unresolved bookings, removes all chat messages/attachments and profile photo, records the minute forfeiture, anonymizes the login identity and disables authentication. It retains a pseudonymous account key so financial/support/private-note relationships are not broken. This is NOT erasure of every operational record and is not a cash refund. Existing support and audit text can still contain personal information.
- Final handling of other operational records, lawful exceptions, backup retention, support contacts, company details and refund eligibility remains a business/legal publication task. Legal pages intentionally retain their draft markers and missing-business-details placeholders; implementation does not certify legal compliance.

## Deployment and checks

Local SQLite migration is applied after a `storage/app/pre-bookings-*.sqlite` backup. No Forge deployment or live payment configuration change was made. Do not run financial migrations backward; restore a verified backup deliberately.

Scheduler: `bookings:review` every minute (also settles readings), `transcripts:retain` daily at 03:00 in the configured app timezone. Existing minute-expiry and payment reconciliation schedules remain. The site must run Laravel's scheduler and have real email delivery configured for production notices. No real user transcript was deleted during development: destructive scenarios ran only in isolated temporary test databases and temporary attachment storage.

`tests/BookingPrivacyCheck.php` verifies reservations, overlap/buffer prevention, protected balances, rescheduling, cancellation, participant authorization, coach acceptance, capped billing, admin-only/idempotent penalties, same-package repeat penalties, excluded-record retention, advance notices, new-activity reset, snapshot deletion, closure authorization and all three role page payloads. Existing billing, minute-wallet and chat-tool checks are also retained.
