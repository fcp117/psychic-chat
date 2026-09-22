# Initial administrator on staging or production

After deploying the code and applying migrations, open an interactive SSH terminal as the site owner, change to the active application directory, and run:

```bash
php artisan app:create-admin
```

Enter full name, username, email, and birthdate (YYYY-MM-DD, at least 18). Enter and confirm a strong password at the hidden prompts. Review the identity and environment, then explicitly answer yes to create the administrator. The default answer is no.

This command works in staging and production without changing APP_ENV. It creates a NEW account only, uses role admin, and marks the email verified through the User model's existing verification method. Because this is a trusted server-side bootstrap, no OTP is sent. Confirm ownership and accuracy of the intended administrator's email yourself.

Existing usernames or emails are rejected; no account is promoted, reset, or overwritten. No credits or counselor approval are granted. Passwords are hashed and are never printed. Password entry refuses terminals that cannot hide input. Noninteractive execution is rejected; do not place this command in deployment scripts or pass secrets as command arguments.

Use an SSH terminal if Forge's command runner does not support interactive hidden input. If the command succeeds, sign in normally at the website. For an existing account, use the normal access-management or recovery process rather than this command.

Checks: php tests/CreateAdminCommandCheck.php (isolated temporary SQLite; no actual accounts changed).