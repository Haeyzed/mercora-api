<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex(['interval']);
            $table->dropIndex(['currency']);
            $table->dropColumn(['price', 'currency', 'interval', 'trial_days']);
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('price')->after('description');
            $table->char('currency', 3)->after('price');
            $table->string('interval')->default('monthly')->after('currency');
            $table->unsignedSmallInteger('trial_days')->default(0)->after('interval');

            $table->index('interval');
            $table->index('currency');
        });
    }
};
