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
        // VAT (7.5%) on the dish/drink subtotal — tracked separately from
        // revenue, same as every other charge-source model (SpaBooking,
        // CinemaBooking, Booking, etc.). Dining orders never had this column,
        // so guests were never actually charged VAT on food/drink orders.
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->integer('vat')->default(0)->after('subtotal');
        });

        // Cached aggregate to match — a tab's total already bakes in each
        // order's VAT, but the breakdown needs its own column to show a
        // Subtotal / VAT / Service Fee / Total split on the tablet.
        Schema::table('bar_tabs', function (Blueprint $table) {
            $table->integer('vat')->default(0)->after('subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropColumn('vat');
        });

        Schema::table('bar_tabs', function (Blueprint $table) {
            $table->dropColumn('vat');
        });
    }
};
