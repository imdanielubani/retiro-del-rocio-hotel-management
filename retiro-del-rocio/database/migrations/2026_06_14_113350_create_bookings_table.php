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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room_name')->nullable();
            $table->unsignedSmallInteger('guests')->default(1);
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedSmallInteger('nights')->default(1);
            $table->unsignedInteger('amount')->default(0); // naira
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('pickup_vehicle')->nullable();
            $table->string('pickup_price')->nullable();
            $table->string('status')->default('paid'); // paid | pending | cancelled
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
