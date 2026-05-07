<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds early-check-in / late-check-out / no-show policy fields to properties.
 * Adds tracking fields to reservations so the EciLcoService can audit
 * what was charged and when.
 */
return new class extends Migration {
    public function up(): void
    {
        // Property-level policy
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $t) {
                if (!Schema::hasColumn('properties', 'eci_grace_hours')) {
                    $t->unsignedTinyInteger('eci_grace_hours')->default(2)
                        ->after('check_out_time')
                        ->comment('Free early check-in hours before standard check-in time');
                }
                if (!Schema::hasColumn('properties', 'eci_half_day_threshold_hours')) {
                    $t->unsignedTinyInteger('eci_half_day_threshold_hours')->default(6)
                        ->after('eci_grace_hours')
                        ->comment('After grace, charge half-day if checked in this many hours early or less');
                }
                if (!Schema::hasColumn('properties', 'eci_half_day_pct')) {
                    $t->unsignedTinyInteger('eci_half_day_pct')->default(50)
                        ->after('eci_half_day_threshold_hours')
                        ->comment('Half-day charge as % of nightly rate');
                }
                if (!Schema::hasColumn('properties', 'eci_full_day_pct')) {
                    $t->unsignedTinyInteger('eci_full_day_pct')->default(100)
                        ->after('eci_half_day_pct')
                        ->comment('Full-day charge as % of nightly rate (beyond half-day threshold)');
                }
                if (!Schema::hasColumn('properties', 'lco_grace_hours')) {
                    $t->unsignedTinyInteger('lco_grace_hours')->default(2)
                        ->after('eci_full_day_pct');
                }
                if (!Schema::hasColumn('properties', 'lco_half_day_threshold_hours')) {
                    $t->unsignedTinyInteger('lco_half_day_threshold_hours')->default(6)
                        ->after('lco_grace_hours');
                }
                if (!Schema::hasColumn('properties', 'lco_half_day_pct')) {
                    $t->unsignedTinyInteger('lco_half_day_pct')->default(50)
                        ->after('lco_half_day_threshold_hours');
                }
                if (!Schema::hasColumn('properties', 'lco_full_day_pct')) {
                    $t->unsignedTinyInteger('lco_full_day_pct')->default(100)
                        ->after('lco_half_day_pct');
                }
                if (!Schema::hasColumn('properties', 'noshow_fee_pct_first_night')) {
                    $t->unsignedTinyInteger('noshow_fee_pct_first_night')->default(100)
                        ->after('lco_full_day_pct')
                        ->comment('No-show charge as % of first night (0–100)');
                }
                if (!Schema::hasColumn('properties', 'noshow_grace_hours_after_arrival')) {
                    $t->unsignedTinyInteger('noshow_grace_hours_after_arrival')->default(6)
                        ->after('noshow_fee_pct_first_night')
                        ->comment('Hours past arrival_date+check_in_time before auto-marking no_show');
                }
                if (!Schema::hasColumn('properties', 'auto_mark_no_show')) {
                    $t->boolean('auto_mark_no_show')->default(true)
                        ->after('noshow_grace_hours_after_arrival');
                }
            });
        }

        // Reservation-level audit so we never double-charge
        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $t) {
                if (!Schema::hasColumn('reservations', 'eci_charge_amount')) {
                    $t->decimal('eci_charge_amount', 12, 2)->default(0)->after('total_amount');
                }
                if (!Schema::hasColumn('reservations', 'eci_charge_kind')) {
                    $t->string('eci_charge_kind', 20)->nullable()->after('eci_charge_amount')
                        ->comment('none|grace|half_day|full_day');
                }
                if (!Schema::hasColumn('reservations', 'lco_charge_amount')) {
                    $t->decimal('lco_charge_amount', 12, 2)->default(0)->after('eci_charge_kind');
                }
                if (!Schema::hasColumn('reservations', 'lco_charge_kind')) {
                    $t->string('lco_charge_kind', 20)->nullable()->after('lco_charge_amount');
                }
                if (!Schema::hasColumn('reservations', 'no_show_fee_amount')) {
                    $t->decimal('no_show_fee_amount', 12, 2)->default(0)->after('lco_charge_kind');
                }
                if (!Schema::hasColumn('reservations', 'no_show_marked_at')) {
                    $t->timestamp('no_show_marked_at')->nullable()->after('no_show_fee_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('properties')) {
            Schema::table('properties', function (Blueprint $t) {
                foreach ([
                    'eci_grace_hours','eci_half_day_threshold_hours','eci_half_day_pct','eci_full_day_pct',
                    'lco_grace_hours','lco_half_day_threshold_hours','lco_half_day_pct','lco_full_day_pct',
                    'noshow_fee_pct_first_night','noshow_grace_hours_after_arrival','auto_mark_no_show',
                ] as $c) if (Schema::hasColumn('properties', $c)) $t->dropColumn($c);
            });
        }
        if (Schema::hasTable('reservations')) {
            Schema::table('reservations', function (Blueprint $t) {
                foreach (['eci_charge_amount','eci_charge_kind','lco_charge_amount','lco_charge_kind','no_show_fee_amount','no_show_marked_at'] as $c) {
                    if (Schema::hasColumn('reservations', $c)) $t->dropColumn($c);
                }
            });
        }
    }
};
