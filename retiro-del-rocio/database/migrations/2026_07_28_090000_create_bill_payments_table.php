<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guest pre-settling their running room folio from the My Bills tablet
 * screen, paid through Paystack. One row per Paystack transaction: created
 * 'pending' when the payment is initialised, flipped to 'success' once the
 * charge is verified. The unique reference makes confirming idempotent — a
 * repeated verify for the same reference is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->unsignedInteger('amount'); // charges settled, excl. VAT (naira)
            $table->unsignedInteger('vat')->default(0);
            $table->string('status')->default('pending'); // pending | success
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
