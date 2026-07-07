<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('refund_amount')->nullable()->after('payment_method');
            $table->string('refund_method')->nullable()->after('refund_amount'); // bank_transfer | card_reversal | hotel_credit
            $table->string('refund_status')->nullable()->after('refund_method');  // pending | completed | declined
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['refund_amount', 'refund_method', 'refund_status']);
        });
    }
};
