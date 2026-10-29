# Progress

## Current position

- **Phase:** 7 - Launch hardening (implementation in progress; provider/commercial and production operations remain open)
- **Status:** Implemented public landing/pricing pages, fictional/referenceable legal sample drafts, tenant activity auditing, owner workspace soft deletion and platform recovery, baseline security headers, SMTP-password-at-rest encryption, tax identifier format normalization and release configuration checks. User approved creating fictional placeholder legal data; approval remains false and sample pages are not launch-ready.
- **Code changes:** Phase 7 additive migration, tenant audit hooks, workspace lifecycle and pages, public routes/views, release check/encryption commands, setup safety, VPS nginx sample and documentation. Existing GST math is unchanged.
- **Migration state:** Phase 7 migration applied to local MySQL and invoiceiq_test on 2026-10-28.
- **Automated tests:** Focused Phase 7 tests pass: 7 tests, 41 assertions. Full MySQL suite passes: **97 tests, 750 assertions** (2026-10-29). Real SMTP/provider/VPS/backup restore/legal review remain unverified.

## Completed

- Documented current schema/features and the SaaS conversion plan in Phase 0.
- Added tenant/workspace and user-membership schema; backfilled existing business rows and users into one default tenant.
- Added tenant context, middleware before route binding, and fail-closed tenant scopes for settings, customers, items, invoices, invoice items, and payments.
- Fixed tenant middleware dependency injection to use Laravel's constructor injection convention after a local runtime error report; authenticated local dashboard request now returns HTTP 200.
- Replaced global unique settings/item/invoice keys with tenant-scoped keys.
- Added tenant-scoped GSTIN uniqueness after checking duplicate groups; empty GSTIN values normalize to NULL.
- Added per-tenant financial-year invoice numbering with transaction and row lock.
- Added database queue configuration and jobs/batches/failed-jobs tables.
- Added MySQL example environment file; updated MySQL default, setup script, README, and affected docs.
- Applied migrations and checked data counts, tenant assignments, queue tables, and invoice route registration.
- Added transactional account/workspace registration, signed email verification, password reset broker screens, and session regeneration on authentication transitions.
- Added onboarding for company/GST/logo/invoice prefix and optional first customer/item; migrated workspaces are marked complete and existing users verified to preserve their sign-in flow.
- Replaced global user CRUD with tenant-scoped invitations, roles, membership updates/removal, and Gates for workspace/record permissions.
- Added SMTP/log mail configuration, updated setup/environment/docs, applied Phase 2 migration, syntax-checked changed PHP files, and confirmed all routes register.
- Handled local SMTP transport failures without turning registration/reset/invitation requests into 500s; switched this development environment to the configured log mailer after confirming the unavailable `127.0.0.1:1025` transport.
- Audited raw SQL, joins, route binding, search, CRUD, reports, exports, cache, PDFs, mail, and file paths; scoped dashboard SQL/cache and GST joins; added customer/item/invoice/line/payment same-tenant guards.
- Moved new logos to private tenant storage and added a command to migrate legacy public logo paths; feature tests cover two-tenant reads, writes, searches, reports, Excel exports, linked records, and logo access.
- Created and migrated the isolated MySQL `invoiceiq_test` database; PHPUnit passes 7 tests and 55 assertions. Read-only audit found zero cross-tenant links in local invoice/customer, line/invoice, line/item, and payment/invoice relationships.

