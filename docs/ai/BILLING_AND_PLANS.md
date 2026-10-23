# Billing and plans

Target subscription behavior; none of this is implemented in the current repository.

## Plans and entitlements

Create configurable Free/Trial, Starter, Pro, and Business plans with monthly/yearly INR pricing and GST applied to subscription charges. Plan entitlements include monthly invoice limit, member limit, GST report access, custom branding, and API access. Keep prices/limits in database/configurable plan records, not scattered controller conditionals.

## Trial and payment lifecycle

Provide a 14-day trial without requiring a card. Integrate Razorpay Subscriptions through server-created subscriptions and client checkout. Verify webhook signatures against raw request bytes, persist event IDs, and process each event idempotently. Handle activated, charged, halted, cancelled, and completed events. Store provider identifiers and payment history without treating a browser callback as proof of payment.

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

Data is never removed when a subscription expires or fails. A friendly `EnsureActiveSubscription` middleware and `CheckPlanLimit` middleware should direct the user to billing; read-only mode must still allow access/export of existing records while blocking writes as policy defines.

## Billing UI and operations

Billing page: current plan/status, trial end or renewal date, usage meters, upgrade/downgrade, cancel, payment history, and downloadable subscription invoices. Record a grace period and make its duration an explicit product/configuration decision. Subscription invoice PDF must identify the SaaS seller and GST treatment.

## Open decisions

Exact INR prices, GST invoice issuer details, grace-period length, downgrade behavior when current usage exceeds plan limits, proration, and refund rules require product decisions before production billing. Do not invent amounts in implementation.
