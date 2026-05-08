<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds desktop-build columns to licenses.
 *
 *  - is_desktop                 → flag distinguishing desktop licenses from SaaS
 *  - desktop_machine_fingerprint → SHA-256 hash of (OS + hostname + machine UUID + MAC + APP_KEY salt)
 *  - desktop_machine_label       → human-readable host label set by the user
 *  - desktop_activated_at        → first activation timestamp
 *  - desktop_last_seen_at        → last successful phone-home
 *  - desktop_max_machines        → number of concurrent desktop seats (default 1)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $t) {
            $t->boolean('is_desktop')->default(false)->after('billing_cycle');
            $t->string('desktop_machine_fingerprint', 128)->nullable()->after('is_desktop');
            $t->string('desktop_machine_label', 191)->nullable()->after('desktop_machine_fingerprint');
            $t->timestamp('desktop_activated_at')->nullable()->after('desktop_machine_label');
            $t->timestamp('desktop_last_seen_at')->nullable()->after('desktop_activated_at');
            $t->unsignedSmallInteger('desktop_max_machines')->default(1)->after('desktop_last_seen_at');

            $t->index(['is_desktop', 'desktop_machine_fingerprint'], 'licenses_desktop_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $t) {
            $t->dropIndex('licenses_desktop_lookup_idx');
            $t->dropColumn([
                'is_desktop',
                'desktop_machine_fingerprint',
                'desktop_machine_label',
                'desktop_activated_at',
                'desktop_last_seen_at',
                'desktop_max_machines',
            ]);
        });
    }
};