- Added configurable plans and tenant subscriptions; new workspaces receive a 14-day card-free trial and migrated workspaces keep an unlimited legacy plan.
- Added Razorpay server-side checkout, raw-body HMAC webhook verification/idempotency, tenant payment history, configurable subscription tax invoices, billing UI, usage meters, and trial/grace/period-end read-only enforcement.
- Added tenant-locked rechecks for invoice/member limits and feature coverage for trials, checkout, webhook replay/payment capture, private billing PDFs, and tenant isolation.
- Added tenant-scoped failed renewal payment history from signed Razorpay webhooks; event replay creates no duplicate payment or invoice.
- Added a two-tenant webhook regression proving provider subscription lookup assigns payment/event records to the correct workspace regardless of active request tenant context.
- Checkout requires a non-whitespace Razorpay webhook secret as well as API credentials; blank secrets cannot enable checkout, show pending links, or accept webhooks.
- A halted initial checkout is retained as `checkout_failed` history without replacing existing trial/legacy entitlement; the owner can retry instead of being stuck behind a pending row.
- Hide the previous halted-checkout notice once the owner has started a replacement checkout.
- Preserve `checkout_failed` on late failed-payment callbacks from an initial halted checkout while retaining the payment attempt in billing history.
- Anchor configured grace deadlines to the provider failure event time so delayed webhook delivery does not grant extra write access.
- Verify missing and malformed provider failure timestamps fall back to webhook receipt time when anchoring grace.
- Preserve the first provider failure timestamp across repeated failures while grace is unset; clear it when payment recovery activates the subscription.
- Verify late `subscription.pending` callbacks cannot reopen a halted initial checkout and still retain failed payment history.
- Verify one provider payment ID remains idempotent across distinct captured-payment webhook event IDs, including tax invoice creation.
- Preserve captured payment details when later captured notifications reuse its provider ID with conflicting amount or currency.
- Require a nonblank tenant state code for saved pending checkout links as well as new checkout; cover missing and whitespace-only state values in the page and endpoint.
- Attempted one unauthenticated Razorpay API connectivity check: API host responded with HTTP 401; no sandbox key, secret, or webhook secret is configured, so no account-backed checkout/webhook or tax-invoice QA was possible. Local mocked inclusive/interstate invoice tests pass.
- Added a guarded local/testing demo catalog (sample prices/quotas and seven-day grace) and explicit UI labeling; demo mode blocks checkout and subscription tax invoices.
- Fixed `payment.failed` webhooks to move established subscriptions into `past_due` with the configured grace window; initial pending checkout subscriptions remain pending.
- Updated billing status labels and read-only/overdue notices to match trial, subscription-period, and grace enforcement.
- Prevented out-of-order provider events from regressing subscription state by storing the latest processed provider state timestamp; stale events still record their payment history.
- Enabled replacement checkout after a configured past-due grace period expires, while preventing overlapping checkout during active grace access.
- Verified cancelled subscriptions retain writes through the provider period end and switch to read-only afterward; billing labels show the access-through date.
- Preserved the provider's final period end on `subscription.completed`, kept `completed` distinct from `cancelled`, and verified read-only enforcement after that date.
- Added `subscription.paused` handling with paid-through access and verified a newer `subscription.resumed` restores active status.
- Serialized checkout creation per workspace and rechecked pending subscriptions under the tenant-row lock to prevent duplicate provider subscriptions on concurrent requests.
- Restricted billing history, checkout, and subscription invoice downloads to the active tenant owner; billing navigation is hidden from other membership roles.
- Made the Billing navigation follow the active workspace owner membership rather than the legacy global user role.
- Added explicit same-tenant validation for billing payment/subscription and billing invoice/payment links, including tenant ID tampering checks.
- Added a subscription save guard against tenant ID changes and extended cross-tenant regression coverage to subscriptions.
- Kept early Razorpay subscription events retryable when provider creation arrives before the local subscription insert; an unresolved provider subscription now returns 503 without consuming the event.
- Made paid active subscriptions require a future provider period end for write access; legacy workspaces remain unlimited, and missing paid period ends display as ended.
- Validated provider subscription IDs and hosted checkout URLs before persisting pending checkouts, avoiding stuck rows after malformed provider responses.
- Added webhook-backed invoice regression coverage for exclusive catalog pricing and interstate IGST alongside the existing inclusive same-state CGST/SGST case.
- Improved plan card sizing and checkout button wrapping for narrow billing page layouts; bumped the shared CSS asset version.
- Added recovery for older pending checkout rows by retrieving and validating their existing Razorpay hosted URL; the billing page now explains when a pending link is unavailable.
- Added an owner-facing restore action for pending checkout rows with missing links and verified the complete page-to-provider recovery flow.
- Made grace deadlines derive from the original provider failure time if `BILLING_GRACE_DAYS` is configured after an event was recorded with grace unset.
- Clarified the past-due billing notice when no grace deadline exists and covered both unset and configured grace states.
- Trimmed seller and tenant state codes before billing eligibility checks and same-state/interstate tax classification; covered padded seller state code in the CGST/SGST regression.
- Allowed a read-only paid subscription with an elapsed or missing provider period end to start replacement checkout, while preserving current paid-period protections and legacy unlimited access.
- Prevented a late failed-payment webhook from downgrading an already captured payment, creating conflicting payment history, or moving its subscription to past due.
- Restricted tax invoice creation to positive INR captures while retaining other captured currency/amount records in billing history.
- Reconciled captured amounts with the subscription's configured cycle price before tax invoice issuance, including GST for exclusive pricing.
- Normalized seller and buyer state codes in saved tax invoice snapshots so PDFs show the same values used for GST classification.
- Extended captured-amount mismatch regression coverage to both inclusive and exclusive price tax modes.
- Ignore malformed or non-positive provider period timestamps and retain the subscription's last valid access period.
- Store missing or malformed provider currency as `XXX` rather than defaulting it to INR, preventing incomplete payment events from producing tax invoices. Malformed payment timestamps now fall back to receipt time.
- Reject malformed, negative, and overflowing webhook amounts; test webhook rejection for missing/blank/oversized event IDs, invalid JSON, and missing configuration.
- Cover member-seat reservations for invitation resends, expiry, acceptance at capacity, and acceptance after a plan limit reduction; enforce the invite limit in its tenant-locked controller transaction.
- Verify expired-trial workspaces cannot create invitations or accept existing invitations.
- Verify monthly invoice usage excludes prior-month records and blocks writes at the exact configured limit.
- Verify yearly exclusive-tax checkout validates the yearly provider plan and enforces monthly/yearly cycle-count boundaries.
- Verify same-state GST component rounding preserves the full tax amount when the split has an odd paise remainder.
- Keep zero-priced paid plans, the free trial, and legacy plan out of Razorpay checkout eligibility.
- Allow replacement checkout after a paused subscription's paid period ends, while preserving write access and blocking replacement checkout during the paid period.
- Hide existing pending checkout URLs and block their direct continuation when webhook authentication is missing; preserve pending state and show an owner-facing explanation.
- Require complete, non-whitespace seller tax fields before checkout or tax-invoice issuance; verify invalid configuration cannot enable checkout or produce invoices.
- Confirmed Razorpay credential variables are configured locally without displaying or transmitting their values. Demo mode remains enabled; seller tax settings and cycle counts are absent. Local HTTP responds on port 8000, but HTTPS handshake fails; no public webhook delivery or account-backed checkout was verified.
- Prevented a signed webhook from reusing a provider payment ID already bound to another tenant/subscription; conflicting events are recorded and ignored without changing either billing record.
- Ignore signed webhook payloads whose subscription and payment entities identify different provider subscriptions; record the event without changing subscription state or payment history.
- Per user direction, defer further payment-gateway-specific test work and sandbox execution for now; existing mocked billing regressions remain in the normal PHPUnit suite.
- Kept settings and member-list pages readable for authorized workspace owners/admins after subscription expiry while applying subscription write enforcement only to settings/member mutations.
- Reject malformed or negative `BILLING_TRIAL_DAYS` before workspace registration writes; zero remains an explicit no-trial configuration.
- Reject malformed or negative `BILLING_GRACE_DAYS` when deriving a grace deadline; zero remains a valid immediate-expiry setting.

