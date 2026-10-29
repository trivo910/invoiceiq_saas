# Permissions matrix

Tenant roles are enforced against the active `tenant_user.role` membership. The legacy global `users.role` remains for compatibility with older rows but is not checked when tenant context is active. Workspace and destructive-record access use Laravel Gates.

| Action | Owner | Admin | Accountant | Staff | Viewer |
|---|---:|---:|---:|---:|---:|
| View dashboard/invoices/customers/items | Yes | Yes | Yes | Yes | Yes |
| Create/edit invoices | Yes | Yes | Yes | Yes | No |
| Delete invoice | Yes | Yes | No by default | No by default | No |
| Record payments/update invoice status | Yes | Yes | Yes | Yes | No |
| Send invoice / access authorized PDF | Yes | Yes | Yes | Yes | Yes (view only) |
| Manage customers/items | Yes | Yes | Yes | Yes | No |
| View/export GST reports | Yes | Yes | Yes | Configurable; default yes | Yes (read-only) |
| Edit company, GST, logo, bank, numbering settings | Yes | Yes | No | No | No |
| Invite/manage team and roles | Yes | Yes | No | No | No |
| View plan status, payment history, and subscription invoices; manage billing/checkout | Yes | No | No | No | No |
| Delete/recover workspace | Yes | No | No | No | No |
| Use super-admin tenant/plan controls | No | No | No | No | No |

Implemented Gates: `manage-workspace` (owner/admin), `write-records` (owner/admin/accountant/staff), and `delete-records` (owner/admin). Super-admin authorization belongs to a separate guard/role, not tenant role strings.

## Current behavior observed

All verified workspace roles can read core lists and GST reports. Owner/admin/accountant/staff can create and edit invoices, customers, items, record payments, and send invoices; only owner/admin can delete records, edit settings, or manage members. Members and invitations are scoped to the active workspace. GST report export remains available to all roles as read-only access.


## Phase 5 platform role

A separate `superadmin` guard authenticates platform accounts from `super_admins`. No tenant role, including owner or legacy global admin, grants platform access. Platform accounts can read platform metrics/directory/audit, manage catalogs, and apply audited tenant actions with reasons. Login and mutations are throttled; inactive platform accounts are rejected at login and each protected request.

Owner impersonation is explicitly read-only: allowed core lists/search, invoice PDF, reports/exports and logo reads retain normal tenant/plan checks. Billing, settings, membership/account endpoints, admin controls, and all mutations are denied. Explicit exit and admin logout remain available. Tenant identity/workspace is restored on end or request-time expiry/revocation.


## Phase 6 workflows

- Owner/admin/accountant/staff: inspect per-invoice sharing/email history; create/revoke finalized-invoice links and send email while subscription writes are allowed.
- Owner/admin/accountant: read tenant CSV exports, including when subscription writes have expired. Staff/viewer cannot export.
- Owner only: request/download private workspace ZIP exports. Worker and download independently recheck ownership; expiry and cross-tenant binding fail closed. Read-only subscription mode permits data export.
- Owner/admin: advanced brand/template updates require write access and custom_branding entitlement. Existing company/logo permissions remain.
- Public bearer: view one finalized invoice PDF through a valid, unexpired, unrevoked token for an active tenant. No mutation, billing, membership or export access.
- Platform read-only impersonation retains its existing route allowlist: new share/mail/export/settings actions are not implicitly granted.

## Phase 7 activity and recovery

- Owner/admin: view workspace audit events and usage/workflow counts. Audit entries omit changed values and are append-only through application model guards.
- Owner: request workspace soft deletion after confirming password and exact workspace name. Active provider-managed subscriptions with remaining lifecycle/access block deletion.
- Platform admin: list soft-deleted workspaces and restore one with a reason. This uses the separate platform guard/audit trail; tenant role does not grant recovery.
- Soft deletion retains data indefinitely. There is no self-service restore or automatic permanent purge until retention policy is approved.
