# Changelog

## Docker PHP 8.3 target (2026-10-30)

Changed the local Docker PHP/Apache base image to PHP 8.3. The existing Composer requirement `^8.2` permits 8.3, and locked package PHP version constraints accept 8.3.0. Docker and PHP 8.3 runtime tests remain unavailable on this machine; rebuild and platform checks are documented in `DEPLOYMENT.md`.

## Local Docker development (2026-10-30)

Added a PHP 8.2/Apache Dockerfile, build-context exclusions and local Compose services for MySQL, persistent app storage and an optional Laravel scheduler. Added instructions for a separate Docker environment file, application key, manual migrations and persistent volumes. Docker is unavailable on this machine, so a container build or startup has not been verified.

## Bulk Import - Step 3 (2026-10-30)

Added explicit import processing for customer/item previews. The user can import Ready rows and skip rows with warnings/errors, or stop the whole batch when any issue remains. Selected business rows, preview statuses and batch completion are committed in one tenant-row-locked transaction after checking subscription write access; repeat processing is rejected. Unique conflicts roll back the whole batch. Current plans have no customer/item quotas, so no unrelated invoice/member quota is applied. Focused upload/import tests pass (6 tests, 54 assertions); the full MySQL suite passes (105 tests, 833 assertions).

## Bulk Import - Step 2 (2026-10-29)

Implemented secure customer/item file upload, automatic header matching with a manual mapping screen, file and row validation, tenant-scoped duplicate warnings, and a paginated preview. Files use private tenant paths; batches/rows use tenant scopes and a composite tenant/batch FK; scheduled cleanup removes expired preview data. Upload and preview do not create or update business records. Added MySQL migration and focused coverage: 5 upload tests/32 assertions plus the 2 template tests/29 assertions. Step 3 processing remains pending.

## Bulk Import - Step 1 (2026-10-29)

Added role-gated Excel/CSV template downloads to customer and item listings. Excel files include three sheets, styles/frozen headings, marked sample rows, instructions and dropdown lists. Added a responsive three-step guide showing Step 1 as available and future upload/review steps as not yet implemented. Templates follow current database/form fields; no import processing, schema migration or record writes were added. Focused suite: 2 tests, 29 assertions.

Screenshot follow-up: corrected the guide dialog positioning and background after the shared CSS reset overrode the browser's default dialog centering and the initial surface token was undefined. Focused template tests and Blade compilation pass.

## Phase 7 - 2026-10-28 - Launch hardening foundation

Added public landing/pricing/draft legal pages, tenant-scoped activity auditing, owner workspace soft deletion, platform recovery, security headers, route throttles, tax identifier format normalization, SMTP password encryption, setup/release commands and a VPS/nginx reference. Added launch and backup/rollback documentation. Workspace records remain retained; legal/pricing approvals, Razorpay account QA, SMTP, browser review and VPS/backup restore are still open.

Added migration `2026_10_28_000003_add_launch_audit_and_workspace_recovery.php`, reviewed with `--pretend` and applied to local MySQL and invoiceiq_test. Phase 7 focused tests passed 7 tests/41 assertions; full MySQL suite passed 97 tests/749 assertions (final run 2026-10-29). No external payment/email, provider setting, or VPS deployment was performed.

Follow-up (2026-10-29): extended mutation throttles, made membership audits transactional, applied security headers to exception responses, and expanded production release configuration checks. Provider, mail, legal, backup/restore and VPS gates remain open.

Legal sample follow-up (2026-10-29): expanded public Terms and Privacy into clearly fictional, source-linked drafts using a reserved non-deliverable email. Added `LEGAL_DRAFT_NOTES.md` with implementation facts, legal unknowns and replacement checklist. Approval remains disabled; no live operator identity was invented or set in configuration. No test suite was run for the content-only update.

Razorpay QA follow-up (2026-10-29): after the user shared a Test Mode dashboard screenshot showing the first pricing-plan flow, a read-only Plans API request returned HTTP 200 with zero plans. This supersedes the prior repeated Plans/Subscriptions 401 state for this endpoint. No provider objects were created; further QA needs plan IDs.

At the user's request, created two Test Mode-only QA Starter plans (₹499 monthly, ₹4,990 yearly), validated them on a tagged repeat lookup, and attached their IDs to the local Starter plan fields. The plans cannot be edited/deleted; no subscription or payment was created. See GATEWAY_QA.md for IDs and remaining QA.

