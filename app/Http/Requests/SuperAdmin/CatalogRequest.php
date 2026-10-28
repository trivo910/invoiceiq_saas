<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool { return auth('superadmin')->user()?->is_active ?? false; }

    protected function prepareForValidation(): void
    {
        if ($this->route('kind') === 'coupons' && is_string($this->input('code'))) $this->merge(['code' => strtoupper(trim($this->input('code')))]);
    }

    public function rules(): array
    {
        $kind = $this->route('kind');
        $window = ['is_active' => ['required', 'boolean'], 'starts_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after:starts_at']];
        if ($kind === 'announcements') return $window + ['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:5000']];
        if ($kind === 'coupons') return $window + [
            'code' => ['required', 'regex:/^[A-Z0-9_-]+$/D', 'max:50', Rule::unique('coupons')->ignore($this->route('record'))],
            'type' => ['required', 'in:percent,fixed_paise'],
            'value' => ['required', 'integer', 'min:1', 'max:'.($this->input('type') === 'percent' ? 100 : 100000000)],
        ];
        abort_unless($kind === 'plans', 404);
        return [
            'code' => ['required', 'regex:/^[a-z0-9_-]+$/D', 'max:50', Rule::unique('plans')->ignore($this->route('record'))],
            'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'monthly_price_paise' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'yearly_price_paise' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'monthly_invoice_limit' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'member_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'gst_reports' => ['nullable', 'boolean'], 'custom_branding' => ['nullable', 'boolean'], 'api_access' => ['nullable', 'boolean'],
            'razorpay_plan_monthly_id' => ['nullable', 'regex:/^plan_[A-Za-z0-9]+$/D', 'max:100'],
            'razorpay_plan_yearly_id' => ['nullable', 'regex:/^plan_[A-Za-z0-9]+$/D', 'max:100'],
            'is_active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
