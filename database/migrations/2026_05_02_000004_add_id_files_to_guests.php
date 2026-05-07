<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            // The base migration already has nationality, passport_number, visa_number,
            // visa_expiry, arrival_in_india, next_destination -- we add only what's missing.

            if (!Schema::hasColumn('guests', 'id_proof_files')) {
                $table->json('id_proof_files')->nullable()->after('id_document_path')
                    ->comment('Array of stored file paths: front, back, selfie, etc.');
            }
            if (!Schema::hasColumn('guests', 'is_foreign_national')) {
                $table->boolean('is_foreign_national')->default(false)->after('nationality');
            }
            if (!Schema::hasColumn('guests', 'passport_expiry')) {
                $table->date('passport_expiry')->nullable()->after('passport_number');
            }
            if (!Schema::hasColumn('guests', 'arrival_from_country')) {
                $table->string('arrival_from_country', 2)->nullable()->after('arrival_in_india')
                    ->comment('ISO country code visitor arrived from');
            }
            if (!Schema::hasColumn('guests', 'arrival_date_in_india')) {
                $table->date('arrival_date_in_india')->nullable()->after('arrival_from_country');
            }
            if (!Schema::hasColumn('guests', 'next_destination_country')) {
                $table->string('next_destination_country', 2)->nullable()->after('next_destination');
            }

            $table->index(['tenant_id', 'is_foreign_national'], 'guests_tenant_foreign_idx');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex('guests_tenant_foreign_idx');
            foreach ([
                'id_proof_files',
                'is_foreign_national',
                'passport_expiry',
                'arrival_from_country',
                'arrival_date_in_india',
                'next_destination_country',
            ] as $col) {
                if (Schema::hasColumn('guests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
