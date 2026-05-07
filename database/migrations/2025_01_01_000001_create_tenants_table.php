<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenants table - represents a hotel group / customer of the SaaS.
 * One tenant can own multiple properties (hotels).
 * This is the SaaS billing boundary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->comment('Subdomain identifier');
            $table->string('legal_name')->nullable();
            $table->string('owner_email');
            $table->string('owner_phone')->nullable();
            $table->string('country', 2)->default('IN');
            $table->string('currency', 3)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('locale', 10)->default('en');

            // SaaS subscription
            $table->enum('plan', ['trial', 'starter', 'growth', 'enterprise'])->default('trial');
            $table->enum('status', ['active', 'trial', 'suspended', 'cancelled'])->default('trial');
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->unsignedInteger('property_limit')->default(1);
            $table->unsignedInteger('room_limit')->default(20);
            $table->unsignedInteger('user_limit')->default(5);

            // Channel manager configuration (encrypted credentials per tenant)
            $table->string('channel_manager_driver')->nullable()->comment('axisrooms|staah|siteminder|null');
            $table->json('channel_manager_config')->nullable()->comment('Encrypted driver-specific config');

            // Feature flags per tenant
            $table->json('features')->nullable();

            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
