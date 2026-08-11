<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A walk-in bar tab has no room booking, unlike every dining order
        // placed from the guest tablet — booking_id must become optional.
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });

        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->change();
        });

        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });

        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreignId('bar_tab_id')->nullable()->after('booking_id')->constrained('bar_tabs')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('payment_method')->constrained('users')->nullOnDelete();
            $table->timestamp('age_verified_at')->nullable()->after('assigned_to');
            $table->foreignId('age_verified_by')->nullable()->after('age_verified_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropForeign(['bar_tab_id']);
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['age_verified_by']);
            $table->dropColumn(['bar_tab_id', 'assigned_to', 'age_verified_at', 'age_verified_by']);
        });

        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });

        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable(false)->change();
        });

        Schema::table('dining_orders', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }
};
