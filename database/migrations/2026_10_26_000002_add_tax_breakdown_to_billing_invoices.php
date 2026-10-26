<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_invoices', 'tax_breakdown')) {
            Schema::table('billing_invoices', function (Blueprint $table) {
                $table->json('tax_breakdown')->nullable()->after('tax_paise');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_invoices', 'tax_breakdown')) {
            Schema::table('billing_invoices', fn (Blueprint $table) => $table->dropColumn('tax_breakdown'));
        }
    }
};
