<?php

namespace App\Services\Import;

use App\Exports\ImportTemplateDefinition;
use App\Models\{Customer, ImportBatch, ImportRow, Item};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportPreviewService
{
    public function headers(string $entity): array
    {
        $fields = ImportTemplateDefinition::for($entity)['fields'];
        $keys = [];
        foreach ($fields as $field) $keys[$this->key($field[0])] = ['label' => $field[0], 'required' => $field[1]];
        return $keys;
    }

    public function autoMap(string $entity, array $headers): array
    {
        $expected = $this->headers($entity);
        $aliases = $this->aliases($entity);
        $mapping = [];
        $unused = [];
        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            $key = $aliases[$normalized] ?? $this->key($header);
            if (isset($expected[$key]) && ! isset($mapping[$key])) $mapping[$key] = $index;
            else $unused[] = $index;
        }
        return ['mapping' => $mapping, 'unused' => $unused, 'expected' => $expected];
    }

    public function mappingNeeded(string $entity, array $headers, array $mapping, array $unused): bool
    {
        foreach ($this->headers($entity) as $key => $field) {
            if ($field['required'] && ! array_key_exists($key, $mapping)) return true;
        }
        return $unused !== [];
    }

    public function validateAndSave(ImportBatch $batch, array $mapping): void
    {
        $reader = app(ImportFileReader::class);
        $extension = strtolower(pathinfo($batch->stored_path, PATHINFO_EXTENSION));
        $parsed = $reader->read(Storage::disk('tenant_private')->path($batch->stored_path), $extension);
        $expected = $this->headers($batch->entity);
        $this->assertValidMapping($expected, $parsed['headers'], $mapping);

        DB::transaction(function () use ($batch, $parsed, $mapping, $expected): void {
            ImportRow::where('import_batch_id', $batch->id)->delete();
            $seen = [];
            $counts = ['ready' => 0, 'warning' => 0, 'error' => 0];
            foreach ($parsed['rows'] as $sourceRow) {
                $payload = [];
                foreach ($expected as $key => $_field) {
                    $index = $mapping[$key] ?? null;
                    $payload[$key] = $index === null ? null : ($sourceRow['cells'][$index] ?? null);
                }
                [$payload, $errors, $warnings, $identity] = $this->validateRow($batch->entity, $payload, $sourceRow['row_number']);
                if ($identity !== null) {
                    if (isset($seen[$identity])) $warnings[] = 'This row repeats the same customer/item earlier in this file (row '.$seen[$identity].').';
                    else $seen[$identity] = $sourceRow['row_number'];
                    if ($this->existsInTenant($batch->entity, $payload)) $warnings[] = 'A customer/item with matching details already exists in this workspace.';
                }
                $status = $errors !== [] ? 'error' : ($warnings !== [] ? 'warning' : 'ready');
                $counts[$status]++;
                ImportRow::create([
                    'import_batch_id' => $batch->id,
                    'row_number' => $sourceRow['row_number'],
                    'status' => $status,
                    'payload' => $payload,
                    'errors' => $errors,
                    'warnings' => $warnings,
                ]);
            }
            $batch->forceFill([
                'status' => 'validated', 'total_rows' => count($parsed['rows']),
                'ready_rows' => $counts['ready'], 'warning_rows' => $counts['warning'], 'error_rows' => $counts['error'],
                'headers' => $parsed['headers'], 'header_map' => $mapping,
            ])->save();
        });
    }

    private function assertValidMapping(array $expected, array $headers, array $mapping): void
    {
        $used = [];
        foreach ($mapping as $key => $index) {
            if (! isset($expected[$key])) throw new ImportFileException('The column mapping changed. Refresh the page and map the headings again.');
            if ($index === null || $index === '') {
                if ($expected[$key]['required']) throw new ImportFileException('Choose a column for “'.$expected[$key]['label'].'”.');
                continue;
            }
            if (! is_numeric($index) || ! array_key_exists((int) $index, $headers)) throw new ImportFileException('One of the selected columns is no longer available. Upload the file again.');
            $index = (int) $index;
            if (isset($used[$index])) throw new ImportFileException('Each file column can be used once. Choose a different column for “'.$expected[$key]['label'].'”.');
            $used[$index] = true;
        }
        foreach ($expected as $key => $field) {
            if ($field['required'] && ! isset($mapping[$key])) throw new ImportFileException('Choose a column for “'.$field['label'].'”.');
        }
    }

    private function validateRow(string $entity, array $data, int $rowNumber): array
    {
        $errors = [];
        $warnings = [];
        $payload = [];
        foreach ($data as $key => $value) $payload[$key] = $this->clean($value);

        if ($entity === 'customers') {
            $payload['customer_name'] = $this->text($payload['customer_name'] ?? null);
            if ($payload['customer_name'] === '') $errors[] = 'Row '.$rowNumber.': Customer Name is required.';
            $payload['gstin'] = strtoupper($this->text($payload['gstin'] ?? null));
            if ($payload['gstin'] !== '' && ! preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $payload['gstin'])) {
                $errors[] = 'Row '.$rowNumber.': GSTIN must be 15 characters in the format 27ABCDE1234F1Z5.';
            }
            $payload['pan'] = strtoupper($this->text($payload['pan'] ?? null));
            if ($payload['pan'] !== '' && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $payload['pan'])) $errors[] = 'Row '.$rowNumber.': PAN must be 10 letters and digits in the correct format.';
            $payload['email'] = $this->text($payload['email'] ?? null);
            if ($payload['email'] !== '' && ! filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Row '.$rowNumber.': Email address is not valid.';
            $phone = $this->digits($payload['phone'] ?? null);
            if ($phone !== '') {
                if (strlen($phone) === 12 && str_starts_with($phone, '91')) $phone = substr($phone, 2);
                if (strlen($phone) !== 10) $errors[] = 'Row '.$rowNumber.': Phone must contain 10 digits, with an optional +91 prefix.';
            }
            $payload['phone'] = $phone;
            foreach (['billing', 'shipping'] as $prefix) $this->state($payload, $prefix, $rowNumber, $errors);
            if ($payload['billing_pincode'] !== null && $payload['billing_pincode'] !== '') {
                $pin = $this->digits($payload['billing_pincode']);
                if (! preg_match('/^[0-9]{6}$/', $pin)) $errors[] = 'Row '.$rowNumber.': Billing Pincode must be 6 digits.';
                $payload['billing_pincode'] = $pin;
            }
            foreach (['credit_limit' => 'Credit Limit', 'payment_terms_days' => 'Payment Terms'] as $key => $label) {
                if (($payload[$key] ?? '') === '' || $payload[$key] === null) continue;
                $number = $this->number($payload[$key]);
                if ($number === null || $number < 0 || floor($number) !== $number) $errors[] = 'Row '.$rowNumber.': '.$label.' must be a whole number of 0 or more.';
                else $payload[$key] = (int) $number;
            }
            $payload['active'] = $this->active($payload['active'] ?? null, $rowNumber, $errors);
            $payload['_source_row'] = $rowNumber;
            $identity = $payload['customer_name'] === '' ? null : ($payload['gstin'] !== '' ? 'gstin:'.$payload['gstin'] : ($phone !== '' ? 'phone:'.$phone : 'name:'.mb_strtolower($payload['customer_name'])));
        } else {
            $payload['item_name'] = $this->text($payload['item_name'] ?? null);
            if ($payload['item_name'] === '') $errors[] = 'Row '.$rowNumber.': Item Name is required.';
            $payload['item_code'] = strtoupper($this->text($payload['item_code'] ?? null));
            if (mb_strlen($payload['item_code']) > 255) $errors[] = 'Row '.$rowNumber.': Item Code is too long.';
            $payload['hsn_sac'] = $this->text($payload['hsn_sac'] ?? null);
            if (mb_strlen($payload['hsn_sac']) > 20) $errors[] = 'Row '.$rowNumber.': HSN/SAC must be 20 characters or fewer.';
            $payload['description'] = $this->text($payload['description'] ?? null);
            $payload['type'] = strtolower($this->text($payload['type'] ?? null));
            if (! in_array($payload['type'], ['goods', 'service'], true)) $errors[] = 'Row '.$rowNumber.': Type must be Goods or Service.';
            $rate = $this->number($payload['selling_price'] ?? null);
            if ($rate === null || $rate < 0 || $rate > 9999999999.99) $errors[] = 'Row '.$rowNumber.': Selling Price must be a number of 0 or more.';
            else $payload['selling_price'] = round($rate, 2);
            $gst = $this->number($payload['gst_rate'] ?? null);
            if ($gst === null || ! in_array((int) $gst, [0, 5, 12, 18, 28], true) || $gst != (int) $gst) $errors[] = 'Row '.$rowNumber.': GST Rate must be 0, 5, 12, 18, or 28.';
            else $payload['gst_rate'] = (int) $gst;
            $payload['unit'] = $this->text($payload['unit'] ?? null);
            if (! in_array($payload['unit'], ImportTemplateDefinition::lists()['units'], true)) $errors[] = 'Row '.$rowNumber.': Unit must match one of the choices in the template.';
            $payload['active'] = $this->active($payload['active'] ?? null, $rowNumber, $errors);
            $payload['_source_row'] = $rowNumber;
            $identity = $payload['item_name'] === '' ? null : ($payload['item_code'] !== '' ? 'code:'.$payload['item_code'] : 'name_hsn:'.mb_strtolower($payload['item_name']).'|'.mb_strtolower($payload['hsn_sac']));
        }

        return [$payload, $errors, $warnings, $identity];
    }

    private function state(array &$payload, string $prefix, int $row, array &$errors): void
    {
        $nameKey = $prefix.'_state';
        $codeKey = $prefix.'_state_code';
        $name = $this->text($payload[$nameKey] ?? null);
        $code = $this->digits($payload[$codeKey] ?? null);
        $states = [];
        foreach (ImportTemplateDefinition::STATES as [$stateName, $stateCode]) {
            $states[mb_strtolower($stateName)] = [$stateName, $stateCode];
            $states[$stateCode] = [$stateName, $stateCode];
        }
        if ($name !== '' && isset($states[mb_strtolower($name)])) [$name, $fromName] = $states[mb_strtolower($name)];
        elseif ($name !== '') $fromName = null;
        else $fromName = null;
        if ($name === '' && $code === '' && $prefix === 'billing' && ! empty($payload['gstin']) && preg_match('/^[0-9]{2}/', $payload['gstin'])) $code = substr($payload['gstin'], 0, 2);
        if ($name !== '' && $fromName === null) $errors[] = 'Row '.$row.': '.$this->label($prefix).' State must match a state in the template list.';
        if ($code !== '' && strlen($code) === 1) $code = '0'.$code;
        if ($code !== '' && ! isset($states[$code])) $errors[] = 'Row '.$row.': '.$this->label($prefix).' State Code is not in the template list.';
        if ($name !== '' && $fromName !== null && $code !== '' && $fromName !== $code) $errors[] = 'Row '.$row.': '.$this->label($prefix).' State and State Code do not match.';
        if ($name === '' && $code !== '' && isset($states[$code])) [$name, $fromName] = $states[$code];
        if ($name === '' && $code !== '' && isset($states[$code])) $code = $states[$code][1];
        if ($name !== '' && $fromName !== null && $code === '') $code = $fromName;
        if ($prefix === 'billing' && ! empty($payload['gstin']) && preg_match('/^[0-9]{2}/', $payload['gstin']) && $code !== '' && substr($payload['gstin'], 0, 2) !== $code) {
            $errors[] = 'Row '.$row.': Billing State Code does not match the first two digits of the GSTIN.';
        }
        $payload[$nameKey] = $name === '' ? null : $name;
        $payload[$codeKey] = $code === '' ? null : $code;
    }

    private function active(mixed $value, int $row, array &$errors): bool
    {
        if ($value === null || $value === '') return true;
        $value = strtolower($this->text($value));
        if (in_array($value, ['yes', 'y', 'true', '1', 'active'], true)) return true;
        if (in_array($value, ['no', 'n', 'false', '0', 'inactive'], true)) return false;
        $errors[] = 'Row '.$row.': Active? must be Yes or No.';
        return true;
    }

    private function existsInTenant(string $entity, array $payload): bool
    {
        if ($entity === 'customers') {
            if (($payload['gstin'] ?? '') !== '' && Customer::where('gstin', $payload['gstin'])->exists()) return true;
            if (($payload['phone'] ?? '') !== '' && Customer::where('phone', $payload['phone'])->exists()) return true;
            return ($payload['customer_name'] ?? '') !== '' && Customer::whereRaw('LOWER(name) = ?', [mb_strtolower($payload['customer_name'])])->exists();
        }
        if (($payload['item_code'] ?? '') !== '' && Item::where('code', $payload['item_code'])->exists()) return true;
        return ($payload['item_name'] ?? '') !== '' && Item::whereRaw('LOWER(name) = ?', [mb_strtolower($payload['item_name'])])
            ->where('hsn_sac', $payload['hsn_sac'] ?? null)->exists();
    }

    private function aliases(string $entity): array
    {
        $common = ['name' => $entity === 'customers' ? 'customer_name' : 'item_name'];
        if ($entity === 'customers') return $common + [
            'customer' => 'customer_name', 'mobile' => 'phone', 'mobile number' => 'phone', 'phone number' => 'phone',
            'address' => 'billing_address', 'city' => 'billing_city', 'state' => 'billing_state', 'state code' => 'billing_state_code',
            'pincode' => 'billing_pincode', 'pin code' => 'billing_pincode', 'terms' => 'payment_terms_days', 'payment terms' => 'payment_terms_days',
            'is active' => 'active', 'active status' => 'active',
        ];
        return $common + [
            'item' => 'item_name', 'sku' => 'item_code', 'code' => 'item_code', 'hsn code' => 'hsn_sac',
            'default rate' => 'selling_price', 'rate' => 'selling_price', 'price' => 'selling_price', 'gst rate' => 'gst_rate',
            'gst' => 'gst_rate', 'item type' => 'type', 'unit of measure' => 'unit', 'is active' => 'active', 'active status' => 'active',
        ];
    }

    private function key(string $header): string
    {
        $header = strtolower(str_replace('*', '', $header));
        return trim(preg_replace('/[^a-z0-9]+/', '_', $header), '_');
    }

    private function normalizeHeader(string $header): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(trim(str_replace('*', '', $header)))));
    }

    private function clean(mixed $value): mixed
    {
        if ($value === null) return null;
        return is_string($value) ? trim(ltrim($value, "'")) : $value;
    }

    private function text(mixed $value): string
    {
        if ($value === null) return '';
        if (is_float($value) && floor($value) === $value && abs($value) < 1.0e15) return number_format($value, 0, '.', '');
        return trim((string) $value);
    }

    private function digits(mixed $value): string
    {
        $text = $this->text($value);
        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) return substr($digits, 2);
        return $digits;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $text = str_replace([',', '₹', ' '], '', $this->text($value));
        return is_numeric($text) ? (float) $text : null;
    }

    private function label(string $prefix): string { return ucfirst($prefix); }
}
