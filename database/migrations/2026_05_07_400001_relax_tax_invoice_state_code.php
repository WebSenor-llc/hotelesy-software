<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Relax NOT NULL on tax_invoices.supplier_state_code, place_of_supply_code,
 * and place_of_supply. Done with raw DDL so we don't need doctrine/dbal.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tax_invoices')) return;

        DB::statement("ALTER TABLE tax_invoices MODIFY supplier_state_code VARCHAR(4) NULL");
        DB::statement("ALTER TABLE tax_invoices MODIFY place_of_supply_code VARCHAR(4) NULL");
        DB::statement("ALTER TABLE tax_invoices MODIFY place_of_supply VARCHAR(80) NULL");
    }

    public function down(): void
    {
        // No-op intentionally — re-adding NOT NULL would crash on rows already nulled.
    }
};
