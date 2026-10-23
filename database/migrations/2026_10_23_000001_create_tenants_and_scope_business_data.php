<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $businessTables = ['settings', 'customers', 'items', 'invoices', 'invoice_items', 'payments'];

    public function up(): void
    {
        $duplicateItemCodes = DB::table('items')
            ->select('code')->whereNotNull('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->count();
        $duplicateInvoiceNumbers = DB::table('invoices')
            ->select('number')->groupBy('number')->havingRaw('COUNT(*) > 1')->count();
        $duplicateCustomerGstins = DB::table('customers')
            ->select('gstin')->whereNotNull('gstin')->where('gstin', '!=', '')
            ->groupBy('gstin')->havingRaw('COUNT(*) > 1')->count();

        if ($duplicateItemCodes || $duplicateInvoiceNumbers || $duplicateCustomerGstins) {
            throw new \RuntimeException('Tenant migration preflight failed: duplicate item codes, invoice numbers, or non-empty customer GSTINs must be resolved before schema changes.');
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 12)->nullable();
            $table->string('state_code', 5)->nullable();
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->char('currency', 3)->default('INR');
            $table->unsignedTinyInteger('financial_year_start_month')->default(4);
            $table->unsignedTinyInteger('financial_year_start_day')->default(1);
            $table->string('invoice_prefix', 10)->default('INV');
            $table->string('status', 30)->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        $setting = fn (string $key, mixed $default = null) => DB::table('settings')->where('key', $key)->value('value') ?? $default;
        $tenantId = DB::table('tenants')->insertGetId([
            'name' => $setting('company_name', 'InvoiceIQ Workspace'),
            'slug' => 'default',
            'gstin' => $setting('company_gstin'),
            'pan' => $setting('company_pan'),
            'state_code' => $setting('company_state_code'),
            'address' => $setting('company_address'),
            'logo_path' => $setting('company_logo'),
            'invoice_prefix' => $setting('invoice_prefix', 'INV'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'tenant_id']);
        });

        $adminAssigned = false;
        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $role = match ($user->role) {
                'admin' => $adminAssigned ? 'admin' : 'owner',
                'viewer' => 'viewer',
                default => 'staff',
            };
            if ($user->role === 'admin') $adminAssigned = true;
            DB::table('tenant_user')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($this->businessTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
            DB::table($tableName)->update(['tenant_id' => $tenantId]);
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique('settings_key_unique');
            $table->unique(['tenant_id', 'key']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique('items_code_unique');
            $table->unique(['tenant_id', 'code']);
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_number_unique');
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('tenant_invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('financial_year', 7);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'financial_year']);
        });

        $startMonth = 4;
        $startDay = 1;
        $currentDate = now();
        $currentYear = ($currentDate->month < $startMonth || ($currentDate->month === $startMonth && $currentDate->day < $startDay))
            ? $currentDate->year - 1 : $currentDate->year;
        $sequenceRows = [];
        foreach (DB::table('invoices')->select('invoice_date', 'number')->get() as $invoice) {
            $date = \Illuminate\Support\Carbon::parse($invoice->invoice_date);
            $year = ($date->month < $startMonth || ($date->month === $startMonth && $date->day < $startDay)) ? $date->year - 1 : $date->year;
            $fy = sprintf('%d-%02d', $year, ($year + 1) % 100);
            preg_match('/(\d+)$/', $invoice->number, $matches);
            $sequenceRows[$fy] = max($sequenceRows[$fy] ?? 1, isset($matches[1]) ? (int) $matches[1] + 1 : 1);
        }
        $currentFy = sprintf('%d-%02d', $currentYear, ($currentYear + 1) % 100);
        $legacyNext = max(1, (int) ($setting('invoice_seq', 1)));
        $sequenceRows[$currentFy] = max($sequenceRows[$currentFy] ?? 1, $legacyNext);
        foreach ($sequenceRows as $financialYear => $nextNumber) {
            DB::table('tenant_invoice_sequences')->insert([
                'tenant_id' => $tenantId,
                'financial_year' => $financialYear,
                'next_number' => $nextNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invoice_sequences');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'number']);
            $table->unique('number');
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->unique('code');
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'key']);
            $table->unique('key');
        });
        foreach (array_reverse($this->businessTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
        Schema::dropIfExists('tenant_user');
        Schema::dropIfExists('tenants');
    }
};
