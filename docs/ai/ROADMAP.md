# SaaS conversion roadmap

## Local Docker development

- [x] Add a PHP/Apache image and isolated MySQL/storage Compose setup with an optional scheduler and startup instructions.
- [ ] Build and run the PHP 8.3 stack on a machine with Docker, then verify Composer platform requirements, migration, login, private files, queued work and cleanup.

Status: **Phases 1-3, 5 and 6 implementation ready; Phase 7 implementation in progress; Phase 4 provider/commercial and production operations pending.**

## Phase 0 — Documentation setup

- [x] Scan current routes, models, migrations, controllers, views, helpers, setup files, and seeder.
- [x] Create the complete `/docs/ai/` documentation set.
- [x] Create root `CLAUDE.md` and `AGENTS.md` continuation instructions.
- [x] User reviewed the docs and instructed continuation to Phase 1.

## Phase 1 — Tenancy foundation (complete)

- [x] Use `tenant_user` membership pivot and inspect configured local MySQL data/duplicates.
- [x] Add tenants and tenant ownership migration with default-tenant backfill.
- [x] Add tenant context/scope/assignment and tenant-scoped unique indexes, including customer GSTIN after duplicate preflight.
- [x] Implement financial-year tenant invoice numbering with transaction/row lock.
- [x] Add database queue configuration and queue tables for hosting constraints.
- [x] Update schema/docs and apply migrations to configured local MySQL; preflight contained customers/items/settings/users but no invoices.

## Phase 2 — Auth and onboarding (complete)

- [x] Registration atomically creates an owner and workspace; signed email verification and password reset flows.
- [x] Onboarding for company/GST/logo/invoice prefix and optional customer/item.
- [x] Tenant-scoped invitations, membership role changes/removal, and Gate-based role checks.
- [x] Add SMTP/log mail configuration and apply the auth/invitation migration on configured local MySQL.

## Phase 3 — Isolation audit (complete)

- [x] Scope dashboard aggregation/cache, invoice/customer/item/payment lookups and mutations, and joined GST reports to the active tenant.
- [x] Validate invoice customer/item IDs and enforce same-tenant invoice line/payment links.
- [x] Move tenant logos to private paths, migrate legacy public logo references, and serve through the authenticated tenant route.
- [x] Add MySQL-backed feature coverage for dashboard/cache, search, route binding, cross-tenant writes/relations, GST reports/Excel export, and logos.

## Phase 4 — Plans and billing (in progress)

