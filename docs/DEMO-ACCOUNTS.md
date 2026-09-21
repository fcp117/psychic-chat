# Development demo accounts

Use this only on a local development/testing installation after configuring .env and installing dependencies:

```powershell
php artisan migrate
php artisan db:seed --class=DemoAccountsSeeder
```

Keep APP_ENV=local for local development. The seeder refuses other environments except testing, even with --force. Do not change a production environment to bypass this protection.

For each missing account, enter and confirm a password privately in the terminal. Input is hidden and is never stored in this document, source code, or environment template. Passwords follow the registration password policy. Noninteractive creation is intentionally unsupported.

| Username | Email | Role | Initial credits |
| --- | --- | --- | --- |
| testuser | abc@chat.com | User | 10 |
| testcounselor | counselor@chat.com | Counselor | 0 |
| admin | admin@chat.com | Admin | 0 |

New demo accounts are email-verified. The demo counselor is approved and uses the default hourly rate. New demo birthdates use the fictional adult date 1990-01-01. These are test identities, not real people or a test of email delivery. Account IDs are assigned by the receiving database.

Reruns preserve existing accounts, including their passwords, credits, roles, birthdates, and verification status. If a username/email is already associated with a different identity or role, setup stops instead of changing that account. The initial ten credits are recorded once in credit transaction history. Account creation is transactional.

This seeder is not called automatically by DatabaseSeeder. Use the explicit command above; the repository's older generic seeders have different behavior. No chats, photos, purchases, or other local records are transferred. Never use migrate:fresh to update an existing client database.

After setup, run .\start.bat and sign in with the passwords you entered. Share passwords privately with authorized testers. Existing accounts do not receive a new password from this command; use the normal password-reset flow if needed.