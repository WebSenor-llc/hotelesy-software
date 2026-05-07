<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Banquet halls
        Schema::create('banquet_halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('area_sqft')->nullable();
            $table->unsignedInteger('theatre_capacity')->nullable();
            $table->unsignedInteger('classroom_capacity')->nullable();
            $table->unsignedInteger('cluster_capacity')->nullable();
            $table->unsignedInteger('banquet_capacity')->nullable();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('half_day_rate', 10, 2)->default(0);
            $table->decimal('full_day_rate', 10, 2)->default(0);
            $table->json('amenities')->nullable()->comment('AV, projector, stage, dance floor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['property_id', 'code']);
        });

        // Banquet event packages (Wedding, Conference, Birthday)
        Schema::create('banquet_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('per_pax_rate', 10, 2);
            $table->decimal('min_pax', 8, 0)->default(50);
            $table->json('inclusions')->nullable()->comment('Menu items, decor, sound');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Banquet bookings
        Schema::create('banquet_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained('banquet_halls');
            $table->string('booking_number', 50)->unique();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name');
            $table->enum('event_type', [
                'wedding', 'reception', 'conference', 'meeting', 'birthday',
                'anniversary', 'corporate', 'product_launch', 'training', 'other',
            ])->default('other');
            $table->date('event_date');
            $table->time('event_start_time');
            $table->time('event_end_time');
            $table->unsignedSmallInteger('expected_pax');
            $table->unsignedSmallInteger('actual_pax')->nullable();
            $table->foreignId('package_id')->nullable()->constrained('banquet_packages')->nullOnDelete();
            $table->decimal('hall_rent', 12, 2)->default(0);
            $table->decimal('food_amount', 12, 2)->default(0);
            $table->decimal('beverage_amount', 12, 2)->default(0);
            $table->decimal('decor_amount', 12, 2)->default(0);
            $table->decimal('av_amount', 12, 2)->default(0);
            $table->decimal('other_amount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('advance_received', 12, 2)->default(0);
            $table->enum('status', ['enquiry', 'tentative', 'confirmed', 'completed', 'cancelled'])->default('enquiry');
            $table->text('menu_details')->nullable();
            $table->text('setup_notes')->nullable();
            $table->text('special_requests')->nullable();
            $table->foreignId('foliosales_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['property_id', 'event_date', 'status']);
        });

        // Housekeeping tasks
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('task_type', [
                'departure_clean', 'turn_down', 'stayover_clean', 'inspection',
                'deep_clean', 'maintenance', 'lost_found', 'minibar_restock', 'linen_change',
            ])->default('departure_clean');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'verified', 'on_hold'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('scheduled_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('time_taken_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->json('checklist')->nullable()->comment('[{item, checked}]');
            $table->timestamps();
            $table->index(['property_id', 'scheduled_date', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // Lost & Found
        Schema::create('housekeeping_lost_found', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_description');
            $table->string('found_location')->nullable();
            $table->date('found_date');
            $table->foreignId('found_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['stored', 'returned', 'donated', 'disposed'])->default('stored');
            $table->date('returned_date')->nullable();
            $table->string('returned_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Store / Inventory items
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('type', ['raw_material', 'beverage', 'liquor', 'housekeeping', 'engineering', 'stationery', 'other'])->default('raw_material');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('store_categories');
            $table->string('code', 30);
            $table->string('name');
            $table->string('unit', 20)->default('pcs')->comment('kg, ltr, pcs, btl');
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->decimal('max_stock', 12, 3)->nullable();
            $table->decimal('average_cost', 10, 2)->default(0);
            $table->decimal('last_purchase_price', 10, 2)->default(0);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        // Vendors
        Schema::create('store_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        // Purchase orders
        Schema::create('store_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('store_vendors');
            $table->string('po_number', 50)->unique();
            $table->date('po_date');
            $table->date('expected_delivery_date')->nullable();
            $table->enum('status', ['draft', 'sent', 'partial', 'received', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('store_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('store_purchase_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('store_items');
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });

        // GRN (Goods Receipt Note)
        Schema::create('store_grns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('store_vendors');
            $table->foreignId('purchase_order_id')->nullable()->constrained('store_purchase_orders')->nullOnDelete();
            $table->string('grn_number', 50)->unique();
            $table->date('grn_date');
            $table->string('vendor_invoice_number')->nullable();
            $table->date('vendor_invoice_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('store_grn_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grn_id')->constrained('store_grns')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('store_items');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        // Stock movements (purchase, issue, adjustment, transfer, wastage)
        Schema::create('store_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('store_items');
            $table->date('movement_date');
            $table->enum('movement_type', ['receipt', 'issue', 'adjustment', 'transfer_in', 'transfer_out', 'wastage', 'return']);
            $table->decimal('quantity', 12, 3)->comment('Positive for in, negative for out');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 12, 2);
            $table->string('reference_type')->nullable()->comment('grn, indent, adjustment');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['item_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_movements');
        Schema::dropIfExists('store_grn_items');
        Schema::dropIfExists('store_grns');
        Schema::dropIfExists('store_purchase_order_items');
        Schema::dropIfExists('store_purchase_orders');
        Schema::dropIfExists('store_vendors');
        Schema::dropIfExists('store_items');
        Schema::dropIfExists('store_categories');
        Schema::dropIfExists('housekeeping_lost_found');
        Schema::dropIfExists('housekeeping_tasks');
        Schema::dropIfExists('banquet_bookings');
        Schema::dropIfExists('banquet_packages');
        Schema::dropIfExists('banquet_halls');
    }
};
