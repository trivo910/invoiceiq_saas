# Testing strategy

## Current state

PHPUnit 11 is a development dependency. Phase 3 added MySQL-backed two-tenant feature tests. Tests use a separate `invoiceiq_test` database configured in `phpunit.xml`; preserve the local `.env` DB host/user/password and do not point the test config at the development database. PHPUnit explicitly disables billing demo mode so checkout and invoice integration tests exercise their configured sandbox paths.

## Target automated coverage

- Migration from an existing populated single-tenant MySQL database, including data counts and unique-index preflight.
- Authentication, registration, verification, onboarding, invitation, role and policy behavior.
- Two-tenant isolation for invoice/customer/item/payment CRUD, route binding, autocomplete, dashboard, GST reports, exports, PDFs, and uploaded files.
- Same-tenant relationship validation for invoice customer, lines, and payments.
- Invoice numbering concurrency/financial-year boundaries and unchanged GST calculation regression cases.
- Subscription limits, grace/read-only state, Razorpay signature/idempotency/state transitions, and super-admin audit/impersonation.

Phase 4 currently tests 14-day trial creation, rejection of malformed/negative trial duration, and explicit zero-day (no trial) registration; malformed/negative grace duration rejection and valid zero-day immediate expiry; trial expiry and read-only billing/membership write blocking while preserving authorized settings/member-list reads, paid active subscriptions without a provider period end and unlimited legacy access, cancellation/completion access through provider period end, pause/resume lifecycle, paused subscriptions retaining writes and blocking replacement checkout until paid-through then allowing replacement checkout, checkout rejection when webhook authentication is not configured, existing pending checkout URL suppression and direct-continuation blocking when webhook authentication is missing or whitespace-only, pending checkout links remaining hidden and blocked when the workspace state code is missing or whitespace-only, checkout rejection and invoice suppression when seller tax fields are missing/whitespace-only or the GST rate is invalid, halted initial checkout release while retaining the current trial, late `payment.failed` and `subscription.pending` callbacks preserving the halted checkout's terminal status while retaining failed payment history, and suppression of the stale failure notice after replacement checkout begins, zero and exact monthly invoice limits with prior-month usage exclusion, invitation reserved-seat counts/resends/expiry/acceptance and plan-limit downgrade enforcement, feature entitlements, grace deadlines anchored to provider failure time when delivery is delayed or grace is configured after failure, fallback to receipt time when the provider failure timestamp is absent or malformed, preservation of the first failure timestamp across repeated failures while grace is unset, clearing that timestamp after payment recovery, and accurate past-due messaging when grace is unset/configured, monthly/yearly Razorpay checkout, exclusive yearly gross-price matching and cycle-count boundaries and free/legacy/zero-price checkout rejection, malformed provider checkout responses, owner-facing recovery of existing pending checkout URLs, duplicate checkout prevention and provider-plan matching, invalid/valid webhook signatures, missing/blank/oversized event ID rejection, invalid JSON and missing/whitespace-only webhook-secret rejection, event replay, retry of webhook delivery before local subscription persistence, and out-of-order state protection, successful capture/tax invoice generation for inclusive same-state CGST/SGST with padded state-code normalization in tax classification and seller/buyer snapshots, plus exclusive interstate IGST and same-state odd-paise CGST/SGST remainder handling, payment and invoice idempotency across duplicate provider payment IDs and distinct webhook event IDs, immutable captured payment details when a duplicate capture event supplies conflicting amount or currency, conflicting payment IDs bound to another subscription/tenant, and mismatched subscription/payment entity IDs, failed renewal history and `past_due` grace enforcement without invoice issuance, replacement checkout eligibility after grace expiration and after a paid active period ends (including a missing period end), malformed provider period timestamp preservation, captured-payment protection from late failure webhooks and invoice suppression for non-INR/zero/mismatched captures under inclusive and exclusive pricing and unknown-currency captures with malformed payment time fallback, malformed/negative/overflow payment amount rejection, owner-only billing/history/invoice access and membership-based billing navigation, same-tenant billing relationships and tenant ID tampering (including subscription tenant immutability), correct tenant assignment when webhook context differs, demo catalog labeling/checkout/invoice guards, billing PDF tenant binding, and the Phase 3 tenant isolation suite. The billing plan layout should also be manually checked at desktop, tablet, and phone widths. Last complete suite before the read-only route adjustment: `php vendor/bin/phpunit` (59 tests, 469 assertions); previously verified billing suite: 52 tests, 414 assertions.