## In progress / next

1. Provision a platform administrator with `php artisan superadmin:create` and manually review the platform screens; Phase 6 workflow and Phase 7 activity/recovery UI review are also pending.
2. Replace local demo assumptions with approved prices/limits, seller tax invoice details, grace duration, downgrade behavior, proration, and refund decisions; then verify Razorpay sandbox events and production tax invoices.
3. Before deploying to another installation, back up its database, preflight actual legacy data/migration drift, then rehearse migrations on staging.

## Known issues and cautions

- Only the configured local MySQL business dataset was inspected; it had no invoices or payments. Other installations with invoice history need backup and preflight.
- Same-tenant relationships are enforced by application validation/model hooks; the schema does not yet have composite tenant foreign keys.
- A full workspace switcher remains later work; Phase 6 mail/export jobs now carry explicit tenant IDs and restore context.
- Email delivery defaults to the `log` mailer; production must configure SMTP credentials and sender identity.
- Legacy public logos should be moved after upgrade with `php artisan tenants:migrate-legacy-logos`.
- Paid checkout remains disabled in demo mode and requires provider plan IDs, credentials, recurring cycle count, and production billing configuration after demo mode is off; invoice issuance is also blocked in demo mode and requires seller/GST/SAC fields and tenant state. Sandbox credentials are now present in local `.env`, but they have not been used for an account-backed API request or checkout; no public HTTPS webhook URL has been verified.
- `BILLING_DEMO_MODE=true` is enabled only in the local `.env`; its seven-day grace assumption is for local exploration. The config ignores demo mode outside `local`/`testing`. With demo mode off, `BILLING_GRACE_DAYS` remains blank by default and `past_due` retains writes until a duration is approved.

