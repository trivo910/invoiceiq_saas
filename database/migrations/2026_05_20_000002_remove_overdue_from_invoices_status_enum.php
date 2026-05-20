<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'overdue' is computed dynamically; any row accidentally stored with it reverts to 'sent'
        DB::table('invoices')->where('status', 'overdue')->update(['status' => 'sent']);

        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','sent','paid','partial','cancelled') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','sent','paid','partial','overdue','cancelled') NOT NULL DEFAULT 'draft'");
    }
};
