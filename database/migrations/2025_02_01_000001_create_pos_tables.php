<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Outlets — restaurants, bars, room service, banquet POS, etc.
        Schema::create('pos_outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->enum('type', ['restaurant', 'bar', 'cafe', 'room_service', 'banquet', 'pool', 'spa', 'other'])->default('restaurant');
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        // Tables / sections
        Schema::create('pos_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('pos_outlets')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('section', 50)->nullable();
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning'])->default('available');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['outlet_id', 'name']);
        });

        // Menu categories (Starters, Main Course, Desserts, Beverages, Liquor)
        Schema::create('pos_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('pos_outlets')->nullOnDelete();
            $table->string('name', 100);
            $table->string('kot_printer')->nullable()->comment('Which kitchen station prints this');
            $table->boolean('is_liquor')->default(false);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Menu items
        Schema::create('pos_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('pos_menu_categories')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(5);
            $table->enum('food_type', ['veg', 'non_veg', 'egg', 'jain', 'beverage', 'liquor'])->default('veg');
            $table->boolean('is_combo')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        // Modifiers (Spice level, Extra cheese, Add-ons)
        Schema::create('pos_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('price_delta', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pos_menu_item_modifier', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained('pos_menu_items')->cascadeOnDelete();
            $table->foreignId('modifier_id')->constrained('pos_modifiers')->cascadeOnDelete();
            $table->primary(['menu_item_id', 'modifier_id']);
        });

        // Orders (KOT/BOT)
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('pos_outlets');
            $table->string('order_number', 50)->unique();
            $table->foreignId('table_id')->nullable()->constrained('pos_tables')->nullOnDelete();
            $table->enum('order_type', ['dine_in', 'room_service', 'takeaway', 'delivery', 'banquet'])->default('dine_in');

            // Room-charge support — tie order to a guest in-house
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_number', 20)->nullable();

            $table->string('guest_name')->nullable();
            $table->unsignedSmallInteger('covers')->default(1)->comment('Number of diners');
            $table->foreignId('server_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['open', 'sent_to_kitchen', 'preparing', 'ready', 'served', 'billed', 'settled', 'voided'])->default('open');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('kot_printed_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->timestamp('settled_at')->nullable();

            // Totals
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('service_charge', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('round_off', 6, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->json('tax_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'status', 'opened_at']);
            $table->index(['reservation_id']);
        });

        // Order items (KOT/BOT lines)
        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('pos_menu_items');

            $table->string('item_name');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->json('modifiers')->nullable()->comment('[{name, price_delta}]');
            $table->text('special_instructions')->nullable();

            $table->decimal('amount', 12, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->enum('status', ['pending', 'sent', 'preparing', 'ready', 'served', 'voided'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->boolean('is_voided')->default(false);
            $table->string('void_reason')->nullable();

            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_menu_item_modifier');
        Schema::dropIfExists('pos_modifiers');
        Schema::dropIfExists('pos_menu_items');
        Schema::dropIfExists('pos_menu_categories');
        Schema::dropIfExists('pos_tables');
        Schema::dropIfExists('pos_outlets');
    }
};