## Resume point

Read `README.md`, this file, `TENANCY_RULES.md`, `CODING_STANDARDS.md`, `DATABASE_SCHEMA.md`, `ROADMAP.md`, `LAUNCH_CHECKLIST.md`, `DEPLOYMENT.md`, and `WORKFLOWS.md`. Phase 7 implementation is in progress; legal approval, Phase 4 provider QA, manual browser review, production SMTP, VPS, backup and restore remain open. Upgrade other installations only after backup, preflight and staging rehearsal.

### Phase 7 continuation - 2026-10-28

- Implemented public home/pricing/terms/privacy pages. Pricing is approval-gated and local sample values are labeled; legal pages are clearly draft placeholders because the operator has not finalized legal content.
- Added tenant activity audit schema and model hooks. Events retain action/target/changed field names only; invitations and membership mutations have explicit events. Added activity/usage view for workspace owner/admin.
- Added password plus exact-name owner soft delete and platform-only deleted-workspace recovery. Data is retained; provider-managed subscriptions block deletion while lifecycle/access remains. Late signed webhooks for deleted roots are acknowledged without applying changes.
- Added baseline security headers, more writer/search/email rate limits, and GSTIN/PAN/state format normalization. GST calculations are unchanged. Added at-rest encryption for SMTP password settings and an explicit legacy encryption command.
- Reworked setup helper to preserve APP_KEY, avoid credentials on command lines, and avoid implicit demo seeding/admin creation. Added `release:check`, VPS/nginx sample, release checklist and deployment/backup/rollback guidance.
- Applied Phase 7 migration to local MySQL and invoiceiq_test after `--pretend`. Focused Phase 7 suite: 7 tests, 41 assertions. Full MySQL suite after Phase 7 changes passed **97 tests, 749 assertions** (2026-10-29).
- User confirmed local development now and VPS deployment later; legal pages are not ready. VPS, SMTP, Razorpay account-backed QA, backup/restore, rollback, browser review and approvals remain launch gates. No provider payment/email or VPS operation performed.

### Phase 7 follow-up - 2026-10-29

