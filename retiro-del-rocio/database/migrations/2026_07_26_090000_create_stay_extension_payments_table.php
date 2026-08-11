<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guest's self-service stay extension paid through Paystack on the in-room
 * tablet. One row per Paystack transaction: created 'pending' when the payment
 * is initialised, flipped to 'success' once the charge is verified and the
 * booking's checkout is moved. The unique reference makes applying an extension
 * idempotent — a repeated verify for the same reference is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stay_extension_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->unsignedInteger('nights');
            $table->date('new_check_out');
            $table->unsignedInteger('amount'); // total charged, incl. VAT (naira)
            $table->unsignedInteger('vat')->default(0);
            $table->string('status')->default('pending'); // pending | success
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_extension_payments');
    }
};
