<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('rooms')) return;
        Schema::table('rooms', function (Blueprint $t) {
            if (! Schema::hasColumn('rooms', 'image_path')) {
                $t->string('image_path')->nullable()->after('view');
            }
            if (! Schema::hasColumn('rooms', 'notes')) {
                $t->text('notes')->nullable()->after('image_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rooms')) return;
        Schema::table('rooms', function (Blueprint $t) {
            foreach (['image_path','notes'] as $c) {
                if (Schema::hasColumn('rooms', $c)) $t->dropColumn($c);
            }
        });
    }
};
