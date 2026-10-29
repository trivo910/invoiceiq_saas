<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->softDeletes());
        Schema::create('tenant_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'created_at']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('tenant_audit_logs');
        Schema::table('tenants', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
