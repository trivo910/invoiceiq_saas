# Permissions matrix

Target roles requested for each tenant: Owner, Admin, Accountant, Staff, Viewer. Phase 1 added a per-tenant `tenant_user.role` membership value and mapped migrated admin/staff/viewer users to owner/staff/viewer. Existing route middleware still checks the legacy global `users.role`; Phase 2 must move enforcement to membership roles and policies.

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
| Manage billing, plans, payment methods | Yes | No | No | No | No |
| Delete/recover workspace | Yes | No | No | No | No |
| Use super-admin tenant/plan controls | No | No | No | No | No |

Policies/gates to introduce with implementation include `viewAny`, `view`, `create`, `update`, `delete` for Invoice, Customer, Item, Payment; `viewReports`/`exportReports`; `manageSettings`; `manageMembers`/`inviteMembers`; `manageBilling`; and `deleteWorkspace`. Exact policy names and accountant/staff write boundaries must be implemented consistently and tested. Super-admin authorization belongs to a separate guard/role, not tenant role strings.

## Current behavior observed

Routes permit admin/staff to write invoices, customers, and items; all authenticated roles can read core lists and GST reports; admin alone controls settings and user CRUD. Legacy admin/staff/viewer remain defined in `User`. No Owner/Accountant policy enforcement exists yet.