## Commands

Latest billing regressions also verify a provider payment ID already bound to another tenant/subscription is not reassigned or allowed to activate the conflicting subscription, and that mismatched subscription/payment entity IDs are ignored. Latest MySQL results (2026-10-27): full suite **59 tests, 469 assertions**; billing suite **52 tests, 414 assertions**. Run the suites sequentially because they share `invoiceiq_test`.

Current targeted non-gateway verification after the read-only route change: `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php --filter=test_expired_trial_keeps_reads_but_blocks_writes` (1 test, 15 assertions) and `php vendor/bin/phpunit tests/Feature/TenantIsolationTest.php` (7 tests, 55 assertions). Trial configuration regression: `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php --filter='test_workspace_registration_rejects_malformed_or_negative_trial_duration|test_new_workspace_receives_a_fourteen_day_trial'` (2 tests, 9 assertions). Grace configuration regression: `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php --filter=test_invalid_grace_duration_is_rejected_and_zero_means_immediate_expiry` (1 test, 4 assertions). The full BillingFeatureTest was not rerun because it includes payment-gateway-focused tests that the user asked to defer.

Create the empty test database once if needed, then migrate and run the suite:

```powershell
$env:APP_ENV = "testing"
$env:DB_CONNECTION = "mysql"
$env:DB_DATABASE = "invoiceiq_test"
php artisan migrate --force
php vendor/bin/phpunit
```

This repository does not include Laravel Collision, so `php artisan test` is unavailable; use PHPUnit directly. Run MySQL-specific migration/locking tests against MySQL; SQLite is not a substitute for MySQL locking or enum/DDL behavior.

## Manual QA by phase

The user resumed gateway QA on 2026-10-28; the earlier deferral is lifted. See GATEWAY_QA.md for current prerequisites and execution steps. Existing mocked checkout/webhook regression tests remain part of the ordinary PHPUnit suites and have not been disabled; their passing results verify local application behavior only, not Razorpay account access or external gateway delivery.

| Phase | Minimum review |
|---|---|
| 0 | Review all docs against code scan; no runtime behavior changed |
| 1 | Upgrade populated legacy DB; confirm all records retained and attached to default tenant; numbering works concurrently |
| 2 | Register/verify, onboard, invite members, exercise all roles |
| 3 | Automated MySQL coverage checks cross-tenant dashboard/cache, reads/writes, search, linked records, GST reports/Excel exports, and private logos; manually inspect a legacy logo migration |
| 4 | Trial, mocked checkout/webhook regressions, limit prompts, grace/read-only; payment-gateway test work and account-backed sandbox checkout/webhook/tax-invoice QA are deferred for now by user direction. Credential variables are present locally, but demo mode remains enabled, seller/cycle configuration is unset, and a public HTTPS webhook URL has not been verified. |
| 5 | Guard separation, tenant actions, audit, impersonation start/exit |
| 6 | Tenant branding/PDF, public token link, email, exports |
| 7 | Landing/pricing/legal pages, shared-host cron/queue, backups and deployment rollback |

### Role-aware restriction regression — 2026-10-27

Added `test_non_owner_plan_restrictions_return_to_dashboard_and_reject_json_writes`, covering expired-trial writes, invoice quota, denied GST reports, visible dashboard warnings, JSON 403 responses, and continued owner-only Billing access. The fixture deliberately retains the legacy global admin role while using a staff workspace membership.

