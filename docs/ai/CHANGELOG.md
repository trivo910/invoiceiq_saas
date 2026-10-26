# Changelog

## Phase 0 — 2026-10-23 — Documentation setup

Added the requested project-specific documentation set under `docs/ai/` and root continuation instructions in `CLAUDE.md` and `AGENTS.md`.

Files added: `README.md`, `PROJECT_CONTEXT.md`, `EXISTING_FEATURES.md`, `GST_RULES.md`, `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `TENANCY_RULES.md`, `PERMISSIONS_MATRIX.md`, `BILLING_AND_PLANS.md`, `SUPERADMIN_SPEC.md`, `SECURITY_CHECKLIST.md`, `CODING_STANDARDS.md`, `TESTING.md`, `DEPLOYMENT.md`, `ROADMAP.md`, `PROGRESS.md`, `DECISIONS.md`, `CHANGELOG.md`, `PHASE_PROMPTS.md`, root `CLAUDE.md`, root `AGENTS.md`.

Modified application files: none. Migrations run: none. Runtime behavior changed: none.

## Phase 1 — 2026-10-23 — Tenancy foundation

Added tenant/workspace schema and default-tenant backfill, tenant membership pivot, tenant context/middleware, `BelongsToTenant` scope, tenant-scoped invoice/customer/item/settings indexes, customer GSTIN uniqueness after duplicate preflight, and a tenant financial-year numbering service. Applied migrations to configured local MySQL after preflight; retained 5 customers, 9 items, 17 settings, and 1 user. The inspected database had no invoices or payments. Added database queue configuration and supporting queue tables, MySQL `.env.example`, and setup/README updates. GST calculations were not changed; invoice date changes now refresh the generated number suggestion for that financial year.

Files added: `app/Models/Tenant.php`, `app/Models/Concerns/BelongsToTenant.php`, `app/Support/TenantContext.php`, `app/Services/InvoiceNumberingService.php`, `app/Http/Middleware/ResolveTenant.php`, `config/queue.php`, `.env.example`, `database/migrations/2026_10_23_000001_create_tenants_and_scope_business_data.php`, `database/migrations/2026_10_23_000002_create_database_queue_tables.php`, `database/migrations/2026_10_23_000003_add_tenant_scoped_customer_gstin_unique.php`.

Files modified: `README.md`, `setup.sh`, `config/database.php`, `bootstrap/app.php`, `routes/web.php`, `resources/views/invoices/form.blade.php`, `app/Providers/AppServiceProvider.php`, `app/Models/User.php`, `Customer.php`, `Item.php`, `Invoice.php`, `InvoiceItem.php`, `Payment.php`, `Setting.php`, `app/Http/Controllers/InvoiceController.php`, `database/seeders/DatabaseSeeder.php`, and affected docs in `docs/ai/`.

Migrations run: all three Phase 1 migrations completed successfully on local MySQL (first two in batch 2, GSTIN constraint in batch 3). Automated tests were not run because no test suite exists and tests were not requested.

### Phase 1 follow-up — tenant middleware runtime fix

Changed `ResolveTenant` to receive `TenantContext` through its constructor; Laravel's middleware pipeline invokes `handle()` with only the request and next callback, so method-parameter injection caused the reported `ArgumentCountError`. PHP syntax validation passes, and an authenticated request to the local dashboard returned HTTP 200 after the fix.

## Phase 2 — Auth and onboarding

Added atomic user/workspace registration, signed email verification, password reset broker flows, session regeneration, and company/GST/logo/invoice prefix onboarding with optional initial customer/item. Added tenant-scoped invitations with SHA-256 token hashes and seven-day expiry, membership role changes/removal, and Gates for workspace and record access. Existing accounts were marked verified and existing workspaces onboarded to preserve their current access.

Added auth, verification, onboarding, and invitation controllers/views, `TenantInvitation`, onboarding middleware, mail config, and `2026_10_23_000004_add_phase_two_auth_and_invitations.php`. Modified auth/team routes and views, tenant role resolution, `ResolveTenant`, `.env.example`, README, and affected docs. Migration 4 applied to configured local MySQL. PHP syntax checks and route listing succeeded; automated tests were not run because they were not requested and the project has no tests directory. Production email requires SMTP configuration; the default local mailer logs messages.

### Phase 2 follow-up — local mail transport unavailable

Registration reported an SMTP connection error because local `.env` selected `smtp` at `127.0.0.1:1025` without a server running. Mail transport failures now return actionable feedback for verification, reset, and invitation flows rather than a 500. The local `.env` mailer was switched to `log` and Laravel's config cache cleared; existing SMTP host/credential entries were left untouched. The account submitted before this fix may already exist and can sign in, then resend verification from the verification screen.

## Phase 3 — 2026-10-23 — Isolation audit and tests

Scoped dashboard raw aggregation to the active tenant and keyed dashboard caching/invalidation by tenant. Added tenant predicates and tenant equality checks to GST invoice-line joins. Invoice validation now scopes selected customers/items, and model guards reject cross-tenant invoice/customer, line/invoice/item, and payment/invoice links. Tenant logos now use private paths served through the authenticated tenant route; a command and lazy path migration move legacy public logos into tenant-private storage. The local data audit found zero cross-tenant links across the four relationship pairs.

Added a dedicated MySQL PHPUnit configuration and seven feature tests with 55 assertions for dashboard/cache, customer/item search, route binding, tampered invoice writes, linked records, GST summaries/Excel export, private logo routes, and legacy logo migration. Created/migrated the isolated `invoiceiq_test` database; the development database was not refreshed. `php vendor/bin/phpunit` passes. `php artisan test` is not available because Laravel Collision is not installed.

## Phase 4 — 2026-10-26 — Plans and billing foundation

Added plan, tenant subscription, idempotent provider event, payment, and subscription invoice tables. New workspaces receive the configured 14-day card-free trial; existing workspaces retain an unlimited legacy plan. Added configurable plan entitlements, usage meters, invoice/member limit middleware with transaction-time tenant locks, and read-only enforcement for expired trials and configured grace/period-end states.

Added server-created Razorpay subscription checkout, raw-body HMAC webhook verification and event replay protection, payment history, billing page, and tenant-scoped subscription invoice PDFs. Invoice issuance requires configured SaaS seller/GST/SAC details. Commercial prices, usage caps, grace duration, downgrade behavior, proration, and refund rules remain unset pending product decisions. Paid checkout requires configured active plan records, provider IDs/credentials, and recurring cycle count.

Added Phase 4 feature coverage for trial initialization/expiry, plan limit behavior, Razorpay checkout and provider-plan matching, webhook verification/replay, payment capture/tax invoice generation, billing PDF rendering, feature entitlement, and tenant boundaries. Failed renewal attempts from signed webhooks are now recorded in tenant-scoped billing history without issuing invoices; replay is idempotent. `php vendor/bin/phpunit` passes 18 tests and 109 assertions against the isolated MySQL test database. Phase 4 remains in progress until product decisions and Razorpay sandbox/tax invoice QA are complete.
