# Security checklist

Use this tick-box list during implementation and before release. Phase 0 records requirements; no checks are claimed complete for the future SaaS.

## Tenant isolation

- [x] Every tenant model scopes and assigns `tenant_id`.
- [x] Raw dashboard SQL, GST joins, cache keys, route binding, AJAX, reports/exports, PDFs, and authorized logo delivery include tenant context.
- [x] Two-tenant tests cover cross-tenant reads, updates, payments, report output, exports, and logos.
- [x] Cross-tenant customer/item IDs and invoice line/payment links are rejected.
- [ ] Queued jobs and emails restore tenant context explicitly.

## Web and account security

- [x] CSRF remains enabled on state-changing web routes.
- [x] Login, registration, password reset, verification, invitation acceptance/creation, and Razorpay webhook routes are throttled.
- [x] Session ID rotates on login; logout invalidates session and CSRF token.
- [x] Email verification and password reset tokens use framework protections and expiry.
- [x] Role checks are enforced server-side through Gates and membership-aware middleware.

## Data and uploads

- [ ] Validate GSTIN shape and state code server-side; normalize identifiers.
- [x] Validate uploaded content, MIME, size, and allowed image formats; use generated names and tenant-private paths.
- [x] Serve logos through an authenticated tenant-scoped route; migrate legacy public logos to private storage.
- [x] Razorpay secrets are environment-configured; webhook HMAC uses raw request bytes, unique event IDs, and does not log credentials or payloads.
- [ ] Audit invoice create/edit/delete, role changes, billing events, impersonation, and super-admin actions.

## Billing and operations

- [x] Razorpay signature verification, idempotency, replay handling, checkout gating, and payment/subscription state updates are feature-tested.
- [x] Invoice/member plan limits are enforced at write routes, rechecked under a tenant-row lock, and checked again at invitation acceptance.
- [x] Expired trials and configured grace/period end enter read-only mode without data deletion; grace behavior stays open until its approved value is configured.
- [ ] Backup/restore, migration rollback, and tenant data export are exercised.
