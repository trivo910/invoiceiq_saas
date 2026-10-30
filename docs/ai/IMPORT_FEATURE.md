# Customer and item bulk import

## Current status

Implementation is deliberately staged. **Steps 1, 2 and 3 are implemented** for Customers and Items. Step 2 uploads a private file, matches or maps headings, validates every row, and displays a preview. Step 3 can add valid rows transactionally, skip rows with warnings/errors, or stop the entire import when any issue remains.

## Step 1: download and prepare a template

On the Customers or Items page, choose **Import**, then download the Excel or CSV template. The Excel workbook contains three sheets:

- **Data:** required columns are marked with `*`; the header is frozen. Two light-grey sample rows are included, with a cell note and instructions reminding users to delete them before using real data. Dropdowns help choose states, state codes, item types, GST rates and units.
- **Instructions:** explains each supported column, whether it is required, example values and common mistakes. It also calls out fields that do not exist in the current schema.
- **Lists:** state/GST code, unit, GST rate and item type values used by Excel dropdowns.

CSV is UTF-8 with a BOM and contains the Data headers plus the same two sample rows. It does not carry Excel dropdowns or the extra instructions/list sheets. Delete the sample rows before using your own data.

The template fields are based on the current database and forms, rather than the larger proposed import schema:

- **Customers:** `name` is the only required field in the current form. Optional fields include GSTIN, PAN, email, phone, billing/shipping address and state details, pincode, credit limit, payment terms, notes and active status. The current customer table has no customer type, company name, country or opening-balance column. GSTIN is currently optional in the application, including for B2B customers; the template does not invent a customer type rule.
- **Items:** name, goods/service type, selling rate, GST rate and unit are required by the current item form. Item code, HSN/SAC, description and active status are optional. The database has an item code field scoped unique per tenant. Purchase price, discount and opening stock are not current item columns.

GST state/code options follow the [GST e-Way Bill state-code list](https://docs.ewaybillgst.gov.in/apidocs/state-code.html). The list is a convenience for selecting a value; validate tax details for the actual transaction before issuing an invoice.

## Permissions and tenancy

Only Owner, Admin and Accountant memberships see the Import control and may download, upload, map and preview. Staff and Viewer are denied. Batch and row records use tenant scopes; uploads are stored privately under `storage/app/tenants/{tenant_id}/imports`, and duplicate checks query only the active tenant. The upload limit is 5 MB and 5,000 nonblank data rows. Excel workbooks must contain a `Data` sheet; merged cells and formulas are rejected. CSV is UTF-8 comma-delimited.

The preview shows all rows and Ready/Warnings/Errors tabs, 50 rows per page. It checks required fields, formats and reference values against the template. Customer warning matches use GSTIN, phone or normalized name; item matches use code or normalized name+HSN/SAC. Repeated candidate records in a file are also warnings. The user can choose **Import valid rows** (adds only Ready rows and skips Warnings/Errors) or **Stop if there are warnings or errors** (atomic no-write result). Imported, skipped and error row results remain visible in the completed preview. A completed batch cannot be processed again. Uploaded files and preview rows are pruned after configured `imports.retention_days` (30 days by default) by the daily `imports:prune` schedule; the host must run Laravel's scheduler.

Processing rechecks subscription write access in controller middleware and inside a tenant-row-locked transaction. Current plan quotas cover monthly invoices and member seats only; there are no customer/item quotas to apply. The 5 MB / 5,000-row import limits still apply. If a concurrent record creates a hard unique conflict after preview, the complete transaction rolls back and the user sees a retry message; no partial business rows remain.

## How to add a template entity

1. Add actual supported fields, examples and plain-language instructions to `ImportTemplateDefinition`.
2. Add entity-specific dropdown mappings in `ImportTemplateDataSheet`; add any new list values and named ranges to `ImportTemplateDefinition` and `ImportTemplateExport`.
3. Add role-gated template routes and a listing-page include using the entity route prefix.
4. Add workbook-content, download, and role-visibility feature coverage in `ImportTemplateTest`.
5. Keep any future import changes transactional and tenant scoped. If customer/item quotas are added to Plan, recheck them while locking the tenant before inserting. Never accept `tenant_id`, IDs, timestamps, or formulas as trusted input.

## End-user help: importing customers and items

### How it will work

1. Download a template and fill in the Data sheet.
2. Upload the completed file and review the preview.
3. Choose to import valid rows while skipping warnings/errors, or stop with no writes until every row is ready.

All three steps are available. The completed preview shows imported, skipped and error rows. It can be reviewed again but cannot be processed a second time.

### Common questions

- **Why are there example rows?** They show the expected column formats. Delete the grey sample rows before using the workbook.
- **Can I add a Customer Type or Opening Balance column?** Not yet. Those fields do not exist in the current customer schema and are not part of this template.
- **Can I add purchase prices or discounts to Items?** Not yet; the current item schema has no such fields.
- **Should I use Excel or CSV?** Excel includes dropdowns and instructions. CSV is a simpler option but has no dropdowns; keep the first row as the exact header row.
- **Are the sample GSTINs or HSN/SAC codes my real customer data?** No. They are illustrative samples. Replace them and verify tax identifiers and classification codes before use.
- **Does upload add records?** No. Upload creates a private preview. Records are added only after choosing an import action on that preview.
- **What happens to duplicate warnings?** The default option skips every warning row. The other option stops the whole import while any warning or error remains; no duplicate rows are inserted.
- **Are customer/item plan limits applied?** No such quotas exist in the current plan schema. Processing still requires current subscription write access.
- **Why is the file rejected?** Use `.xlsx`, `.xls` or `.csv`, keep the template header row on top, remove formulas/merged cells and stay under 5 MB/5,000 rows.

## Step 1 QA checklist

- Owner/Admin/Accountant can download both Excel templates and CSV templates.
- Staff/Viewer do not see the Import control and receive a denied response for template routes.
- Excel opens with Data, Instructions and Lists sheets; headers remain visible while scrolling; dropdowns use the workbook Lists sheet; example rows are highlighted and labeled.
- CSV begins with a UTF-8 BOM and contains the matching Data header and example rows.
- No customer/item records are read or changed by a template download.

Step 1 automated coverage is in `tests/Feature/ImportTemplateTest.php`.

## Step 2 QA checklist

- Owner/Admin/Accountant can upload and preview customer/item CSV or Excel files; Staff/Viewer are denied.
- Unsupported files, empty data, formulas, merged cells, oversized files and over-limit row counts are rejected before preview.
- Unknown headings open a tenant-private mapping page; mapping columns cannot be reused and required fields must be mapped.
- Preview validates all rows, shows warning/error counts and is paginated at 50 rows per page.
- Duplicate checks cannot see records belonging to another tenant.
- Upload and mapping never create or update customers/items; only the explicit processing action does.
- Private source files and rows are removed by the scheduled retention command after configured retention.

Focused coverage is in `tests/Feature/ImportUploadTest.php` and `tests/Feature/ImportTemplateTest.php`, including all-or-nothing behavior, warning-row skipping and repeat-submit protection.
