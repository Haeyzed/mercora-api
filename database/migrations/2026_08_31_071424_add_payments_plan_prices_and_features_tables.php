<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->unsignedInteger('amount');
            $table->string('interval');
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['plan_id', 'currency', 'interval', 'interval_count']);
            $table->index(['plan_id', 'is_active']);
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
            $table->json('value');
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('provider');
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->unsignedInteger('amount');
            $table->char('currency', 3);
            $table->string('status');
            $table->string('payment_method')->nullable();
            $table->string('checkout_url')->nullable();
            $table->json('metadata')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['provider', 'provider_reference']);
            $table->index('status');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_price_id')->nullable()->after('plan_id')->constrained('plan_prices')->nullOnDelete();
            $table->string('plan_name')->nullable()->after('plan_price_id');
            $table->unsignedSmallInteger('interval_count')->default(1)->after('interval');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('subscription_id')->constrained('payments')->nullOnDelete();
        });

        $this->migrateLegacyPlanPrices();
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_price_id');
            $table->dropColumn(['plan_name', 'interval_count']);
        });

        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plan_prices');
    }

    private function migrateLegacyPlanPrices(): void
    {
        $now = now();

        foreach (DB::table('plans')->orderBy('id')->get() as $plan) {
            $priceId = DB::table('plan_prices')->insertGetId([
                'plan_id' => $plan->id,
                'currency' => $plan->currency,
                'amount' => $plan->price,
                'interval' => $plan->interval,
                'interval_count' => 1,
                'trial_days' => $plan->trial_days,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('subscriptions')
                ->where('plan_id', $plan->id)
                ->whereNull('plan_price_id')
                ->update([
                    'plan_price_id' => $priceId,
                    'plan_name' => $plan->name,
                    'interval_count' => 1,
                ]);
        }
    }
};
