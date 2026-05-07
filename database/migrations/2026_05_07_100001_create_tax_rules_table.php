<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * tax_rules — matrix of GST/VAT rules for India hotel ops.
 *
 * The TaxEngine looks up the row whose scope + conditions match a given line
 * (room, F&B, banquet, liquor, service, etc.) and returns the CGST/SGST/IGST
 * breakdown applicable based on intra-state vs inter-state supply.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('code', 60)->comment('e.g. ROOM_GST_12, FNB_5_NO_ITC, BANQUET_18');
            $table->string('name');
            $table->enum('scope', [
                'room',          // Accommodation
                'fnb_no_itc',    // F&B with declared tariff < 7500 (5%, no ITC)
                'fnb_with_itc',  // F&B in hotel with declared tariff >= 7500 (18%, with ITC)
                'banquet',       // Hall + outdoor catering (18%)
                'service',       // Spa, laundry, parking, transport etc. (18%)
                'liquor',        // VAT only — handled separately
                'tobacco',       // 28% + cess
                'other',
            ])->default('service');

            $table->string('hsn_sac_code', 12)->nullable();
            $table->decimal('total_rate', 6, 3)->comment('Total tax % e.g. 12.000 / 18.000');
            $table->decimal('cgst_rate', 6, 3)->default(0);
            $table->decimal('sgst_rate', 6, 3)->default(0);
            $table->decimal('igst_rate', 6, 3)->default(0);
            $table->decimal('cess_rate', 6, 3)->default(0);

            // Conditions (room tariff threshold, etc.)
            $table->decimal('room_tariff_min', 12, 2)->nullable();
            $table->decimal('room_tariff_max', 12, 2)->nullable();
            $table->boolean('applies_when_hotel_has_liquor_license')->nullable();

            $table->boolean('itc_available')->default(true);
            $table->text('description')->nullable();

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(100)->comment('Lower priority wins when multiple match');

            $table->timestamps();

            $table->index(['property_id', 'scope', 'is_active']);
            $table->unique(['property_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
