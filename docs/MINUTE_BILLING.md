# Minute billing — approved List 3

New sessions use billing_version 2. All coaches consume one balance minute per reading minute. Integer storage remains 3600 units per minute; compatibility column and route names still say credits. Connected time is settled incrementally, without rounding each poll. At termination the session total rounds upward once to a whole minute, limited to the available balance (no debt). Disconnection grace is not billed. Existing version-1 sessions retain their snapshotted rate and second-based rules.

## Fixed PHP prices

Initial conversion: BSP PHP62.766/USD, October 8, 2026, https://www.bsp.gov.ph/statistics/external/day99_data.aspx. Prices are fixed, admin-editable, not live exchange quotes.

| Package | Minutes | USD reference | PHP |
| --- | ---: | ---: | ---: |
| Welcome | 5 | 5.55 | 348.35 |
| Quick Insight | 10 | 25.00 | 1,569.15 |
| Clarity | 20 | 45.00 | 2,824.47 |
| Blueprint | 30 | 60.00 | 3,765.96 |
| Deep Dive | 60 | 115.00 | 7,218.09 |
| Extra time, per minute | 1 | 2.99 | 187.67 |

Extra time is a separate one-time upfront PayMongo checkout, with explicit amount/minute consent. It is NOT a card hold, postpaid metering, automatic renewal, or automatic top-up. Payment verification remains sandbox-only. No live payments have been enabled.

## Wallet and records

- Existing balances convert 1:1, including fractions, into non-expiring lots. A zero-amount conversion ledger entry records the event; historic rows keep their credits label and original amounts.
- New confirmed purchases create one lot per purchase, expiring 365 days after confirmation. Soonest-expiring lots are consumed first, then non-expiring balances. Pending pre-migration purchases receive non-expiring 1:1 minutes when paid.
- Expiry runs at access and through minutes:expire every minute. The Laravel scheduler must be running in production, as for reading settlement/payment reconciliation.
- Admin adjustments and reading refunds create non-expiring minutes. Refunds reduce version-appropriate coach totals and cannot exceed net charged time. They do not issue cash refunds.
- New coach reports show delivered minutes separately from historical credit earnings. Neither is a cash payout promise; no payout formula has been invented.
- Welcome reserves a claim when checkout is created, with a unique identity hash for normalized email and normalized name + birthdate when present. Claims survive account deletion. This is duplicate deterrence, not verified real-world identity; changed names/details can evade it and matching details can create false positives. Admins can disable Welcome eligibility in Edit access, but cannot reset a used claim there.
- A failed/cancelled Welcome checkout retains its claim because a provider payment may still complete. Reuse its recent-purchase checkout where available. Otherwise support must verify provider state before manually resolving eligibility; do not blindly delete claims or create duplicate discounted payments.
- Old rate preferences are reset to require new agreement; unconfirmed old invitations expire at migration. Active and pending legacy sessions are not rewritten.

## Local verification and deployment

MinuteWalletCheck covers fractional migration, no repeat conversion, round-once billing, legacy billing, expiry, FIFO spending, separate earnings, explicit payment consent, stale price rejection, Welcome duplicate checks, payment idempotency, and 365-day lots. PaymentWorkflowCheck uses mocked provider responses only. BillingWorkflowCheck covers refunds, exhaustion, privacy and admin routes. ConversationToolsCheck covers free messaging/invitations/notes/uploads.

Before production: back up the database, deploy code and frontend assets together with migrations, restart workers, and confirm the scheduler. The migration is intentionally forward-only: financial rollback requires a verified backup, not dropping minute tables. Do not run seeding/reset commands against existing customer data. Local migration was backed up under storage/app/pre-minute-*.sqlite; production has not been modified. Browser visual QA and real merchant checkout are separate from these automated checks.
