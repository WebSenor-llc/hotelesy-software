<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pos_orders gains payment-tracking columns so we can record:
 *   - WHEN payment was/will be taken (on_bill | prepaid | room_charge)
 *   - HOW it was paid (mode + reference + paid_at)
 *   - Customer contact for takeaway / delivery
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pos_orders')) return;

        Schema::table('pos_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('pos_orders', 'payment_timing')) {
                $table->enum('payment_timing', ['on_bill', 'prepaid', 'room_charge'])
                    ->default('on_bill')
                    ->after('total_amount')
                    ->comment('When payment is collected: on_bill | prepaid | room_charge');
            }
            if (! Schema::hasColumn('pos_orders', 'payment_mode')) {
                $table->string('payment_mode', 30)->nullable()->after('payment_timing')
                    ->comment('cash | card | upi | wallet | bank_transfer | room_charge');
            }
            if (! Schema::hasColumn('pos_orders', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable()->after('payment_mode');
            }
            if (! Schema::hasColumn('pos_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_reference');
            }
            if (! Schema::hasColumn('pos_orders', 'guest_phone')) {
                $table->string('guest_phone', 30)->nullable()->after('guest_name');
            }
            if (! Schema::hasColumn('pos_orders', 'delivery_address')) {
                $table->string('delivery_address', 500)->nullable()->after('guest_phone');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pos_orders')) return;
        Schema::table('pos_orders', function (Blueprint $table) {
            foreach (['payment_timing','payment_mode','payment_reference','paid_at','guest_phone','delivery_address'] as $c) {
                if (Schema::hasColumn('pos_orders', $c)) $table->dropColumn($c);
            }
        });
    }
};
