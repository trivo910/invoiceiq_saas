<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class InvoiceNumberingService
{
    public function peek(?string $invoiceDate = null): string
    {
        [$tenant, $financialYear] = $this->context($invoiceDate);
        $row = DB::table('tenant_invoice_sequences')
            ->where('tenant_id', $tenant->id)
            ->where('financial_year', $financialYear)
            ->first();

        $sequence = (int) ($row->next_number ?? 1);
        return $this->format($tenant, $financialYear, $sequence);
    }

    public function next(?string $invoiceDate = null): string
    {
        [$tenant, $financialYear] = $this->context($invoiceDate);

        return DB::transaction(function () use ($tenant, $financialYear): string {
            DB::table('tenant_invoice_sequences')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'financial_year' => $financialYear,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('tenant_invoice_sequences')
                ->where('tenant_id', $tenant->id)
                ->where('financial_year', $financialYear)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;

            DB::table('tenant_invoice_sequences')
                ->where('tenant_id', $tenant->id)
                ->where('financial_year', $financialYear)
                ->update(['next_number' => $number + 1, 'updated_at' => now()]);

            return $this->format($tenant, $financialYear, $number);
        });
    }

    public function nextNumberForCurrentYear(): int
    {
        [$tenant, $financialYear] = $this->context(null);
        $row = DB::table('tenant_invoice_sequences')
            ->where('tenant_id', $tenant->id)
            ->where('financial_year', $financialYear)
            ->first();

        return (int) ($row->next_number ?? 1);
    }

    public function setNextNumberForCurrentYear(int $number): void
    {
        [$tenant, $financialYear] = $this->context(null);
        DB::table('tenant_invoice_sequences')->updateOrInsert(
            ['tenant_id' => $tenant->id, 'financial_year' => $financialYear],
            ['next_number' => $number, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function context(?string $invoiceDate): array
    {
        $tenant = app(TenantContext::class)->current();
        if (! $tenant) {
            throw new LogicException('A tenant context is required for invoice numbering.');
        }

        $date = $invoiceDate ? CarbonImmutable::parse($invoiceDate) : CarbonImmutable::today();
        $month = max(1, min(12, (int) $tenant->financial_year_start_month));
        $monthStart = CarbonImmutable::createFromDate($date->year, $month, 1)->startOfMonth();
        $day = max(1, min($monthStart->daysInMonth, (int) $tenant->financial_year_start_day));
        $financialYearStart = $monthStart->day($day);
        $startYear = $date->lessThan($financialYearStart) ? $date->year - 1 : $date->year;
        $financialYear = sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);

        return [$tenant, $financialYear];
    }

    private function format(Tenant $tenant, string $financialYear, int $sequence): string
    {
        $prefix = trim((string) Setting::get('invoice_prefix', $tenant->invoice_prefix ?: 'INV'));
        return sprintf('%s-%s-%04d', $prefix, $financialYear, $sequence);
    }
}
