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

`TenantContext` resolves the authenticated user's first (lowest-ID) `tenant_user` membership. `ResolveTenant` is ordered after authentication and before implicit route-model binding. Settings, customers, items, invoices, invoice items, and payments use `BelongsToTenant`; without context, model queries return no rows and model creation is rejected. Settings cache keys include tenant ID. Invoice numbering uses tenant/year sequence rows inside a transaction with `lockForUpdate`.

Phase 1 is a foundation, not a completed isolation audit. Phase 3 must audit `DashboardController` raw aggregation/global cache, `GstReportController` joins/exports, direct query-builder access, quick-create endpoints, uploaded logo handling, and same-tenant relationship integrity. Existing reports/raw queries must not be assumed safe yet.

Phase 2 stores access roles on each `tenant_user` membership. Role checks run after tenant resolution and read the active membership. Tenant invites store only a hash of a random token and are linked to one workspace. The active workspace is session-selected when the user belongs to it; otherwise the lowest-ID membership is used. Raw SQL, cache, and linked-record isolation still require Phase 3 review.
