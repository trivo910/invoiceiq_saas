<?php
namespace App\Services;

use App\Models\{Customer, Invoice, InvoiceItem, Item, Payment, Setting};
use App\Support\TenantContext;

class WorkspaceExport
{
    public const RESOURCES = ['customers', 'items', 'invoices', 'invoice_items', 'payments'];
    public const FIELDS = [
        'customers' => ['id', 'name', 'gstin', 'pan', 'email', 'phone', 'billing_address', 'billing_city', 'billing_state', 'billing_state_code', 'billing_pincode', 'shipping_address', 'shipping_city', 'shipping_state', 'shipping_state_code', 'credit_limit', 'payment_terms', 'notes', 'is_active'],
        'items' => ['id', 'name', 'code', 'hsn_sac', 'description', 'type', 'rate', 'gst_rate', 'unit', 'is_active'],
        'invoices' => ['id', 'number', 'status', 'invoice_date', 'due_date', 'customer_id', 'customer_name', 'customer_gstin', 'customer_billing_address', 'customer_city', 'customer_state', 'customer_state_code', 'place_of_supply', 'place_of_supply_code', 'is_intra_state', 'discount_type', 'discount_value', 'discount_amount', 'sub_total', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax', 'round_off', 'grand_total', 'amount_paid', 'visible_columns', 'notes', 'terms', 'shipping_address'],
        'invoice_items' => ['id', 'invoice_id', 'item_id', 'sort_order', 'item_name', 'description', 'hsn_sac', 'qty', 'unit', 'rate', 'discount_percent', 'discount_amount', 'taxable_amount', 'gst_rate', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'cess_rate', 'cess_amount', 'total_tax', 'total_amount'],
        'payments' => ['id', 'invoice_id', 'amount', 'payment_date', 'mode', 'reference', 'notes'],
    ];

    public function writeCsv(string $resource, $stream): void
    {
        abort_unless(app(TenantContext::class)->id(), 403);
        $class = match ($resource) { 'customers' => Customer::class, 'items' => Item::class, 'invoices' => Invoice::class, 'invoice_items' => InvoiceItem::class, 'payments' => Payment::class, default => abort(404) };
        $fields = self::FIELDS[$resource];
        fputcsv($stream, $fields, ',', '"', '');
        foreach ($class::orderBy('id')->cursor() as $record) {
            fputcsv($stream, array_map(fn ($field) => $this->cell($record->getAttribute($field)), $fields), ',', '"', '');
        }
    }

    public function cell(mixed $value): string
    {
        if (is_array($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($value instanceof \DateTimeInterface) $value = $value->format('Y-m-d H:i:s');
        $value = (string) $value;
        // Prevent spreadsheet formula interpretation, including leading whitespace/control bytes.
        return preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $value) ? "'".$value : $value;
    }

    public function manifest(): array
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);
        return [
            'format' => 1, 'created_at' => now()->toIso8601String(),
            'workspace' => $tenant->only(['id', 'name', 'slug', 'gstin', 'pan', 'state_code', 'address', 'currency', 'invoice_prefix', 'status', 'trial_ends_at', 'created_at']),
            'settings' => array_intersect_key(Setting::all_settings(), array_flip(array_merge(InvoicePdf::SETTING_KEYS, ['invoice_prefix', 'default_terms', 'email_subject', 'email_body']))),
            'members' => $tenant->users()->get()->map(fn ($user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->pivot->role])->all(),
            'invitations' => \App\Models\TenantInvitation::get(['email', 'role', 'expires_at', 'accepted_at'])->toArray(),
            'subscriptions' => \App\Models\TenantSubscription::with('plan:id,code,name')->get(['id', 'plan_id', 'status', 'billing_interval', 'trial_ends_at', 'current_period_starts_at', 'current_period_ends_at', 'grace_ends_at'])->toArray(),
            'billing_payments' => \App\Models\BillingPayment::get(['id', 'subscription_id', 'amount_paise', 'currency', 'status', 'method', 'paid_at'])->toArray(),
            'billing_invoices' => \App\Models\BillingInvoice::get(['id', 'payment_id', 'number', 'subtotal_paise', 'tax_paise', 'tax_breakdown', 'total_paise', 'currency', 'seller_snapshot', 'buyer_snapshot', 'issued_at'])->toArray(),
            'invoice_sequences' => \Illuminate\Support\Facades\DB::table('tenant_invoice_sequences')->where('tenant_id', $tenant->id)->get(['financial_year', 'next_number'])->toArray(),
            'excludes' => ['passwords', 'SMTP credentials', 'API/webhook credentials', 'session/reset/invitation/share tokens', 'provider metadata', 'platform audit data'],
        ];
    }
}
