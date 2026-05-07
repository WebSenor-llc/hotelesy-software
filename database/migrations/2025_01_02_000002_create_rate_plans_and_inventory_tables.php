<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();

            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();

            // Meal plan: EP=European (room only), CP=Continental (room+breakfast),
            // MAP=Modified American (room+breakfast+1 meal), AP=American (all meals)
            $table->enum('meal_plan', ['EP', 'CP', 'MAP', 'AP'])->default('EP');

            // Pricing - either fixed or derived from base
            $table->enum('pricing_mode', ['fixed', 'percentage_of_base', 'amount_off_base', 'amount_added'])->default('fixed');
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('rate_modifier', 12, 2)->default(0)->comment('Used with pricing_mode');

            // Restrictions
            $table->unsignedSmallInteger('min_stay')->default(1);
            $table->unsignedSmallInteger('max_stay')->default(0)->comment('0 = no max');
            $table->unsignedSmallInteger('advance_booking_days')->default(0);
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);

            // Cancellation policy
            $table->boolean('refundable')->default(true);
            $table->unsignedSmallInteger('cancellation_hours')->default(24);
            $table->decimal('cancellation_charge_percent', 5, 2)->default(0);

            $table->boolean('is_corporate')->default(false);
            $table->boolean('is_promotional')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('sell_on_channels')->default(true);
            $table->json('channel_mappings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'room_type_id', 'is_active']);
        });

        // Daily rates - the actual selling rate for a date / room type / rate plan combination.
        // Channel manager pushes these out; revenue manager updates them.
        Schema::create('daily_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->decimal('rate', 12, 2);
            $table->decimal('extra_adult_rate', 12, 2)->default(0);
            $table->decimal('extra_child_rate', 12, 2)->default(0);

            $table->unsignedSmallInteger('min_stay')->default(1);
            $table->boolean('closed_to_arrival')->default(false);
            $table->boolean('closed_to_departure')->default(false);
            $table->boolean('stop_sell')->default(false);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_pushed_to_channels_at')->nullable();
            $table->timestamps();

            $table->unique(['rate_plan_id', 'room_type_id', 'date'], 'daily_rates_unique');
            $table->index(['property_id', 'date']);
        });

        // Daily inventory - allocated rooms per type per date.
        // This is the canonical inventory the channel manager pushes.
        Schema::create('daily_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();

            $table->date('date');
            $table->unsignedSmallInteger('total_rooms')->default(0);
            $table->unsignedSmallInteger('rooms_sold')->default(0);
            $table->unsignedSmallInteger('rooms_blocked')->default(0)->comment('Out of order / maintenance');
            $table->unsignedSmallInteger('rooms_held')->default(0)->comment('Tentative / waitlist holds');
            $table->unsignedSmallInteger('overbooking_allowed')->default(0);
            $table->boolean('stop_sell')->default(false);

            $table->timestamp('last_pushed_to_channels_at')->nullable();
            $table->timestamps();

            $table->unique(['room_type_id', 'date']);
            $table->index(['property_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_inventory');
        Schema::dropIfExists('daily_rates');
        Schema::dropIfExists('rate_plans');
    }
};
