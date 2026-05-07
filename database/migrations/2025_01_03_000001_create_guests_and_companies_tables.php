<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Companies first because guests references it
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('code', 30);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();

            $table->text('billing_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();

            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // City ledger / credit
            $table->boolean('is_credit_account')->default(false);
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->decimal('current_outstanding', 14, 2)->default(0);

            // Negotiated rates
            $table->boolean('has_corporate_rate')->default(false);
            $table->decimal('corporate_discount_percent', 5, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('salutation', 10)->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('full_name')->virtualAs("CONCAT_WS(' ', first_name, last_name)");

            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('phone_alternate', 20)->nullable();
            $table->date('dob')->nullable();
            $table->enum('gender', ['M', 'F', 'O'])->nullable();

            $table->string('nationality', 2)->default('IN');
            $table->string('id_type', 30)->nullable()->comment('aadhaar|pan|passport|driving_license|voter_id');
            $table->string('id_number')->nullable();
            $table->string('id_issuing_country', 2)->nullable();
            $table->date('id_expiry')->nullable();
            $table->string('id_document_path')->nullable()->comment('Scanned ID file');

            // Foreign visitor specifics (for FRRO Form-C compliance)
            $table->string('passport_number')->nullable();
            $table->string('visa_number')->nullable();
            $table->date('visa_expiry')->nullable();
            $table->date('arrival_in_india')->nullable();
            $table->string('next_destination')->nullable();

            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->default('IN');
            $table->string('postal_code', 20)->nullable();

            // GST for B2C invoicing
            $table->string('gst_number', 20)->nullable();
            $table->string('company_name')->nullable();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            // CRM / Loyalty
            $table->string('segment')->nullable()->comment('VIP|corporate|leisure|family|business');
            $table->string('loyalty_tier')->nullable();
            $table->string('loyalty_number')->nullable();
            $table->unsignedInteger('total_visits')->default(0);
            $table->decimal('lifetime_spend', 14, 2)->default(0);
            $table->date('last_stay_date')->nullable();
            $table->date('first_stay_date')->nullable();

            $table->json('preferences')->nullable()->comment('Pillow, room temp, dietary, etc.');
            $table->text('notes')->nullable();
            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklist_reason')->nullable();

            $table->boolean('marketing_consent_email')->default(false);
            $table->boolean('marketing_consent_sms')->default(false);
            $table->boolean('marketing_consent_whatsapp')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'last_name', 'first_name']);
            $table->index(['tenant_id', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
        Schema::dropIfExists('companies');
    }
};
