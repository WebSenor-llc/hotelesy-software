<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a single JSON `site_content` column to `properties` to back the
 * public hotel-site CMS. Storing the whole content tree as JSON keeps the
 * admin page simple (one row per property) and avoids needing a separate
 * pages/blocks/media schema for the demo. Future structured CMS can
 * supersede this; the column then becomes a fallback / migration source.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('properties')) return;
        if (Schema::hasColumn('properties', 'site_content')) return;

        Schema::table('properties', function (Blueprint $table) {
            $table->json('site_content')->nullable()->comment(
                'Public-site CMS payload: hero slides, about, contact text, footer links etc.'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('properties')) return;
        if (! Schema::hasColumn('properties', 'site_content')) return;
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('site_content');
        });
    }
};
