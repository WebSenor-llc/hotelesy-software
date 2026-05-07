<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('licenses', 'razorpay_subscription_id')) {
                $table->string('razorpay_subscription_id')->nullable()->after('billing_cycle')->index();
            }
            if (! Schema::hasColumn('licenses', 'razorpay_customer_id')) {
                $table->string('razorpay_customer_id')->nullable()->after('razorpay_subscription_id');
            }
            if (! Schema::hasColumn('licenses', 'auto_renew')) {
                $table->boolean('auto_renew')->default(false)->after('razorpay_customer_id');
            }
            if (! Schema::hasColumn('licenses', 'next_billing_at')) {
                $table->timestamp('next_billing_at')->nullable()->after('auto_renew');
            }
            if (! Schema::hasColumn('licenses', 'last_reminder_sent_at')) {
                $table->timestamp('last_reminder_sent_at')->nullable()->after('next_billing_at');
            }
            if (! Schema::hasColumn('licenses', 'mandate_status')) {
                $table->string('mandate_status', 30)->nullable()->after('last_reminder_sent_at')->comment('created|active|paused|cancelled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $cols = ['razorpay_subscription_id','razorpay_customer_id','auto_renew','next_billing_at','last_reminder_sent_at','mandate_status'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('licenses', $c)) $table->dropColumn($c);
            }
        });
    }
};
