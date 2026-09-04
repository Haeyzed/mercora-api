<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('dunning_attempts')->default(0)->after('canceled_at');
            $table->timestamp('last_dunned_at')->nullable()->after('dunning_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['dunning_attempts', 'last_dunned_at']);
        });
    }
};
