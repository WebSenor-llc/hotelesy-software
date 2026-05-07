<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Channel mappings - maps our room types & rate plans to OTA / channel manager IDs
        Schema::create('channel_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('channel')->comment('axisrooms, staah, siteminder, booking_com, mmt, agoda, expedia, goibibo');
            $table->string('external_hotel_id')->nullable();
            $table->string('external_property_code')->nullable();

            $table->foreignId('room_type_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('external_room_type_id')->nullable();
            $table->string('external_rate_plan_id')->nullable();
            $table->string('external_meal_plan_code')->nullable();

            $table->boolean('push_inventory')->default(true);
            $table->boolean('push_rates')->default(true);
            $table->boolean('pull_bookings')->default(true);

            $table->timestamp('last_sync_at')->nullable();
            $table->enum('last_sync_status', ['success', 'partial', 'failed', 'pending'])->nullable();
            $table->text('last_sync_error')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['property_id', 'channel', 'is_active']);
            $table->unique(['property_id', 'channel', 'room_type_id', 'rate_plan_id'], 'channel_mapping_unique');
        });

        // Channel sync log - every push and pull operation logged
        Schema::create('channel_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('channel');
            $table->enum('direction', ['push', 'pull']);
            $table->enum('operation', [
                'inventory_push',
                'rate_push',
                'restriction_push',
                'booking_pull',
                'booking_modify',
                'booking_cancel',
                'mapping_sync',
                'parity_audit',
            ]);

            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->json('payload_sent')->nullable();
            $table->json('payload_received')->nullable();

            $table->enum('status', ['queued', 'in_progress', 'success', 'partial', 'failed', 'retry'])->default('queued');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();

            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamps();

            $table->index(['property_id', 'channel', 'created_at']);
            $table->index(['status', 'next_retry_at']);
        });

        // Offline sync queue - each operation done while offline lands here
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->string('queue_id')->unique()->comment('Client-generated UUID');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('entity_type')->comment('reservation|folio|payment|room_status|kot|housekeeping');
            $table->string('local_entity_id')->nullable();
            $table->unsignedBigInteger('server_entity_id')->nullable();

            $table->enum('action', ['create', 'update', 'delete', 'approve', 'post', 'void']);
            $table->json('payload');
            $table->json('original_state')->nullable()->comment('For conflict detection');

            $table->string('device_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('client_created_at');
            $table->enum('sync_status', ['pending', 'syncing', 'synced', 'failed', 'conflict', 'resolved'])->default('pending');
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('synced_at')->nullable();

            // Conflict resolution
            $table->json('conflict_details')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->enum('resolution', ['client_wins', 'server_wins', 'merged', 'manual'])->nullable();

            $table->timestamps();

            $table->index(['property_id', 'sync_status']);
            $table->index(['device_id', 'sync_status']);
            $table->index(['entity_type', 'local_entity_id']);
        });

        // Audit log - who did what when
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('event')->comment('created|updated|deleted|login|logout|posted|voided');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id')->nullable();
            $table->string('url')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });

        // Night audit log - the daily close process
        Schema::create('night_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->date('business_date');
            $table->date('next_business_date');

            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->enum('status', ['running', 'completed', 'failed', 'rolled_back'])->default('running');

            // Snapshot of operations performed
            $table->unsignedInteger('reservations_processed')->default(0);
            $table->unsignedInteger('rooms_posted')->default(0);
            $table->unsignedInteger('no_shows_marked')->default(0);
            $table->unsignedInteger('folios_closed')->default(0);
            $table->decimal('total_room_revenue', 14, 2)->default(0);
            $table->decimal('total_pos_revenue', 14, 2)->default(0);
            $table->decimal('total_tax', 14, 2)->default(0);
            $table->decimal('total_payments', 14, 2)->default(0);
            $table->unsignedInteger('rooms_occupied')->default(0);
            $table->decimal('occupancy_percent', 5, 2)->default(0);
            $table->decimal('arr', 12, 2)->default(0)->comment('Average Room Rate');
            $table->decimal('revpar', 12, 2)->default(0)->comment('Revenue Per Available Room');

            $table->json('exceptions')->nullable()->comment('Issues flagged during audit');
            $table->text('notes')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'business_date']);
            $table->index(['tenant_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('night_audit_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_queue');
        Schema::dropIfExists('channel_sync_logs');
        Schema::dropIfExists('channel_mappings');
    }
};
