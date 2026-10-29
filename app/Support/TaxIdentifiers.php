<?php

namespace App\Support;

use Illuminate\Http\Request;

class TaxIdentifiers
{
    // Format validation only: this does not verify registration, checksum or legal tax treatment.
    public const GSTIN = 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D';
    public const PAN = 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/D';
    public const STATE = 'regex:/^(0[1-9]|[1-9][0-9])$/D';

    public static function normalize(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (is_string($request->input($field))) {
                $value = strtoupper(trim($request->input($field)));
                $request->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