Verification follow-up (2026-10-29): full MySQL test suite passes **97 tests, 750 assertions**. Updated a stale legal-page test assertion to match the current explicit `SAMPLE DRAFT` and `NOT APPROVED` labels.

Phase 7 audit follow-up (2026-10-29): invoice edits now record a transactional `invoice.lines_replaced` summary because relationship bulk deletion skips model audit hooks. The event stores removed/replacement counts only. Automated tests were not run for this change.

Phase 7 audit follow-up: workspace profile query-builder updates now log changed field names, and logo upload/removal records the operation without persisting private paths. Automated tests were not run for this change.

Phase 7 audit follow-up: invitation reissues now use a distinct `invitation.resent` action so activity history accurately reflects the workflow. Email addresses and token data remain excluded. Automated tests were not run for this change.

Phase 7 activity UI follow-up: the workspace timeline now uses readable labels and renders allowlisted audit summaries such as changed field names, invoice line counts, and logo operations. Secret values and free-form detail payloads are not displayed. Automated tests were not run for this change.

Phase 7 release preflight follow-up: `release:check --production` now checks all nonempty tenant SMTP password rows for the expected encrypted format and decryptability under the current APP_KEY. The check reports status only and never prints stored values. Automated tests were not run for this change.

Phase 7 audit follow-up: terminal invoice-email and account-export job callbacks now update tenant models under restored tenant context when possible, so their failure status changes appear in tenant audit history. Suspended/deleted workspace fallback updates remain constrained by trusted tenant and record IDs. Automated tests were not run for this change.

## Phase 0 — 2026-10-23 — Documentation setup

Added the requested project-specific documentation set under `docs/ai/` and root continuation instructions in `CLAUDE.md` and `AGENTS.md`.

Files added: `README.md`, `PROJECT_CONTEXT.md`, `EXISTING_FEATURES.md`, `GST_RULES.md`, `ARCHITECTURE.md`, `DATABASE_SCHEMA.md`, `TENANCY_RULES.md`, `PERMISSIONS_MATRIX.md`, `BILLING_AND_PLANS.md`, `SUPERADMIN_SPEC.md`, `SECURITY_CHECKLIST.md`, `CODING_STANDARDS.md`, `TESTING.md`, `DEPLOYMENT.md`, `ROADMAP.md`, `PROGRESS.md`, `DECISIONS.md`, `CHANGELOG.md`, `PHASE_PROMPTS.md`, root `CLAUDE.md`, root `AGENTS.md`.

Modified application files: none. Migrations run: none. Runtime behavior changed: none.

## Phase 1 — 2026-10-23 — Tenancy foundation

Added tenant/workspace schema and default-tenant backfill, tenant membership pivot, tenant context/middleware, `BelongsToTenant` scope, tenant-scoped invoice/customer/item/settings indexes, customer GSTIN uniqueness after duplicate preflight, and a tenant financial-year numbering service. Applied migrations to configured local MySQL after preflight; retained 5 customers, 9 items, 17 settings, and 1 user. The inspected database had no invoices or payments. Added database queue configuration and supporting queue tables, MySQL `.env.example`, and setup/README updates. GST calculations were not changed; invoice date changes now refresh the generated number suggestion for that financial year.

Files added: `app/Models/Tenant.php`, `app/Models/Concerns/BelongsToTenant.php`, `app/Support/TenantContext.php`, `app/Services/InvoiceNumberingService.php`, `app/Http/Middleware/ResolveTenant.php`, `config/queue.php`, `.env.example`, `database/migrations/2026_10_23_000001_create_tenants_and_scope_business_data.php`, `database/migrations/2026_10_23_000002_create_database_queue_tables.php`, `database/migrations/2026_10_23_000003_add_tenant_scoped_customer_gstin_unique.php`.

Files modified: `README.md`, `setup.sh`, `config/database.php`, `bootstrap/app.php`, `routes/web.php`, `resources/views/invoices/form.blade.php`, `app/Providers/AppServiceProvider.php`, `app/Models/User.php`, `Customer.php`, `Item.php`, `Invoice.php`, `InvoiceItem.php`, `Payment.php`, `Setting.php`, `app/Http/Controllers/InvoiceController.php`, `database/seeders/DatabaseSeeder.php`, and affected docs in `docs/ai/`.

