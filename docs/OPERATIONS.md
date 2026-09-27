# Operations, backup, and rollback

## Before every release

1. Record the current Git commit and create a database backup.
2. Confirm that `APP_DEBUG=false` and payment providers remain disabled.
3. Deploy code, build assets, run `php artisan migrate --force`, then restart queue workers, scheduler, and Reverb.
4. Perform the staging acceptance checks in `STAGING-READINESS.md`.

## Rollback

If a release affects normal use, enable maintenance mode, deploy the last known-good Git commit, rebuild assets, clear/recache configuration, and restart background services. Do not roll back database migrations automatically. Restore a database backup only after assessing data created since the backup.

## Backup and recovery

Use automated daily MySQL backups with retention configured by the hosting provider. At least once before public launch, restore a backup into an isolated database and verify that users, chats, credit transactions, and purchases can be read. Do not test a restore over the live database.

## Incident response

Treat leaked keys as compromised: rotate them in the provider dashboard, update the private server environment, clear configuration cache, and restart affected workers. Preserve application logs and the relevant request IDs; do not copy private messages or credentials into support tickets.

## Monitoring

Alert on unavailable HTTP checks, failed queue jobs, repeated application exceptions, Reverb process exits, missed scheduler runs, and database backup failures. Add a named support owner and escalation contact before public launch.
