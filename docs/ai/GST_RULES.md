# GST and invoice calculation rules

**Protected: existing GST calculation logic must stay untouched unless the user approves a change.** This document describes the current implementation, not legal advice or a claim that every tax edge case is supported.

## State of supply

The invoice form carries the seller's `company_state_code` from settings and the customer's/place-of-supply state code. Equal state codes select intra-state tax: split the GST rate equally into CGST and SGST. Different state codes select inter-state tax: use the full rate as IGST. The form exposes the supply selection and updates the tax-type indicator live. Server-side invoice recalculation persists the corresponding rates and amounts.

## HSN/SAC and GST rates

Items distinguish goods/services, store `hsn_sac`, and carry GST rate. Invoice lines snapshot item name, HSN/SAC, description, unit, rate, and GST values so historical invoices do not depend on later master edits. The current item form/rules expose GST rates 0%, 5%, 12%, 18%, and 28%; do not silently expand this set as part of tenancy work.

## Discounts and amounts

Line discount is a percentage applied to quantity × rate to derive the taxable line amount. Invoice discount can be percentage or flat; the server recalculation allocates the invoice discount proportionally to tax buckets. Line tax and invoice totals are stored in decimal columns at two decimal places (quantity uses three decimal places). The current server calculator rounds stored component totals to two decimals and rounds the final raw grand total to the nearest whole rupee, storing the difference as `round_off`. Confirm exact edge cases in `InvoiceController::recalculate()` before any future change.

## Number words and display

Helpers and the invoice form implement Indian numbering vocabulary (thousand, lakh, crore) and rupee/paise wording. The form currently rounds the grand amount for amount-in-words display. `fmt_inr()` is a PHP display helper; JS formats amounts in the inline workflow. These presentation rules are protected.

## Calculation reference points

- Server calculation: `app/Http/Controllers/InvoiceController.php` (`invoiceData`, `syncItems`, `recalculate`).
- Interactive preview: `resources/views/invoices/form.blade.php` (`updateGstTag`, `recalc`, `n2w`).
- PHP formatting and number words: `app/helpers.php`.
- Persisted representation: `invoices` and `invoice_items` migrations.

## Caution

Client preview and server persistence are separate code paths; keep their behavior aligned when explicitly authorized to change tax behavior. Automated regression tests should be added before changing these protected calculations.
