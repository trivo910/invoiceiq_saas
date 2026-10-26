# Billing and plans

Phase 4 implementation status: configurable billing foundation is implemented. Commercial values and a few lifecycle rules remain open, so paid checkout and any behavior that depends on those values stay unavailable until configured. See the current position in `PROGRESS.md`.

## Plans and entitlements

Configurable plan records exist for Existing workspace, Free trial, Starter, Pro, and Business. Prices are stored in paise and limits/features on `plans`; paid plan rows start inactive and blank until product values and matching Razorpay plan IDs are supplied. A null limit means no limit is applied. No prices or quotas are seeded. The trial duration is 14 days by default (`BILLING_TRIAL_DAYS`) and new registrations receive a trial subscription. Migrated workspaces keep an unlimited Existing workspace subscription to preserve access.

Plan entitlements are monthly invoice limit, member limit (includes outstanding invitations), GST report access, custom branding, and API access. Monthly invoice/member limits and the GST report flag are enforced. Custom branding and API flags are visible/configurable but have no corresponding features to gate yet. An unset feature flag is treated as undecided/allowed; paid checkout stays disabled until every feature flag is explicitly set.

## Trial and payment lifecycle

The trial does not require a card. Razorpay subscription creation uses server-side credentials and plan IDs, interval-specific configured cycle count, then redirects to the provider-hosted short URL. The configured Razorpay plan is fetched and its amount, currency, and interval are compared with the local plan before checkout. The browser return is not proof of payment. `POST /webhooks/razorpay` verifies HMAC against raw request bytes, requires the provider event ID, records it uniquely, and processes events idempotently. Activated/charged/resumed events activate the subscription; pending/halted events enter `past_due` only after an established paid subscription fails; cancellation preserves access through the provider period end when supplied, and completion ends access. Captured subscription payments are retained in tenant-scoped history.

Configure plan rows and Razorpay plan IDs in MySQL after setting `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`, `RAZORPAY_MONTHLY_CYCLE_COUNT`, and `RAZORPAY_YEARLY_CYCLE_COUNT`. Checkout remains disabled unless price, matching provider plan ID, active flag, all feature flags, credentials, interval cycle count, seller identity, GST rate, SAC, and `BILLING_PRICE_TAX_MODE` are set. The tax mode is `inclusive` (catalog price includes GST) or `exclusive` (GST is added to the catalog price). Checkout fetches the Razorpay plan and verifies the exact gross amount, INR currency, and billing interval before creating a subscription. A pending checkout does not replace the current trial/legacy subscription. Cycle counts are capped at 1,200 monthly or 100 yearly cycles, matching Razorpay's documented 100-year maximum subscription duration.

## Status state diagram

```text
trialing --trial expires/subscribe--> active
trialing --trial expires/no payment--> expired (read-only)
active --payment failure--> past_due (grace period)
past_due --payment succeeds--> active
past_due --grace expires--> read_only
active --cancel requested--> cancel_at_period_end --> cancelled
active --provider completion--> completed
```

Data is never removed when a subscription expires or fails. The billing page and reads/exports remain accessible. The subscription middleware blocks writes when a trial ends, a configured grace period expires, or a subscription ends. When grace is unset, `past_due` retains write access until a product decision is supplied; set `BILLING_GRACE_DAYS` before production. Invoice and member seat limits are checked at the route and rechecked while locking the tenant row during invoice creation/invitation/acceptance to prevent concurrent over-limit writes.

## Billing UI and operations

The billing page shows the current plan/status, trial or renewal date, usage, configured plan choices, payment history, and private tenant-scoped invoice PDF downloads. Upgrade checkout is available only where a plan and provider configuration are complete. Downgrade, active subscription changes, cancellation requests, proration, and refunds remain unavailable until product decisions exist.

Subscription invoice PDFs are issued only when seller name, GSTIN, PAN, address, state code, GST rate, SAC, tax mode are configured, and the tenant has a state code. The invoice gross equals the amount actually captured; inclusive mode derives the taxable subtotal from that gross, while exclusive mode uses the catalog base price. Same-state invoices split GST into CGST/SGST; other-state invoices show IGST. Seller/buyer details are snapshotted at issue time. Configure the correct seller/tax treatment for the actual SaaS entity before enabling this in production.

## Open decisions

Exact INR prices and quotas, GST invoice issuer/tax details, grace-period length, downgrade behavior when current usage exceeds plan limits, proration, and refund rules require product decisions before enabling production billing. Do not invent amounts in implementation.
