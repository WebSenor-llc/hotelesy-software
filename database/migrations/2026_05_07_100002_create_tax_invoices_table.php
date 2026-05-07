<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax_invoices + tax_invoice_lines — government-compliant tax invoice
 * (Rule 46 of CGST Rules, 2017). One invoice can be linked to a Folio,
 * POS Order, or Banquet Booking via polymorphic source_type / source_id.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            // Polymorphic link
            $table->string('source_type', 80)->comment('App\\Models\\Folio | Pos\\Order | Banquet\\BanquetBooking');
            $table->unsignedBigInteger('source_id');

            // Invoice identity
            $table->string('invoice_number', 50)->unique();
            $table->string('financial_year', 9)->comment('e.g. 2026-27');
            $table->enum('document_type', ['tax_invoice', 'bill_of_supply', 'credit_note', 'debit_note', 'proforma'])
                ->default('tax_invoice');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            // Supplier (snapshot from property at issue time)
            $table->string('supplier_name');
            $table->string('supplier_legal_name')->nullable();
            $table->text('supplier_address');
            $table->string('supplier_state', 80);
            $table->string('supplier_state_code', 4)->comment('2-digit GST state code');
            $table->string('supplier_gstin', 20)->nullable();
            $table->string('supplier_pan', 12)->nullable();
            $table->string('supplier_email')->nullable();
            $table->string('supplier_phone', 30)->nullable();

            // Recipient (guest or company)
            $table->string('recipient_name');
            $table->text('recipient_address')->nullable();
            $table->string('recipient_state', 80)->nullable();
            $table->string('recipient_state_code', 4)->nullable();
            $table->string('recipient_gstin', 20)->nullable();
            $table->string('recipient_pan', 12)->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 30)->nullable();

            // Place of supply (drives intra-state vs inter-state)
            $table->string('place_of_supply', 80);
            $table->string('place_of_supply_code', 4);
            $table->boolean('is_inter_state')->default(false);
            $table->boolean('is_reverse_charge')->default(false);
            $table->boolean('is_export')->default(false);
            $table->boolean('is_sez')->default(false);
            $table->string('currency', 5)->default('INR');
            $table->decimal('exchange_rate', 12, 4)->default(1);

            // Totals (computed by InvoiceBuilder)
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('cgst_total', 14, 2)->default(0);
            $table->decimal('sgst_total', 14, 2)->default(0);
            $table->decimal('igst_total', 14, 2)->default(0);
            $table->decimal('cess_total', 14, 2)->default(0);
            $table->decimal('tcs_amount', 14, 2)->default(0)->comment('TCS u/s 206C if applicable');
            $table->decimal('round_off', 6, 2)->default(0);
            $table->decimal('grand_total', 14, 2);
            $table->string('amount_in_words', 500);

            // Payment summary
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);

            // E-invoice fields (IRN / QR for B2B if turnover > ₹5cr)
            $table->boolean('e_invoice_required')->default(false);
            $table->string('irn', 80)->nullable()->index();
            $table->text('qr_code_payload')->nullable();
            $table->timestamp('irn_generated_at')->nullable();
            $table->string('e_invoice_status', 30)->nullable()->comment('pending|generated|cancelled|failed');
            $table->text('e_invoice_error')->nullable();

            // Status & lifecycle
            $table->enum('status', ['draft', 'issued', 'cancelled', 'amended'])->default('draft');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            // Outputs
            $table->string('pdf_path')->nullable();
            $table->json('signed_qr')->nullable()->comment('Digitally signed QR payload from IRP for e-invoice');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'invoice_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'invoice_date']);
        });

        Schema::create('tax_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');

            $table->string('description', 500);
            $table->string('hsn_sac_code', 12)->nullable();
            $table->string('item_type', 30)->nullable()->comment('room|fnb|banquet|service|liquor|other');

            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 20)->default('NOS');

            $table->decimal('rate', 14, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('discount_pct', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);

            $table->decimal('cgst_rate', 6, 3)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_rate', 6, 3)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_rate', 6, 3)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('cess_rate', 6, 3)->default(0);
            $table->decimal('cess_amount', 14, 2)->default(0);

            $table->decimal('total_amount', 14, 2)->default(0);

            $table->foreignId('tax_rule_id')->nullable()->constrained('tax_rules')->nullOnDelete();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->index(['tax_invoice_id', 'line_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_invoice_lines');
        Schema::dropIfExists('tax_invoices');
    }
};
