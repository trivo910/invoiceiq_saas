# Progress

## Current position

- **Phase:** 3 — Isolation audit and tests
- **Status:** Complete; ready for Phase 4.
- **Code changes:** Tenant-scoped dashboard/report queries and cache, same-tenant relationship validation, private tenant logo storage/migration, and MySQL isolation tests, plus prior phases.
- **Migration state:** No Phase 3 schema migration. Phase 1/2 migrations applied to local MySQL; the separate `invoiceiq_test` database is migrated for tests.
- **Automated tests:** `php vendor/bin/phpunit` passes: 7 tests, 55 assertions on MySQL.

## Completed

- Documented current schema/features and the SaaS conversion plan in Phase 0.
- Added tenant/workspace and user-membership schema; backfilled existing business rows and users into one default tenant.
- Added tenant context, middleware before route binding, and fail-closed tenant scopes for settings, customers, items, invoices, invoice items, and payments.
- Fixed tenant middleware dependency injection to use Laravel's constructor injection convention after a local runtime error report; authenticated local dashboard request now returns HTTP 200.
- Replaced global unique settings/item/invoice keys with tenant-scoped keys.
- Added tenant-scoped GSTIN uniqueness after checking duplicate groups; empty GSTIN values normalize to NULL.
- Added per-tenant financial-year invoice numbering with transaction and row lock.
- Added database queue configuration and jobs/batches/failed-jobs tables.
- Added MySQL example environment file; updated MySQL default, setup script, README, and affected docs.
- Applied migrations and checked data counts, tenant assignments, queue tables, and invoice route registration.
- Added transactional account/workspace registration, signed email verification, password reset broker screens, and session regeneration on authentication transitions.
- Added onboarding for company/GST/logo/invoice prefix and optional first customer/item; migrated workspaces are marked complete and existing users verified to preserve their sign-in flow.
- Replaced global user CRUD with tenant-scoped invitations, roles, membership updates/removal, and Gates for workspace/record permissions.
- Added SMTP/log mail configuration, updated setup/environment/docs, applied Phase 2 migration, syntax-checked changed PHP files, and confirmed all routes register.
- Handled local SMTP transport failures without turning registration/reset/invitation requests into 500s; switched this development environment to the configured log mailer after confirming the unavailable `127.0.0.1:1025` transport.
- Audited raw SQL, joins, route binding, search, CRUD, reports, exports, cache, PDFs, mail, and file paths; scoped dashboard SQL/cache and GST joins; added customer/item/invoice/line/payment same-tenant guards.
- Moved new logos to private tenant storage and added a command to migrate legacy public logo paths; feature tests cover two-tenant reads, writes, searches, reports, Excel exports, linked records, and logo access.
- Created and migrated the isolated MySQL `invoiceiq_test` database; PHPUnit passes 7 tests and 55 assertions. Read-only audit found zero cross-tenant links in local invoice/customer, line/invoice, line/item, and payment/invoice relationships.

## In progress / next

1. Phase 4: plans, trials, usage limits, and billing.
2. Before deploying to another installation, back up its database, preflight actual legacy data/migration drift, then rehearse migrations on staging.

## Known issues and cautions

- Only the configured local MySQL business dataset was inspected; it had no invoices or payments. Other installations with invoice history need backup and preflight.
- Same-tenant relationships are enforced by application validation/model hooks; the schema does not yet have composite tenant foreign keys.
- A full workspace switcher and tenant-aware queued business jobs remain later work.
- Email delivery defaults to the `log` mailer; production must configure SMTP credentials and sender identity.
- Legacy public logos should be moved after upgrade with `php artisan tenants:migrate-legacy-logos`.

## Resume point

Read `README.md`, this file, `TENANCY_RULES.md`, `CODING_STANDARDS.md`, `DATABASE_SCHEMA.md`, and `ROADMAP.md`. Phases 1–3 are complete. Continue with Phase 4 only when requested; migrate other installations only after backup, preflight, and staging rehearsal.
