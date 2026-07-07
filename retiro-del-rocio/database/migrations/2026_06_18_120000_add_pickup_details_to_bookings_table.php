<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('pickup_passengers')->nullable()->after('pickup_price');
            $table->string('pickup_location')->nullable()->after('pickup_passengers');
            $table->date('pickup_arrival_date')->nullable()->after('pickup_location');
            $table->string('pickup_time')->nullable()->after('pickup_arrival_date');
            $table->string('pickup_flight_number')->nullable()->after('pickup_time');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_passengers', 'pickup_location', 'pickup_arrival_date',
                'pickup_time', 'pickup_flight_number',
            ]);
        });
    }
};