- [x] Configurable plan/subscription/payment/invoice/webhook schema; card-free 14-day trials for new workspaces.
- [x] Razorpay server-side checkout and signed, idempotent webhook state/payment processing.
- [x] Billing page, tenant payment history, configurable subscription tax invoice PDFs, usage meters, and read-only behavior.
- [x] Invoice/member plan limit checks with tenant-row lock rechecks for concurrent writes.
- [x] Save failed renewal attempts from signed webhooks in tenant billing history without issuing invoices; verify provider event replay is idempotent.
- [x] Verify signed webhook payment records follow the provider subscription's tenant even when another tenant is active in request context.
- [x] Add a local/testing-only demo catalog and seven-day grace assumption; label sample values and block checkout/tax invoice issuance in demo mode.
- [x] Prevent out-of-order signed provider events from overwriting newer subscription state while retaining payment history.
- [x] Verify cancellation preserves write access through provider period end, then switches the workspace to read-only.
- [x] Preserve the provider's final period end for completed subscriptions and enforce read-only access after it.
- [x] Handle provider pause/resume events with paid-through access and restored active state.
- [x] Serialize workspace checkout creation and reuse an existing pending provider subscription.
- [x] Restrict billing details and actions to the workspace owner role.
- [x] Enforce same-tenant billing payment/subscription and billing invoice/payment relationships.
- [x] Reject tenant ID tampering on subscription saves and cover it with a two-tenant regression.
- [x] Leave webhooks for not-yet-persisted provider subscriptions unconsumed and retryable.
- [x] Require a confirmed paid period end for active paid write access while preserving unlimited legacy access.
- [x] Validate provider checkout response IDs and hosted URLs before saving a pending subscription.
- [x] Verify both configured GST price modes and same-state versus interstate tax breakdowns in webhook-issued invoices.
- [x] Keep plan cards and checkout actions responsive at narrow viewport widths.
- [x] Recover missing hosted URLs for existing pending checkouts without creating duplicate provider subscriptions.
- [x] Expose pending checkout recovery as an owner billing-page action and verify its redirect.
- [x] Apply a grace duration configured after a failure from that original provider failure time.
- [x] Show accurate past-due messaging when grace is unset or has a calculated deadline.
- [x] Normalize state-code whitespace before subscription checkout and tax invoice state classification.
- [x] Allow replacement checkout after an active paid period has ended even if the provider did not send a terminal lifecycle event.
- [x] Keep captured payment state and subscription access stable if a later failure webhook references the same payment ID.
- [x] Issue GST invoices only for positive INR captured payments; retain non-INR and zero-value captures in payment history.
- [x] Match captured payment totals to the configured subscription cycle price before issuing tax invoices.
- [x] Store normalized seller and buyer state codes in issued tax invoice snapshots.
- [x] Cover mismatched captured totals for inclusive and exclusive tax pricing.
- [x] Preserve valid subscription access dates when provider lifecycle events contain malformed timestamps.
- [x] Treat absent or invalid payment currency as unknown and block tax invoice issuance for that capture.
- [x] Reject invalid webhook request metadata and malformed/negative/overflowing payment amounts before persistence.
- [x] Cover pending invitation reservations, resends, expiration, and seat-limit rechecks on acceptance.
- [x] Block member invitation creation and acceptance while a workspace is read-only.
- [x] Test exact monthly invoice-limit enforcement and prior-month usage exclusion.
- [x] Test yearly exclusive-tax checkout and recurring cycle-count upper/lower bounds.
- [x] Verify same-state CGST/SGST rounding retains every paise in the invoice tax total.
- [x] Reject checkout for zero-priced plans, the free trial, and the legacy plan.
- [x] Allow replacement checkout for paused subscriptions after paid access ends; keep checkout blocked during the paid period.
- [x] Require configured webhook authentication before enabling provider checkout.
- [x] Reject whitespace-only webhook secrets consistently in checkout readiness, pending checkout recovery, and webhook processing.
- [x] Hide/block existing pending checkout links when webhook authentication is missing; preserve pending state and explain the issue to the owner.
- [x] Release a pending checkout after a provider `subscription.halted` event while preserving existing workspace access.
- [x] Ignore late failure callbacks for subscription entitlement after an initial checkout has halted; keep the failed payment attempt in history.
- [x] Anchor grace deadlines to provider failure time when its webhook is delayed.
- [x] Verify missing or malformed provider failure timestamps use webhook receipt time for grace.
- [x] Preserve original past-due start across repeated failures when grace is unset; clear it on recovery.
- [x] Ignore late `subscription.pending` callbacks for halted initial checkouts while retaining payment history.
- [x] Verify duplicate capture notifications with distinct provider event IDs do not duplicate payment or invoice records.
- [x] Preserve captured payment details across conflicting duplicate capture notifications with the same provider payment ID.
- [x] Require a nonblank workspace state code before exposing or continuing a saved pending checkout.
- [x] Reject missing/whitespace-only seller tax identity fields and invalid GST rates for checkout and tax-invoice issuance.
- [x] Ignore a reused provider payment ID when it is already bound to another tenant/subscription.
- [x] Ignore webhook events whose subscription entity and payment entity reference different provider subscriptions.
- [x] Preserve read-only access to admin settings and member-list pages when subscription writes are blocked.
- [x] Reject malformed/negative trial-day configuration at registration; support zero days as an explicit no-trial setting.
- [x] Reject malformed/negative grace-day configuration; support zero days as immediate grace expiry.
- [x] Re-run the full MySQL suite after Phase 4 billing changes (59 tests, 469 assertions).
- [ ] Set approved plan prices/limits, Razorpay plan IDs and cycle count; run provider sandbox verification (QA resumed 2026-10-28; authenticated API check returned HTTP 401, demo mode remains enabled, and checkout configuration is incomplete).
- [ ] Set seller GST/SAC invoice details and approved GST treatment; verify tax invoice output (requires seller/tax inputs; sandbox QA resumed 2026-10-28, see GATEWAY_QA.md).
- [ ] Set approved grace period, downgrade-over-limit, proration, and refund rules.

