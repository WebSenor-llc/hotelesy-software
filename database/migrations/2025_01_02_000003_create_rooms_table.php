<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();

            $table->string('number', 20)->comment('Room number e.g. 101, A-204');
            $table->string('floor', 10)->nullable();
            $table->string('wing', 30)->nullable();
            $table->string('view')->nullable()->comment('sea|garden|pool|city|mountain');

            // Operational status (the housekeeping & front office statuses)
            $table->enum('status', [
                'vacant_clean',
                'vacant_dirty',
                'occupied_clean',
                'occupied_dirty',
                'inspected',
                'out_of_order',
                'out_of_service',
                'blocked',
            ])->default('vacant_clean');

            // Front office state (different from housekeeping)
            $table->enum('fo_status', [
                'vacant',
                'reserved',
                'occupied',
                'due_out',
                'on_request',
            ])->default('vacant');

            $table->text('out_of_order_reason')->nullable();
            $table->date('out_of_order_until')->nullable();

            $table->boolean('is_smoking')->default(false);
            $table->boolean('is_accessible')->default(false);
            $table->boolean('has_extra_bed')->default(false);
            $table->boolean('has_connecting_room')->default(false);
            $table->foreignId('connecting_room_id')->nullable()->constrained('rooms')->nullOnDelete();

            $table->json('amenities')->nullable();
            $table->text('housekeeping_notes')->nullable();
            $table->text('maintenance_notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_id', 'number']);
            $table->index(['property_id', 'status']);
            $table->index(['property_id', 'room_type_id']);
        });

        // Room status change log - audit trail for housekeeping
        Schema::create('room_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['room_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_status_logs');
        Schema::dropIfExists('rooms');
    }
};
