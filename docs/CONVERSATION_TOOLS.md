# Conversation tools and free messaging

## User flow
- Users select **Send a free private message** in the coach directory. Opening this conversation creates no paid reading and requires no credit balance.
- Both participants can exchange messages/images/PDFs offline. Messages outside an active reading are free. An active paid reading continues billing independently of whether participants type.
- Coaches use **Invite to paid reading**. The invitation shows a snapshotted rate and expires after 24 hours. A new invitation supersedes an older unconfirmed invitation for the same pair.
- The client explicitly agrees and confirms. This creates a pending reading using the existing server-side rate/balance checks. The coach must accept with the client connected; no charges are made for an invitation or a pending request.
- The existing client-initiated request flow remains available. Active readings end explicitly, on disconnection, or on exhausted credits, not AFK. Free messages remain available afterward.
- Rainbow-only mode restricts new paid requests/invitations and new coach contacts, but preserves existing conversations and active readings.

## Chat tools
- Date separators plus visible message times use the viewer's local time. Transcripts explicitly use UTC.
- Elapsed and estimated remaining time use server-confirmed billing seconds and the client's balance at the agreed hourly rate. Both roles get the same timer data; coaches do not receive the client's full wallet balance.
- A visible warning appears at 90 seconds or less. The client can open Add Credits in a new tab. No automatic purchase occurs.
- Coaches enable a short browser-generated sound in the navigation bar. Browser interaction is required. A request ID is recorded locally after sounding to prevent repeat ringing during polling/revisits. Sound permission/enablement is needed again after a page remount/reload; visuals remain available without sound.
- Private notes are explicitly saved per coach/client pair, and only that coach can read/write them. They are never included in broadcasts, client payloads, or transcripts.
- Transcript view, clipboard copy, and UTF-8 .txt downloads require participant membership. Admins have no blanket export access. Attachment filenames are listed; binary files are not embedded in the export.
- JPG/JPEG, PNG, WebP, and PDF files up to 5 MB are MIME/extension checked. Stored under the private local disk and downloaded through an authenticated participant-authorized endpoint, not public URLs. Files are downloads rather than inline active documents. No antivirus scanner is configured; production malware scanning/storage quotas are an additional hardening option.

## Support
- Support navigation contains separate technical and credit conversations. Each account has one persistent thread per category.
- Users and coaches can leave free support messages; admins can review/reply through Admin Settings → Support inbox.
- Other accounts cannot read these threads. Coach-to-client messages remain in Chat, not the admin support inbox.
- Inbox lists paginate at 12 threads; messages paginate at 30. Notifications link to private conversations and do not include message content.

## Deployment and checks
- Run `php artisan migrate --force` with the normal deployment process before serving the new build. Migration: `2026_10_09_140000_add_conversation_tools`.
- Run the normal frontend build. Keep `storage/app/private` persistent across Forge releases and writable by the application. Do not expose it through the public storage link.
- Local isolated checks: `tests/ConversationToolsCheck.php`, `tests/BillingWorkflowCheck.php`, `tests/RecoveryWorkflowCheck.php`, `tests/CoachSiteCheck.php`, `tests/CoachReviewsCheck.php`.
- Browser acceptance: use client and coach accounts to send offline messages; invite/confirm/accept; verify the same timer, low-balance warning, sound enable/mute, notes isolation, attachment download, and transcript copy. Confirm support categories and replies with a separate admin account.
- No production deployment or pricing model change is included in this revision.

## Reference screenshot
The supplied counselor screenshot shows an empty coach inbox with client-facing discovery prompts. The empty state is now role-aware: coaches see guidance about receiving client messages/requests, private notes, and reading invitations. It does not invent a deeper server issue based solely on that screenshot.