Migrations run: all three Phase 1 migrations completed successfully on local MySQL (first two in batch 2, GSTIN constraint in batch 3). Automated tests were not run because no test suite exists and tests were not requested.

### Phase 1 follow-up — tenant middleware runtime fix

Changed `ResolveTenant` to receive `TenantContext` through its constructor; Laravel's middleware pipeline invokes `handle()` with only the request and next callback, so method-parameter injection caused the reported `ArgumentCountError`. PHP syntax validation passes, and an authenticated request to the local dashboard returned HTTP 200 after the fix.

## Phase 2 — Auth and onboarding

Added atomic user/workspace registration, signed email verification, password reset broker flows, session regeneration, and company/GST/logo/invoice prefix onboarding with optional initial customer/item. Added tenant-scoped invitations with SHA-256 token hashes and seven-day expiry, membership role changes/removal, and Gates for workspace and record access. Existing accounts were marked verified and existing workspaces onboarded to preserve their current access.

Added auth, verification, onboarding, and invitation controllers/views, `TenantInvitation`, onboarding middleware, mail config, and `2026_10_23_000004_add_phase_two_auth_and_invitations.php`. Modified auth/team routes and views, tenant role resolution, `ResolveTenant`, `.env.example`, README, and affected docs. Migration 4 applied to configured local MySQL. PHP syntax checks and route listing succeeded; automated tests were not run because they were not requested and the project has no tests directory. Production email requires SMTP configuration; the default local mailer logs messages.

### Phase 2 follow-up — local mail transport unavailable

Registration reported an SMTP connection error because local `.env` selected `smtp` at `127.0.0.1:1025` without a server running. Mail transport failures now return actionable feedback for verification, reset, and invitation flows rather than a 500. The local `.env` mailer was switched to `log` and Laravel's config cache cleared; existing SMTP host/credential entries were left untouched. The account submitted before this fix may already exist and can sign in, then resend verification from the verification screen.

## Phase 3 — 2026-10-23 — Isolation audit and tests

Scoped dashboard raw aggregation to the active tenant and keyed dashboard caching/invalidation by tenant. Added tenant predicates and tenant equality checks to GST invoice-line joins. Invoice validation now scopes selected customers/items, and model guards reject cross-tenant invoice/customer, line/invoice/item, and payment/invoice links. Tenant logos now use private paths served through the authenticated tenant route; a command and lazy path migration move legacy public logos into tenant-private storage. The local data audit found zero cross-tenant links across the four relationship pairs.

Added a dedicated MySQL PHPUnit configuration and seven feature tests with 55 assertions for dashboard/cache, customer/item search, route binding, tampered invoice writes, linked records, GST summaries/Excel export, private logo routes, and legacy logo migration. Created/migrated the isolated `invoiceiq_test` database; the development database was not refreshed. `php vendor/bin/phpunit` passes. `php artisan test` is not available because Laravel Collision is not installed.

## Phase 4 — 2026-10-26 — Plans and billing foundation

Added plan, tenant subscription, idempotent provider event, payment, and subscription invoice tables. New workspaces receive the configured 14-day card-free trial; existing workspaces retain an unlimited legacy plan. Added configurable plan entitlements, usage meters, invoice/member limit middleware with transaction-time tenant locks, and read-only enforcement for expired trials and configured grace/period-end states.

Added server-created Razorpay subscription checkout, raw-body HMAC webhook verification and event replay protection, payment history, billing page, and tenant-scoped subscription invoice PDFs. Invoice issuance requires configured SaaS seller/GST/SAC details. Commercial prices, usage caps, grace duration, downgrade behavior, proration, and refund rules remain unset pending product decisions. Paid checkout requires configured active plan records, provider IDs/credentials, and recurring cycle count.

