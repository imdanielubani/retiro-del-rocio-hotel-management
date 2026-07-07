<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price')->default(0);   // naira / period
            $table->string('period')->default('month');
            $table->string('tagline')->nullable();           // short line under the name
            $table->json('features')->nullable();            // array of "what's included" lines
            $table->boolean('is_featured')->default(false);  // the highlighted (Standard) card
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('gym_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                // MP-723653-RDR
            $table->string('reference')->nullable()->unique(); // Paystack reference
            $table->foreignId('gym_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('period')->default('month');
            $table->string('type')->default('subscribe');    // subscribe | renewal
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->date('dob')->nullable();
            $table->string('status')->default('active');      // active | expired | cancelled
            $table->string('payment_status')->default('pending'); // paid | pending | refunded
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_memberships');
        Schema::dropIfExists('gym_plans');
    }
};
