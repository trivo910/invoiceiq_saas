# Progress

## Current position

- **Phase:** 1 — Tenancy foundation
- **Status:** Complete; ready for Phase 2.
- **Code changes:** Tenant schema/context/model scopes, per-tenant unique indexes and invoice numbering, database queue config/tables, MySQL `.env.example`, and setup/docs updates.
- **Migration state:** All three Phase 1 migrations applied successfully to the configured local MySQL database.
- **Automated tests:** Not run; no `tests/` directory exists.

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

## In progress / next

1. Begin Phase 2 after user direction.
2. Before deploying to any other installation, back up its database, preflight actual legacy data/migration drift, then rehearse the additive migration on staging.

## Known issues and cautions

- Dashboard raw SQL/global cache and GST report raw joins still require tenant predicates/cache-key isolation in Phase 3.
- Same-tenant links between invoice/customer/item/payment foreign keys need an isolation audit and tests.
- Only the configured local MySQL dataset was inspected; it had no invoices or payments. Other installations with invoice history need preflight and backup before migration.
- Existing role middleware still checks legacy `users.role`; Phase 2 must use tenant membership roles and policies.
- Dashboard cache invalidation remains global until Phase 3; this affects cache efficiency, and reports/dashboard raw SQL are not yet proven tenant-isolated.
- Invoice numbering, tenant scopes, and migrations were verified only against configured local MySQL. No automated isolation/concurrency test suite exists yet.

## Resume point

Read `README.md`, this file, `TENANCY_RULES.md`, `CODING_STANDARDS.md`, `DATABASE_SCHEMA.md`, and `ROADMAP.md`. Phase 1 is complete. Continue with Phase 2 only when requested; migrate other installations only after backup, preflight, and staging rehearsal.
