<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spa_bookings', function (Blueprint $table) {
            // Separate the payment state (paid|pending|refunded) from the
            // booking/session state (confirmed|pending|cancelled).
            $table->string('payment_status')->default('pending')->after('status');
        });

        // Migrate existing rows: the old single `status` conflated both.
        DB::table('spa_bookings')->where('status', 'paid')
            ->update(['payment_status' => 'paid', 'status' => 'confirmed']);
        DB::table('spa_bookings')->where('status', 'cancelled')
            ->update(['payment_status' => 'refunded']);
        // 'pending' rows stay status=pending, payment_status=pending.
    }

    public function down(): void
    {
        DB::table('spa_bookings')->where('status', 'confirmed')
            ->update(['status' => 'paid']);

        Schema::table('spa_bookings', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
