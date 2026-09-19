# Credit shop and sandbox payments

## Use the shop

Admin Settings → Pricing & Billing → Credit purchases controls custom peso pricing, the minimum purchase and credit packages. The initial custom price is PHP 1.00 per credit, minimum PHP 1.00. One active package offers 1 credit for PHP 1.00. Add a package with a name, whole-credit quantity, total peso price and visibility. Packages below the shop minimum are hidden. Manual admin credit adjustments remain available.

Prices are integer centavos. Accepted purchases snapshot their price and credit quantity. Changing catalog prices does not rewrite existing purchases. Client-provided totals are checked against server quotes before checkout. The purchase limit is PHP 100,000 and 100,000 credits.

## Connection status

All providers are disabled by default. The application currently supports SANDBOX ONLY. No real payment was made during development. Configure test credentials in the local .env using the blank names in .env.example, then run `php artisan config:clear`. Never put secrets in frontend code, admin forms, source control, screenshots or chat.

- PayPal: sandbox client ID, secret and webhook ID, then PAYPAL_ENABLED=true. Register /payment-webhooks/paypal for CHECKOUT.ORDER.APPROVED and PAYMENT.CAPTURE.COMPLETED. The approved order is captured server-side with an idempotency key.
- Stripe: sk_test_ secret and test webhook signing secret, then STRIPE_ENABLED=true. Register /payment-webhooks/stripe for checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed and checkout.session.expired. Live keys and live responses are rejected.
- Maya: sandbox public and secret API keys, then MAYA_ENABLED=true. Register /payment-webhooks/maya for PAYMENT_SUCCESS, PAYMENT_FAILED, PAYMENT_EXPIRED and PAYMENT_CANCELLED. Only documented sandbox source IPs are allowed, followed by API verification. Configure trusted proxies correctly if hosting behind a proxy; do not allow arbitrary forwarded IP headers.

Set APP_URL to the actual externally reachable HTTPS website URL before testing provider callbacks. Localhost is not reachable by payment providers. A public HTTPS test deployment or development tunnel is needed for webhooks. No tunnel or external deployment has been created automatically.

Keep `php artisan schedule:work` running. Authenticated webhooks flag saved purchases for reconciliation and return promptly; the scheduler checks pending purchases every minute, at most 20 per run. The return page also checks the payment through an owner-only POST endpoint. A return URL, a screenshot, or an unverified callback never adds credits. All successful credits are recorded once under the unique purchase ID in the ledger. Test top-ups are labeled sandbox_topup.

The configured provider minimums start at 100 centavos and can be raised in server configuration. Provider/account/settlement-currency restrictions may impose a higher actual minimum or reject a payment. A package can remain available while a particular payment method cannot process it.

## Testing and activation

`php tests/PaymentWorkflowCheck.php` runs against a temporary SQLite database with mocked HTTP APIs and blocks external requests. It covers pricing, hidden packages, unauthorized access, amount/currency/reference verification, sandbox restrictions and duplicate fulfillment. `php tests/BillingWorkflowCheck.php` covers the existing billing/admin workflow.

No provider end-to-end sandbox checkout has been verified yet because merchant credentials are not configured. Once credentials are available, test hosted checkout, abandonment, failed payments, delayed/duplicate callbacks, minimum amounts and displayed balances for each provider before enabling customer use.

Use separate test users/databases for sandbox purchases. Sandbox credits are spendable within this development app but have no monetary value. Do not carry test balances into a live deployment. Live payment activation, merchant eligibility, real-money refunds, chargebacks and production accounting require a separate implementation/review; the existing reading refund returns app credits only.

Official references:
- https://developer.paypal.com/sandbox-testing/accounts
- https://developer.paypal.com/api/orders/v2
- https://docs.stripe.com/api/checkout/sessions/create
- https://docs.stripe.com/currencies
- https://developers.maya.ph/reference/createv1checkout
- https://developers.maya.ph/reference/getpaymentviapaymentid-1
- https://developers.maya.ph/reference/configuring-your-webhook-for-maya-checkout
