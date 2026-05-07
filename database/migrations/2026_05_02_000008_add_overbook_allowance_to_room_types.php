<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            if (!Schema::hasColumn('room_types', 'allow_overbook')) {
                $table->boolean('allow_overbook')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('room_types', 'overbook_limit')) {
                $table->unsignedSmallInteger('overbook_limit')->default(0)->after('allow_overbook')
                    ->comment('Max rooms to oversell beyond inventory (e.g. 2)');
            }
        });

        // daily_rates.stop_sell already exists in the base migration (2025_01_02). Add only if missing.
        Schema::table('daily_rates', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_rates', 'stop_sell')) {
                $table->boolean('stop_sell')->default(false);
            }
            if (!Schema::hasColumn('daily_rates', 'is_sold_out')) {
                $table->boolean('is_sold_out')->default(false)->after('stop_sell')
                    ->comment('Manually marked sold-out (separate from auto-calculated availability)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            if (Schema::hasColumn('room_types', 'overbook_limit')) {
                $table->dropColumn('overbook_limit');
            }
            if (Schema::hasColumn('room_types', 'allow_overbook')) {
                $table->dropColumn('allow_overbook');
            }
        });
        Schema::table('daily_rates', function (Blueprint $table) {
            if (Schema::hasColumn('daily_rates', 'is_sold_out')) {
                $table->dropColumn('is_sold_out');
            }
        });
    }
};
