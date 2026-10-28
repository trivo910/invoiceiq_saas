# Database schema

## Current schema through Phase 4

The following describes checked-in migrations through Phase 4. Phase 3 changed application validation/storage behavior without adding schema. The local configured MySQL database was also inspected before and after migration; other deployed databases may differ and require their own preflight.

| Table | Columns and relationships | Indexes/uniqueness |
|---|---|---|
| `tenants` | id, name, slug, gstin, pan, state_code, address, logo_path, currency, financial_year_start_month/day, invoice_prefix, status, trial_ends_at, onboarding_completed_at, timestamps | unique slug |
| `tenant_user` | id, tenant_id FK, user_id FK, role (`owner`, `admin`, `accountant`, `staff`, `viewer`), timestamps | unique tenant_id+user_id; user_id+tenant_id index |
| `tenant_invitations` | id, tenant_id FK, invited_by FK, email, role, SHA-256 token hash, expires_at, accepted_at, timestamps | unique token_hash; unique tenant_id+email |
| `settings` | id, tenant_id FK, key, nullable text value, timestamps | unique tenant_id+key |
| `customers` | id, tenant_id FK, name, gstin/pan/email/phone, billing/shipping address/city/state/state_code/pincode, credit_limit, payment_terms, notes, is_active, timestamps | unique tenant_id+gstin (multiple NULLs allowed); existing name/gstin/email/billing_state and active+name indexes |
| `items` | id, tenant_id FK, name, code, hsn_sac, description, type, rate, gst_rate, unit, is_active, timestamps | unique tenant_id+code; existing name and active+name indexes |
| `invoices` | id, tenant_id FK, number/status/dates, nullable customer FK, customer snapshot, supply/place codes, intra-state flag, discounts, tax/total fields, visible_columns JSON, notes/terms/shipping address, timestamps | unique tenant_id+number; existing status/date/due/customer indexes |
| `invoice_items` | id, tenant_id FK, invoice FK, nullable item FK, sort order, item snapshot, qty/rate/discount/taxable fields, GST/cess rates and amounts, total tax/amount, timestamps | existing HSN, GST rate, invoice+sort indexes |
| `payments` | id, tenant_id FK, invoice FK, amount/date/mode/reference/notes, timestamps | existing invoice+payment_date index |
| `tenant_invoice_sequences` | id, tenant_id FK, financial_year (`YYYY-YY`), next_number, timestamps | unique tenant_id+financial_year |
| `plans` | code/name/description, nullable monthly/yearly INR price in paise, nullable invoice/member limits, nullable report/branding/API feature flags, Razorpay monthly/yearly plan IDs, active/sort fields | unique code |
| `tenant_subscriptions` | tenant_id FK, plan_id FK, status (`trialing`, `pending`, `active`, `past_due`, `paused`, `cancelled`, `completed`, `expired`; `checkout_failed` is a terminal initial-checkout history state excluded from entitlement selection), interval, unique Razorpay subscription ID, trial/period/grace/past-due start/cancel/end timestamps, provider state event timestamp, provider metadata JSON | tenant/status index |
| `billing_events` | unique provider event ID, event type, nullable tenant FK, processed timestamp | unique provider_event_id |
| `billing_payments` | tenant_id FK, nullable subscription FK, unique provider payment ID, amount/currency/status/method/paid timestamp/provider metadata | tenant/created index |
| `billing_invoices` | tenant_id FK, nullable payment FK, unique number, subtotal/tax/tax breakdown/total/currency, seller/buyer JSON snapshots, issue timestamp | tenant/issued index |
| `users` | id, name, globally unique email, verified timestamp, password, legacy role, remember token, timestamps | unique email |
| `password_reset_tokens` | email primary key, token, created_at | primary key email |
| `jobs` | queue, payload, attempts, reservation/availability/creation timestamps | queue index |
| `job_batches` | Laravel batch metadata | primary key id |
| `failed_jobs` | connection/queue/payload/exception/failure timestamp | unique uuid |
| `migrations` | Laravel migration ledger | framework managed |

Business tables `settings`, `customers`, `items`, `invoices`, `invoice_items`, and `payments` have non-null indexed tenant foreign keys with cascade delete. Existing relationships remain: invoice.customer_id → customers (null on delete); invoice_items.invoice_id → invoices (cascade), invoice_items.item_id → items (null on delete); payments.invoice_id → invoices (cascade). The schema does not have composite same-tenant foreign keys; model save guards and request validation enforce tenant matches for invoice customers, line invoice/items, and payments. GST reports are computed, not stored in report tables.

## Membership and ownership

Users remain global identity records. `tenant_user` supports multiple workspaces and holds the role per workspace. Requests select the session's tenant when the user belongs to it; otherwise they use the lowest-ID membership. Accepting an invitation selects that workspace in the session. Password reset and queue infrastructure tables are not tenant-owned.

## Migration order and status