Validation: PHP syntax checks passed for the shared response helper, all three changed middleware classes, and BillingFeatureTest; `git diff --check` passed. Focused PHPUnit execution encountered MySQL connection-refused/server-gone-away errors before assertions. An outside-sandbox retry also encountered database connection errors. After MySQL connectivity was restored, the following command passed (4 tests, 39 assertions). A subsequent `php vendor/bin/phpunit tests/Feature/TenantIsolationTest.php` run also passed (7 tests, 55 assertions). No code fixes were needed:

```powershell
php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php --filter='test_non_owner_plan_restrictions|test_expired_trial_keeps_reads_but_blocks_writes|test_invoice_limit_redirects_to_billing_when_reached|test_explicit_plan_feature_denial_redirects_to_billing'
```

Gateway test execution resumed on 2026-10-28; see the latest results below.

### Gateway QA resumed ? 2026-10-28

`php vendor/bin/phpunit` passed against the isolated MySQL test database: **62 tests, 502 assertions**. This includes mocked checkout/webhook tests and tenant isolation. No provider-backed payment was made by this suite.

An authenticated read-only GET of the official Razorpay plans endpoint returned **HTTP 401**. The original sandbox transport failed; an approved outside-sandbox retry reached the endpoint. A test-prefixed key and API/webhook secrets are present, but account authentication remains unresolved. No API credentials were displayed. Demo mode, absent provider plan IDs/cycle counts/seller tax settings, and an unverified public HTTPS endpoint also prevent end-to-end checkout. See GATEWAY_QA.md for the provider-backed checklist; this work is pending prerequisites, not marked complete.

### Tunnel readiness - 2026-10-28

The approved temporary webhook-only HTTPS tunnel passed GET root/private-path rejection (404), unsigned webhook rejection (400), and a locally signed synthetic event plus replay (204 each, one stored billing event). This is not provider-generated evidence. Updated .env credentials were verified as loaded but the provider plans read still returned HTTP 401. See GATEWAY_QA.md for endpoint, private process configuration, and shutdown. No application code changed, so the previously passing 62-test/502-assertion suite was not repeated.


### Webhook setting correction - 2026-10-28

The user's latest .env change put an HTTPS URL in `RAZORPAY_WEBHOOK_SECRET`. The app uses this value as the HMAC signing key, not as an endpoint setting. Replaced the URL-valued secret with a generated private secret without displaying it and cleared configuration cache. The Razorpay Test Mode dashboard must now use the new local `RAZORPAY_WEBHOOK_SECRET` value in its Secret field and the public tunnel endpoint in its Webhook URL field. No dedicated webhook URL environment setting is currently consumed by the app.

The authenticated official plans read was retried with the currently loaded API pair and still returned HTTP 401. The key/secret match the .env file with no surrounding whitespace. This remains separate from webhook setup; provider checkout/renewal QA is not complete.

Recheck after secret correction passed: public root/private paths 404, unsigned webhook 400, signed synthetic event/replay 204 with one event row (`gateway_qa_connectivity_20261028_6c27674400`). This proves local signing across the public tunnel, not Razorpay-generated delivery. Tunnel remains running for dashboard setup.

### Latest webhook-secret recheck - 2026-10-28

Updated local secret passed signed synthetic event/replay checks (204 each, one event row); unsigned requests returned 400 and other paths 404. The old tunnel expired and was replaced. Current endpoint: https://window-flower-unified-affair.trycloudflare.com/webhooks/razorpay. Update the Test Mode dashboard URL and Secret together. API plans authentication still returns HTTP 401. No provider-backed payment was made; tunnel remains running for setup. See GATEWAY_QA.md for evidence and shutdown.


### User-managed tunnel verification - 2026-10-28

The user reported configuring `https://paths-protein-tractor-freeze.trycloudflare.com/webhooks/razorpay` in Razorpay and matching its Secret to local `.env`. Direct public verification failed before HTTP: hostname resolution failed. An independent Cloudflare DNS-over-HTTPS lookup returned DNS status 3 (NXDOMAIN), with no A records. This is a DNS/tunnel availability issue, not a webhook signature rejection.

