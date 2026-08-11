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
        Schema::create('work_order_attachments', function (Blueprint $table) {
            $table->id();
            // Cascades — an attachment has no meaning once its order is gone,
            // unlike `work_orders.room_unit_id`/`booking_id` which stay for
            // history after the room/booking itself disappears.
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('path'); // public-disk relative path
            $table->string('type'); // photo | video
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('work_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_order_attachments');
    }
};
