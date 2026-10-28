# Super-admin specification

## Phase 5 implementation

Platform administration is available at `/superadmin/login` and `/superadmin`. It uses a separate `superadmin` session guard, `SuperAdmin` model, provider, and `super_admins` table. Workspace roles and tenant login credentials never grant platform privileges. No default platform account is seeded.

Create the initial account from a trusted terminal:

```powershell
php artisan superadmin:create
```

The command prompts for name/email and a hidden password of at least 12 characters; account creation is audited. It does not change tenant user roles or accept passwords as command arguments. Disable an administrator with the `is_active` field through trusted maintenance access; every admin request rechecks that state. Platform password reset/MFA/account-management UI are not part of this phase.

## Dashboard and operations

- Platform overview: total workspaces, current active/trial/ended subscription counts, suspended workspaces, six-month signup trend, and catalog MRR estimate.
- MRR includes only current active provider subscriptions in active workspaces; yearly catalog price is divided by twelve. Legacy/trial plans, manual grants, overdue/ended subscriptions, and suspended workspaces are excluded. It is a catalog estimate, not captured revenue, and does not adjust discounts/tax.
- Workspace directory supports name/slug search and active/suspended filtering. Detail pages show scoped subscription/usage and workspace owners.
- Suspension/reactivation requires a reason and is audited. Suspended workspaces cannot access tenant routes or obtain writes through billing/invitation flows. Provider webhooks still record their state while suspended.
- Trial extension requires a reason, 1-365 days, and a locally managed trial. It extends from the later of now or existing trial end and synchronizes the tenant/subscription dates.
- Manual plan grants require an active non-system plan, a reason, and a future access deadline within five years. They create local active entitlement without a payment or provider call. They cannot replace outstanding provider subscriptions or cancelled/completed subscriptions with paid access remaining. Provider lifecycle/plan changes remain a Phase 4 policy dependency.
- Plan catalog supports create/update/delete. Trial/legacy codes cannot be renamed or deleted. Referenced plans cannot be deleted; prices/provider IDs/code cannot be changed once referenced. Create a new plan for a new price contract. Quotas/entitlements may be edited, preserving business data and enforcing limits on later writes.
- Coupon catalog supports percent/fixed-paise values, normalized unique codes, status and date windows. Checkout redemption is intentionally unavailable until discount/provider billing rules are approved. Creating a catalog coupon does not discount a charge.
- Announcement catalog supports plain-text title/body, status and date windows; currently visible announcements render escaped on tenant pages. Future, expired, and inactive notices are hidden.

## Impersonation

A deliberate POST from the workspace detail selects a verified owner of an active, onboarded workspace and records a support reason. The session lasts at most 15 minutes, stays bound to that workspace, and is read-only. Core lists, private business invoice PDFs, reports/exports and logo reads retain normal tenant authorization. Billing, account/settings/member screens, platform controls, and all mutations are blocked during impersonation.

A persistent banner identifies the session, reason, workspace and expiry, with an explicit POST exit. Exit/expiry restores the original tenant identity/workspace, or logs out the tenant guard if none existed; the platform guard remains separate. Session IDs and CSRF tokens rotate. The middleware ends the session on the next request after expiry, administrator disablement, owner membership revocation, or workspace suspension. Expiry is request-driven; no cron worker is required.

Start/end audit records include actor, workspace, target owner, reason and timestamps (end cause is recorded). There is no password or provider-secret disclosure through impersonation. The read-only restriction is deliberately narrower than normal owner privileges.

## Audit and boundaries

`RequireSuperAdmin` guards all platform controls and records access; tenant-role attempts are denied and audited. Mutation services reauthorize the active platform identity and transactionally record changes. `admin_audit_logs` has no edit/delete endpoints and rejects Eloquent record updates/deletes. Database administrators can still modify storage; this is application-level append-only enforcement.

Tenant-owned model reads/writes use `SuperAdminService::withinTenant`, which temporarily sets an explicit tenant and restores the previous context in `finally`. No tenant scopes are removed. Platform aggregates and plan-reference checks are the documented authorized cross-tenant Query Builder exception; they expose aggregates or reference checks, not business payloads. Forms use CSRF, login/sensitive mutations are throttled, and validated fields are allowlisted in Form Requests.

Phase 5 does not enable production checkout or complete Phase 4 gateway/commercial QA. Razorpay support is investigating the endpoint-specific Subscriptions/Plans issue. Phase 6 is not started.