Added Phase 4 feature coverage for trial initialization/expiry, plan limit behavior, Razorpay checkout and provider-plan matching, webhook verification/replay, payment capture/tax invoice generation, billing PDF rendering, feature entitlement, and tenant boundaries. Improved responsive billing plan cards and checkout actions for narrow screens and bumped the stylesheet cache version. Existing pending checkouts with a missing hosted URL can now recover it from the matching provider subscription rather than creating duplicates; the billing page offers an owner restore action and explains when the link cannot be restored. Added regression coverage for inclusive same-state CGST/SGST (including padded state-code normalization) and exclusive interstate IGST invoice calculations. Failed renewal attempts from signed webhooks are now recorded in tenant-scoped billing history without issuing invoices; replay is idempotent. Events that arrive before their provider subscription is persisted locally now receive a retryable 503 and are not consumed, covering the checkout/webhook race. Provider subscription IDs and hosted checkout URLs are validated before pending checkout rows are saved, so malformed provider responses do not strand workspaces with unusable pending checkouts. Paid active subscriptions now require a confirmed future period end for write access; missing period ends are treated as ended, while the explicit legacy plan remains unlimited. If grace was unset when a failure arrived, a later configured grace period is anchored to the original provider failure timestamp. The past-due billing notice now clearly explains when grace is unset versus when a deadline is active. `payment.failed` now also transitions an established subscription to `past_due` and applies the configured grace period while leaving initial pending checkouts alone. Subscription state transitions now reject out-of-order provider events while retaining payment history, backed by a new `provider_state_at` migration and regression test. A workspace may start replacement checkout after configured grace expires but not during active grace. Cancelled and completed subscriptions retain access through the provider period end; paused subscriptions retain paid-through access and resume on a newer provider event. These states become read-only after that date. Checkout creation is serialized per workspace, with a pending-subscription recheck to avoid duplicate provider subscriptions during concurrent requests. Billing history, checkout, and subscription invoice downloads are owner-only; the Billing navigation follows the active workspace's owner membership rather than the legacy global role. Added immutable tenant ownership validation for subscription saves, alongside the payment/subscription and billing invoice/payment link guards. Billing status labels and read-only notices now match the trial, period-end, and grace enforcement rules. Added a two-tenant regression confirming webhook records use the subscription tenant even if another tenant is active in request context. Added a local/testing-only demo catalog and seven-day grace assumption; checkout and tax invoice issuance are blocked in demo mode, and the billing page labels sample prices. Fixed the demo tax label rendering and added a regression check against raw Blade directives appearing in the page. PHPUnit disables demo mode in `phpunit.xml` so configured sandbox integration coverage remains available. `php vendor/bin/phpunit` passes 33 tests and 229 assertions against the isolated MySQL test database. Ended paid subscriptions with missing or elapsed provider periods can now start replacement checkout without waiting for a terminal provider event; current paid periods remain protected and legacy workspaces retain their existing upgrade path. Added a regression for ended, current, and legacy states. Late failure webhooks referencing an already captured payment now preserve the captured payment and active subscription state. GST invoice creation now requires a positive INR capture; non-INR and zero-value captures remain in payment history without an invoice. Tax invoice issuance now also requires the captured amount to match the configured subscription cycle price, including GST in exclusive mode. Tax invoice snapshots now store normalized seller and buyer state codes. Added mismatch regression coverage for both inclusive and exclusive GST price modes. Malformed or non-positive provider period timestamps now preserve the last valid paid-through date. Missing or malformed payment currency is now stored as `XXX` and cannot be treated as INR for invoice issuance. Malformed payment timestamps now use receipt time instead of corrupting the payment date. Added webhook request validation coverage for missing/blank/oversized IDs, invalid JSON, and unconfigured secrets; payment amount validation now rejects malformed, negative, and overflowing values. Invitation seat regressions now cover reserved-seat resends, expiration, acceptance at capacity, and limit reductions before acceptance. Read-only workspaces also now have regression coverage proving they cannot create or accept member invitations. Monthly invoice quota coverage now verifies the exact cap and excludes prior-month usage. Yearly checkout now has coverage for exclusive-tax gross matching and configured cycle-count boundaries. Same-state tax regression now verifies odd-paise GST splits preserve the full tax amount. Razorpay checkout now rejects zero-priced plans and the non-purchasable free trial/legacy plans. The full suite passes 47 tests and 332 assertions, including 40 billing tests and 277 assertions. Phase 4 remains in progress until production product decisions and Razorpay sandbox/tax invoice QA are complete.

### Phase 4 follow-up — 2026-10-27 — Billing verification

Found and fixed a paused-subscription recovery gap: after the paid-through date elapsed, a workspace became read-only but could not start replacement checkout. Replacement checkout is now permitted for an ended paused subscription and remains blocked while paused paid access is still current. Added a regression proving both cases.

