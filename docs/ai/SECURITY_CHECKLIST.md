# Security checklist

Use this tick-box list during implementation and before release. Phase 0 records requirements; no checks are claimed complete for the future SaaS.

## Tenant isolation

- [ ] Every tenant model scopes and assigns `tenant_id`.
- [ ] Raw SQL, query builder, joins, aggregates, caches, route binding, AJAX, exports, and PDFs include tenant context.
- [ ] Tenant A cannot read, update, delete, enumerate, or infer Tenant B invoices, customers, items, payments, reports, or files.
- [ ] Cross-tenant customer/item IDs are rejected when creating invoices.
- [ ] Queued jobs and emails restore tenant context explicitly.

## Web and account security

- [ ] CSRF remains enabled on state-changing web routes.
- [ ] Login, registration, password reset, invite, webhook, and API endpoints have appropriate throttles.
- [ ] Session ID rotates on login; logout invalidates session and CSRF token.
- [ ] Email verification and password reset tokens use framework protections and expiry.
- [ ] Role checks are enforced by policies/server-side authorization, not UI only.

## Data and uploads

- [ ] Validate GSTIN shape and state code server-side; normalize identifiers.
- [ ] Validate uploaded content, MIME, size, and allowed image formats; use generated names and tenant-private paths.
- [ ] Serve private files only through authorized tenant-scoped routes.
- [ ] Protect secrets and webhook signatures; never log credentials, payment secrets, or full sensitive payloads.
- [ ] Audit invoice create/edit/delete, role changes, billing events, impersonation, and super-admin actions.

## Billing and operations

- [ ] Razorpay signature verification, idempotency, replay handling, and state transitions are tested.
- [ ] Plan limit checks cannot be bypassed by alternate routes or concurrent requests.
- [ ] Failed billing enters configured grace/read-only behavior without data deletion.
- [ ] Backup/restore, migration rollback, and tenant data export are exercised.
