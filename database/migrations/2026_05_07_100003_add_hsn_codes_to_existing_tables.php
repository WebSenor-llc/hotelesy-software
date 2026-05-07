<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add HSN/SAC codes + tax-relevant flags to room types, menu items,
 * banquet packages, amenities, and properties.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('room_types') && !Schema::hasColumn('room_types', 'hsn_sac_code')) {
            Schema::table('room_types', function (Blueprint $table) {
                $table->string('hsn_sac_code', 12)->nullable()->default('996311')->after('base_rate');
            });
        }

        if (Schema::hasTable('menu_items') && !Schema::hasColumn('menu_items', 'hsn_sac_code')) {
            Schema::table('menu_items', function (Blueprint $table) {
                $table->string('hsn_sac_code', 12)->nullable()->after('description');
                $table->boolean('is_alcohol')->default(false)->after('hsn_sac_code')
                    ->comment('Alcohol = VAT not GST; flagged separately');
                $table->boolean('is_tobacco')->default(false)->after('is_alcohol');
            });
        }

        if (Schema::hasTable('banquet_packages') && !Schema::hasColumn('banquet_packages', 'hsn_sac_code')) {
            Schema::table('banquet_packages', function (Blueprint $table) {
                $table->string('hsn_sac_code', 12)->nullable()->default('999692')->after('description');
            });
        }

        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $table) {
                if (!Schema::hasColumn('properties', 'state_code')) {
                    $table->string('state_code', 4)->nullable()->after('state')
                        ->comment('2-digit GST state code (e.g. 27 for Maharashtra, 08 for Rajasthan)');
                }
                if (!Schema::hasColumn('properties', 'pan_number')) {
                    $table->string('pan_number', 12)->nullable()->after('gst_number');
                }
                if (!Schema::hasColumn('properties', 'declared_max_room_tariff')) {
                    $table->decimal('declared_max_room_tariff', 12, 2)->nullable()->after('pan_number')
                        ->comment('Drives F&B GST: <7500 = 5% no-ITC, ≥7500 = 18% with-ITC');
                }
                if (!Schema::hasColumn('properties', 'has_liquor_license')) {
                    $table->boolean('has_liquor_license')->default(false)->after('declared_max_room_tariff');
                }
                if (!Schema::hasColumn('properties', 'is_e_invoice_required')) {
                    $table->boolean('is_e_invoice_required')->default(false)->after('has_liquor_license')
                        ->comment('True if turnover > ₹5cr (auto-flag for IRN generation)');
                }
                if (!Schema::hasColumn('properties', 'invoice_prefix')) {
                    $table->string('invoice_prefix', 10)->nullable()->after('is_e_invoice_required');
                }
                if (!Schema::hasColumn('properties', 'invoice_signature_path')) {
                    $table->string('invoice_signature_path')->nullable()->after('invoice_prefix');
                }
            });
        }

        // Sequence counter per property per FY
        if (! Schema::hasTable('invoice_sequences')) {
            Schema::create('invoice_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->string('financial_year', 9);
                $table->string('document_type', 30)->default('tax_invoice');
                $table->unsignedInteger('next_number')->default(1);
                $table->timestamps();
                $table->unique(['property_id', 'financial_year', 'document_type'], 'inv_seq_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
        if (Schema::hasTable('room_types') && Schema::hasColumn('room_types', 'hsn_sac_code')) {
            Schema::table('room_types', fn(Blueprint $t) => $t->dropColumn('hsn_sac_code'));
        }
        if (Schema::hasTable('menu_items') && Schema::hasColumn('menu_items', 'hsn_sac_code')) {
            Schema::table('menu_items', fn(Blueprint $t) => $t->dropColumn(['hsn_sac_code','is_alcohol','is_tobacco']));
        }
        if (Schema::hasTable('banquet_packages') && Schema::hasColumn('banquet_packages', 'hsn_sac_code')) {
            Schema::table('banquet_packages', fn(Blueprint $t) => $t->dropColumn('hsn_sac_code'));
        }
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $t) {
                foreach (['state_code','pan_number','declared_max_room_tariff','has_liquor_license','is_e_invoice_required','invoice_prefix','invoice_signature_path'] as $c) {
                    if (Schema::hasColumn('properties', $c)) $t->dropColumn($c);
                }
            });
        }
    }
};