1. [x] Create `tenants`, seed a default tenant from existing company settings, then attach users through `tenant_user`.
2. [x] Add/backfill tenant FKs for every existing business table, then enforce non-null constraints.
3. [x] Replace global settings-key, item-code, and invoice-number constraints with tenant-scoped composite unique indexes; add tenant-scoped unique customer GSTIN after duplicate preflight. Empty GSTIN strings are normalized to NULL.
4. [x] Create per-tenant financial-year sequence rows; preserve historical invoice numbers.
5. [x] Add database queue config and jobs/batches/failed-jobs tables for shared-host-compatible queuing.
6. [x] Add onboarding state, mark migrated tenants complete and existing users verified to preserve their access, then add tenant invitations.
7. [x] Phase 3: scope raw dashboard SQL and GST joins; validate linked records and private logo access; add two-tenant feature tests.
8. [x] Phase 4 foundation: plans/subscriptions/payments/invoices/events schema; new-account trials; checkout configuration; signed/idempotent webhook processing; billing history and read-only/usage enforcement.
9. [ ] Phase 4 product configuration: populate approved prices/limits, seller tax details, grace behavior, downgrade/proration/refund policy, then run provider sandbox and invoice QA.

Migration files: `2026_10_23_000001_create_tenants_and_scope_business_data.php`, `2026_10_23_000002_create_database_queue_tables.php`, `2026_10_23_000003_add_tenant_scoped_customer_gstin_unique.php`, `2026_10_23_000004_add_phase_two_auth_and_invitations.php`, `2026_10_26_000001_create_billing_tables.php`, `2026_10_26_000002_add_tax_breakdown_to_billing_invoices.php`, `2026_10_26_000003_make_plan_features_optional.php`, `2026_10_26_000004_add_provider_state_timestamp_to_tenant_subscriptions.php`, and `2026_10_27_000001_add_past_due_started_at_to_tenant_subscriptions.php`.

## Preflight and verification

On the configured local MySQL database before migration: 5 customers, 9 items, 1 user, 17 settings, 0 invoices, 0 invoice lines, 0 payments; no duplicate item codes, non-empty GSTINs, or invoice numbers and no orphaned invoice references. After migration, the five customers, nine items, 17 settings, and one user remain; all business rows have one tenant, the user has one membership, and all three queue tables exist. Other installations must back up and resolve duplicate customer GSTINs before applying the unique index.

New generated invoice numbers use `PREFIX-YYYY-YY-NNNN` (for example `INV-2026-27-0001`) based on the tenant's configured FY start. Existing invoice numbers are not rewritten. SQLite is not the production database target; the migration and locking behavior were exercised against the configured local MySQL only.


Phase 3 introduced no schema migration. Phase 4 adds migrations `2026_10_26_000001_create_billing_tables.php`, `2026_10_26_000002_add_tax_breakdown_to_billing_invoices.php`, `2026_10_26_000003_make_plan_features_optional.php`, `2026_10_26_000004_add_provider_state_timestamp_to_tenant_subscriptions.php`, and `2026_10_27_000001_add_past_due_started_at_to_tenant_subscriptions.php`. Feature tests run against a separate MySQL database named invoiceiq_test; the local development database is not used by the suite. The checked local data audit found zero cross-tenant invoice/customer, line/invoice, line/item, or payment/invoice links.


## Phase 5 schema

Migration `2026_10_28_000001_create_superadmin_tables.php` adds platform-owned infrastructure (these tables do not use tenant scopes):

| Table | Fields and relationships | Constraints |
|---|---|---|
| `super_admins` | name, unique email, hashed password, is_active, timestamps | Separate from tenant users; no default account |
| `admin_audit_logs` | nullable super_admin_id/tenant_id FKs, action, target_type/id, reason, JSON details, IP, created_at | Actor/tenant deletion sets FK null; action/time indexes; application append-only |
| `coupons` | unique normalized code, percent/fixed_paise type, positive integer value, active flag, optional start/expiry, timestamps | Percent <=100 validated; catalog-only, no checkout redemption |
| `announcements` | title, plain-text body, active flag, optional start/expiry, timestamps | Escaped and date-filtered at display |

The existing tenant status field now enforces suspension. Manual grants reuse tenant_subscriptions with an explicit paid-through deadline, no provider ID, and `provider_metadata.manual_grant=true`; no payment/invoice is synthesized. Original tenant-owned model schemas/scopes remain intact. Migration is reversible by dropping only the four new tables in reverse dependency order; rollback removes their platform data and should be rehearsed on a backup.

Local preflight found the older Phase 4 past_due_started_at migration pending despite prior documentation; applied it before the Phase 5 migration. The isolated test DB already had that column. Phase 5 tables were applied to both local and test MySQL on 2026-10-28.


## Phase 6 schema

Additive migration `2026_10_28_000002_create_workspace_workflow_tables.php` adds:

| Table | Fields | Boundary |
|---|---|---|
| invoice_share_links | tenant/invoice/creator FKs, unique 64-character token_hash, expiry/revocation, timestamps | Hash only; tenant-scoped admin access, exact capability lookup for public reads |
| invoice_deliveries | tenant/invoice/requester FKs, status, encrypted mediumtext payload, attempts, sent_at, timestamps | Scalar queue IDs; encrypted recipient/message data, no transport secrets in jobs |
| account_exports | tenant/requester FKs, status, private path, expiry, timestamps | Owner-only generation/download, tenant path prefix validation |

All three models use BelongsToTenant. Invoice-linked saves reject foreign invoices and tenant reassignment; requester checks prevent foreign actors. Export requesters are workspace owners at creation and cannot be reassigned; worker/download checks re-evaluate owner permissions. Tenant/invoice deletion cascades workflow rows; referenced user deletion is restricted. Rollback drops only these new tables and their workflow history; file cleanup requires operator handling.

Brand color/template are tenant settings keys, not new columns. Company profile updates synchronize existing tenant identity fields. No original invoice/GST schema changed. Migration applied to local MySQL and invoiceiq_test on 2026-10-28.