Re-ran `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` and `php vendor/bin/phpunit` against the isolated MySQL test database. All 41 billing tests (281 assertions) and 48 full-suite tests (336 assertions) pass. Production checkout and tax-invoice QA remain gated on approved plan values, seller tax details, grace/downgrade/proration/refund decisions, and Razorpay sandbox configuration.

### Phase 4 follow-up — 2026-10-27 — Require webhook secret for checkout

Closed a payment activation gap by requiring a non-empty `RAZORPAY_WEBHOOK_SECRET` in checkout readiness. A provider API key/secret alone is insufficient: without a webhook secret, the app cannot authenticate payment and subscription callbacks. Added a regression proving checkout is rejected without the webhook secret and makes no provider request; existing successful checkout tests now explicitly configure the webhook secret.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (42 tests, 285 assertions) and `php vendor/bin/phpunit` (49 tests, 340 assertions) against the isolated MySQL test database. Production product/tax decisions and sandbox QA remain open.

### Phase 4 follow-up — 2026-10-27 — Recover halted initial checkouts

A signed `subscription.halted` event on a checkout that never activated previously left its local row `pending`, blocking any new checkout. Initial halted checkouts now move to `checkout_failed` history, which is excluded from active entitlement selection; the prior trial/legacy plan stays effective and the billing page tells the owner they can retry. Late failed-payment callbacks preserve this terminal initial-checkout state while retaining failed payment history. The old failure notice is suppressed after a replacement checkout begins. Established subscriptions still use the existing `past_due`/grace behavior. No migration was required because subscription status is a string.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (43 tests, 302 assertions) and `php vendor/bin/phpunit` (50 tests, 357 assertions) against the isolated MySQL test database.

### Phase 4 follow-up — 2026-10-27 — Anchor grace to provider failure time

Fixed grace calculation for delayed failure webhooks: when grace is configured, `grace_ends_at` now uses Razorpay's event timestamp rather than receipt time, preventing delivery delays from extending the workspace's write-access period. Invalid or absent event timestamps continue to fall back to receipt time. Added a regression proving that a failure received after its two-day grace has already elapsed is immediately read-only and eligible for replacement checkout.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (44 tests, 308 assertions) and `php vendor/bin/phpunit` (51 tests, 363 assertions) against the isolated MySQL test database.

### Phase 4 follow-up — 2026-10-27 — Gate pending checkout on webhook readiness

Closed a recovery-path gap where a previously issued Razorpay URL remained visible and directly reachable after webhook authentication was removed. The billing page now hides the pending link and recovery action, and a direct continuation request returns to billing with an actionable warning. The pending subscription state and current plan entitlement are unchanged. Added regression coverage for link suppression, endpoint handling, and state preservation.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (45 tests, 315 assertions) and `php vendor/bin/phpunit` (52 tests, 370 assertions) against the isolated MySQL test database.

### Phase 4 follow-up — 2026-10-27 — Reject whitespace-only webhook secrets

Hardened billing readiness so a whitespace-only `RAZORPAY_WEBHOOK_SECRET` is treated as unconfigured consistently: checkout readiness is false, existing pending checkout URLs and restore actions stay unavailable, and the webhook endpoint returns 503 without consuming an event. Added regression assertions for pending continuation and webhook processing.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (45 tests, 323 assertions) and `php vendor/bin/phpunit` (52 tests, 378 assertions) against the isolated MySQL test database.

### Phase 4 follow-up — 2026-10-27 — Cover grace timestamp fallbacks

Added regression coverage for both missing and malformed Razorpay failure-event timestamps. In either case, the configured grace window now has an explicit test proving it starts from webhook receipt time, while valid provider timestamps continue to anchor the deadline to event time.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (46 tests, 331 assertions) and `php vendor/bin/phpunit` (53 tests, 386 assertions) against the isolated MySQL test database.

### Phase 4 follow-up — 2026-10-27 — Preserve original past-due timestamp

Added `past_due_started_at` so repeated provider failure events do not move the grace origin when grace is not yet configured. Grace configured later derives from the first failure time, while `provider_state_at` continues tracking the latest event for out-of-order protection. Successful activation clears the past-due origin and grace deadline. Added a regression covering repeated failure events, delayed grace configuration, and recovery.

