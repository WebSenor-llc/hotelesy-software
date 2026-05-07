<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kitchen stations — physical kitchen sections (Tandoor, Cold, Bar, Garde Manger, Pickup)
        Schema::create('kds_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('pos_outlets')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('type', ['hot_kitchen', 'cold_kitchen', 'bar', 'tandoor', 'pizza', 'grill', 'pickup_window', 'expo', 'other'])->default('hot_kitchen');
            $table->string('printer_ip')->nullable()->comment('For physical KOT printer routing');
            $table->unsignedSmallInteger('default_prep_minutes')->default(15);
            $table->json('display_config')->nullable()->comment('Color theme, font size, screen orientation');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        // Map menu categories to stations (Tandoor cooks all Tandoor items, etc.)
        Schema::create('kds_station_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained('kds_stations')->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained('pos_menu_categories')->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->timestamps();
            $table->unique(['station_id', 'menu_category_id']);
        });

        // KDS tickets — one per (order × station). An order with hot food + drinks creates 2 tickets.
        Schema::create('kds_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('kds_stations');
            $table->string('ticket_number', 30)->comment('Display number on KDS screen');
            $table->enum('status', ['queued', 'started', 'ready', 'served', 'recalled', 'voided'])->default('queued');

            // Timing — these power the KDS color escalation (green→yellow→red as ticket ages)
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->unsignedSmallInteger('target_prep_seconds')->nullable();
            $table->unsignedInteger('actual_prep_seconds')->nullable();
            $table->boolean('is_overdue')->default(false);

            $table->enum('priority', ['low', 'normal', 'high', 'rush'])->default('normal');
            $table->boolean('is_recall')->default(false)->comment('Server flagged item as wrong/cold');
            $table->text('special_instructions')->nullable();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete()->comment('Cook who started this ticket');

            $table->timestamps();
            $table->index(['station_id', 'status', 'queued_at']);
            $table->index(['order_id']);
        });

        // KDS ticket items — line items per ticket (one order item may span tickets if it has parts at different stations)
        Schema::create('kds_ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('kds_tickets')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('pos_order_items');
            $table->string('item_name');
            $table->decimal('quantity', 8, 2);
            $table->json('modifiers')->nullable();
            $table->text('special_instructions')->nullable();
            $table->enum('status', ['queued', 'started', 'ready', 'served', 'voided'])->default('queued');
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
        });

        // KDS event log — every state transition logged for performance analytics
        Schema::create('kds_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('kds_tickets')->cascadeOnDelete();
            $table->string('event_type', 30)->comment('queued, started, ready, served, recalled, voided, station_changed, priority_changed');
            $table->json('event_data')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['ticket_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kds_events');
        Schema::dropIfExists('kds_ticket_items');
        Schema::dropIfExists('kds_tickets');
        Schema::dropIfExists('kds_station_routes');
        Schema::dropIfExists('kds_stations');
    }
};
