# Tenancy rules

These rules govern every phase that adds or touches tenant-owned data. They are target requirements, not claims that the current app already satisfies them.

1. Every tenant-owned business Eloquent model uses `BelongsToTenant`; its global scope and create assignment fail closed if a required tenant cannot be resolved. Tenant root, user identity, and infrastructure models are not tenant-scoped.
2. Do not use unscoped tenant model queries. Avoid `withoutGlobalScopes()`; any exceptional cross-tenant operation belongs in a separately authorized super-admin service and must be audited.
3. Raw SQL/Query Builder must include an explicit `tenant_id` predicate. Review joins and nested subqueries too. Bind tenant ID from trusted server-side context, never request input.
4. Route-model binding must return only records in the active tenant. A foreign tenant's identifier must not disclose existence; use 404/authorization behavior consistently.
5. Search/autocomplete endpoints for customers/items must scope before filters and limits. Quick-create must assign the current tenant server-side.
6. Reports, aggregates, exports, PDFs, dashboard counts, and cache keys must include tenant scope. Tenant ID belongs in every tenant-derived cache key.
7. Invoice lines/payments must belong to the same tenant as their invoice; selected customer/item IDs must be verified against the current tenant.
8. Files live under `storage/app/tenants/{tenant_id}/...`; serve them via authorized routes. Do not expose predictable public storage URLs for private tenant files.
9. Queued jobs and mail must carry an explicit tenant identifier and restore tenant context before querying. Never rely on whichever tenant happens to be active when a worker runs.
10. Settings, invoice sequences, exports, and idempotency records must be tenant-specific where they represent tenant business state.
11. Tests must exercise two tenants with overlapping identifiers and prove reads, writes, search, report output, files, and jobs cannot cross the boundary.
12. Cross-tenant super-admin access is explicit, separately guarded, least-privilege, and logged. Impersonation must be visible and reversible.

## Phase 1 implementation state

`TenantContext` resolves the authenticated user's session-selected membership when valid, then falls back to the lowest-ID `tenant_user` membership. `ResolveTenant` is ordered after authentication and before implicit route-model binding. Settings, customers, items, invoices, invoice items, and payments use `BelongsToTenant`; without context, model queries return no rows and model creation is rejected. Settings and dashboard cache keys include tenant ID. Invoice numbering uses tenant/year sequence rows inside a transaction with `lockForUpdate`.

Phase 3 scopes dashboard aggregation/cache and GST report joins. Invoice create/update validates customer and item references; Invoice, InvoiceItem, and Payment save hooks reject cross-tenant links. Logo uploads use tenant-private paths and the authenticated logo route; `tenants:migrate-legacy-logos` moves prior public logo files/references. Two-tenant MySQL feature tests cover these paths. Tenant-owned queue jobs remain later work because the current app does not queue business operations.

Phase 2 stores access roles on each `tenant_user` membership. Role checks run after tenant resolution and read the active membership. Tenant invites store only a hash of a random token and are linked to one workspace. The active workspace is session-selected when the user belongs to it; otherwise the lowest-ID membership is used.

Phase 4 billing history, subscription invoice snapshots, and subscription state are tenant-owned and use the active tenant scope. Subscription saves reject tenant ID tampering. Billing payments must link to a subscription in the same active tenant; billing invoices must link to a payment in that tenant, and save guards reject tenant ID tampering. The Razorpay webhook is the only intentional cross-tenant lookup: it first verifies the raw-body signature and unique event ID, resolves the tenant by the provider subscription ID, then sets that tenant's context before creating payment/invoice records. Two-tenant feature tests verify webhook records follow the subscription tenant and billing subscription/payment/invoice records cannot cross workspaces. Invoice and member limits are checked again while locking the owning tenant row; invitation acceptance also checks the active subscription and reserved seats.


## Phase 5 platform access

Platform identities use their own table/provider/session guard. `SuperAdminService` explicitly authorizes the active platform identity; `withinTenant` sets one tenant context for scoped model/relationship work and restores the previous context in finally. No global scope is removed. Authorized platform subscription metrics and plan-reference checks are the explicit cross-tenant Query Builder exception; `RequireSuperAdmin` audits platform request access, and mutation services transactionally audit changes. Platform tables (admins/audit/coupons/announcements) are not tenant business records.

Support impersonation selects a verified owner of one active/onboarded workspace, pins tenant selection, expires in 15 minutes and is read-only. Normal tenant scopes/binding remain in force. End/expiry/revocation restores the original identity/workspace. Tenant suspension denies workspace routes and subscription writes, including invitation paths using BillingService. Signed provider webhook processing continues independently of suspension.


## Phase 6 files, capabilities and queued work

TenantWork establishes explicit context from a trusted scalar tenant ID and restores the previous context in finally. SendInvoice and BuildAccountExport carry only tenant/record IDs and query scoped records after context is set; current membership is rechecked. Failure callbacks update only their explicit tenant/record pair. No tenant-owned model is serialized into these jobs.

Public invoice links use an exact random-token hash/expiry/revocation lookup in the capability table to resolve a tenant, then all invoice/settings/logo access is scoped. This is the documented public capability exception, independent of the provider webhook and guarded platform aggregate exceptions. Bearer links never authorize writes.

CSV streaming establishes context inside the streamed callback. ZIPs and mail temporary files use tenant-private directories; export downloads/cleanup validate a tenant-specific UUID ZIP path. Owner export manifests use allowlists that omit credentials/tokens/provider metadata. Authorized maintenance pruning loops tenant roots and applies explicit tenant_id predicates to each export row.
