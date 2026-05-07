<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'tds_applicable')) {
                $table->boolean('tds_applicable')->default(false)->after('pan_number');
            }
            if (!Schema::hasColumn('companies', 'tds_certificate_number')) {
                $table->string('tds_certificate_number', 50)->nullable()->after('tds_applicable');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'tds_amount')) {
                $table->decimal('tds_amount', 14, 2)->default(0)->after('amount');
            }
            if (!Schema::hasColumn('payments', 'tds_section')) {
                $table->string('tds_section', 20)->nullable()->after('tds_amount')
                    ->comment('194I rent / 194J professional / etc.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['tds_applicable', 'tds_certificate_number'] as $col) {
                if (Schema::hasColumn('companies', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('payments', function (Blueprint $table) {
            foreach (['tds_amount', 'tds_section'] as $col) {
                if (Schema::hasColumn('payments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
