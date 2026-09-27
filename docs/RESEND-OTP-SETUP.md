# Resend OTP setup

Intuition Island already sends a six-digit email verification code after registration and when a signed-in member requests another code. The same mail configuration also sends password-reset links.

## Resend account steps

1. In Resend, add and verify the client-owned domain that will send email, such as `mail.example.com` or `example.com`.
2. Add the DNS records Resend provides in the client’s DNS account. Wait until Resend marks the domain as verified.
3. Create a restricted Resend API key for the production site.

## Environment settings

Add these only to the ignored local `.env` file or Laravel Forge environment settings. Never commit the real key.

```dotenv
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="noreply@your-verified-domain.com"
MAIL_FROM_NAME="Intuition Island"
RESEND_API_KEY=re_your_real_key
```

The sender address must belong to the verified Resend domain. After changing production environment values, clear Laravel's configuration cache and restart the queue worker if one is running.

## Test before launch

1. Register with a real inbox that you control.
2. Confirm the six-digit code arrives, including in Gmail/Outlook inbox rather than spam.
3. Confirm the code succeeds once, expires after 10 minutes, and rejects five incorrect attempts.
4. Confirm the resend button unlocks after 60 seconds and sends a new code.
5. Confirm the wrong-email correction flow sends the new code only to the updated address.
6. Confirm the password-reset email arrives and its link works.

Until `MAIL_MAILER=resend` and `RESEND_API_KEY` are configured, the app deliberately shows a clear delivery-configuration message rather than claiming an OTP was sent.
