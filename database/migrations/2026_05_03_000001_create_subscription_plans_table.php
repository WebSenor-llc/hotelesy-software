<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription plans — the SaaS pricing catalog.
 * One plan can be assigned to many tenants via the `licenses` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('trial, starter, growth, enterprise');
            $table->string('name');
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->default(0);
            $table->string('billing_currency', 3)->default('INR');
            $table->unsignedSmallInteger('max_properties')->default(1);
            $table->unsignedSmallInteger('max_rooms')->default(50);
            $table->unsignedSmallInteger('max_users')->default(5);
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
