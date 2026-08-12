<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the Kitchen set (and increase) how long a ticket still needs,
     * so the Bar Tablet can tell the guest a real time instead of guessing.
     */
    public function up(): void
    {
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->timestamp('estimated_ready_at')->nullable()->after('age_verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('dining_orders', function (Blueprint $table) {
            $table->dropColumn('estimated_ready_at');
        });
    }
};