## Phase 5 - Super-admin (implemented)

- [x] Separate guard/identity, dashboard, tenant/plan controls, announcements/coupon catalog (checkout redemption awaits Phase 4 rules).
- [x] Audited 15-minute read-only owner impersonation, expiry/revocation, identity restore, persistent exit banner and sensitive-action logging.

## Phase 6 - Branding and SaaS workflows (implemented)

- [x] Tenant settings/profile synchronization, private uploads and selectable classic/accent PDFs with plan entitlement.
- [x] Expiring/revocable public invoice PDF capabilities, tenant-aware queued email/history, scoped CSV and private owner account exports.

## Phase 7 — Launch hardening (implementation in progress)

- [x] Public landing/pricing and fictional/referenceable Terms/Privacy sample drafts with primary legal source links. Prices remain approval-gated; legal samples are explicitly unapproved.
- [x] Append-only tenant activity events, usage/workflow counts, owner-confirmed soft deletion and platform-only restoration.
- [x] Record an invoice-line replacement summary when edit flow bulk-deletes prior lines (counts only; same transaction).
- [x] Record tenant profile field names and logo upload/removal actions where query-builder updates bypass Eloquent audit hooks.
- [x] Distinguish invitation reissues in the tenant activity audit without recording email addresses or tokens.
- [x] Render audit action labels and safe count/action summaries in the workspace activity timeline.
- [x] Audit terminal email/export job failures through tenant model hooks where the workspace remains active.
- [x] Baseline security headers, per-route throttle additions, SMTP password encryption at rest, tax identifier shape checks/normalization.
- [x] Safe setup script, release configuration report, VPS/nginx reference and launch checklist.
- [x] Include a secret-safe production preflight check for legacy SMTP encryption and current APP_KEY compatibility.
- [ ] Add/verify legacy SMTP password encryption in the deployed DB; review business-record audit coverage and recovery flow in browser.
- [ ] Approve legal and pricing copy; verify backup/restore and rollback on a staging VPS; configure production HTTPS, SMTP, cron and queue operation.
- [ ] Resolve Phase 4 Razorpay support/account-access issue and complete account-backed sandbox QA before enabling checkout.

## Bulk Import - staged feature

- [x] Step 1: customer/item Excel and CSV templates, instructions/lists, role gating and listing-page guide.
- [x] Step 1 visual follow-up: center the native dialog and give it an opaque surface under the shared CSS reset.
- [x] Step 2: private upload, smart header mapping, server-side row validation and review preview; no business rows written.
- [x] Step 3: transactional tenant-safe processing, skip-valid/stop-on-issues choices, completed row results and repeat-submit protection.
- [ ] Future import history listing, downloadable error report, and customer/item quotas if those plan limits are approved and added to the schema.

### Phase 4 continuation — plan restriction navigation

- [x] Route blocked non-owner actions to an accessible dashboard warning and return JSON 403 for read-only, quota, and feature denials; preserve owner Billing redirects.

- [x] Verify restriction redirects after MySQL reconnection (4 tests, 39 assertions) and tenant isolation (7 tests, 55 assertions); gateway QA remains deferred.

### Gateway QA continuation ? 2026-10-28

- [x] Resume full automated testing including mocked gateway regressions: 62 tests, 502 assertions.
- [x] Inspect sandbox readiness and attempt an authenticated read-only provider plans request (HTTP 401).
- [ ] Resolve Test Mode authentication, approve sandbox assumptions, and verify public HTTPS webhook configuration before completing provider-backed checkout/renewal QA.