Verified with `php vendor/bin/phpunit tests/Feature/BillingFeatureTest.php` (47 tests, 347 assertions) and `php vendor/bin/phpunit` (54 tests, 402 assertions) against the isolated MySQL test database. Migration `2026_10_27_000001_add_past_due_started_at_to_tenant_subscriptions.php` was applied to the isolated test database.

### Phase 4 follow-up — 2026-10-27 — Cover late pending callbacks after halted checkout

Extended the halted-checkout regression to include a later `subscription.pending` callback as well as a late `payment.failed` callback. Both callbacks retain the terminal `checkout_failed` state and the original trial entitlement while preserving payment-attempt history.

Verified sequentially against the isolated MySQL test database: billing suite (47 tests, 352 assertions) and full suite (54 tests, 407 assertions). Test commands share the same database and should not run concurrently.

### Phase 4 follow-up — 2026-10-27 — Verify payment ID idempotency across events

Extended captured-payment coverage to deliver the same provider payment ID under a distinct `payment.captured` event ID after the original `subscription.charged` event. The webhook retains one payment and one subscription tax invoice, in addition to deduplicating exact event replays.

Verified sequentially against the isolated MySQL test database: billing suite (47 tests, 354 assertions) and full suite (54 tests, 409 assertions).

### Phase 4 follow-up — 2026-10-27 — Preserve captured payment details on duplicate events

Hardened duplicate payment handling: after a payment ID is recorded as captured, later captured notifications cannot rewrite its amount, currency, method, or paid timestamp. This keeps the immutable tax invoice aligned with the captured payment record while preserving provider event idempotency. Added a regression using a distinct event ID and conflicting captured amount/currency.

Verified sequentially against the isolated MySQL test database: billing suite (47 tests, 358 assertions) and full suite (54 tests, 413 assertions).

### Phase 4 follow-up — 2026-10-27 — Attempt sandbox connectivity; defer account QA

Made one unauthenticated request to the Razorpay API endpoint. The API responded HTTP 401, confirming network/API-host reachability only. Local sandbox key, secret, and webhook secret are not configured, so no provider checkout, signed event, or account-backed tax-invoice QA was attempted. Focused local mocked tests for inclusive same-state and exclusive interstate tax invoices passed (2 tests, 29 assertions). Live provider QA remains explicitly deferred until sandbox credentials and approved seller/tax inputs are available.

### Phase 4 follow-up — 2026-10-27 — Require workspace state for pending checkout

Aligned existing pending checkout continuation with the checkout and tax-invoice requirement for a buyer state code. The billing page no longer exposes a saved checkout URL or recovery action when the workspace state code is missing/whitespace-only, and the endpoint rejects direct continuation without changing the pending subscription. Added a regression for both null and whitespace-only state values.

Verified sequentially against the isolated MySQL test database: billing suite (48 tests, 369 assertions) and full suite (55 tests, 424 assertions).

### Phase 4 follow-up — 2026-10-27 — Reject blank seller tax configuration

Centralized seller tax readiness validation so checkout and subscription tax-invoice issuance share the same requirements: nonblank seller name, GSTIN, PAN, address, state code, SAC, valid inclusive/exclusive tax mode, and a GST rate greater than zero and no more than 100. Added regressions proving whitespace-only seller fields and a zero GST rate cannot enable checkout or cause a tax invoice to be issued.

Verified sequentially against the isolated MySQL test database: billing suite (50 tests, 396 assertions) and full suite (57 tests, 451 assertions).

### Phase 4 follow-up — 2026-10-27 — Recheck sandbox readiness

Confirmed the Razorpay API key, API secret, and webhook secret are present in local `.env` without printing or transmitting their values. No authenticated Razorpay request was made. The local app remains in demo mode, with seller tax configuration and subscription cycle counts unset, so checkout is not ready. Confirmed `POST /webhooks/razorpay` is registered; the local HTTPS URL failed its TLS handshake, while plain HTTP responded 405 to GET as expected for a POST-only route. A public HTTPS webhook URL or secure tunnel is still needed before Razorpay can deliver sandbox events. Account-backed checkout/webhook/tax-invoice QA remains deferred.

### Phase 4 follow-up — 2026-10-27 — Preserve payment ownership on conflicting webhook IDs

Hardened webhook payment idempotency against a provider payment ID being presented with a different tenant/subscription association. The handler locks and checks the existing payment row globally; a conflicting event is logged and consumed without changing subscription state, payment data, or invoices. Added a two-tenant regression proving an already captured payment cannot be rebound and a conflicting subscription cannot be activated by that payload.

