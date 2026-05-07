<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Booking engine — public direct booking
        Schema::create('booking_engine_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_slug', 100)->unique()->comment('Used in URL: book.miraj.com/{slug}');
            $table->json('hero_images')->nullable();
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();
            $table->json('policies')->nullable()->comment('Cancellation, child policy, pet policy');
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('payment_gateway_provider', 30)->default('razorpay');
            $table->string('payment_gateway_key_id')->nullable();
            $table->string('analytics_ga_id')->nullable();
            $table->string('analytics_meta_pixel')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Promo codes
        Schema::create('booking_engine_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('discount_type', ['percent', 'fixed', 'free_night'])->default('percent');
            $table->decimal('discount_value', 10, 2);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->date('stay_from')->nullable();
            $table->date('stay_to')->nullable();
            $table->unsignedInteger('min_nights')->default(1);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->json('applicable_room_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        // Web booking sessions (cart) — created before payment, reservation only on success
        Schema::create('booking_engine_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->uuid('session_token')->unique();
            $table->date('arrival_date');
            $table->date('departure_date');
            $table->unsignedSmallInteger('adults');
            $table->unsignedSmallInteger('children');
            $table->json('selected_rooms')->comment('[{room_type_id, rate_plan_id, count, rate}]');
            $table->foreignId('promo_code_id')->nullable()->constrained('booking_engine_promo_codes')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->enum('status', ['pending', 'payment_initiated', 'payment_failed', 'completed', 'expired', 'abandoned'])->default('pending');
            $table->string('payment_gateway_order_id')->nullable();
            $table->string('payment_gateway_payment_id')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['property_id', 'status']);
        });

        // sync_queue is created in 2025_01_06_000001_create_channel_sync_audit_tables.php

        // Integrations log — payment gateway, door lock, ID scanner
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('integration', 50)->comment('razorpay, onity, jumio, etc.');
            $table->string('operation', 50);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('called_at');
            $table->timestamps();
            $table->index(['integration', 'operation', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('booking_engine_sessions');
        Schema::dropIfExists('booking_engine_promo_codes');
        Schema::dropIfExists('booking_engine_settings');
    }
};
