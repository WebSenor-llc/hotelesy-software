<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('code', 30)->index();
            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('type', ['percentage', 'flat_amount', 'free_night', 'package_upgrade', 'complimentary_addon']);
            $table->decimal('value', 12, 2)->default(0)->comment('% off, ₹ off, free nights count');

            $table->enum('applies_to', ['all', 'room_types', 'rate_plans', 'corporate'])->default('all');
            $table->json('target_room_type_ids')->nullable();
            $table->json('target_rate_plan_ids')->nullable();

            // Eligibility
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->unsignedSmallInteger('min_nights')->default(1);
            $table->decimal('min_amount', 12, 2)->default(0);
            $table->unsignedInteger('max_uses')->nullable()->comment('Total redemptions allowed');
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedSmallInteger('max_per_guest')->nullable();
            $table->unsignedSmallInteger('advance_days')->default(0)->comment('Must be booked X days before stay');

            // Channels
            $table->boolean('available_direct')->default(true);
            $table->boolean('available_ota')->default(false);
            $table->boolean('available_corporate')->default(true);
            $table->boolean('is_stackable')->default(false);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true)->comment('Show in booking engine');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'is_active', 'valid_from', 'valid_to']);
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('redeemed_at')->useCurrent();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['promotion_id','redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotions');
    }
};
