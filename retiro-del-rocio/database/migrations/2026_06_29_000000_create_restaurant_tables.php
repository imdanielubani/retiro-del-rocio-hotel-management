<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dining tables + lounge spaces managed in the admin Restaurant module.
        Schema::create('restaurant_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('area')->default('dining'); // dining | lounge
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->string('shape')->default('round');  // round | square | rectangle
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Reservations made on the restaurant page (refundable fee via Paystack).
        Schema::create('restaurant_reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('reference')->nullable()->unique();
            $table->string('area')->default('dining'); // dining | lounge
            $table->foreignId('restaurant_table_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_label')->nullable();
            $table->string('occasion')->nullable();
            $table->unsignedSmallInteger('guests')->default(1);
            $table->date('reserved_date');
            $table->string('reserved_time')->nullable();
            $table->text('special_request')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('confirmed');      // confirmed | seated | completed | cancelled | no_show
            $table->string('payment_status')->default('paid');   // paid | pending | refunded
            $table->unsignedInteger('fee')->default(10000);
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_reservations');
        Schema::dropIfExists('restaurant_tables');
    }
};
