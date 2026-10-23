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

## Tenancy foundation

Business records are scoped to a workspace through `tenant_id`. Existing users are attached to the default tenant in the migration. Each authenticated user currently resolves their first tenant membership; workspace switching and invitations are part of a later phase. Invoice numbers use a tenant-specific financial-year sequence and the configured invoice prefix. The core invoice GST calculation and inline invoice workflow are unchanged.

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
