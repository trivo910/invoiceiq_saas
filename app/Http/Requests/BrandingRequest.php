<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrandingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasRole(['owner', 'admin']) ?? false; }
    public function rules(): array
    {
        return ['invoice_pdf_template' => ['required', 'in:classic,accent'], 'brand_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/D']];
    }
}
