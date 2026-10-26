# Deployment

## Current documented setup

Root `README.md` describes PHP 8.2+, Composer, `php artisan migrate --seed`, and local serving. Phases 1 and 2 add `.env.example` with MySQL, database queue, and log mail defaults; `setup.sh` configures MySQL interactively and runs Composer/migrations/seeding. No production deployment was verified.

Set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, optional `MAIL_USERNAME`/`MAIL_PASSWORD`, `MAIL_SCHEME` when needed, and `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` for verification, password reset, and invitation delivery. The `log` mailer is suitable only for local development.

After upgrading Phase 2 installations, run `php artisan tenants:migrate-legacy-logos` once to move any legacy public logo files and references into tenant-private storage. New onboarding and settings uploads already use private tenant paths.

Phase 4 migrations create configurable billing plans/subscriptions and tenant-scoped payment/invoice history. New workspaces receive a 14-day trial. Before enabling paid billing, obtain approved product values, set Razorpay credentials, monthly/yearly cycle counts, and `BILLING_PRICE_TAX_MODE` (`inclusive` or `exclusive`) in protected environment configuration, populate active plan prices/quotas and matching Razorpay plan IDs, and configure the SaaS seller GST/SAC invoice details. Register `https://<your-domain>/webhooks/razorpay` with Razorpay for subscription activated/authenticated/charged/pending/halted/cancelled/completed/resumed and payment captured events, using the matching webhook secret. Keep checkout disabled until a Razorpay sandbox checkout and signed webhook replay have been verified. `BILLING_GRACE_DAYS` must be set from an approved decision; while blank, failed payments do not switch a workspace to read-only.

## Target common requirements

- Production database: MySQL with UTF-8 (`utf8mb4`); configure `.env`, `config/database.php`, and setup documentation together. The application now defaults to MySQL when `DB_CONNECTION` is omitted.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, HTTPS/session/mail settings, and protect `.env` from web access.
- Set `QUEUE_CONNECTION=database`; Phase 1 includes `jobs`, `job_batches`, and `failed_jobs`. Run queued work using hosting-supported short-lived cron/scheduler invocations. Configure cron for `php artisan schedule:run` each minute where supported.
- Back up database and tenant files, test restore, and take a backup before migrations.

## Hostinger shared hosting (future verified runbook)

Point the document root to Laravel `public/` where supported, keep application/storage outside public web root, configure PHP extensions and writable `storage`/`bootstrap/cache`, create MySQL database/user, set environment, run migrations with production-safe command access, configure HTTPS and cron. Shared-host restrictions differ by plan; verify shell/cron access and document a manual fallback without exposing application source.

## VPS with nginx (future verified runbook)

Use a supported PHP-FPM version, nginx root at `public/`, deny access to dotfiles, configure TLS, database credentials, file permissions, logs/rotation, backups, and deployment rollback. Use cron for scheduler and an approved short-lived database queue worker strategy; no Redis/Horizon or daemon requirement.

## Open work

Confirm scheduler commands, permissions, cron syntax, Hostinger plan limits, queue worker invocation, and Laravel 11 deployment commands before calling this runbook production-ready. Never put real secrets in docs.
