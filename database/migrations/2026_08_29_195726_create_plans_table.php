<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price');
            $table->char('currency', 3);
            $table->string('interval')->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->string('status')->default('draft');
            $table->json('features')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('interval');
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
