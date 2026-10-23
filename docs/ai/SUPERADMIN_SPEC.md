# Super-admin specification

Future area at `/superadmin`; not present in the current routes or models.

## Separation and access

Use a distinct guard and explicit `is_super_admin`/super-admin authorization model. Tenant users must never gain super-admin access through tenant roles. Protect every route and action; do not rely on hiding navigation links.

## Dashboard and operations

- Summary: total tenants, active/trial/expired counts, MRR, signup trend chart.
- Tenant directory: search/filter, tenant detail and usage, suspend/activate, extend trial, manually change plan.
- Plan management CRUD, coupon codes, and announcement banners.
- Impersonation of tenant owner only through a deliberate, time-bounded flow with reason, actor, target, start/end, and audit event. Show persistent impersonation indicator; provide explicit exit; prevent privilege escalation and sensitive credential access.

## Audit and safety

Log super-admin access and every tenant/plan/status/trial change. Enforce least privilege, CSRF, rate limits on sensitive actions, and separate audit review. Direct cross-tenant data access must use explicit service methods and must not disable tenant scopes globally for a request.
