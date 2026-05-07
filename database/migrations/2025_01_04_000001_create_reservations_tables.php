<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            // Reservation number - human-readable, e.g. MLPU/2026/00001
            $table->string('reservation_number', 50)->unique();
            $table->string('confirmation_number', 50)->nullable()->unique();

            // Group bookings - parent reservation for multi-room group
            $table->foreignId('group_reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->boolean('is_group_master')->default(false);

            // Guest / company
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->comment('Denormalised for fast list rendering');
            $table->string('guest_phone', 20)->nullable();
            $table->string('guest_email')->nullable();

            // Source - direct, walk-in, OTA, corporate, travel agent
            $table->enum('source_type', [
                'direct',
                'walk_in',
                'phone',
                'email',
                'website',
                'ota',
                'corporate',
                'travel_agent',
                'gds',
                'group',
            ])->default('direct');
            $table->string('source_name')->nullable()->comment('e.g. Booking.com, MakeMyTrip, Goibibo');
            $table->string('ota_booking_id')->nullable()->comment('External OTA reservation ID');
            $table->string('ota_channel_code', 50)->nullable();

            $table->string('market_segment')->nullable();
            $table->string('business_source')->nullable();

            // Dates
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->unsignedSmallInteger('nights')->default(1);

            // Occupancy
            $table->unsignedSmallInteger('rooms_count')->default(1);
            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('infants')->default(0);

            // Status flow
            $table->enum('status', [
                'tentative',     // Held but not confirmed
                'confirmed',     // Confirmed booking
                'waitlist',      // No inventory, waiting
                'checked_in',    // Guest in-house
                'checked_out',   // Guest departed
                'cancelled',
                'no_show',
                'voided',
            ])->default('confirmed');
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();

            // Pricing summary (room nights only - folio holds full breakdown)
            $table->decimal('room_revenue', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('balance_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('INR');

            // Deposit / advance
            $table->decimal('advance_amount', 12, 2)->default(0);
            $table->date('advance_due_date')->nullable();
            $table->boolean('advance_received')->default(false);

            // Cancellation
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('cancellation_charge', 12, 2)->default(0);

            // Special handling
            $table->text('special_requests')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_complimentary')->default(false);
            $table->boolean('is_house_use')->default(false);

            // Billing instructions
            $table->enum('billing_to', ['guest', 'company', 'split'])->default('guest');
            $table->text('billing_instructions')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Sync / offline
            $table->string('device_id')->nullable();
            $table->unsignedInteger('sync_version')->default(1);
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'arrival_date', 'status']);
            $table->index(['property_id', 'departure_date']);
            $table->index(['property_id', 'status']);
            $table->index(['guest_id']);
            $table->index(['ota_booking_id']);
        });

        // Reservation rooms - one row per room booked.
        // A reservation can hold multiple rooms (group bookings, multi-room family).
        Schema::create('reservation_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete()->comment('Allocated physical room');

            $table->date('arrival_date');
            $table->date('departure_date');
            $table->unsignedSmallInteger('nights');

            $table->unsignedSmallInteger('adults')->default(1);
            $table->unsignedSmallInteger('children')->default(0);
            $table->unsignedSmallInteger('extra_beds')->default(0);

            $table->string('guest_name')->nullable();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('average_rate', 12, 2)->default(0);
            $table->decimal('total_rate', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->enum('status', [
                'booked',
                'allocated',
                'checked_in',
                'checked_out',
                'cancelled',
                'no_show',
            ])->default('booked');

            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('key_card_number')->nullable();
            $table->text('special_requests')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'arrival_date', 'status']);
            $table->index(['room_id', 'status']);
        });

        // Daily booking lines - one row per room per night.
        // This is what we count for inventory and what channel manager pushes against.
        Schema::create('reservation_room_nights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();

            $table->date('night_date');
            $table->decimal('rate', 12, 2);
            $table->decimal('extra_adult_charge', 12, 2)->default(0);
            $table->decimal('extra_child_charge', 12, 2)->default(0);
            $table->decimal('extra_bed_charge', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);

            $table->boolean('is_posted')->default(false)->comment('Posted to folio by night audit');
            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->unique(['reservation_room_id', 'night_date']);
            $table->index(['property_id', 'night_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_room_nights');
        Schema::dropIfExists('reservation_rooms');
        Schema::dropIfExists('reservations');
    }
};
