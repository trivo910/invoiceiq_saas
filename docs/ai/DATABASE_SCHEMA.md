# Database schema

## Current schema through Phase 2

The following describes checked-in migrations after Phase 1. The local configured MySQL database was also inspected before and after migration; other deployed databases may differ and require their own preflight.

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
| `users` | id, name, globally unique email, verified timestamp, password, legacy role, remember token, timestamps | unique email |
| `password_reset_tokens` | email primary key, token, created_at | primary key email |
| `jobs` | queue, payload, attempts, reservation/availability/creation timestamps | queue index |
| `job_batches` | Laravel batch metadata | primary key id |
| `failed_jobs` | connection/queue/payload/exception/failure timestamp | unique uuid |
| `migrations` | Laravel migration ledger | framework managed |

Business tables `settings`, `customers`, `items`, `invoices`, `invoice_items`, and `payments` have non-null indexed tenant foreign keys with cascade delete. Existing relationships remain: invoice.customer_id → customers (null on delete); invoice_items.invoice_id → invoices (cascade), invoice_items.item_id → items (null on delete); payments.invoice_id → invoices (cascade). Application scope currently prevents ordinary cross-tenant model lookups; same-tenant consistency for linked IDs still needs Phase 3 audit and tests. GST reports are computed, not stored in report tables.

## Membership and ownership

Users remain global identity records. `tenant_user` supports multiple workspaces and holds the role per workspace. Requests select the session's tenant when the user belongs to it; otherwise they use the lowest-ID membership. Accepting an invitation selects that workspace in the session. Password reset and queue infrastructure tables are not tenant-owned.

## Migration order and status

1. [x] Create `tenants`, seed a default tenant from existing company settings, then attach users through `tenant_user`.
2. [x] Add/backfill tenant FKs for every existing business table, then enforce non-null constraints.
3. [x] Replace global settings-key, item-code, and invoice-number constraints with tenant-scoped composite unique indexes; add tenant-scoped unique customer GSTIN after duplicate preflight. Empty GSTIN strings are normalized to NULL.
4. [x] Create per-tenant financial-year sequence rows; preserve historical invoice numbers.
5. [x] Add database queue config and jobs/batches/failed-jobs tables for shared-host-compatible queuing.
6. [x] Add onboarding state, mark migrated tenants complete and existing users verified to preserve their access, then add tenant invitations.
7. [ ] Phase 3: tenant-aware raw-query/report indexes, linked-record validation, and isolation tests.

Migration files: `2026_10_23_000001_create_tenants_and_scope_business_data.php`, `2026_10_23_000002_create_database_queue_tables.php`, `2026_10_23_000003_add_tenant_scoped_customer_gstin_unique.php`, `2026_10_23_000004_add_phase_two_auth_and_invitations.php`.

## Preflight and verification

On the configured local MySQL database before migration: 5 customers, 9 items, 1 user, 17 settings, 0 invoices, 0 invoice lines, 0 payments; no duplicate item codes, non-empty GSTINs, or invoice numbers and no orphaned invoice references. After migration, the five customers, nine items, 17 settings, and one user remain; all business rows have one tenant, the user has one membership, and all three queue tables exist. Other installations must back up and resolve duplicate customer GSTINs before applying the unique index.

New generated invoice numbers use `PREFIX-YYYY-YY-NNNN` (for example `INV-2026-27-0001`) based on the tenant's configured FY start. Existing invoice numbers are not rewritten. SQLite is not the production database target; the migration and locking behavior were exercised against the configured local MySQL only.
