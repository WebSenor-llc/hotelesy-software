<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of Accounts (Indian standard)
        Schema::create('accounts_chart', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->enum('subtype', [
                'current_asset', 'fixed_asset', 'bank', 'cash',
                'current_liability', 'long_term_liability', 'tax_payable',
                'capital', 'reserves',
                'sales_revenue', 'service_revenue', 'other_income',
                'cost_of_sales', 'operating_expense', 'admin_expense', 'tax_expense',
            ])->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts_chart')->nullOnDelete();
            $table->boolean('is_system')->default(false)->comment('Auto-managed accounts');
            $table->boolean('is_active')->default(true);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'code']);
            $table->index(['property_id', 'type']);
        });

        // Voucher types: Receipt, Payment, Journal, Contra, Sales, Purchase
        Schema::create('accounts_voucher_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->enum('type', ['receipt', 'payment', 'journal', 'contra', 'sales', 'purchase', 'credit_note', 'debit_note']);
            $table->string('prefix', 10)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        // Vouchers (header)
        Schema::create('accounts_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_type_id')->constrained('accounts_voucher_types');
            $table->string('voucher_number', 50)->unique();
            $table->date('voucher_date');
            $table->date('business_date');
            $table->string('reference_type')->nullable()->comment('reservation, folio, payment, grn, manual');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('narration')->nullable();
            $table->decimal('total_debit', 14, 2);
            $table->decimal('total_credit', 14, 2);
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('posted');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reverses_voucher_id')->nullable()->constrained('accounts_vouchers')->nullOnDelete();
            $table->timestamps();
            $table->index(['property_id', 'voucher_date']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Voucher entries (debit/credit lines — must balance per voucher)
        Schema::create('accounts_voucher_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('accounts_vouchers')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts_chart');
            $table->enum('side', ['debit', 'credit']);
            $table->decimal('amount', 14, 2);
            $table->string('narration')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'side']);
        });

        // GST returns — quarterly/monthly summary
        Schema::create('accounts_gst_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->enum('return_type', ['gstr1', 'gstr3b', 'gstr2a', 'gstr9']);
            $table->string('period', 10)->comment('YYYY-MM');
            $table->date('start_date');
            $table->date('end_date');

            // Outward supplies
            $table->decimal('taxable_value', 14, 2)->default(0);
            $table->decimal('cgst_amount', 14, 2)->default(0);
            $table->decimal('sgst_amount', 14, 2)->default(0);
            $table->decimal('igst_amount', 14, 2)->default(0);
            $table->decimal('cess_amount', 14, 2)->default(0);

            // ITC claimed
            $table->decimal('itc_cgst', 14, 2)->default(0);
            $table->decimal('itc_sgst', 14, 2)->default(0);
            $table->decimal('itc_igst', 14, 2)->default(0);

            $table->json('detailed_data')->nullable()->comment('Line items as filed');
            $table->enum('status', ['draft', 'computed', 'filed', 'amended'])->default('draft');
            $table->timestamp('filed_at')->nullable();
            $table->string('arn_number', 50)->nullable()->comment('ARN from GST portal');
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'return_type', 'period']);
        });

        // Bank reconciliation
        Schema::create('accounts_bank_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts_chart');
            $table->date('statement_date');
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('closing_balance', 14, 2);
            $table->string('statement_file')->nullable();
            $table->timestamps();
        });

        Schema::create('accounts_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained('accounts_bank_statements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 100)->nullable();
            $table->string('description');
            $table->foreignId('matched_voucher_id')->nullable()->constrained('accounts_vouchers')->nullOnDelete();
            $table->enum('match_status', ['unmatched', 'auto_matched', 'manual_matched', 'ignored'])->default('unmatched');
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['statement_id', 'match_status']);
        });

        // night_audit_logs is created in 2025_01_06_000001_create_channel_sync_audit_tables.php
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_bank_transactions');
        Schema::dropIfExists('accounts_bank_statements');
        Schema::dropIfExists('accounts_gst_returns');
        Schema::dropIfExists('accounts_voucher_entries');
        Schema::dropIfExists('accounts_vouchers');
        Schema::dropIfExists('accounts_voucher_types');
        Schema::dropIfExists('accounts_chart');
    }
};
