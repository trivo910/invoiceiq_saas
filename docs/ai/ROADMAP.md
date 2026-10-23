# SaaS conversion roadmap

Status after Phase 2: **Phases 1 and 2 complete; Phase 3 next**.

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

## Phase 3 — Isolation audit (not started)

- [ ] Audit every controller, binding, AJAX endpoint, raw query, report, export, and file route.
- [ ] Add tests proving tenant isolation across existing modules and GST reports.

## Phase 4 — Plans and billing (not started)

- [ ] Plans, trial, usage and plan limit middleware.
- [ ] Razorpay subscriptions, verified idempotent webhooks, billing UI, grace/read-only behavior.

## Phase 5 — Super-admin (not started)

- [ ] Separate guard, dashboard, tenant/plan controls, announcements/coupons.
- [ ] Audited impersonation and sensitive-action logging.

## Phase 6 — Branding and SaaS workflows (not started)

- [ ] Tenant settings/branding and selectable PDF templates.
- [ ] Public token invoice links, transactional mail, CSV/account exports.

## Phase 7 — Launch hardening (not started)

- [ ] Landing/pricing/legal pages, audit logs, security hardening, analytics, soft-delete recovery.
- [ ] Verify Hostinger and VPS deployment, database queue, cron, backup and rollback.
