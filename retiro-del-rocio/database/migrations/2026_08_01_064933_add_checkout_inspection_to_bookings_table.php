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
        Schema::table('bookings', function (Blueprint $table) {
            // Reception's sign-off that the room was inspected (no damage or
            // missing items) before this guest was allowed to check out.
            $table->foreignId('checkout_inspected_by')->nullable()->after('checked_out_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('checkout_inspected_at')->nullable()->after('checkout_inspected_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checkout_inspected_by');
            $table->dropColumn('checkout_inspected_at');
        });
    }
};
