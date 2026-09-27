# MySQL compatibility review

The application migrations use Laravel schema features supported by MySQL 8 / MariaDB: foreign keys, unique indexes, JSON audit columns, UUIDs, timestamps, enums, and unsigned integers. The application configuration already uses `utf8mb4`, strict mode, and `prefix_indexes` for MySQL.

Billing, credit purchases, chat acceptance, message creation, account cleanup, and administrator adjustments use database transactions with `lockForUpdate()`. These row locks are supported by InnoDB and are more protective under MySQL than SQLite’s coarse locking.

Before staging launch, use MySQL 8 or a supported managed-MariaDB version with InnoDB as the default engine. Run `php artisan migrate --force` on a new empty staging database, then perform the two-account acceptance checks in `STAGING-READINESS.md`. Do not use SQLite results as proof of production database behavior.

No SQLite-specific SQL was found in application migrations or the billing/chat/credit services during this review. The remaining required verification is execution against the actual staging MySQL server.
