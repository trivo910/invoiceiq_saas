<?php
namespace App\Services;

use App\Http\Controllers\SettingsController;
use App\Models\{Invoice, Setting};
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdf
{
    public const SETTING_KEYS = ['company_name', 'company_gstin', 'company_pan', 'company_address', 'company_city', 'company_state', 'company_state_code', 'company_phone', 'company_email', 'company_logo', 'bank_name', 'bank_acc_no', 'bank_ifsc', 'bank_branch', 'default_notes', 'invoice_pdf_template', 'brand_color'];
    public function make(Invoice $invoice)
    {
        abort_unless((int) $invoice->tenant_id === app(\App\Support\TenantContext::class)->id(), 404);
        $invoice = Invoice::whereKey($invoice->id)->firstOrFail();
        $invoice->load('items', 'payments');
        $settings = array_intersect_key(Setting::all_settings(), array_flip(self::SETTING_KEYS));
        $logoBase64 = SettingsController::logoBase64($settings);
        $brandColor = preg_match('/^#[0-9a-fA-F]{6}$/D', $settings['brand_color'] ?? '') ? $settings['brand_color'] : '#245dc9';
        $template = $settings['invoice_pdf_template'] ?? 'classic';
        $plan = app(BillingService::class)->subscription(app(\App\Support\TenantContext::class)->current())?->plan;
        if ($plan?->custom_branding === false) { $template = 'classic'; $brandColor = '#245dc9'; }
        $view = $template === 'accent' ? 'invoices.pdf-accent' : 'invoices.pdf';
        return Pdf::loadView($view, compact('invoice', 'settings', 'logoBase64', 'brandColor'))->setPaper('a4', 'portrait');
    }
}
