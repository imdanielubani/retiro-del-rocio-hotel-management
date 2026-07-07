<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cinema_bookings', function (Blueprint $table) {
            // Tickets + snacks subtotal, convenience fee and VAT — mirrors the spa
            // checkout breakdown. `amount` remains the grand total charged.
            $table->unsignedInteger('subtotal')->default(0)->after('snacks');
            $table->unsignedInteger('fee')->default(0)->after('subtotal');
            $table->unsignedInteger('taxes')->default(0)->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('cinema_bookings', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'fee', 'taxes']);
        });
    }
};
