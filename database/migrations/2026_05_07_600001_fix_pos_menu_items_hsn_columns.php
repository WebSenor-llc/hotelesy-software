<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The earlier hsn_sac_code migration targeted a non-existent `menu_items`
 * table — this version applies the columns to the correct `pos_menu_items`
 * table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pos_menu_items')) return;

        Schema::table('pos_menu_items', function (Blueprint $t) {
            if (! Schema::hasColumn('pos_menu_items', 'hsn_sac_code')) {
                $t->string('hsn_sac_code', 12)->nullable()->after('description');
            }
            if (! Schema::hasColumn('pos_menu_items', 'is_alcohol')) {
                $t->boolean('is_alcohol')->default(false)->after('hsn_sac_code');
            }
            if (! Schema::hasColumn('pos_menu_items', 'is_tobacco')) {
                $t->boolean('is_tobacco')->default(false)->after('is_alcohol');
            }
        });

        // Also add a code column to pos_menu_categories so we can target
        // categories deterministically from seeders / imports.
        if (Schema::hasTable('pos_menu_categories') && ! Schema::hasColumn('pos_menu_categories', 'code')) {
            Schema::table('pos_menu_categories', function (Blueprint $t) {
                $t->string('code', 30)->nullable()->after('outlet_id');
                $t->index(['property_id', 'outlet_id', 'code'], 'pos_menu_cat_code_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pos_menu_items')) {
            Schema::table('pos_menu_items', function (Blueprint $t) {
                foreach (['hsn_sac_code','is_alcohol','is_tobacco'] as $c) {
                    if (Schema::hasColumn('pos_menu_items', $c)) $t->dropColumn($c);
                }
            });
        }
        if (Schema::hasTable('pos_menu_categories') && Schema::hasColumn('pos_menu_categories', 'code')) {
            Schema::table('pos_menu_categories', function (Blueprint $t) {
                $t->dropIndex('pos_menu_cat_code_idx');
                $t->dropColumn('code');
            });
        }
    }
};
