# Ready-to-paste phase prompts

Each prompt is self-contained enough to resume without chat history. Read the docs first. Do one phase only, then stop and summarize for review.

## Current continuation state

Phases 1-3, 5 and 6 implementation are ready; Phase 7 launch hardening is in progress. Phase 4 provider-backed QA and commercial decisions remain pending the Razorpay support reply. Latest full MySQL suite: 97 tests, 750 assertions (2026-10-29). Read PROGRESS.md, LAUNCH_CHECKLIST.md, LEGAL_DRAFT_NOTES.md, DEPLOYMENT.md and TESTING.md. User's current target is local development, with VPS/nginx deployment later; public legal pages contain fictional reference drafts that remain unapproved. VPS, real SMTP/provider QA, backup restoration and production approvals remain unverified. The prompts below are reusable historical templates; do not reimplement completed phases.

## Phase 1 — Tenancy foundation

You are continuing InvoiceIQ's SaaS conversion. Read `/docs/ai/README.md`, `PROGRESS.md`, `TENANCY_RULES.md`, `CODING_STANDARDS.md`, `DATABASE_SCHEMA.md`, `GST_RULES.md`, and `ROADMAP.md`. The user has approved Phase 0 and authorized Phase 1 only. Inspect actual legacy schema/data read-only first, especially duplicate codes/numbers and migration drift. Decide/document user membership shape before coding. Add the tenants table, tenant ownership fields/FKs/indexes for every business table, create/backfill the default tenant without losing existing records, replace global unique constraints with tenant-scoped constraints where appropriate, add `BelongsToTenant` scope/assignment, and implement tenant + financial-year invoice numbering with safe transaction/row lock while preserving existing invoice numbers. Preserve GST logic and UI. Before coding, list files and risks. Update `.env.example`, setup script and README if relevant, docs including schema/progress/roadmap/changelog/decisions, then stop and report files, migrations, tests/commands, and manual QA. Do not begin Phase 2.

## Phase 2 — Auth and onboarding

Read the mandatory docs and current progress. Implement only Phase 2: registration atomically creates user and tenant; email verification, login/password reset/session handling, onboarding for company/GST/logo/numbering with skippable first customer/item, and tenant-scoped member invitations/roles/policies. Use Blade and existing visual conventions. Preserve existing workflows. List files and risks before coding; provide complete runnable changes, update `.env.example`, setup/README and affected docs, then stop with test commands/manual QA. Do not begin Phase 3.

## Phase 3 — Isolation audit and tests

Read mandatory docs plus schema, GST rules, security checklist, testing, and progress. Audit every existing controller, binding, raw SQL/DB query, report, export, autocomplete/AJAX endpoint, cache key, PDF, and file endpoint. Correct all tenant leaks and add automated tests proving two-tenant isolation for invoices, customers, items, payments, reports, exports, and files. Preserve protected GST behavior. List files/risks first; update affected docs and stop after Phase 3 with tests and manual QA. Do not begin Phase 4.

## Phase 4 — Plans and billing

Read mandatory docs plus billing, security, schema, and progress. Implement only plans, trial, tenant usage/limits, friendly middleware, Razorpay subscriptions/checkout, verified idempotent webhooks, billing page/payment history/invoices, grace period, and read-only behavior. Confirm open pricing/tax/grace details from approved decisions; do not invent them. Keep database queue and shared-host compatibility. List files/risks first; update schema, environment/setup/README and docs; stop with tests/manual QA. Do not begin Phase 5.

## Phase 5 — Super-admin

Read mandatory docs plus super-admin spec, permissions, security, schema, and progress. Implement only isolated `/superadmin` access with separate guard, tenant dashboard/list/actions, plan/coupon/announcement management, and audited impersonation. Enforce and test separation from tenant roles. List files/risks first; update affected docs and stop with tests/manual QA. Do not begin Phase 6.

## Phase 6 — Branding and SaaS workflows

Read mandatory docs plus architecture, security, schema, and progress. Implement tenant company settings/branding, authorized tenant uploads, selectable PDF templates, tokenized view-only invoice links, queued transactional mail, invoice messaging, tenant CSV exports, and owner full-account export. Keep private files tenant-scoped and queued jobs tenant-aware. Preserve existing invoice UX. List files/risks first; update docs and deployment/env references; stop with tests/manual QA. Do not begin Phase 7.

## Phase 7 — Launch hardening

Read mandatory docs plus deployment, security, testing, roadmap, and progress. Implement landing/pricing/terms/privacy pages, audit log coverage, soft-delete workspace recovery, usage analytics, rate limiting and remaining hardening. Verify Hostinger shared hosting and VPS/nginx guidance, MySQL config, database queue and cron `schedule:run`, backups and rollback. Update all docs and `.env.example`, setup script, README. Run appropriate tests/checks and provide a complete release/QA summary. Do not add unrelated features.