- Closed an audit blind spot in invoice edits: relationship bulk deletion of old invoice lines bypassed model events. Invoice updates now append an `invoice.lines_replaced` event in the same transaction, containing removed/replacement counts only. No invoice contents, field values, or customer data are copied into audit details. Broader browser/staging audit review and retention policy remain open.
- Closed workspace settings audit gaps: profile query-builder updates now log field names, while logo replacement/removal records the operation only. No profile values or private file paths are stored in the event details.
- Invitation reissues now use a distinct `invitation.resent` audit action rather than appearing as newly created invitations; email addresses and token data remain excluded.
- Workspace activity now shows human-readable labels and safe event summaries, including invoice line counts and logo operations, instead of hiding non-field details.
- Production `release:check` now detects plaintext legacy SMTP passwords and encrypted values that the configured APP_KEY cannot decrypt, without printing credential contents.
- Terminal invoice-email and account-export job failures now use tenant-scoped model updates (and audit hooks) when the workspace is active; suspended/deleted workspaces retain the explicit tenant-ID fallback.

- Extended rate limits to settings/logo/branding/share revocation/member mutations. Membership change and audit events now share a DB transaction.
- Security headers are applied to normal and exception responses; regression coverage checks headers on a 404.
- Expanded production `release:check` to check paid billing values without exposing them, approvals, HTTPS/session, DB queue, SMTP config, PHP version/extensions, active platform admin, pending migrations and writable private storage.
- Latest full suite: **97 tests, 750 assertions**; focused Phase 7: **7 tests, 41 assertions**. Production release check correctly reports remaining configuration gates in this local setup. It cannot verify external service behavior or hosting.

### Legal sample continuation - 2026-10-29

- Expanded Terms and Privacy pages into clearly fictional sample drafts with `InvoiceIQ Sample Operator` and reserved non-deliverable `legal@example.invalid` details. The pages remain marked sample/not approved regardless of local environment values.
- Added direct primary-source references for the DPDP Act, phased commencement notification, DPDP Rules, IT Act, and 2011 SPDI Rules. Added `LEGAL_DRAFT_NOTES.md` with code-grounded data categories, unknowns, replacement checklist, and a caution that cited provisions have phased commencement and applicability must be checked by counsel.
- Kept `LAUNCH_LEGAL_APPROVED=false`; no approval state, legal entity, contact email, or production setting was changed. No automated tests were run for this content-only documentation/view update.

### Razorpay Test Mode recheck - 2026-10-29

- Updated Subscriptions dashboard screenshot shows Test Mode and an enabled first-plan creation flow. Read-only `GET /v1/plans?count=100` now returns HTTP 200 with zero plans; test credentials matched local `.env` and were not printed. This is a changed provider/account state compared with the prior 401.
- No provider plan/payment/subscription was created. Next manual step is to create QA-labeled plans using the approved demo catalog, then provide the plan IDs so they can be attached to local plan records and checkout QA can proceed. See GATEWAY_QA.md.

### Razorpay QA sample plans - 2026-10-29

- At the user's request, created Test Mode QA-only Starter monthly/yearly plans at the approved sample amounts (₹499 / ₹4,990) and saved their returned IDs to the matching local Starter plan fields. An idempotent follow-up list check matched both plans' period, interval, currency, amounts and local IDs.
- IDs: monthly `plan_TtgrwbxWmjwMXJ`; yearly `plan_TtgrxK0onQQQgF`. No subscription or payment created; no real money or production plan used. IDs and evidence are recorded in GATEWAY_QA.md.
- Next: perform application checkout in a QA workspace, then verify actual Razorpay webhook/checkout outcomes. Do not interpret plan creation as checkout/webhook completion.

### Latest continuation — 2026-10-27

- Fixed non-owner plan-restriction redirects: expired access, reached quotas, and denied features lead to the dashboard with an owner-contact warning; owners retain Billing redirects.
- JSON restriction responses consistently return HTTP 403; owner-only Billing access is preserved.
- Added focused non-gateway regression coverage. Validation result is recorded in TESTING.md.
- Commercial decisions and gateway QA remain deferred; no Phase 5 work or migrations were started.
- Latest continuation validation: PHP syntax and diff whitespace checks passed; after MySQL reconnection, focused restriction tests passed (4 tests, 39 assertions) and tenant isolation passed (7 tests, 55 assertions). Gateway tests remain deferred. See TESTING.md for commands.