Verified sequentially against the isolated MySQL test database: billing suite (51 tests, 407 assertions) and full suite (58 tests, 462 assertions).

### Phase 4 follow-up — 2026-10-27 — Reject inconsistent webhook subscription references

Hardened webhook processing when the subscription entity and payment entity in a signed event identify different provider subscription IDs. The event is logged and marked processed without applying lifecycle changes or recording its payment. Added a regression proving neither subscription changes and no payment is created.

Verified sequentially against the isolated MySQL test database: billing suite (52 tests, 414 assertions) and full suite (59 tests, 469 assertions).

### Phase 4 follow-up — 2026-10-27 — Defer payment-gateway test work

Per user direction, defer additional payment-gateway-specific testing and Razorpay sandbox QA for now. Existing mocked checkout/webhook regression tests remain enabled in the normal PHPUnit suites; their results are local application checks and do not establish external provider readiness. Continue Phase 4 work outside account-backed gateway testing.

### Phase 4 follow-up — 2026-10-27 — Keep read-only workspace pages accessible

Adjusted admin route middleware so owners/admins can still view settings and the member list when a workspace is read-only. Subscription enforcement now applies to the corresponding mutations (settings/logo/email actions and member invite/role/removal); role authorization remains in place. Extended the expired-trial regression to verify settings/member pages remain readable and settings/invitation mutations remain blocked.

Verified without running payment-gateway tests: focused billing read-only regression (1 test, 15 assertions) and tenant isolation suite (7 tests, 55 assertions) passed against MySQL. The complete billing and full suites were not rerun because the billing suite includes payment-gateway-focused tests deferred by user request.

### Phase 4 follow-up — 2026-10-27 — Validate trial duration configuration

Removed the silent integer cast for `BILLING_TRIAL_DAYS` and validate it before workspace registration begins. Malformed and negative values now raise an explicit configuration exception before user or tenant rows are created; zero remains valid and creates a trial ending immediately. Added a registration regression for malformed, negative, and zero-day settings.

Verified against the isolated MySQL test database without invoking payment-provider behavior: default-trial and trial-duration regressions (2 tests, 9 assertions).

### Phase 4 follow-up — 2026-10-27 — Validate grace duration configuration

Removed the silent integer cast for `BILLING_GRACE_DAYS` and validate the setting when deriving a grace deadline. Malformed and negative values now raise an explicit configuration exception; zero remains valid and expires access at the original failure time. Added a focused non-gateway regression.

Verified against the isolated MySQL test database without invoking payment-provider behavior: grace-duration regression (1 test, 4 assertions). Per user direction, sandbox and tax-invoice QA remain skipped.

### Phase 4 continuation — role-aware plan restrictions (2026-10-27)

Subscription read-only, invoice quota, and feature-denial middleware now send non-owner browser requests to the dashboard with a visible warning directing them to their workspace owner. Owners retain the billing redirect and existing billing flash keys. JSON requests receive HTTP 403 with the corresponding explanation. Billing remains owner-only, using the active tenant membership. No schema or commercial configuration changed; gateway QA remains deferred.

Validation after MySQL reconnection: focused restriction tests passed (4 tests, 39 assertions); tenant isolation passed (7 tests, 55 assertions). No additional application changes or migrations were needed.

### Phase 4 ? gateway QA resumed (2026-10-28)

Resumed user-authorized gateway verification. Full MySQL suite passed (62 tests, 502 assertions). A read-only authenticated Razorpay plans request returned HTTP 401 after a sandbox transport failure was retried outside the sandbox. No provider objects were created; credentials were not displayed. Documented missing configuration and the provider-backed checklist in GATEWAY_QA.md. Production commercial decisions remain open.

### Phase 4 - isolated sandbox and tunnel setup (2026-10-28)

Applied user-approved sample assumptions only to private QA helper processes; left the main environment/catalog/entitlements unchanged. Downloaded the official signed Cloudflare client and started a webhook-only temporary tunnel. Public-path and synthetic signed/replay checks passed (404/400/204/204, one no-op event). Updated credentials still returned provider HTTP 401 despite being loaded from the edited .env. Real provider QA is pending authentication and dashboard configuration. Added endpoint/evidence/shutdown instructions to GATEWAY_QA.md.


