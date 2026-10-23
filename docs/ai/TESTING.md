# Testing strategy

## Current state

PHPUnit 11 is a development dependency, but the repository scan found no `tests/` directory. No automated test coverage is claimed. Phase 1 migrations were applied to configured local MySQL and checked through migration status, schema/count inspection, route registration, and PHP syntax checks; automated tests were not run.

## Target automated coverage

- Migration from an existing populated single-tenant MySQL database, including data counts and unique-index preflight.
- Authentication, registration, verification, onboarding, invitation, role and policy behavior.
- Two-tenant isolation for invoice/customer/item/payment CRUD, route binding, autocomplete, dashboard, GST reports, exports, PDFs, and uploaded files.
- Same-tenant relationship validation for invoice customer, lines, and payments.
- Invoice numbering concurrency/financial-year boundaries and unchanged GST calculation regression cases.
- Subscription limits, grace/read-only state, Razorpay signature/idempotency/state transitions, and super-admin audit/impersonation.

## Commands

After test infrastructure is created, expected command is `php artisan test` (or `vendor/bin/phpunit`). Confirm the project PHPUnit configuration and database setup before relying on this command. Run MySQL-specific migration/locking tests against MySQL; SQLite is not a substitute for MySQL locking or enum/DDL behavior.

## Manual QA by phase

| Phase | Minimum review |
|---|---|
| 0 | Review all docs against code scan; no runtime behavior changed |
| 1 | Upgrade populated legacy DB; confirm all records retained and attached to default tenant; numbering works concurrently |
| 2 | Register/verify, onboard, invite members, exercise all roles |
| 3 | Attempt cross-tenant reads/writes/search/reports/files and verify denial |
| 4 | Trial, checkout, webhook replay/failure, limit prompts, grace/read-only |
| 5 | Guard separation, tenant actions, audit, impersonation start/exit |
| 6 | Tenant branding/PDF, public token link, email, exports |
| 7 | Landing/pricing/legal pages, shared-host cron/queue, backups and deployment rollback |
