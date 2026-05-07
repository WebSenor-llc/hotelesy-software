<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * marketing_leads — captures inquiries from the public landing page.
 * Distinct from CRM guest "leads" — these are pre-customer, pre-tenant leads.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30);
            $table->string('hotel_name')->nullable();
            $table->string('city')->nullable();
            $table->unsignedInteger('rooms_count')->nullable();
            $table->string('current_pms')->nullable()->comment('What software they currently use');
            $table->text('message')->nullable();

            $table->enum('source', [
                'landing_page', 'demo_request', 'pricing_page',
                'trial_signup', 'referral', 'other',
            ])->default('landing_page');

            $table->enum('status', [
                'new', 'contacted', 'qualified', 'demo_scheduled',
                'proposal_sent', 'won', 'lost', 'unqualified',
            ])->default('new');

            $table->foreignId('converted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();

            $table->text('notes')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['email']);
            $table->index(['next_followup_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_leads');
    }
};
