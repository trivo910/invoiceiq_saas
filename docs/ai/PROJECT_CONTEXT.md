# Project context

## Product

InvoiceIQ is an existing Indian GST invoice management application for small businesses and their accountants. The current application is a single shared company workspace with role-managed users. The SaaS target is multiple independent businesses using the same application and MySQL schema, each with isolated records and company-specific settings.

## Current stack (observed)

| Area | Current repository |
|---|---|
| Framework/runtime | Laravel 11, PHP ^8.2 |
| UI | Blade, custom CSS in `public/css/app.css`, inline vanilla JavaScript |
| Database | MySQL default in `config/database.php`; MySQL connection configured |
| Packages | DomPDF, Laravel Excel, Tinker; PHPUnit/Faker for development |
| Authentication | Custom Blade login controller and Laravel auth middleware; Phase 1 associates users with tenants through `tenant_user` |
| Hosting | README/setup target PHP/Composer and MySQL; no verified production deployment |

## Users and SaaS vision

Target customers are Indian GST-registered or GST-aware businesses issuing invoices, tracking collections, and preparing GST reports. The target is a shared-schema, single-database multi-tenant SaaS with workspace/company ownership, scoped users, plans, billing, and a distinct super-admin area. Subdomain tenant resolution is explicitly a later optional phase and is off by default.

## Hosting constraints

Deployment must support Hostinger shared hosting as well as a VPS. Avoid Redis, Horizon, and long-running workers. The requested queue strategy is Laravel's database queue driver, with cron invoking `schedule:run`; deployment specifics remain future work and must be checked against Laravel 11 and the actual hosting plan before rollout.

## Current limitations relevant to conversion

- Phase 1 adds the tenant model, tenant foreign keys, request tenant context, scoped business models, and invoice-numbering service. Signup/verification, plans/billing, super-admin, and policies remain unimplemented.
- Settings are tenant-scoped; users remain global identities with roles on the `tenant_user` membership pivot. Legacy route authorization still reads `users.role` pending Phase 2.
- Existing controllers generally use inline `$request->validate()` rather than Form Requests.
- `DashboardController` executes a raw aggregate query against all invoices and caches under one global key.
- Existing data migration must preserve historical invoices and their customer/item snapshots.
- No `tests/` directory was found in the repository scan. Do not infer test coverage from PHPUnit being installed.

## Current boundary after Phase 1

The tenant schema and model foundation exist, but Phase 3 isolation audit is still required for raw queries, reports, exports, and files. Do not treat Phase 1 as proof of complete isolation.
