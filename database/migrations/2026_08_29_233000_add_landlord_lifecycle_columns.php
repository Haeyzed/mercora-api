<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('provisioned_at')->nullable()->after('status');
            $table->text('provision_error')->nullable()->after('provisioned_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_current')->nullable()->after('status');
            $table->unique(['tenant_id', 'is_current']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('period_starts_at')->nullable()->after('issued_at');
            $table->timestamp('period_ends_at')->nullable()->after('period_starts_at');
            $table->unique(['subscription_id', 'period_starts_at']);
        });

        DB::table('subscriptions')
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->whereNull('deleted_at')
            ->update(['is_current' => 1]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'period_starts_at']);
            $table->dropColumn(['period_starts_at', 'period_ends_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'is_current']);
            $table->dropColumn('is_current');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['provisioned_at', 'provision_error']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
