# Progress

## Current position

- **Phase:** 2 — Auth and onboarding
- **Status:** Complete; ready for Phase 3.
- **Code changes:** Tenant registration and verification, password recovery, workspace onboarding, invitations, tenant membership roles/Gates, mail configuration, plus prior Phase 1 tenancy changes.
- **Migration state:** All four Phase 1/2 migrations applied successfully to the configured local MySQL database.
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
- Added transactional account/workspace registration, signed email verification, password reset broker screens, and session regeneration on authentication transitions.
- Added onboarding for company/GST/logo/invoice prefix and optional first customer/item; migrated workspaces are marked complete and existing users verified to preserve their sign-in flow.
- Replaced global user CRUD with tenant-scoped invitations, roles, membership updates/removal, and Gates for workspace/record permissions.
- Added SMTP/log mail configuration, updated setup/environment/docs, applied Phase 2 migration, syntax-checked changed PHP files, and confirmed all routes register.
- Handled local SMTP transport failures without turning registration/reset/invitation requests into 500s; switched this development environment to the configured log mailer after confirming the unavailable `127.0.0.1:1025` transport.

## In progress / next

1. Phase 3: audit tenant isolation in raw queries, reports, linked records, exports, cache keys and file routes; add isolation tests.
2. Before deploying to any other installation, back up its database, preflight actual legacy data/migration drift, then rehearse migrations on staging.

## Known issues and cautions

- Dashboard raw SQL/global cache and GST report raw joins still require tenant predicates/cache-key isolation in Phase 3.
- Same-tenant links between invoice/customer/item/payment foreign keys need an isolation audit and tests.
- Only the configured local MySQL dataset was inspected; it had no invoices or payments. Other installations with invoice history need preflight and backup before migration.
- Workspace selection is session-based and defaults to the lowest-ID membership; a full workspace switcher remains future work.
- Email delivery defaults to the `log` mailer; production must configure working SMTP credentials and sender identity.
- Dashboard cache invalidation remains global until Phase 3; this affects cache efficiency, and reports/dashboard raw SQL are not yet proven tenant-isolated.
- Invoice numbering, tenant scopes, and migrations were verified only against configured local MySQL. No automated isolation/concurrency test suite exists yet.

## Resume point

Read `README.md`, this file, `TENANCY_RULES.md`, `CODING_STANDARDS.md`, `DATABASE_SCHEMA.md`, and `ROADMAP.md`. Phases 1 and 2 are complete. Continue with Phase 3 only when requested; migrate other installations only after backup, preflight, and staging rehearsal.