### Approved sandbox setup - 2026-10-28

- [x] Obtain user approval for sample sandbox settings and a temporary tunnel.
- [x] Prepare isolated QA configuration and verify the HTTPS webhook path with a signed synthetic event/replay.
- [ ] Resolve repeated provider HTTP 401 with the updated credentials.
- [ ] Register the temporary endpoint in Test Mode and verify real provider delivery, checkout, renewal/recovery, and invoice PDFs.

- [x] Correct URL-valued webhook secret, generate a private signing key, and clear config cache.
- [ ] Update Test Mode dashboard Secret to the generated local value and verify delivery; current API credentials still return HTTP 401.

- [x] Recheck user-updated webhook secret through replacement tunnel: signed synthetic event/replay 204, one event; unsigned request 400, private paths 404.
- [ ] Update expired tunnel URL in the Test Mode dashboard and resolve independent API HTTP 401 before provider-backed checkout QA.

- [x] Verify current local webhook secret on the user-managed tunnel origin: signed/replay 204, unsigned/tampered 400, one event.
- [ ] Obtain a currently resolving user-managed tunnel URL (supplied hostname returns public DNS NXDOMAIN), then verify actual provider delivery. API HTTP 401 remains unresolved.

- [x] Verify replacement user-managed public hostname prediction-attempts-configure-tires.trycloudflare.com: signed/replay 204, unsigned/tampered 400, one event.
- [ ] Confirm latest URL in Test Mode dashboard, receive an actual provider event, and resolve the last API authentication HTTP 401 before checkout/renewal QA.

- [x] Record user confirmation of the current dashboard endpoint and repeat successful synthetic public signature/replay checks.
- [ ] Receive a real provider event (none found in last-24-hour event log) and resolve the repeated API authentication HTTP 401 before account-backed checkout QA.

- [x] Recheck after updated Subscriptions dashboard state: Plans GET now returns 200 with zero records; Test Mode API access is available.
- [x] Create QA-labeled Test Mode Starter monthly/yearly plans and attach returned IDs to the local sample catalog.
- [ ] Verify application checkout, real provider delivery, renewal/failure/recovery, and invoice behavior; no subscription/payment has been created yet.

- [x] Validate current Test Mode API credentials independently: payments endpoint returns 200 with correct Basic Auth/TLS.
- [ ] Investigate Plans/Subscriptions-specific HTTP 401 (both fail with the same valid credentials); confirm Test Mode dashboard product access or obtain provider support findings. Earlier invalid-key assumptions are superseded.

- [x] Reconfirm endpoint-specific behavior on dashboard inspection request: payments 200, plans/subscriptions 401.
- [ ] Inspect user-visible Test Mode Subscriptions page; no authenticated dashboard/browser session is available to the agent.

- [x] Inspect user-supplied dashboard evidence: Get Started leads to Failed to load subscriptions; API comparison still payments 200 vs plans/subscriptions 401.
- [ ] Obtain Razorpay support resolution for Test Mode Subscriptions access/dashboard failure, then resume account-backed subscription QA.

- [x] User submitted a Razorpay support ticket for the Subscriptions dashboard/Plans API issue.
- [ ] Resume provider-backed subscription QA after support resolution; keep outstanding Phase 4 production decisions visible while selecting independent next-phase work.

### Phase 5 - Super-admin (implemented)

- [x] Local/test migrations, full MySQL suite (77 tests, 632 assertions), PHP syntax, routes and Blade compilation.
- [ ] Operator provisioning (`php artisan superadmin:create`) and manual desktop/mobile platform review.
- [ ] Phase 4 provider support resolution and approved production billing/discount decisions remain separate dependencies.

### Phase 6 validation - 2026-10-28

- [x] Local/test additive migration and full suite: 90 tests, 708 assertions.
- [x] PHP syntax, Blade compilation, protected workflow routes and short worker/prune schedule registration.
- [ ] Manual desktop/mobile PDF/sharing/export review and real SMTP/hosting cron execution.
- [ ] Phase 4 provider/commercial work remains open; Phase 7 is not started.
