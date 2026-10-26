<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['gst_reports', 'custom_branding', 'api_access'] as $column) {
            DB::statement("ALTER TABLE plans MODIFY {$column} TINYINT(1) NULL DEFAULT NULL");
        }
        DB::table('plans')->whereIn('code', ['legacy', 'trial', 'starter', 'pro', 'business'])->update([
            'gst_reports' => null, 'custom_branding' => null, 'api_access' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('plans')->whereNull('gst_reports')->orWhereNull('custom_branding')->orWhereNull('api_access')
            ->update(['gst_reports' => false, 'custom_branding' => false, 'api_access' => false]);
        foreach (['gst_reports', 'custom_branding', 'api_access'] as $column) {
            DB::statement("ALTER TABLE plans MODIFY {$column} TINYINT(1) NOT NULL DEFAULT 0");
        }
    }
};
