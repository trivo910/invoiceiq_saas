# Workspace branding and SaaS workflows

## Phase 6 implementation

The user continued after Phase 5. This phase adds workspace branding/PDF selection, public invoice capabilities, queued invoice email/history and tenant data exports. It does not change GST calculations, invoice numbering or the inline entry workflow. Phase 4 provider support/commercial decisions remain separate.

## Branding and PDFs

Existing company/logo settings remain. Company name/GSTIN/PAN/state/address updates also synchronize the tenant root used for billing snapshots. Existing private logo validation/delivery remains in effect.

Settings has a separate Invoice branding form: Classic preserves the existing invoice presentation; Accent wraps the same invoice/line/totals view with a validated hex color accent. Template names are allowlisted and colors are validated again when rendering. Explicit custom_branding=false blocks advanced changes and makes rendering fall back to Classic; undecided feature flags retain existing allowed semantics. Company/logo access is not retroactively removed.

InvoicePdf is shared by authenticated downloads, public PDF views and email attachments. It checks tenant context and loads scoped current data, only passes an invoice-print settings allowlist and embeds the authorized private logo. SMTP/config fields never enter the print view. InvoiceMail preserves the existing PDF-in-ZIP attachment format and sanitizes archive filenames; temporary ZIPs are tenant-private and deleted on normal completion/failure.

## View-only invoice links

Open Share / Email from the invoice list. Writers can create 1-90 day links (default UI choice: seven days) and revoke them. Only finalized invoices can be shared; draft/cancelled invoices are rejected. Expired subscriptions keep existing read access but cannot create/revoke links through write-protected routes.

A cryptographically random 32-byte token is shown once as a URL; only its SHA-256 hash is persisted. Public GET `/share/invoices/{token}` resolves context by exact hash, expiry and revocation, then requires an active tenant and a scoped finalized invoice. This is the explicit bearer-capability lookup exception; no unscoped tenant model query is used. Possession of the URL grants viewing that invoice PDF without login, including its printed customer/bank details. There is no public write endpoint. Cancellation/draft changes or suspension make it unavailable.

Responses carry private/no-store, no-referrer, noindex/nofollow and nosniff headers. Public requests are throttled. These links are not a way to stop recipients saving/copying a PDF. Treat URLs as private; do not add analytics or log raw tokens. The configured/application public host must be reachable for recipients (a localhost link is useful only on that machine). Use HTTPS in deployments and retain the correct public host/proxy configuration.

## Queued invoice email and history

Existing send/finalise email actions validate recipients/subject/body and create an encrypted invoice_deliveries payload. Jobs contain only explicit tenant/delivery IDs. The user sees queued status, then sent after worker success or failed after permission revocation/exhausted retries. Draft invoices become sent only after successful delivery. Per-invoice history is available to writers; viewing history does not send anything.

SendInvoice establishes/restores tenant context, rejects suspended tenants, rechecks the requester's current writer membership and enabled email setting, then renders/sends in that context. Tenant SMTP uses a fresh transport per message; without tenant SMTP it uses the application mailer (log mailer in local development). Payloads are encrypted with APP_KEY, which must be preserved in backups. Queue/failed-job serialized payloads contain IDs, not bodies/SMTP passwords. Transport exception details are replaced by a generic message.

Delivery uses a row lock and skips already-sent records. SMTP cannot guarantee exactly-once delivery if a worker dies after the remote server accepts mail but before the DB commit; retries are at-least-once. Each explicit send action creates a new delivery. Queued work requested while authorized can finish after subscription expiry; current tenant activity and membership/email permission are rechecked. No external email was sent by implementation tests (mail was faked).

## CSV and owner workspace exports

Owner/admin/accountant can download tenant-scoped customer/item/invoice/line/payment CSVs, including in subscription read-only mode. A streamed callback establishes its own tenant context because request middleware has already cleared context when streaming begins. Fields are allowlisted and formula-like cells, including after leading whitespace/control characters, receive a leading apostrophe for spreadsheet safety.

Only owners request/download full workspace ZIPs. BuildAccountExport carries scalar tenant/export IDs, restores context and rechecks current owner membership. ZIP includes the business CSVs, allowlisted workspace/settings/member/invitation/subscription/billing/sequence data in workspace.json, and private logo when available. Passwords, SMTP/API/webhook credentials, session/reset/invitation/share tokens, provider metadata and platform audit records are excluded. This is a portable data export, not a database/credential backup or restore/import facility.

Files live at `storage/app/tenants/{tenant_id}/exports/{uuid}.zip` and use an authorized owner route, never a public storage URL. Path prefixes/extensions are validated on download and pruning. Ready files expire after 24 hours; `workflows:prune` deletes expired files and clears their paths. History rows remain. Large exports still depend on hosting memory/runtime limits; production-scale export sizing and failure/crash recovery are part of hosting QA.

## Queue and maintenance operation

PHP's ZipArchive extension is required. Existing database queue configuration/tables are used; no Redis/Horizon/daemon dependency is added.

`routes/console.php` schedules a bounded worker every minute:

```powershell
php artisan queue:work database --stop-when-empty --max-time=50 --tries=3 --timeout=60
```

Worker overlap is prevented with a two-minute lock. Daily `workflows:prune` removes expired files. Configure the host's cron to invoke `php artisan schedule:run` every minute; Windows local review can run the bounded worker manually when intentionally testing delivery. `schedule:list` verifies registration without executing jobs. A job may run up to its timeout beyond max-time; deployment process limits must support that. No scheduler/SMTP production execution is claimed yet.

## Validation and remaining review

Additive migration `2026_10_28_000002_create_workspace_workflow_tables.php` is applied to local/test MySQL. Full suite: 90 tests, 708 assertions. New workflow tests cover public token hashing/expiry/revocation, finalized/suspended/read-only access, tenant/role binding, encrypted queued mail, context restoration/retry failure/membership revocation/wrong IDs, CSV isolation/formula safety, owner export privacy/expiry/pruning/role revocation and branding/PDF attachment generation.

Manual review remains: desktop/mobile workflow pages, both PDF styles, an intentionally addressed real test email with configured SMTP, scheduled worker/prune execution on the target host, large workspace export sizing and production HTTPS. Operator super-admin provisioning remains separate. Phase 7 is not started.
