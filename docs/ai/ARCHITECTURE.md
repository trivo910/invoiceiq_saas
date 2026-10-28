# Architecture

## Current application (observed)

Laravel 11 routes requests through `routes/web.php` into controllers, Eloquent models, Blade views, and MySQL. Phases 1–4 add tenant middleware/context, membership authorization, auth/onboarding, invoice numbering, isolation protections, trials, plans, billing records, Razorpay webhooks, and subscription/limit middleware. Most validation remains inside controllers; invoice math is split between the invoice controller and browser JavaScript. Commercial billing values remain unconfigured pending product decisions.

## Target tenancy design

- One MySQL database and shared tables, with `tenant_id` on every tenant-owned business record.
- `tenants` represents a company/workspace; authenticated membership resolves the active tenant. Initial account creation creates the default workspace and associates the owner.
- A `BelongsToTenant` model trait supplies tenant scope and tenant assignment. Tenant resolution must be explicit and fail closed when a tenant is required but unavailable.
- Users connect to tenants through `tenant_user`, with a role per membership. Requests use the session-selected tenant when valid, otherwise the lowest-ID membership; accepting an invitation selects that workspace.
- Authenticated web request flow: authenticate user → resolve membership/current tenant → enforce role/policy and subscription/plan limits → execute tenant-scoped controller/service → render response.
- Route model binding, autocomplete, reports, exports, PDF/logo access, and queued work must retain tenant context.
- Optional subdomain resolution remains off until a later planned phase.

## Target folders and boundaries

Keep current Laravel folders. Add focused `app/Services`, `app/Http/Requests`, `app/Policies`, tenant middleware/context, and tenant-aware models only as phases require. Controllers coordinate requests and responses; Form Requests validate; policies authorize; service classes hold multi-step invoice numbering, onboarding, billing, export, and webhook workflows.

## Transactions, queues, and hosting

Invoice numbering should be tenant and financial-year scoped, configurable by tenant prefix, and claimed inside a DB transaction using a locked sequence row. Webhooks must be signature-verified and idempotent. Use the database queue driver; cron invokes `schedule:run`. The host may not support persistent workers, Redis, or Horizon.

Phase 1 implements the tenant root, membership pivot, request context, scoped business models, financial-year numbering service, and database queue config/tables. Phase 2 adds registration, verification, recovery, onboarding, member invitations, and role Gates. Phase 3 scopes reports and files. Phase 4 adds configurable billing, verified provider events, and subscription/usage checks; production checkout awaits approved plan/tax/grace values and provider sandbox QA.

## Data transition

Phase 1 must create one default tenant and associate all existing business data with it without dropping records. Schema changes and backfill order are specified in `DATABASE_SCHEMA.md`; actual data shape and duplicates need preflight checks before applying per-tenant uniqueness.

## Architecture boundaries

The business database remains MySQL for target shared hosting and production. Existing GST math and the inline Blade/vanilla-JS workflow are protected. Do not introduce a frontend framework or rewrite the app.


## Phase 5 implementation

Platform routes live in `routes/superadmin.php` under `/superadmin`, authenticated by the separate SuperAdmin provider/guard. RequireSuperAdmin rechecks active platform identity and audits access before binding. Catalog/TenantAction Form Requests validate allowlisted fields. SuperAdminService owns authorized aggregates, temporary scoped tenant work and transactional mutations/audits. ImpersonationService and web middleware enforce owner selection, read-only route allowlist, workspace pinning, expiry/revocation and session restoration. The tenant layout displays persistent support status and visible escaped platform announcements.

Platform identity, audit, coupon and announcement records use four separate infrastructure tables. Interactive console provisioning avoids default credentials. The implementation adds no frontend framework, queue daemon, external runtime dependency or production billing assumptions. Provider changes/discount redemption remain a Phase 4 boundary.
