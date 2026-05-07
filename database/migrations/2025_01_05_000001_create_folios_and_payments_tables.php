<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tax masters - GST slabs, service charge, etc.
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('code', 20);
            $table->string('name');
            $table->enum('type', ['gst', 'cgst', 'sgst', 'igst', 'service_charge', 'luxury_tax', 'other'])->default('gst');
            $table->decimal('rate', 6, 3)->default(0)->comment('e.g. 12.000 for 12%');
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_compoundable')->default(false);

            // GST slab logic for hotels:
            // ≤ ₹1000: 0%, ₹1001-7500: 12%, > ₹7500: 18%
            $table->decimal('threshold_min', 12, 2)->nullable();
            $table->decimal('threshold_max', 12, 2)->nullable();

            $table->boolean('applies_to_room')->default(true);
            $table->boolean('applies_to_food')->default(false);
            $table->boolean('applies_to_other')->default(false);

            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->timestamps();
            $table->index(['property_id', 'is_active']);
        });

        // Folios - billing containers per reservation/guest/company.
        // A reservation can have multiple folios (folio split scenario).
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->nullable()->constrained()->nullOnDelete();

            $table->string('folio_number', 50)->unique();
            $table->enum('type', ['guest', 'company', 'master', 'pos', 'banquet'])->default('guest');

            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('billing_name');
            $table->text('billing_address')->nullable();
            $table->string('billing_gst', 20)->nullable();

            // Totals (always recomputed from folio_charges + payments)
            $table->decimal('total_charges', 14, 2)->default(0);
            $table->decimal('total_taxes', 14, 2)->default(0);
            $table->decimal('total_discounts', 14, 2)->default(0);
            $table->decimal('total_payments', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->string('currency', 3)->default('INR');

            $table->enum('status', ['open', 'closed', 'settled', 'transferred', 'voided'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // Invoice generation
            $table->string('invoice_number', 50)->nullable()->unique();
            $table->timestamp('invoice_generated_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status']);
            $table->index(['reservation_id']);
        });

        // Folio charges - every line item: room rent, F&B, laundry, mini-bar, etc.
        Schema::create('folio_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();

            $table->date('charge_date');
            $table->time('charge_time')->nullable();
            $table->date('business_date')->comment('Date locked by night audit');

            $table->enum('category', [
                'room',
                'food',
                'beverage',
                'laundry',
                'mini_bar',
                'spa',
                'telephone',
                'misc',
                'damage',
                'extra_bed',
                'package',
                'discount',
                'tax',
                'service_charge',
                'transfer',
                'adjustment',
            ]);
            $table->string('description');
            $table->string('reference', 100)->nullable()->comment('POS bill #, KOT #, etc.');

            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 14, 2);

            $table->json('tax_breakdown')->nullable()->comment('CGST, SGST split for GST');

            $table->boolean('is_voided')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();

            $table->timestamps();

            $table->index(['folio_id', 'business_date']);
            $table->index(['property_id', 'business_date', 'category']);
        });

        // Payments
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('receipt_number', 50)->unique();
            $table->date('payment_date');
            $table->date('business_date');

            $table->enum('mode', [
                'cash',
                'card',
                'upi',
                'bank_transfer',
                'cheque',
                'wallet',
                'gift_voucher',
                'company_credit',
                'ota_collect',
                'advance_adjustment',
                'refund',
            ]);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('INR');

            // Card specifics (no PAN stored - PCI)
            $table->string('card_last4', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('approval_code')->nullable();

            // UPI / digital
            $table->string('upi_reference')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('gateway_payment_id')->nullable();

            // Cheque
            $table->string('cheque_number', 30)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name')->nullable();

            // City ledger
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'voided'])->default('completed');
            $table->text('notes')->nullable();

            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'business_date']);
            $table->index(['folio_id']);
            $table->index(['reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('folio_charges');
        Schema::dropIfExists('folios');
        Schema::dropIfExists('taxes');
    }
};
