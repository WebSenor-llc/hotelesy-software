<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_bank_statements', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('closing_balance');
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('accounts_bank_transactions', function (Blueprint $table) {
            $table->foreignId('matched_payment_id')->nullable()->after('matched_voucher_id')->constrained('payments')->nullOnDelete();
            $table->string('match_reason')->nullable()->after('matched_by');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('matched_payment_id');
            $table->dropColumn('match_reason');
        });

        Schema::table('accounts_bank_statements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['status', 'closed_at']);
        });
    }
};
