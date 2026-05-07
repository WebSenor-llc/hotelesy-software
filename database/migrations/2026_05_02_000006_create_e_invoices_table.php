<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('e_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();

            $table->string('invoice_number', 50);
            $table->string('irn', 64)->nullable()->comment('Invoice Reference Number, 64-char SHA256 hash');
            $table->string('ack_number', 50)->nullable();
            $table->dateTime('ack_date')->nullable();

            $table->text('qr_code_data')->nullable();
            $table->json('json_payload')->nullable();

            $table->string('status', 20)->default('draft')->comment('draft|generated|cancelled');
            $table->string('error_message', 500)->nullable();

            $table->dateTime('generated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'status'], 'einv_tps_idx');
            $table->unique(['property_id', 'invoice_number'], 'einv_property_inv_uq');
        });

        Schema::create('form_c_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('form_number', 30)->comment('Auto-incrementing per property per year');
            $table->dateTime('generated_at');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('frro_acknowledgement_number', 50)->nullable();
            $table->string('submission_method', 20)->default('printed')
                ->comment('printed|online|portal');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'property_id'], 'fcs_tp_idx');
            $table->index(['property_id', 'submitted_at'], 'fcs_p_submitted_idx');
            $table->unique(['property_id', 'form_number'], 'fcs_property_form_uq');
        });

        // Property setting: turnover ≥ 5cr — drives e-invoicing requirement
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'einvoice_required')) {
                $table->boolean('einvoice_required')->default(false)->after('liquor_license')
                    ->comment('True when annual aggregate turnover is >=5cr (B2B IRN mandatory)');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_c_submissions');
        Schema::dropIfExists('e_invoices');
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'einvoice_required')) {
                $table->dropColumn('einvoice_required');
            }
        });
    }
};
