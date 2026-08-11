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
        Schema::create('bar_tabs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('table_label')->nullable();
            $table->string('guest_name')->nullable();
            $table->boolean('is_vip')->default(false);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open | settled | void
            $table->integer('subtotal')->default(0);
            $table->integer('service_fee')->default(0);
            $table->integer('total')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_tabs');
    }
};