Read-only process inspection found a user-managed Cloudflare tunnel forwarding to `http://localhost:8000` (PID 22888 at inspection), separate from the earlier agent-managed webhook-only tunnel. No user process or dashboard setting was changed. Requested the current URL printed by the running user-managed tunnel. Dashboard registration and secret matching are user-reported; actual provider delivery is not verified.

The local endpoint on `http://127.0.0.1:8000/webhooks/razorpay` passed signed synthetic event and replay (204 each), unsigned request rejection (400), tampered-body rejection (400), and exactly one event row. Event ID: `gateway_qa_connectivity_20261028_290f19f1ff`. The private-path GET returned 404. These results validate the local endpoint and current local secret, not Razorpay-generated events or the unavailable public hostname.

The authenticated official plans API still returned HTTP 401 using the currently loaded .env API credentials. No provider plans, subscriptions, or payments were created. The private verifier now accepts an explicit HTTPS Cloudflare origin or the known loopback QA listeners.

### Latest user-managed public URL passed - 2026-10-28

Current endpoint: `https://prediction-attempts-configure-tires.trycloudflare.com/webhooks/razorpay`. After a sandbox connection failure, the outside-sandbox public verification passed: root GET 401 (JSON guest authentication), private path GET 404, unsigned webhook 400, signed synthetic webhook 204, replay 204, tampered body 400, exactly one billing event row. Synthetic event ID: `gateway_qa_connectivity_20261028_3255c0b550`. User must use this replacement URL in the Test Mode dashboard. No provider-generated delivery or payment was verified; last API authentication status remains HTTP 401 (not retried in this check). No application code/configuration changed.


### Recheck after dashboard URL update - 2026-10-28

User confirmed saving the current endpoint in the Razorpay dashboard. Public verification of `https://prediction-attempts-configure-tires.trycloudflare.com/webhooks/razorpay` passed again: signed synthetic event/replay 204, unsigned/tampered-body 400, one event row. Synthetic event ID: `gateway_qa_connectivity_20261028_2f3fe59caf`. Read-only aggregation of the last 24 hours found no non-connectivity webhook event records. Actual Razorpay delivery remains unverified; endpoint registration is user-reported.

Retried authenticated official plans GET: HTTP 401, with loaded credentials matching local .env and no surrounding whitespace. Checkout QA is waiting on the Test Mode API key/secret pair, independently of the working webhook signature path. No provider plans/subscriptions/payments were created. No application configuration or code changed during this recheck.


### Authentication diagnosis corrected - 2026-10-28

Do not treat the plans HTTP 401 as proof of invalid API credentials. Independent native-cURL comparisons using the exact same current Test Mode key/secret returned:

| Read-only endpoint | HTTP result |
|---|---|
| GET `/v1/payments?count=1` | 200 |
| GET `/v1/plans?count=1` | 401 |
| GET `/v1/subscriptions?count=1` | 401 |

Diagnostic checks confirmed one entry per credential in .env, the loaded pair matches the file, no credential whitespace, valid test-key prefix/character format, key and secret are distinct, no cached config, successful TLS verification, and the transmitted Basic Auth header matches the expected credentials. Header contents, API secrets, and returned payment data were never printed.

The payments result verifies the configured credentials authenticate successfully. The failures are specific to Plans/Subscriptions access in these checks; account/product permission or provisioning is a hypothesis, not a confirmed cause. Do not ask the user to regenerate keys based solely on these plans/subscriptions responses. Requested what the Test Mode dashboard shows under Payment Products -> Subscriptions. If needed, ask Razorpay support to investigate the endpoint-specific 401 with the above comparison, without sharing secrets.

No application runtime code/configuration changed and no provider objects/payments were created. Earlier notes requesting corrected credentials based only on plans HTTP 401 are superseded by this diagnosis. Provider-backed subscription checkout remains pending.

### Dashboard inspection request - 2026-10-28

