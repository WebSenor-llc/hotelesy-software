<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('code', 20)->comment('e.g. DLX, STE, SUP');
            $table->string('name')->comment('e.g. Deluxe, Suite, Superior');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('base_occupancy')->default(2);
            $table->unsignedSmallInteger('max_occupancy')->default(3);
            $table->unsignedSmallInteger('max_adults')->default(2);
            $table->unsignedSmallInteger('max_children')->default(2);
            $table->unsignedSmallInteger('extra_bed_capacity')->default(0);

            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('extra_adult_rate', 12, 2)->default(0);
            $table->decimal('extra_child_rate', 12, 2)->default(0);
            $table->decimal('extra_bed_rate', 12, 2)->default(0);

            $table->decimal('size_sqft', 8, 2)->nullable();
            $table->string('bed_type')->nullable()->comment('king|queen|twin|double');
            $table->json('amenities')->nullable();
            $table->json('photos')->nullable();

            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('sell_on_channels')->default(true);

            // Channel manager mapping
            $table->json('channel_mappings')->nullable()->comment('OTA-specific room type IDs');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'code']);
            $table->index(['tenant_id', 'property_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
