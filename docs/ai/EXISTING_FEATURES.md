# Existing features (protected)

Every business feature below is **protected: do not change logic without approval**. Phase 1 added a tenant foundation underneath these flows; it did not change GST math or the inline UI. This inventory was checked against routes, controllers, models, migrations, Blade templates, helpers, and `DatabaseSeeder`; static inspection does not prove production behavior.

| Feature | Observed implementation | Protection |
|---|---|---|
| Dashboard | Invoice totals/status counts, customer count, recent invoices, monthly totals; raw SQL aggregate and global cache key `dashboard.stats` | protected: do not change logic without approval |
| Invoice listing | Search/filter/pagination and computed overdue display | protected: do not change logic without approval |
| Inline invoice entry | Editable item rows, master-item autocomplete, quick-create item/customer modals, mobile item drawer | protected: do not change logic without approval |
| GST type | Company and place-of-supply state codes drive intra-state CGST/SGST versus inter-state IGST | protected: do not change logic without approval |
| Discounts | Per-line percentage discount and invoice-level percent/flat discount | protected: do not change logic without approval |
| Invoice columns | Visibility toggles persist as invoice `visible_columns` JSON | protected: do not change logic without approval |
| Amount in words | Browser helper in invoice form and PHP helper in `app/helpers.php`; Indian numbering vocabulary | protected: do not change logic without approval |
| HSN/SAC | Master item and invoice-line HSN/SAC snapshots; GST report grouping | protected: do not change logic without approval |
| PDF | DomPDF invoice output and GST report PDF; company settings/logo are rendered | protected: do not change logic without approval |
| Customer master | CRUD, search, active flag, GSTIN/PAN, billing/shipping contact/address, payment terms | protected: do not change logic without approval |
| Item master | CRUD/search, goods/service, code, HSN/SAC, rate, GST rate, unit, active flag | protected: do not change logic without approval |
| Payments | Invoice-linked payment rows; invoice form records payment and updates status/balance | protected: do not change logic without approval |
| Settings | Tenant-scoped company profile, bank data, invoice prefix/sequence, terms/notes, SMTP/email template, logo | protected: do not change logic without approval |
| User administration | Admin-managed users and `admin`, `staff`, `viewer` roles | protected: do not change logic without approval |
| Login | Custom Blade login, remember option, session regeneration, logout invalidation; login POST throttled 5/minute | protected: do not change logic without approval |

## GST report inventory

`GstReportController::REPORT_LABELS` has five report selections. GSTR-1 Summary itself contains B2B and B2C sections.

| Report | Observed content |
|---|---|
| GSTR-1 Summary | B2B and B2C transaction summaries over a selected period |
| B2B — Registered Customers | Registered-customer rows grouped by GSTIN/name/state |
| B2C — Unregistered Customers | Unregistered-customer aggregation by state and intra/inter-state supply |
| HSN / SAC Wise Summary | Invoice lines grouped by HSN/SAC, unit, and GST rate |
| Tax Liability Report | Tax-rate summaries and monthly tax-liability series used by report charts |

Reports can be viewed and exported via Excel and PDF. **protected: do not change logic without approval** for every report's filters, grouping, tax math, and export shape.

## UI conventions to preserve

The invoice screen favors inline work, autocomplete, quick-create modals, immediate client-side recalculation, column toggles, and a mobile drawer. The global layout uses a dark sidebar and custom CSS. Preserve these workflows during tenancy work.
