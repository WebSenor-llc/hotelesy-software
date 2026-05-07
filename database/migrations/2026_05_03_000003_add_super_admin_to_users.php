<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `is_super_admin` column already exists on the `users` table from the
 * initial migration (`2025_01_01_000003_create_users_table.php`).
 *
 * This migration is a noop placeholder kept for the SaaS-licensing migration
 * batch (2026_05_03_*). It also defensively adds the column on installs that
 * predate it. The User model's $fillable already includes 'is_super_admin'.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_super_admin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_super_admin')
                    ->default(false)
                    ->after('designation')
                    ->comment('SaaS operator level - bypasses tenant scoping');
            });
        }
    }

    public function down(): void
    {
        // Intentionally noop — column predates this migration on existing installs.
    }
};