### Gateway QA resumed ? 2026-10-28

- User lifted the gateway testing deferral. Full automated suite passed: 62 tests, 502 assertions.
- Authenticated plans GET reached the official Razorpay API but returned HTTP 401. No provider objects or payments were created.
- Requested corrected Test Mode credentials, sandbox assumptions, and public HTTPS staging/tunnel choice. Credentials were not displayed.
- Added GATEWAY_QA.md with prerequisites, execution checklist, and evidence requirements. No application configuration, catalog, or schema was changed.

### Approved sandbox setup - 2026-10-28

- User approved sample prices/seller data, 12 monthly/2 yearly cycles, seven-day grace, and a temporary tunnel.
- Updated credentials were loaded correctly but the provider plans request still returned HTTP 401.
- Prepared isolated QA settings and a webhook-only HTTPS tunnel. Public checks passed: private paths 404, unsigned webhook 400, signed synthetic event and replay 204 with one stored event. This does not prove provider delivery.
- Waiting for corrected account access and Test Mode webhook registration. Tunnel remains running for this setup; see GATEWAY_QA.md for endpoint and shutdown command.


### Webhook setting correction - 2026-10-28

The user's latest .env change put an HTTPS URL in `RAZORPAY_WEBHOOK_SECRET`. The app uses this value as the HMAC signing key, not as an endpoint setting. Replaced the URL-valued secret with a generated private secret without displaying it and cleared configuration cache. The Razorpay Test Mode dashboard must now use the new local `RAZORPAY_WEBHOOK_SECRET` value in its Secret field and the public tunnel endpoint in its Webhook URL field. No dedicated webhook URL environment setting is currently consumed by the app.

The authenticated official plans read was retried with the currently loaded API pair and still returned HTTP 401. The key/secret match the .env file with no surrounding whitespace. This remains separate from webhook setup; provider checkout/renewal QA is not complete.

Recheck after secret correction passed: public root/private paths 404, unsigned webhook 400, signed synthetic event/replay 204 with one event row (`gateway_qa_connectivity_20261028_6c27674400`). This proves local signing across the public tunnel, not Razorpay-generated delivery. Tunnel remains running for dashboard setup.

### Latest webhook-secret recheck - 2026-10-28

Updated local secret passed signed synthetic event/replay checks (204 each, one event row); unsigned requests returned 400 and other paths 404. The old tunnel expired and was replaced. Current endpoint: https://window-flower-unified-affair.trycloudflare.com/webhooks/razorpay. Update the Test Mode dashboard URL and Secret together. API plans authentication still returns HTTP 401. No provider-backed payment was made; tunnel remains running for setup. See GATEWAY_QA.md for evidence and shutdown.

### User-managed tunnel check - 2026-10-28

- Supplied public tunnel hostname returns DNS NXDOMAIN; requested current tunnel URL.
- Local port-8000 webhook passes signed/replay 204, unsigned/tampered 400, one event record. This is synthetic evidence.
- Authenticated plans API still returns HTTP 401. No provider objects or payments were created. See GATEWAY_QA.md.

### Replacement user-managed tunnel verified - 2026-10-28

Public endpoint https://prediction-attempts-configure-tires.trycloudflare.com/webhooks/razorpay passed signed/replay 204, unsigned/tampered 400, one event row. Event ID: gateway_qa_connectivity_20261028_3255c0b550. This is synthetic evidence only. Configure this current URL in the Test Mode dashboard and retain the matching local webhook secret. The earlier API HTTP 401 remains unresolved; no new API request or provider payment was made in this check.

### Dashboard URL confirmation - 2026-10-28

User confirmed dashboard URL update; public synthetic signed/replay checks passed again. Last-24-hour webhook infrastructure aggregation found no non-connectivity events. Actual provider delivery remains unverified. Plans API retry still returns HTTP 401; account-backed checkout requires working Test Mode API credentials. See GATEWAY_QA.md.

### Corrected API diagnosis - 2026-10-28

