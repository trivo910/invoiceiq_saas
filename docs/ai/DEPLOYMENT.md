# Deployment

## Current documented setup

Root `README.md` describes PHP 8.2+, Composer, `php artisan migrate --seed`, and local serving. Phases 1 and 2 add `.env.example` with MySQL, database queue, and log mail defaults; `setup.sh` configures MySQL interactively and runs Composer/migrations/seeding. No production deployment was verified.

Set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, optional `MAIL_USERNAME`/`MAIL_PASSWORD`, `MAIL_SCHEME` when needed, and `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` for verification, password reset, and invitation delivery. The `log` mailer is suitable only for local development.

After upgrading Phase 2 installations, run `php artisan tenants:migrate-legacy-logos` once to move any legacy public logo files and references into tenant-private storage. New onboarding and settings uploads already use private tenant paths.

Phase 4 migrations create configurable billing plans/subscriptions and tenant-scoped payment/invoice history. New workspaces receive a 14-day trial. Local development may opt into `BILLING_DEMO_MODE=true` and run `php artisan db:seed --class=Database\\Seeders\\BillingDemoSeeder --force` for sample prices/limits and a seven-day grace assumption; this mode is ignored outside local/testing and blocks Razorpay checkout and subscription tax invoices. Never enable demo mode or use those values in production. Before enabling paid billing, obtain approved product values, set Razorpay API credentials, a non-whitespace webhook secret, monthly/yearly cycle counts, and `BILLING_PRICE_TAX_MODE` (`inclusive` or `exclusive`) in protected environment configuration, populate active plan prices/quotas and matching Razorpay plan IDs, and configure the SaaS seller GST/SAC invoice details. The application keeps checkout disabled until both the provider credentials and webhook secret are configured, so a paid checkout cannot proceed without authenticated callbacks; whitespace-only webhook secrets are treated as unconfigured, including for existing pending checkout links and webhook processing. Register a reachable `https://<your-domain>/webhooks/razorpay` endpoint with Razorpay for subscription activated/authenticated/charged/pending/halted/paused/cancelled/completed/resumed and payment captured/failed events, using the matching webhook secret. A loopback URL such as `https://127.0.0.1:8000/webhooks/razorpay` is not reachable by Razorpay; use a public HTTPS host or secure tunnel for local QA. Keep checkout disabled until a Razorpay sandbox checkout and signed webhook replay have been verified. An unauthenticated API check on 2026-10-27 returned HTTP 401; sandbox credential variables were subsequently confirmed present locally, but no authenticated API request or account-backed checkout/webhook/tax-invoice QA has been performed. At that check, demo mode was enabled and seller tax/cycle-count settings were unset. `BILLING_GRACE_DAYS` must be set from an approved decision; while blank and demo mode is off, failed payments do not switch a workspace to read-only.

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

### Sandbox QA update ? 2026-10-28

An authenticated read-only request to the official Razorpay plans endpoint has now been attempted and returned HTTP 401. Account-backed checkout and public webhook delivery remain unverified. Gateway QA is resumed; see GATEWAY_QA.md. Choose a provider-supported public HTTPS endpoint: Razorpay documents blocked tunnel domains, so do not assume every tunnel URL is accepted. See https://razorpay.com/docs/webhooks/validate-test/.

### Temporary QA tunnel - 2026-10-28

The user approved a temporary tunnel. A Cloudflare Quick Tunnel now forwards to a dedicated loopback listener exposing only POST `/webhooks/razorpay`, with debug output disabled. Sample billing overrides are private to this QA process; main environment configuration is unchanged. See GATEWAY_QA.md for the current ephemeral URL and the process-identity-checked stop command. Dashboard acceptance and actual Razorpay delivery remain unverified.


## Phase 5 provisioning and review

Run migrations before using platform routes. The Phase 5 migration only adds super_admins, admin_audit_logs, coupons and announcements; it does not promote existing tenant users. Both local and isolated test MySQL migrations were applied on 2026-10-28. No production deployment is claimed.

Create an administrator from a trusted terminal with `php artisan superadmin:create`, then sign in at `/superadmin/login`. The command prompts for a hidden password (minimum 12 characters); no seeded platform credential or new environment secret exists. Existing SMTP/session configuration remains unchanged. Preserve HTTPS, secure cookies and protected storage for production. Protect/retain platform audit records with database backups.

Manually review directory/filtering, reasoned status/trial/local grants, catalog CRUD and announcements, then start/exit/expire read-only impersonation at desktop/mobile widths. Do not use manual grants to bypass provider billing rules. Coupon redemption/provider changes remain unavailable. Review rollback on staging with backups; dropping Phase 5 tables removes platform accounts/audit/catalog data. Gateway support resolution remains independent.


## Phase 6 queue and workflow operation

Run migrations before deploying workflow routes; Phase 6 tables were applied on local/test MySQL. Require PHP ZipArchive (already exercised locally), writable tenant-private storage and the existing database queue. Retain APP_KEY in protected backups: it decrypts invoice delivery payloads. No new environment variable or dependency installation is required; `.env.example` and setup already configure the database queue/migrations.

Configure the host's cron to invoke `php artisan schedule:run` every minute. The schedule starts a bounded `queue:work database --stop-when-empty --max-time=50 --tries=3 --timeout=60` with overlap protection and daily `workflows:prune`. Inspect `php artisan schedule:list` without sending mail. These schedule definitions were verified locally; actual host cron/process limits/SMTP delivery remain unverified. Queue retries can duplicate SMTP acceptance after a crash before DB commit.

Use a reachable HTTPS application host for public invoice links, keep tenant directories outside public storage and disable production debug output. Workspace ZIP downloads expire after 24 hours; the prune command deletes expired files. Test large workspace runtime/memory and crash recovery on staging. See WORKFLOWS.md for the exact sharing/permission/export boundaries. Phase 4 Razorpay support and production billing configuration remain unresolved.
