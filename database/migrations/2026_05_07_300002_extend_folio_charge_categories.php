<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend the folio_charges.category enum to include the new
 * lifecycle-fee categories the EciLcoService posts.
 *
 * MySQL ENUMs can only be altered via raw DDL.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('folio_charges')) return;

        DB::statement("
            ALTER TABLE folio_charges
            MODIFY COLUMN category ENUM(
                'room',
                'food',
                'beverage',
                'laundry',
                'mini_bar',
                'spa',
                'telephone',
                'misc',
                'damage',
                'tax',
                'service_charge',
                'discount',
                'transport',
                'extra_bed',
                'package',
                'amenity',
                'banquet',
                'banquet_food',
                'banquet_beverage',
                'banquet_decor',
                'banquet_av',
                'early_check_in',
                'late_check_out',
                'no_show_fee',
                'cancellation_fee',
                'tcs',
                'tds',
                'refund',
                'other'
            ) NOT NULL DEFAULT 'misc'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('folio_charges')) return;

        // Revert any new categories to 'misc' before shrinking the enum
        DB::table('folio_charges')
            ->whereIn('category', [
                'early_check_in','late_check_out','no_show_fee','cancellation_fee',
                'transport','extra_bed','package','amenity',
                'banquet','banquet_food','banquet_beverage','banquet_decor','banquet_av',
                'tcs','tds','refund',
            ])
            ->update(['category' => 'misc']);

        DB::statement("
            ALTER TABLE folio_charges
            MODIFY COLUMN category ENUM(
                'room','food','beverage','laundry','mini_bar','spa',
                'telephone','misc','damage','tax','service_charge','discount','other'
            ) NOT NULL DEFAULT 'misc'
        ");
    }
};
