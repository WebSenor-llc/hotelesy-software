<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend `licenses.billing_cycle` enum to include `trial` so the
 * trial-signup flow can persist a trial license without truncating.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('licenses')) return;

        // SQLite has dynamic typing — no enum widening needed.
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::statement("
            ALTER TABLE licenses
            MODIFY COLUMN billing_cycle ENUM('monthly','yearly','lifetime','trial')
            NOT NULL DEFAULT 'monthly'
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('licenses')) return;
        if (DB::connection()->getDriverName() !== 'mysql') return;

        // Reset any trial rows to 'monthly' before shrinking the enum back
        DB::table('licenses')->where('billing_cycle', 'trial')->update(['billing_cycle' => 'monthly']);

        DB::statement("
            ALTER TABLE licenses
            MODIFY COLUMN billing_cycle ENUM('monthly','yearly','lifetime')
            NOT NULL DEFAULT 'monthly'
        ");
    }
};
