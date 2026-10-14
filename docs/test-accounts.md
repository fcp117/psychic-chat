# Six-role testing accounts

Do not delete the current database. This setup creates missing QA accounts without resetting existing accounts, passwords, balances, roles, content, or readings.

Run these commands from the project directory. On Forge, connect to the **test server** using interactive SSH first. Commands are the same on Mac, Windows, and Linux.

```sh
php artisan migrate
php artisan db:seed --class=RoleTestAccountsSeeder
```

Confirm that this is a disposable test database, then enter and confirm a password for each missing account. Password entry is hidden; passwords are not stored in this file or committed to Git.

| Role | Username | Test email |
| --- | --- | --- |
| User | qa_user | qa.user@example.invalid |
| Coach | qa_coach | qa.coach@example.invalid |
| Virtual Encoder / Assistant | qa_encoder | qa.encoder@example.invalid |
| Manager | qa_manager | qa.manager@example.invalid |
| Support | qa_support | qa.support@example.invalid |
| Admin | qa_admin | qa.admin@example.invalid |

New accounts are pre-verified. The new QA Coach is approved and assigned to QA Manager. The new QA User receives 30 non-cash testing minutes. Existing matches are preserved, including suspensions and coach assignments. Identity conflicts stop setup rather than overwrite an account.

The accounts are defined in database/seeders/RoleTestAccountsSeeder.php. The .invalid email addresses intentionally cannot receive real mail. To test email delivery, replace them with addresses you control **before the first run**; changing identities later requires resolving existing-account conflicts.

This seeder only runs in local, testing, or staging environments. It refuses production even with --force. Do not relabel a live customer deployment to bypass the guard. Use a separate staging site/database, keep APP_DEBUG=false on public staging, and restrict access to testers.

If you need an entirely fresh test database, create a separate empty database, point the isolated test deployment to it, and run the commands above. Migrations create tables; this seeder only creates accounts. No destructive reset command is required.

Coach directory visibility still follows Rainbow-only settings. Admin must configure that separately if testers need QA Coach visible. Availability, bookings, reports, and content are not fabricated.

After testing, disable the QA accounts from Admin Settings. Never reuse test passwords for real accounts.
