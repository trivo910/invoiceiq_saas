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
