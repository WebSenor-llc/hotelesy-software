<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->comment('reservation|folio|invoice|receipt|kot|bot');
            $table->string('year', 4);
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_counters');
    }
};
