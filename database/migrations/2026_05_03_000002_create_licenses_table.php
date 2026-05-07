<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licenses — one active license per tenant.
 * Tracks expiry, billing cycle, status. Plain license_key is shown once on issue;
 * thereafter only license_key_hash (sha256) is stored for validation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans');
            $table->string('license_key', 64)->unique();
            $table->string('license_key_hash', 64)->index();
            $table->enum('status', ['trial', 'active', 'past_due', 'suspended', 'expired', 'cancelled'])->default('trial');
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->dateTime('last_validated_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('issued_at');
            $table->dateTime('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
