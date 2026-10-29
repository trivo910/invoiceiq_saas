# Security checklist

Use this tick-box list during implementation and before release. Phase 0 records requirements; no checks are claimed complete for the future SaaS.

## Tenant isolation

- [x] Every tenant model scopes and assigns `tenant_id`.
- [x] Raw dashboard SQL, GST joins, cache keys, route binding, AJAX, reports/exports, PDFs, and authorized logo delivery include tenant context.
- [x] Two-tenant tests cover cross-tenant reads, updates, payments, report output, exports, and logos.
- [x] Cross-tenant customer/item IDs and invoice line/payment links are rejected.
- [x] Phase 6 invoice mail/export jobs restore explicit tenant context and recheck membership.

## Web and account security

- [x] CSRF remains enabled on state-changing web routes.
- [x] Login, registration, password reset, verification, invitation acceptance/creation, and Razorpay webhook routes are throttled.
- [x] Session ID rotates on login; logout invalidates session and CSRF token.
- [x] Email verification and password reset tokens use framework protections and expiry.
- [x] Role checks are enforced server-side through Gates and membership-aware middleware.

## Data and uploads

- [x] Validate GSTIN/PAN/state-code format server-side and normalize identifiers (format checks only; no registry/checksum verification).
- [x] Validate uploaded content, MIME, size, and allowed image formats; use generated names and tenant-private paths.
- [x] Serve logos through an authenticated tenant-scoped route; migrate legacy public logos to private storage.
- [x] Razorpay secrets are environment-configured; webhook HMAC uses raw request bytes, unique event IDs, and does not log credentials or payloads.
- [x] Record tenant-owned model create/update/delete events and explicit invitation/membership changes with field names only; distinguish invitation reissues; invoice-line bulk replacement records counts, profile/logo query-builder writes record field/action names, and terminal queue failures use model hooks when tenant context is available; platform actions remain in separate admin audit.

## Billing and operations

- [x] Razorpay signature verification, event ID/body validation, idempotency, replay handling, retryable early events, checkout gating, and payment/subscription state updates are feature-tested.
- [x] Billing history, checkout, and subscription invoice downloads are restricted to the tenant owner.
- [x] Invoice/member plan limits are enforced at write routes, rechecked under a tenant-row lock, and checked again at invitation acceptance.
- [x] Billing payments can only reference a same-tenant subscription; subscription invoices can only reference a same-tenant payment.
- [x] Expired trials and configured grace/period end enter read-only mode without data deletion; grace behavior stays open until its approved value is configured.
- [x] Paid active subscriptions without a confirmed period end fail closed to read-only; legacy subscriptions remain unlimited.
- [x] A grace duration configured after a recorded failure uses the original provider event time.
- [x] Tenant owner export exists; documented restore/rollback procedures and release-check command are available.
- [ ] Exercise database and private-file backup/restore plus migration rollback/repair on staging.

## Phase 7 controls

- [x] Public legal pages are clearly marked draft until approved; pricing sample values are labeled and approval-gated.
- [x] Owner workspace deletion requires current password and exact name; data is soft-deleted, retained, and restorable by separately authenticated platform admin.
- [x] Tenant activity history is scoped and append-only at application model layer; field values and secret payloads are omitted, while generated count/action summaries are safely rendered.
- [x] Security headers, input rate limits, SMTP secret encryption-at-rest migration path and release configuration report added; production preflight detects plaintext or undecryptable stored SMTP passwords without exposing them.
- [ ] Review full audit event coverage, retention/purge policy, and release behavior in browser/staging. Invoice-line bulk replacement is explicitly covered; normal and exception responses are covered by automated header checks.
- [ ] Verify legal approval, real SMTP/provider delivery, VPS deployment, backup restoration and rollback before release.


## Phase 5 verified controls

- [x] Separate platform identity/table/provider/guard; tenant credentials and roles cannot access controls.
- [x] Platform login and mutations throttled, CSRF preserved, session IDs/tokens rotated on authentication/impersonation transitions.
- [x] Transactional sensitive-action audit plus access/denial/login records; immutable Eloquent audit records and no audit edit/delete routes.
- [x] Reasoned owner-only, time-bounded, read-only impersonation; tenant pinning, persistent exit banner, expiry/revocation and original identity restoration.
- [x] Suspension denies tenant access/write flows while preserving data and provider event intake.
- [x] Platform service authorization and explicit context restoration; no global tenant-scope bypass.
- [x] Coupon validation and escaped/date-scoped announcements; immutable referenced plan price contracts and protected system/referenced plan deletion.

These Phase 5 checks do not complete MFA/recovery UI, production deployment verification, or Phase 4 provider QA. Audit immutability is at the application layer, not tamper-proof database storage.


## Phase 6 verified controls

- [x] Explicit scalar tenant IDs and context restoration for mail/export jobs and streamed CSVs; foreign job IDs cannot resolve records.
- [x] Hash-only random public invoice tokens, bounded expiry/revocation, finalized/active-tenant checks and read-only public endpoint with no-store/no-referrer/noindex headers.
- [x] Encrypted mail payload/history and generic transport failures; SMTP credentials never enter serialized jobs or exports.
- [x] Private owner export routes, membership rechecks, expiry/pruning, validated file prefixes and spreadsheet-formula-safe CSVs.
- [x] PDF template/color allowlists and shared tenant-scoped rendering; private logos and protected GST markup retained.

Actual SMTP delivery, deployed cron, production HTTPS, large-export runtime/crash recovery and broader Phase 7 hardening remain manual/launch checks. SMTP is at-least-once on a crash after acceptance, not exactly-once.