User asked the agent to check the Test Mode Subscriptions dashboard. No authenticated browser tool or connected document session is available, so dashboard UI/enablement could not be inspected. Independent API rerun reconfirmed payments GET 200, plans GET 401, subscriptions GET 401 with the same valid Test Mode pair, correct Basic Auth, and successful TLS. Product provisioning/permission remains a hypothesis. Need the user-visible Subscriptions page state (or a screenshot without secrets); no dashboard or account settings were changed.


### Dashboard failure after Get Started - 2026-10-28

User screenshots show Test Mode enabled, Subscriptions initially offering Get Started, and then the Razorpay dashboard error: "Failed to load subscriptions. Please try again." API checks after this step still returned payments 200 and plans/subscriptions 401 with the same validated pair and correct Basic Auth/TLS.

This supports investigation of the provider's Subscriptions product/account access or service behavior; the exact root cause is not confirmed. A route/controller or webhook URL change cannot be justified by the observed dashboard error. Await Razorpay support findings before repeating account-backed subscription QA. No credentials/configuration were changed, and no provider objects were created.

### Provider checks awaiting support - 2026-10-28

The user has submitted a Razorpay support ticket. Do not rerun the unchanged failing provider checks until account/configuration state changes or support supplies a relevant diagnostic step. No test execution was needed for this documentation-only handoff; last automated suite remains 62 tests, 502 assertions.


## Phase 5 validation - 2026-10-28

`php vendor/bin/phpunit` passed: **77 tests, 632 assertions**, including 15 new SuperAdminFeatureTest scenarios and all previous billing/isolation regressions. No provider-backed API/payment request was required for this phase. Tests run sequentially on invoiceiq_test and roll back fixture records.

New coverage: denial of all tenant roles; separate credentials/guards even for overlapping email addresses; inactive admins; login throttling; tenant directory/detail and reasoned suspension; unaffected second tenant; trial validation/context restoration; local manual grants and rejection of provider-managed changes; protected plan contracts/system deletion; coupon validation/normalization; announcement visibility/escaping; owner-target validation, tenant pinning and read-only impersonation; expiry/restoration; owner-role revocation; provider-period MRR estimate versus manual grants; catalog deletion audit and audit record immutability.

```powershell
php vendor/bin/phpunit tests/Feature/SuperAdminFeatureTest.php
php vendor/bin/phpunit
php artisan route:list --path=superadmin
php artisan view:cache
```

PHP syntax validation passed for application/routes/config/migrations/tests. Fourteen platform routes register and all Blade templates compile. Initial focused run exposed an in-memory active-default mismatch for newly created admins; fixed the model default and reran successfully before the full suite. No default platform account was created. Operator provisioning and manual browser/accessibility/responsive review remain to be performed; screenshots/browser interaction were not available in this session.


## Phase 6 validation - 2026-10-28

Full isolated MySQL suite: **90 tests, 708 assertions** using `php vendor/bin/phpunit`. This includes 13 WorkspaceWorkflowTest scenarios and prior tenant, billing and super-admin tests. The initial focused 11-scenario workflow run passed 70 assertions; two additional worker-ID/owner-revocation tests are included in the full result.

Coverage: public PDF link hash storage, expiry/revocation, draft/cancelled/suspended denial, cross-tenant bearer access without context leakage, no public write route; writer/export roles and expired-subscription read/write boundaries; encrypted queued message payloads/scalar jobs, successful-send dedupe, sanitized retry failures, status transitions, tenant-scoped failed callbacks, sender membership revocation and wrong-tenant IDs; streaming context/formula-neutralized CSV isolation; owner-only private ZIP generation/download, secret exclusion, expiry/pruning/role revocation; validated plan-gated branding and actual PDF/ZIP attachment rendering.

```powershell
php vendor/bin/phpunit tests/Feature/WorkspaceWorkflowTest.php
php vendor/bin/phpunit
php artisan view:cache
php artisan schedule:list
```

No actual SMTP/email recipient or provider API/payment was used by these tests. PHP syntax and Blade compilation passed. Production cron/SMTP, manual browser/mobile review and large-export/crash behavior are not claimed verified. Initial test failures were addressed by using the available Artisan call API without adding Mockery and loading persisted invoice defaults in the scoped PDF service.
