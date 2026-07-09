<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lounge guests choose where they sit: Rooftop or Ground floor.
 * Null for dining reservations, which have no floor choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_reservations', function (Blueprint $table) {
            $table->string('floor')->nullable()->after('table_label');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_reservations', function (Blueprint $table) {
            $table->dropColumn('floor');
        });
    }
};
