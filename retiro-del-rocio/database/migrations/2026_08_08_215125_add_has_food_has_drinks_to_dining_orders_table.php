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
        Schema::table('dining_orders', function (Blueprint $table) {
            // Which admin queue(s) an order belongs to (Kitchen / Bar &
            // Lounge) — derived from the categories of its snapshotted items
            // at order time, so the queues stay stable even if a menu item's
            // category changes later.
            $table->boolean('has_food')->default(true)->after('items');
            $table->boolean('has_drinks')->default(false)->after('has_food');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropColumn(['has_food', 'has_drinks']);
        });
    }
};
