<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Tenant scoping. Nullable for super-admins (SaaS operators / WebSenor staff).
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            // Default property a user logs into. Users may have access to multiple via pivot.
            $table->foreignId('default_property_id')->nullable()->constrained('properties')->nullOnDelete();

            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('employee_code', 30)->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();

            $table->boolean('is_super_admin')->default(false)->comment('SaaS operator level - bypasses tenant scoping');
            $table->boolean('is_active')->default(true);

            // Security
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('last_login_device')->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->boolean('force_password_change')->default(false);
            $table->timestamp('password_changed_at')->nullable();

            // Cashier / cash drawer
            $table->boolean('can_handle_cash')->default(false);
            $table->decimal('cash_drawer_limit', 12, 2)->default(0);

            $table->rememberToken();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Property access pivot - user can access multiple properties within same tenant
        Schema::create('property_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'user_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('property_user');
        Schema::dropIfExists('users');
    }
};
