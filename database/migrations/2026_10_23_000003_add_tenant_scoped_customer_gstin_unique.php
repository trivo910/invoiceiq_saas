<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('customers')
            ->select('tenant_id', 'gstin')
            ->whereNotNull('gstin')
            ->where('gstin', '!=', '')
            ->groupBy('tenant_id', 'gstin')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new \RuntimeException('Cannot add tenant GSTIN uniqueness: duplicate non-empty customer GSTIN values must be reviewed and resolved first.');
        }

        DB::table('customers')->where('gstin', '')->update(['gstin' => null]);

        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['tenant_id', 'gstin']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'gstin']);
        });
    }
};
