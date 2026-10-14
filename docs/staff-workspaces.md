# Staff workspaces

The existing design and content editors are shared. Menu visibility is not the security boundary: permissions are also checked on server routes and sensitive actions.

## Setup

1. Run migrations and build assets on deployment.
2. In Admin Settings → Accounts → Edit access, assign an existing verified account one of: Virtual Encoder / Assistant, Manager, Support. Public registration cannot select a staff role.
3. For each coach, choose their assigned Manager in the same form. Managers can see every coach's schedule, but can only approve coach reports for their assigned coaches. Reports against regular users are available to Managers.
4. Staff open their workspace from the profile menu.

## Permissions

- Encoder / Assistant: edit and publish Birth Chart interpretations, Moonoscope content, and Daily Tarot content.
- Manager: all content permissions, all-coach calendar, assigned-coach and user report recommendations.
- Support: private support inbox, read-only system health, privacy assessments, report recommendations, and service emails.
- Admin: all existing administration and final restriction/deletion decisions.

Reports follow Support → Manager → Admin. Support and Manager decisions do not change access. Manager can return a recommendation. Admin can apply the proposed action or dismiss it; Admin cannot silently substitute another action. The final approver must differ from the Support reviewer and Manager. A resolved report can receive a new recommendation, for example to restore access; prior actions remain in the audit log.

Suspension and ban both deny account access until Admin restores it. No automatic expiry is configured. Exact-IP blocking is separate, with existing public-IP and shared-address safeguards; this never blocks an entire ISP.

Support privacy assessments leave requests pending. Only Admin can approve actual transcript deletion or account closure.

## Service email

- Four fixed service templates: support, privacy, account, appointment.
- Verified, non-closed recipients from any role; suspended users can still receive service notices.
- Preview and confirmation required. Each recipient gets an independent email.
- Maximum 10 recipients per send, 50 emails per sender per rolling 24 hours.
- Duplicate submissions with the same request key do not resend.
- Emails use the site's configured mail provider and queue. Keep the normal Laravel queue worker running in Forge. Restart workers after deployments using the existing deployment process.
- History states: queued, sending, accepted, failed, cancelled. Accepted is mail-provider acceptance, not a confirmed delivery/read receipt. No delivery-webhook integration is claimed.
- Permission and current recipient address are checked again when the job runs. Failed or interrupted sends are not retried automatically, to avoid duplicates after ambiguous provider timeouts. Check the provider before issuing a new send.
- Do not put passwords, card information, or private reading transcripts into email details.

## Verification

Run the standalone StaffRolesCheck.php and ModerationCheck.php checks. They use temporary SQLite databases, fake queues, and an in-memory mail transport; no real service messages are sent.
