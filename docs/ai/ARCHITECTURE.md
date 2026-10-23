# Architecture

## Current application (observed)

Laravel 11 routes requests through `routes/web.php` into controllers, Eloquent models, Blade views, and MySQL. Phase 1 adds tenant middleware/context, a tenant scope trait, and the invoice numbering service. Most validation remains inside controllers; invoice math is split between the invoice controller and browser JavaScript. Dashboard and GST reports still include direct `DB` queries that require Phase 3 audit.

## Target tenancy design

- One MySQL database and shared tables, with `tenant_id` on every tenant-owned business record.
- `tenants` represents a company/workspace; authenticated membership resolves the active tenant. Initial account creation creates the default workspace and associates the owner.
- A `BelongsToTenant` model trait supplies tenant scope and tenant assignment. Tenant resolution must be explicit and fail closed when a tenant is required but unavailable.
- Users connect to tenants through `tenant_user`, with a role per membership. Until workspace switching is implemented, requests select the lowest-ID membership.
- Authenticated web request flow: authenticate user → resolve membership/current tenant → enforce role/policy and subscription/plan limits → execute tenant-scoped controller/service → render response.
- Route model binding, autocomplete, reports, exports, PDF/logo access, and queued work must retain tenant context.
- Optional subdomain resolution remains off until a later planned phase.

## Target folders and boundaries

Keep current Laravel folders. Add focused `app/Services`, `app/Http/Requests`, `app/Policies`, tenant middleware/context, and tenant-aware models only as phases require. Controllers coordinate requests and responses; Form Requests validate; policies authorize; service classes hold multi-step invoice numbering, onboarding, billing, export, and webhook workflows.

## Transactions, queues, and hosting

Invoice numbering should be tenant and financial-year scoped, configurable by tenant prefix, and claimed inside a DB transaction using a locked sequence row. Webhooks must be signature-verified and idempotent. Use the database queue driver; cron invokes `schedule:run`. The host may not support persistent workers, Redis, or Horizon.

Phase 1 implements the tenant root, membership pivot, request context, scoped business models, financial-year numbering service, and database queue config/tables. Raw report/dashboard queries and private file handling remain later isolation work.

## Data transition

Phase 1 must create one default tenant and associate all existing business data with it without dropping records. Schema changes and backfill order are specified in `DATABASE_SCHEMA.md`; actual data shape and duplicates need preflight checks before applying per-tenant uniqueness.

## Architecture boundaries

The business database remains MySQL for target shared hosting and production. Existing GST math and the inline Blade/vanilla-JS workflow are protected. Do not introduce a frontend framework or rewrite the app.
