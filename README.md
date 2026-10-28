# InvoiceIQ — GST Billing Suite
**Laravel 11 + Blade | MySQL | PHP 8.2+**

## Quick Setup (4 commands)

```bash
cp .env.example .env
composer install
php artisan key:generate && php artisan migrate --seed
php artisan serve
```

Open: **http://localhost:8000**

## What's included

| Feature | Details |
|---|---|
| Dashboard | Stats, recent invoices, quick actions |
| Invoice Form | Live GST calc, item auto-suggest, quick-add modals |
| GST Logic | Auto CGST+SGST (intra) vs IGST (inter) based on state |
| PDF Export | DomPDF — professional print-ready layout |
| Customer Master | Full CRUD, GSTIN, state for GST routing |
| Item Master | Full CRUD, HSN/SAC, GST rate, unit |
| Settings | Company profile, bank details, invoice numbering |
| Payments | Record partial/full payments |
| Billing | Trial, plan usage, Razorpay subscription checkout, payment history, subscription invoice PDFs |

## Requirements
- PHP >= 8.2
- Composer
- MySQL 8.0+

## Using MySQL
Edit `.env`:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=invoiceiq
DB_USERNAME=root
DB_PASSWORD=yourpassword
```
Then: `php artisan migrate --seed`. The tenancy foundation migration creates a default workspace and assigns legacy business records and existing users to it.

## Accounts and workspace setup

Visitors can create an account and workspace. New accounts must verify their email before using billing screens. Configure `MAIL_MAILER=smtp` and the SMTP host, port, credentials, encryption, and sender in `.env` to deliver verification, password reset, and team invitation emails. The default `log` mailer writes email content to the Laravel log for local development.

After verification, the workspace owner completes company/GST details and invoice prefix setup. Adding an initial customer and item is optional. Owners and admins can invite teammates with workspace-scoped roles.

New workspaces receive a 14-day card-free trial by default. Phase 4 stores configurable plans, quotas, subscriptions, provider events, and billing history. Paid checkout is disabled until approved plan prices/limits, Razorpay plan IDs/credentials/cycle count, grace duration, and the SaaS seller's tax invoice details are configured. See `docs/ai/BILLING_AND_PLANS.md`; do not activate billing with placeholder tax or pricing values.

For local UI and lifecycle development only, set `BILLING_DEMO_MODE=true` and run `php artisan db:seed --class=Database\\Seeders\\BillingDemoSeeder --force`. This loads marked sample prices/limits and assumes seven grace days. Demo mode is ignored in production and blocks Razorpay checkout and subscription tax invoices.

## Tenancy foundation

Business records are scoped to a workspace through `tenant_id`. Existing users are attached to the default tenant in the migration. A user's first workspace membership is selected automatically; workspace switching is not part of this phase. Invoice numbers use a tenant-specific financial-year sequence and the configured invoice prefix. The core invoice GST calculation and inline invoice workflow are unchanged.

## PDF Generation
Uses `barryvdh/laravel-dompdf`. Installed via composer automatically.
Access at: `/invoices/{id}/pdf`

## Project Structure
```
app/
  Http/Controllers/    — Dashboard, Invoice, Customer, Item, Settings
  Models/              — Tenant, Invoice, InvoiceItem, Customer, Item, Payment, Setting
  Services/            — tenant-scoped invoice numbering
  helpers.php          — fmt_inr(), number_to_words(), setting()
database/
  migrations/          — core schema, tenancy, and database queue tables
  seeders/             — Sample data
resources/views/
  layouts/app.blade.php
  invoices/form.blade.php    — Main invoice form with JS
  invoices/pdf.blade.php     — PDF template
  customers/index.blade.php
  items/index.blade.php
  settings/index.blade.php
  dashboard.blade.php
public/css/app.css           — Full professional CSS (no Tailwind dependency)
```


## Platform administration

After migrations, run `php artisan superadmin:create` from a trusted terminal. Enter the administrator name/email and a hidden password of at least 12 characters, then visit `/superadmin/login`. Platform accounts are separate from workspace users; no default administrator is seeded and workspace admin roles cannot sign in here.

Phase 5 provides platform metrics, workspace controls, plan/coupon/announcement catalogs, audit review, and 15-minute read-only owner impersonation with an explicit exit. Coupon checkout redemption and provider-managed plan changes remain pending Phase 4 decisions/QA. See `docs/ai/SUPERADMIN_SPEC.md` for controls and limitations.


## Invoice sharing, email and exports

Phase 6 adds Settings -> Invoice branding (Classic/Accent), per-invoice Share / Email, and Data exports for owner/admin/accountant. Public links are expiring/revocable view-only PDFs; treat their URLs as private. Advanced branding follows the plan entitlement. Full workspace ZIP exports are owner-only, private and expire after 24 hours; credentials/tokens are excluded.

Invoice mail and workspace ZIPs use the database queue. With PHP ZipArchive enabled, configure cron to run `php artisan schedule:run` every minute. The schedule runs a bounded worker and daily `workflows:prune`; inspect it with `php artisan schedule:list`. In local development, deliberately run `php artisan queue:work database --stop-when-empty --max-time=50 --tries=3 --timeout=60` when ready to process queued mail/exports. A log mailer does not deliver real mail. Keep APP_KEY secure for encrypted delivery records. See `docs/ai/WORKFLOWS.md` for permissions, privacy, worker operation and manual QA.
