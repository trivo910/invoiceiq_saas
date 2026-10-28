# Razorpay sandbox QA

> Latest finding: API credentials are valid (payments GET returns 200); plans and subscriptions GET return 401 with the same credentials. See the corrected diagnosis at the end. Earlier invalid-credential assumptions are superseded.

## Current run: 2026-10-28

The user authorized resuming gateway QA. The earlier deferral is lifted for sandbox testing. Production commercial decisions remain open.

Readiness inspection found a test-prefixed API key, an API secret, and a webhook secret configured without displaying their values. The authenticated, read-only `GET https://api.razorpay.com/v1/plans?count=100` returned HTTP 401. Network access initially failed inside the sandbox; an approved outside-sandbox retry reached the endpoint. Account access has not passed validation. No provider plans, subscriptions, or payments were created.

Initial missing prerequisites (see the approved setup update below for subsequent progress):

- Demo mode is enabled, which blocks application checkout and subscription tax invoices.
- Starter, Pro, and Business have sample prices but no monthly or yearly provider plan IDs.
- Monthly and yearly recurring cycle counts are unset.
- All seller tax fields are unset; the application cannot issue subscription tax invoices.
- The configured application URL is HTTP. A reachable public HTTPS webhook endpoint has not been supplied or verified.
- Sandbox configuration assumptions and a staging/tunnel choice have been requested from the user. Do not treat them as approved until answered.

## Execution checklist

1. Replace or verify the matching Test Mode key/secret in local `.env`. Clear cached configuration if needed and repeat the authenticated plans read. Never print credentials or use live credentials for this run.
2. Obtain the sandbox configuration values or explicit approval for sample values. Isolate test work in a dedicated QA workspace; do not change existing workspace entitlements to generate evidence.
3. Prepare a public HTTPS staging endpoint or an approved tunnel to the app. Verify `/webhooks/razorpay` is reachable; an unsigned POST must be rejected. Do not expose local debug output or source files through a tunnel.
4. In the Razorpay Test Mode dashboard, register that endpoint using the same webhook secret as the app. Enable subscription lifecycle and payment events listed in DEPLOYMENT.md. A locally signed fixture does not prove Razorpay delivery.
5. Create or select test plans whose INR amounts, interval, and cycle period match the application catalog and selected inclusive/exclusive tax mode. Save the IDs in the local plan records. Set cycle counts and sample seller details, and disable demo mode only for the agreed QA configuration.
6. As the QA workspace owner, initiate checkout through the application. Verify one pending local subscription, the matching provider subscription, and the hosted checkout redirect. Complete the interactive authentication payment using Razorpay's current subscription testing instructions.
7. Verify real provider delivery, signature acceptance, processed event IDs, correct tenant assignment, paid-through dates, and payment history. Inspect generated invoice totals, seller/buyer snapshots, GST breakdown, and the private PDF. Sample invoice data proves formatting and application behavior only.
8. Trigger a subsequent successful test charge and a failed charge from the dashboard where available. Verify renewal dates, failed payment history, configured grace/read-only enforcement, and recovery. Check cancellation/paid-through behavior and replay without duplicate payments or invoices. Distinguish dashboard-triggered provider events from local fixtures in results.
9. Record results and remaining gaps, stop any temporary tunnel, and restore temporary configuration as agreed. Keep production billing disabled until approved production values and launch checks are complete.

## Evidence to record

For each scenario, record test time, scenario, test workspace/subscription identifiers, provider event ID, HTTP outcome, local status, payment/invoice counts, and pass/fail. Keep secrets, authorization headers, card details, and full customer payloads out of docs and chat.

## Provider references

