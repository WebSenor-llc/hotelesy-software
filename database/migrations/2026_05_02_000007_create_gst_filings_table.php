<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gst_filings')) {
            return;
        }

        Schema::create('gst_filings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();

            $table->string('return_period', 7)->comment('YYYY-MM');
            $table->enum('return_type', ['GSTR-1', 'GSTR-3B', 'GSTR-9']);
            $table->enum('status', ['draft', 'generated', 'filed', 'revised'])->default('draft');

            $table->dateTime('generated_at')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('filed_at')->nullable();
            $table->foreignId('filed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('ack_number', 50)->nullable();

            $table->decimal('taxable_value', 16, 2)->default(0);
            $table->decimal('total_tax', 16, 2)->default(0);

            $table->json('json_payload')->nullable()->comment('Full return JSON payload');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'property_id', 'return_period'], 'gstf_tpr_idx');
            $table->index(['property_id', 'return_type', 'status'], 'gstf_p_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_filings');
    }
};
