<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Properties = individual hotels within a tenant's account.
 * A tenant (e.g. "Miraj Hospitality Group") may own multiple properties
 * (e.g. "Miraj Lake Palace Udaipur", "Miraj Beach Resort Goa").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('code', 20)->comment('Short property code, e.g. MLPU');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            // Indian tax / regulatory
            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('fssai_number', 20)->nullable();
            $table->string('liquor_license', 50)->nullable();

            // Operations
            $table->time('check_in_time')->default('14:00');
            $table->time('check_out_time')->default('12:00');
            $table->time('night_audit_time')->default('02:00');
            $table->date('current_business_date')->nullable()->comment('Locked by night audit');
            $table->boolean('night_audit_locked')->default(false);

            $table->string('currency', 3)->default('INR');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->unsignedInteger('total_rooms')->default(0);
            $table->unsignedInteger('floors')->default(1);

            $table->enum('status', ['active', 'inactive', 'setup'])->default('setup');
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
