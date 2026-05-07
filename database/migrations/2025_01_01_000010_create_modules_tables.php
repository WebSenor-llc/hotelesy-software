<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog of available modules. System-managed; admin seeds, tenants do not edit.
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('pms, pos, kds, channel_manager, banquet, housekeeping, accounts, booking_engine, revenue, reviews, amenities, cms, store, payroll');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 50)->default('operations')->comment('core, operations, distribution, finance, marketing, integrations');
            $table->boolean('is_core')->default(false)->comment('Cannot be disabled');
            $table->json('depends_on')->nullable()->comment('Module codes this requires');
            $table->json('available_in_plans')->nullable()->comment('null = all plans');
            $table->decimal('addon_price_inr', 10, 2)->nullable()->comment('Monthly add-on if not in plan');
            $table->string('icon', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Per-tenant module enablement. Default state derives from plan; this row exists when tenant overrides.
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable()->comment('Per-tenant module config');
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'module_id']);
        });

        // Per-property module enablement (chains may turn POS off at one property)
        Schema::create('property_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_modules');
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('modules');
    }
};
