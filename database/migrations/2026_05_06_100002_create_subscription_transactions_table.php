<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * subscription_transactions — every payment / refund / mandate auth attempt
 * against a tenant's license. Drives the Super Admin Revenue dashboard.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('license_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('marketing_leads')->nullOnDelete();

            $table->enum('type', [
                'mandate_auth',     // ₹1 UPI mandate verification
                'subscription',     // recurring monthly charge
                'one_time',         // monthly/yearly upfront
                'refund', 'addon',
            ])->default('subscription');

            $table->enum('status', [
                'pending', 'authorized', 'captured', 'failed', 'refunded', 'cancelled',
            ])->default('pending');

            $table->string('billing_cycle', 30)->nullable()->comment('monthly|yearly|trial');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 5)->default('INR');
            $table->decimal('tax_amount', 12, 2)->default(0);

            // Razorpay artifacts
            $table->string('razorpay_order_id')->nullable()->index();
            $table->string('razorpay_payment_id')->nullable()->index();
            $table->string('razorpay_subscription_id')->nullable()->index();
            $table->string('razorpay_signature', 255)->nullable();
            $table->json('razorpay_payload')->nullable();

            // Card / UPI mandate
            $table->string('payment_method', 30)->nullable()->comment('card|upi|netbanking|wallet');
            $table->string('upi_vpa')->nullable();
            $table->boolean('is_mandate')->default(false);

            $table->string('invoice_number')->nullable()->unique();
            $table->string('description')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