Validated current Test Mode credentials with payments GET 200; plans/subscriptions GET return 401 using the same pair. Direct cURL confirms correct outgoing Basic Auth and TLS. Previous advice to replace credentials based only on plans 401 is superseded. Investigating endpoint-specific account/product access; awaiting Subscriptions dashboard status. See GATEWAY_QA.md.

### Dashboard inspection request - 2026-10-28

User asked the agent to check the Test Mode Subscriptions dashboard. No authenticated browser tool or connected document session is available, so dashboard UI/enablement could not be inspected. Independent API rerun reconfirmed payments GET 200, plans GET 401, subscriptions GET 401 with the same valid Test Mode pair, correct Basic Auth, and successful TLS. Product provisioning/permission remains a hypothesis. Need the user-visible Subscriptions page state (or a screenshot without secrets); no dashboard or account settings were changed.


### Dashboard failure after Get Started - 2026-10-28

User screenshots show Test Mode enabled, Subscriptions initially offering Get Started, and then the Razorpay dashboard error: "Failed to load subscriptions. Please try again." API checks after this step still returned payments 200 and plans/subscriptions 401 with the same validated pair and correct Basic Auth/TLS.

This supports investigation of the provider's Subscriptions product/account access or service behavior; the exact root cause is not confirmed. A route/controller or webhook URL change cannot be justified by the observed dashboard error. Await Razorpay support findings before repeating account-backed subscription QA. No credentials/configuration were changed, and no provider objects were created.

### Support ticket submitted - 2026-10-28

User confirmed a Razorpay support ticket has been submitted. Gateway provider checks remain pending the response; no ticket ID or ETA was supplied. Do not repeatedly retry the same failing endpoints without a configuration/provider-state change. Phase 4 is not marked complete; commercial decisions and provider-backed QA remain open. Next-phase scope was presented to the user while reviewing the Phase 5 spec.


### Phase 5 implementation - 2026-10-28

- Implemented a separate platform identity/guard and interactive `superadmin:create`; no default admin/password or new environment secrets.
- Added platform overview, workspace directory/details, audited suspension/reactivation, local trial extension and time-limited manual grants; provider-managed plan changes are guarded.
- Added plan/coupon/announcement CRUD, protected existing price contracts, scheduled escaped announcements, and platform audit review. Coupon redemption remains a Phase 4 billing-policy dependency.
- Added 15-minute owner-only read-only impersonation, tenant binding, persistent exit banner, actor/target/reason/start/end audit, expiry/revocation checks and original identity restoration.
- Enforced suspension at tenant resolution and billing write checks. Existing GST/invoice workflow is unchanged.
- Applied local/test migrations and passed full suite (77 tests, 632 assertions), syntax, routes and Blade compilation. No Phase 6 work started. Actual browser review/first admin provisioning remain operator steps, not claimed as executed.


### Phase 6 implementation - 2026-10-28

- Added classic/accent invoice PDF selection and validated brand color under the custom_branding entitlement; private logos and existing GST/line/totals markup remain intact. Company setting edits now synchronize root tenant identity fields.
- Added hashed 256-bit invoice capabilities with 1-90 day expiry, revocation, finalized-invoice checks, suspension checks, no-store/no-referrer/noindex headers and no mutation surface.
- Added encrypted email-delivery records and scalar-ID jobs that restore tenant context and recheck sender membership. Existing email/finalise actions now queue, retain ZIP/PDF attachment behavior and mark a draft sent only after success. Delivery status is visible per invoice.
- Added role-protected tenant CSVs with spreadsheet-formula protection and owner-only queued private ZIP exports including business records, safe settings/member/billing data and logo. Secret/token fields are excluded. Exports expire after 24 hours and have a scoped pruning command.
- Added short database queue-worker scheduling and daily pruning; only schedule definitions were inspected, no real queued email was executed.
- Applied Phase 6 migration locally/on test DB. Full suite passed: 90 tests, 708 assertions. Manual browser, SMTP delivery and hosting cron review remain unverified. Phase 7 not started.
