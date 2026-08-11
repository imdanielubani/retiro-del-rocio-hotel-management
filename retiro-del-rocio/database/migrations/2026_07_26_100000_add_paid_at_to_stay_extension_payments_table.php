<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when (and how) the guest actually paid for a stay extension, so the
 * admin Payments module can list each extension as its own transaction with the
 * date the charge was verified — separate from the booking's original payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stay_extension_payments', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->string('payment_method')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('stay_extension_payments', function (Blueprint $table) {
            $table->dropColumn(['paid_at', 'payment_method']);
        });
    }
};
