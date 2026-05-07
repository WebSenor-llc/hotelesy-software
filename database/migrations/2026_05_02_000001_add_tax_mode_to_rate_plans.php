<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->enum('tax_mode', ['inclusive', 'exclusive'])
                ->default('exclusive')
                ->after('rate_modifier')
                ->comment('Whether base_rate already includes GST (inclusive) or GST is added on top (exclusive)');
        });
    }

    public function down(): void
    {
        Schema::table('rate_plans', function (Blueprint $table) {
            $table->dropColumn('tax_mode');
        });
    }
};
