<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spa_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->json('services'); // [{name, price, guests, subtotal}]
            $table->unsignedSmallInteger('guests')->default(1);
            $table->date('date')->nullable();
            $table->string('time')->nullable();
            $table->text('special_request')->nullable();
            $table->unsignedInteger('subtotal')->default(0);
            $table->unsignedInteger('fees')->default(0);
            $table->unsignedInteger('taxes')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->default('paid'); // paid | pending | cancelled
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spa_bookings');
    }
};
