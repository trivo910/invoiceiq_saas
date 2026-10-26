# Progress

## Current position

- **Phase:** 4 - Plans and billing
- **Status:** Local demo catalog and grace assumptions are loaded; production billing still awaits approved prices, limits, seller tax identity/treatment, grace duration, and downgrade/proration/refund decisions.
- **Code changes:** Phase 4 plan/subscription/payment/invoice/event schema, 14-day trials, usage/read-only middleware, Razorpay checkout/webhook services, billing page/history/PDF, and tests, plus completed Phases 1-3.
- **Migration state:** Phase 4 migrations applied to local MySQL and the separate `invoiceiq_test` database.
- **Automated tests:** `php vendor/bin/phpunit` passes: 21 tests, 143 assertions on MySQL.

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

- Added configurable plans and tenant subscriptions; new workspaces receive a 14-day card-free trial and migrated workspaces keep an unlimited legacy plan.
- Added Razorpay server-side checkout, raw-body HMAC webhook verification/idempotency, tenant payment history, configurable subscription tax invoices, billing UI, usage meters, and trial/grace/period-end read-only enforcement.
- Added tenant-locked rechecks for invoice/member limits and feature coverage for trials, checkout, webhook replay/payment capture, private billing PDFs, and tenant isolation.
- Added tenant-scoped failed renewal payment history from signed Razorpay webhooks; event replay creates no duplicate payment or invoice.
- Added a two-tenant webhook regression proving provider subscription lookup assigns payment/event records to the correct workspace regardless of active request tenant context.
- Added a guarded local/testing demo catalog (sample prices/quotas and seven-day grace) and explicit UI labeling; demo mode blocks checkout and subscription tax invoices.
- Fixed `payment.failed` webhooks to move established subscriptions into `past_due` with the configured grace window; initial pending checkout subscriptions remain pending.
- Updated billing status labels and read-only/overdue notices to match trial, subscription-period, and grace enforcement.
- Prevented out-of-order provider events from regressing subscription state by storing the latest processed provider state timestamp; stale events still record their payment history.

## In progress / next

1. Replace local demo assumptions with approved prices/limits, seller tax invoice details, grace duration, downgrade behavior, proration, and refund decisions; then verify Razorpay sandbox events and production tax invoices.
2. Before deploying to another installation, back up its database, preflight actual legacy data/migration drift, then rehearse migrations on staging.

## Known issues and cautions

- Only the configured local MySQL business dataset was inspected; it had no invoices or payments. Other installations with invoice history need backup and preflight.
- Same-tenant relationships are enforced by application validation/model hooks; the schema does not yet have composite tenant foreign keys.
- A full workspace switcher and tenant-aware queued business jobs remain later work.
- Email delivery defaults to the `log` mailer; production must configure SMTP credentials and sender identity.
- Legacy public logos should be moved after upgrade with `php artisan tenants:migrate-legacy-logos`.
- Paid checkout remains disabled in demo mode and requires provider plan IDs, credentials, recurring cycle count, and production billing configuration after demo mode is off; invoice issuance is also blocked in demo mode and requires seller/GST/SAC fields and tenant state.
- `BILLING_DEMO_MODE=true` is enabled only in the local `.env`; its seven-day grace assumption is for local exploration. The config ignores demo mode outside `local`/`testing`. With demo mode off, `BILLING_GRACE_DAYS` remains blank by default and `past_due` retains writes until a duration is approved.

## Resume point

Read `README.md`, this file, `TENANCY_RULES.md`, `CODING_STANDARDS.md`, `DATABASE_SCHEMA.md`, and `ROADMAP.md`. Phases 1–3 are complete. Phase 4 foundation is implemented; finish its commercial configuration when approved values are supplied. Migrate other installations only after backup, preflight, and staging rehearsal.
