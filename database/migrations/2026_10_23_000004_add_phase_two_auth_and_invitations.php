<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable()->after('trial_ends_at');
        });
        DB::table('tenants')->update(['onboarding_completed_at' => now()]);
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 30);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('onboarding_completed_at');
        });
    }
};
