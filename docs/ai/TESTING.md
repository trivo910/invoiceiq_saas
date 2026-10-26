# Testing strategy

## Current state

PHPUnit 11 is a development dependency. Phase 3 added MySQL-backed two-tenant feature tests. Tests use a separate `invoiceiq_test` database configured in `phpunit.xml`; preserve the local `.env` DB host/user/password and do not point the test config at the development database.

## Target automated coverage

- Migration from an existing populated single-tenant MySQL database, including data counts and unique-index preflight.
- Authentication, registration, verification, onboarding, invitation, role and policy behavior.
- Two-tenant isolation for invoice/customer/item/payment CRUD, route binding, autocomplete, dashboard, GST reports, exports, PDFs, and uploaded files.
- Same-tenant relationship validation for invoice customer, lines, and payments.
- Invoice numbering concurrency/financial-year boundaries and unchanged GST calculation regression cases.
- Subscription limits, grace/read-only state, Razorpay signature/idempotency/state transitions, and super-admin audit/impersonation.

Phase 4 currently tests 14-day trial creation, trial expiry, invoice limits, feature entitlements, Razorpay checkout configuration and provider-plan matching, invalid/valid webhook signatures, event replay, successful capture/tax invoice generation, failed renewal history without invoice issuance, billing PDF tenant binding, and the Phase 3 tenant isolation suite. Full suite: `php vendor/bin/phpunit` (18 tests, 109 assertions).

## Commands

Create the empty test database once if needed, then migrate and run the suite:

```powershell
$env:APP_ENV = "testing"
$env:DB_CONNECTION = "mysql"
$env:DB_DATABASE = "invoiceiq_test"
php artisan migrate --force
php vendor/bin/phpunit
```

This repository does not include Laravel Collision, so `php artisan test` is unavailable; use PHPUnit directly. Run MySQL-specific migration/locking tests against MySQL; SQLite is not a substitute for MySQL locking or enum/DDL behavior.

## Manual QA by phase

| Phase | Minimum review |
|---|---|
| 0 | Review all docs against code scan; no runtime behavior changed |
| 1 | Upgrade populated legacy DB; confirm all records retained and attached to default tenant; numbering works concurrently |
| 2 | Register/verify, onboard, invite members, exercise all roles |
| 3 | Automated MySQL coverage checks cross-tenant dashboard/cache, reads/writes, search, linked records, GST reports/Excel exports, and private logos; manually inspect a legacy logo migration |
| 4 | Trial, checkout, webhook replay/failure, limit prompts, grace/read-only |
| 5 | Guard separation, tenant actions, audit, impersonation start/exit |
| 6 | Tenant branding/PDF, public token link, email, exports |
| 7 | Landing/pricing/legal pages, shared-host cron/queue, backups and deployment rollback |