- [Razorpay subscription testing](https://razorpay.com/docs/payments/subscriptions/test/): use Test Mode, configure a webhook, create plans/subscriptions, and simulate subsequent charges from the dashboard.
- [Webhook validation and testing](https://razorpay.com/docs/webhooks/validate-test/): localhost needs a public endpoint; some tunnel domains are blocked. Select a supported endpoint rather than assuming a common tunnel will work. Validate the signature over the original raw request body.

These references were checked on 2026-10-28. Follow their current test instructions when completing interactive payment steps.


## Approved setup update: 2026-10-28

The user saved replacement credentials and explicitly approved existing demo prices, clearly marked sample seller details, 12 monthly/2 yearly cycles, seven-day grace, and preparing a temporary tunnel. These assumptions apply only to sandbox QA and do not settle production decisions.

A fresh PHP process confirmed it loaded the exact updated key/secret from `.env`, with no surrounding whitespace and no cached configuration. The authenticated plans request still returned HTTP 401 with `{"error":"Unauthorized"}`. Corrected account access remains required; no provider plans, subscriptions, or payments have been created.

Prepared private QA helpers under ignored `storage/app/private/gateway-qa/`. Their bootstrap overrides billing demo mode (off), approved cycle counts/grace, and sample seller fields only in the QA process. Sample tax setup uses 18% inclusive pricing, Maharashtra state code 27, and explicitly fictitious seller data. The main `.env`, existing plans, and workspace entitlements were not modified.

Downloaded Cloudflare's official Windows client (version 2026.10.5); its Authenticode signature validated as Cloudflare, Inc. Started a temporary HTTPS tunnel to a loopback-only PHP listener on port 8189. The listener exposes only POST `/webhooks/razorpay`; all other methods/paths return 404. Debug output is disabled for this process. It invokes the regular application HTTP middleware/controller, signature verification, and event processor.

Current temporary endpoint:

`https://window-flower-unified-affair.trycloudflare.com/webhooks/razorpay`

Public-path verification results:

| Check | Result |
|---|---|
| GET `/` | 404 |
| GET `/.env` | 404 |
| Unsigned webhook POST | 400 |
| Locally signed synthetic event POST | 204 |
| Replay of the same signed event | 204 |
| Stored event rows for that ID | 1 |

Synthetic event ID: `gateway_qa_connectivity_20261028_7c570c91b3`. This is connectivity/signature/idempotency evidence generated locally, not a Razorpay event or payment. It added one no-op billing event with no tenant/payment/subscription change.

The user was asked to configure the endpoint in the Razorpay Test Mode dashboard with the local webhook secret and supported subscription lifecycle/payment events. Provider acceptance of the tunnel URL, real delivery, checkout, renewals, and tax PDF QA are still pending.

The tunnel and listener were left running for dashboard configuration (PIDs at setup: cloudflared 13712, PHP 25932). Their PID files and logs are in the private QA directory. Stop only these QA services with:

```powershell
powershell -NoProfile -File storage/app/private/gateway-qa/stop.ps1
```

The stop helper checks process identity before stopping it. Restarting the tunnel changes its public hostname; update the dashboard accordingly. The tunnel must be stopped after this interactive QA session or if it is no longer needed.

Cloudflare's [Quick Tunnel documentation](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/) describes the temporary hostname and process lifetime. Razorpay accepting the URL in its dashboard is a separate prerequisite.


### Webhook setting correction - 2026-10-28

The user's latest .env change put an HTTPS URL in `RAZORPAY_WEBHOOK_SECRET`. The app uses this value as the HMAC signing key, not as an endpoint setting. Replaced the URL-valued secret with a generated private secret without displaying it and cleared configuration cache. The Razorpay Test Mode dashboard must now use the new local `RAZORPAY_WEBHOOK_SECRET` value in its Secret field and the public tunnel endpoint in its Webhook URL field. No dedicated webhook URL environment setting is currently consumed by the app.

The authenticated official plans read was retried with the currently loaded API pair and still returned HTTP 401. The key/secret match the .env file with no surrounding whitespace. This remains separate from webhook setup; provider checkout/renewal QA is not complete.

Recheck after secret correction passed: public root/private paths 404, unsigned webhook 400, signed synthetic event/replay 204 with one event row (`gateway_qa_connectivity_20261028_6c27674400`). This proves local signing across the public tunnel, not Razorpay-generated delivery. Tunnel remains running for dashboard setup.


### User-updated webhook secret recheck - 2026-10-28

Retried after the user saved a new webhook secret. The old tunnel had expired (`Tunnel not found`, DNS no longer resolving); replaced only the QA tunnel process. The current endpoint above is the replacement and must be updated in the Razorpay Test Mode dashboard. The private verifier now reads the current hostname from tunnel startup output rather than hardcoding it.

With the newly saved secret, public checks passed: root/private path 404, unsigned webhook 400, signed synthetic event and replay 204, exactly one event row. Event ID: `gateway_qa_connectivity_20261028_aca4163326`. This is locally generated evidence; it does not verify that the dashboard uses the same secret or prove provider delivery.

The authenticated plans request still returns HTTP 401 using the current API pair. User must configure the replacement endpoint and matching local webhook secret in the dashboard; API authentication remains a separate unresolved prerequisite. No provider plans/subscriptions/payments were created. The replacement tunnel remains running for dashboard setup.


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


Support message draft (not sent):

> In Test Mode, Subscriptions -> Get Started shows "Failed to load subscriptions" in the Razorpay dashboard. Using the same Test API key pair and valid Basic Auth, GET /v1/payments?count=1 returns 200, but GET /v1/plans?count=1 and GET /v1/subscriptions?count=1 return 401. Please investigate Test Mode Subscriptions provisioning/permissions and the dashboard error. Screenshot available. No key secret is included.

### Provider support pending - 2026-10-28

User confirmed submitting a support ticket for the Test Mode Subscriptions dashboard failure and endpoint-specific API 401. Ticket ID/ETA are unknown. Preserve the validated API pair and defer repeated identical provider requests until support responds or account state changes. Provider-backed subscription QA remains incomplete. Existing successful synthetic webhook evidence remains local/application evidence only.