### Webhook setting correction - 2026-10-28

The user's latest .env change put an HTTPS URL in `RAZORPAY_WEBHOOK_SECRET`. The app uses this value as the HMAC signing key, not as an endpoint setting. Replaced the URL-valued secret with a generated private secret without displaying it and cleared configuration cache. The Razorpay Test Mode dashboard must now use the new local `RAZORPAY_WEBHOOK_SECRET` value in its Secret field and the public tunnel endpoint in its Webhook URL field. No dedicated webhook URL environment setting is currently consumed by the app.

The authenticated official plans read was retried with the currently loaded API pair and still returned HTTP 401. The key/secret match the .env file with no surrounding whitespace. This remains separate from webhook setup; provider checkout/renewal QA is not complete.

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

### Phase 4 - provider support ticket pending (2026-10-28)

Recorded the user-submitted Razorpay support ticket; no ticket number or ETA is available. Retained validated credentials and deferred repeated identical provider checks until the external state changes. Phase 4 remains incomplete. Reviewed Phase 5 separation/audit requirements and requested next-phase scope. No application code or database change in this continuation.


## Phase 5 - 2026-10-28 - Super-admin

Added separate platform credentials/session guard, interactive console provisioning, dashboard/signup trend/catalog MRR estimate, workspace directory/scoped detail, transactional audited suspension/reactivation/trial extensions/local time-limited plan grants, catalog CRUD, announcement banners and append-only application audit records. System/referenced plan deletion and referenced plan price/provider/code changes are blocked. Coupon management is catalog-only; checkout redemption and provider-managed plan changes await Phase 4 policy/provider verification.

Added owner-only 15-minute read-only impersonation with tenant binding, reason, start/end audit, persistent banner/exit, expiry/revocation checks and original identity restoration. Tenant role strings never grant platform access. Suspended workspaces now fail tenant access and billing writes. No GST math or invoice workflow changes.

Added `2026_10_28_000001_create_superadmin_tables.php`, platform models/services/controller/Form Requests/middleware/views/routes and SuperAdminFeatureTest. Updated auth/bootstrap/provider/tenant resolution/billing write guard/console routes/tenant layout and docs. Applied the additive Phase 5 migration on local MySQL and invoiceiq_test; local preflight also required applying the previously pending Phase 4 past_due_started_at migration. No data backfill, destructive migration or external provider mutation was performed.

Validation: full suite **77 tests, 632 assertions**; PHP syntax checks pass; 14 platform routes register; all Blade views compile. First platform admin is not pre-seeded: run `php artisan superadmin:create`. Manual browser QA remains an operator step. Phase 4 support ticket and commercial decisions remain open; Phase 6 not started.


## Phase 6 - 2026-10-28 - Branding and workspace workflows

Added advanced invoice branding with classic/accent rendering from a shared PDF service, retaining GST calculation and invoice line/totals markup. Company profile saves synchronize tenant identity fields; advanced styling obeys custom_branding entitlement and private logo authorization remains unchanged.

Added tenant-owned invoice_share_links (hash-only capability storage), encrypted invoice_deliveries and account_exports. Public finalized-invoice PDF links expire/revoke and fail closed on invalid/draft/cancelled/suspended records. New mail/export jobs serialize tenant/record IDs, restore context, recheck current membership/owner permissions and keep files private. Invoice email actions now queue and expose status rather than reporting delivery before SMTP succeeds. ZIP attachment behavior is preserved; temporary ZIPs use tenant-private paths.

Added formula-safe tenant CSVs and owner ZIP exports with allowlisted workspace/settings/member/billing data and private logo; passwords, SMTP/API credentials, tokens and provider metadata are excluded. Added 24-hour export expiry, scoped pruning, bounded database worker/daily maintenance schedules and workflow UI. No external email/payment was sent during implementation.

Applied additive migration `2026_10_28_000002_create_workspace_workflow_tables.php` to local MySQL and invoiceiq_test. New focused suite initially passed 11 tests/70 assertions; final full suite with added worker checks passed **90 tests, 708 assertions**. PHP syntax and Blade checks passed. Manual SMTP/browser/hosting cron review remains open. Updated progress/roadmap/testing/schema/permissions/tenancy/deployment/phase handoff and WORKFLOWS.md. Phase 4 support dependency remains; Phase 7 not started.
