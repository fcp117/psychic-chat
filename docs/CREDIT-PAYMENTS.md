# Credit shop and PayMongo sandbox payments

## What is connected

The application uses one provider: **PayMongo**. The hosted PayMongo Checkout page displays only the payment methods configured in `PAYMONGO_PAYMENT_METHOD_TYPES`. The starter setting is `card`; use a comma-separated list such as `card,gcash,paymaya` only after those methods have been activated for the merchant account.

No PayPal, Stripe, standalone Maya, or standalone GCash integration remains in the application. PayMongo manages the customer payment screen and merchant settlement.

## Test setup

Keep checkout in sandbox while staging:

```env
PAYMONGO_ENABLED=true
PAYMONGO_TEST_SECRET=sk_test_...
PAYMONGO_TEST_WEBHOOK_SECRET=...
PAYMONGO_PAYMENT_METHOD_TYPES=gcash
PAYMONGO_MINIMUM_CENTAVOS=100
```

Clear cached configuration after updating environment settings. Create one PayMongo **test-mode** webhook for:

```text
https://YOUR-STAGING-DOMAIN/payment-webhooks/paymongo
```

Subscribe it to `checkout_session.payment.paid`, copy its signing secret to `PAYMONGO_TEST_WEBHOOK_SECRET`, and keep the scheduler running. A public HTTPS staging URL is required; PayMongo cannot reach `localhost`.

The webhook is only a prompt to reconcile the saved purchase. Credits are issued only after the server uses the authenticated PayMongo API to confirm the matching checkout ID, reference number, PHP amount, configured payment type, test mode, and paid status. Each purchase can create credit ledger entries only once.

## Safety rules

- Never place secret keys in source code, frontend code, screenshots, or Git.
- Use only `sk_test_...` credentials for this staging implementation.
- Sandbox checkout is deliberately blocked when `APP_ENV=production`.
- A real-money release needs a separate reviewed change: live PayMongo keys, live webhook, production callback testing, refund/chargeback procedure, and explicit production safety approval.

Run `php tests/PaymentWorkflowCheck.php` for mocked, isolated payment regression checks. It makes no external request and never uses your credentials.
