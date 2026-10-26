# SaaS conversion roadmap

Status: **Phases 1–3 complete; Phase 4 foundation implemented, awaiting product decisions and sandbox QA**.

## Phase 0 — Documentation setup

- [x] Scan current routes, models, migrations, controllers, views, helpers, setup files, and seeder.
- [x] Create the complete `/docs/ai/` documentation set.
- [x] Create root `CLAUDE.md` and `AGENTS.md` continuation instructions.
- [x] User reviewed the docs and instructed continuation to Phase 1.

## Phase 1 — Tenancy foundation (complete)

- [x] Use `tenant_user` membership pivot and inspect configured local MySQL data/duplicates.
- [x] Add tenants and tenant ownership migration with default-tenant backfill.
- [x] Add tenant context/scope/assignment and tenant-scoped unique indexes, including customer GSTIN after duplicate preflight.
- [x] Implement financial-year tenant invoice numbering with transaction/row lock.
- [x] Add database queue configuration and queue tables for hosting constraints.
- [x] Update schema/docs and apply migrations to configured local MySQL; preflight contained customers/items/settings/users but no invoices.

## Phase 2 — Auth and onboarding (complete)

- [x] Registration atomically creates an owner and workspace; signed email verification and password reset flows.
- [x] Onboarding for company/GST/logo/invoice prefix and optional customer/item.
- [x] Tenant-scoped invitations, membership role changes/removal, and Gate-based role checks.
- [x] Add SMTP/log mail configuration and apply the auth/invitation migration on configured local MySQL.

## Phase 3 — Isolation audit (complete)

- [x] Scope dashboard aggregation/cache, invoice/customer/item/payment lookups and mutations, and joined GST reports to the active tenant.
- [x] Validate invoice customer/item IDs and enforce same-tenant invoice line/payment links.
- [x] Move tenant logos to private paths, migrate legacy public logo references, and serve through the authenticated tenant route.
- [x] Add MySQL-backed feature coverage for dashboard/cache, search, route binding, cross-tenant writes/relations, GST reports/Excel export, and logos.

## Phase 4 — Plans and billing (in progress)

- [x] Configurable plan/subscription/payment/invoice/webhook schema; card-free 14-day trials for new workspaces.
- [x] Razorpay server-side checkout and signed, idempotent webhook state/payment processing.
- [x] Billing page, tenant payment history, configurable subscription tax invoice PDFs, usage meters, and read-only behavior.
- [x] Invoice/member plan limit checks with tenant-row lock rechecks for concurrent writes.
- [x] Save failed renewal attempts from signed webhooks in tenant billing history without issuing invoices; verify provider event replay is idempotent.
- [x] Verify signed webhook payment records follow the provider subscription's tenant even when another tenant is active in request context.
- [x] Add a local/testing-only demo catalog and seven-day grace assumption; label sample values and block checkout/tax invoice issuance in demo mode.
- [x] Prevent out-of-order signed provider events from overwriting newer subscription state while retaining payment history.
- [ ] Set approved plan prices/limits, Razorpay plan IDs and cycle count; run provider sandbox verification.
- [ ] Set seller GST/SAC invoice details and approved GST treatment; verify tax invoice output.
- [ ] Set approved grace period, downgrade-over-limit, proration, and refund rules.

## Phase 5 — Super-admin (not started)

- [ ] Separate guard, dashboard, tenant/plan controls, announcements/coupons.
- [ ] Audited impersonation and sensitive-action logging.

## Phase 6 — Branding and SaaS workflows (not started)

- [ ] Tenant settings/branding and selectable PDF templates.
- [ ] Public token invoice links, transactional mail, CSV/account exports.

## Phase 7 — Launch hardening (not started)

- [ ] Landing/pricing/legal pages, audit logs, security hardening, analytics, soft-delete recovery.
- [ ] Verify Hostinger and VPS deployment, database queue, cron, backup and rollback.
