<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('monthly_price_paise')->nullable();
            $table->unsignedBigInteger('yearly_price_paise')->nullable();
            $table->unsignedInteger('monthly_invoice_limit')->nullable();
            $table->unsignedInteger('member_limit')->nullable();
            $table->boolean('gst_reports')->nullable()->default(null);
            $table->boolean('custom_branding')->nullable()->default(null);
            $table->boolean('api_access')->nullable()->default(null);
            $table->string('razorpay_plan_monthly_id')->nullable();
            $table->string('razorpay_plan_yearly_id')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->string('billing_interval', 12)->nullable();
            $table->string('razorpay_subscription_id')->nullable()->unique();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancel_at_period_end')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('billing_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_event_id')->unique();
            $table->string('event_type', 100);
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('tenant_subscriptions')->nullOnDelete();
            $table->string('provider_payment_id')->unique();
            $table->unsignedBigInteger('amount_paise');
            $table->char('currency', 3)->default('INR');
            $table->string('status', 30);
            $table->string('method', 50)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('billing_payments')->nullOnDelete();
            $table->string('number')->unique();
            $table->unsignedBigInteger('subtotal_paise');
            $table->unsignedBigInteger('tax_paise')->default(0);
            $table->json('tax_breakdown')->nullable();
            $table->unsignedBigInteger('total_paise');
            $table->char('currency', 3)->default('INR');
            $table->json('seller_snapshot');
            $table->json('buyer_snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->index(['tenant_id', 'issued_at']);
        });

        DB::table('plans')->insert([
            'code' => 'legacy', 'name' => 'Existing workspace', 'description' => 'Existing workspace plan; no limits applied.',
            'is_active' => false, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('plans')->insert([
            'code' => 'trial', 'name' => 'Free trial', 'description' => '14-day trial. Limits remain configurable.',
            'is_active' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([['starter', 'Starter', 2], ['pro', 'Pro', 3], ['business', 'Business', 4]] as [$code, $name, $sort]) {
            DB::table('plans')->insert([
                'code' => $code, 'name' => $name, 'description' => null,
                'is_active' => false, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $legacyPlan = DB::table('plans')->where('code', 'legacy')->value('id');
        foreach (DB::table('tenants')->orderBy('id')->pluck('id') as $tenantId) {
            DB::table('tenant_subscriptions')->insert([
                'tenant_id' => $tenantId, 'plan_id' => $legacyPlan, 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_events');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('plans');
    }
};
