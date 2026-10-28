<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class TenantActionRequest extends FormRequest
{
    public function authorize(): bool { return auth('superadmin')->user()?->is_active ?? false; }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'action' => ['required', 'in:status,extend_trial,plan'],
            'status' => ['required_if:action,status', 'in:active,suspended'],
            'days' => ['required_if:action,extend_trial', 'integer', 'min:1', 'max:365'],
            'plan_id' => ['required_if:action,plan', 'integer', 'exists:plans,id'],
            'access_until' => ['required_if:action,plan', 'date', 'after:now', 'before:'.now()->addYears(5)->format('Y-m-d')],
        ];
    }
}
