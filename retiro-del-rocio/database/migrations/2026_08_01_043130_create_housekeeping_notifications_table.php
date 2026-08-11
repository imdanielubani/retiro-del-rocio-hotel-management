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
        Schema::create('housekeeping_notifications', function (Blueprint $table) {
            $table->id();
            // Nullable and cascade-free: a notification still makes sense to
            // housekeeping after the booking it refers to is gone.
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('read_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('housekeeping_notifications');
    }
};
