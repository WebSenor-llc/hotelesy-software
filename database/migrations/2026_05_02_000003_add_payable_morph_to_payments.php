<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payable_type')->nullable()->after('company_id');
            $table->unsignedBigInteger('payable_id')->nullable()->after('payable_type');
            $table->index(['payable_type', 'payable_id'], 'payments_payable_morph_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_payable_morph_idx');
            $table->dropColumn(['payable_type', 'payable_id']);
        });
    }
};
