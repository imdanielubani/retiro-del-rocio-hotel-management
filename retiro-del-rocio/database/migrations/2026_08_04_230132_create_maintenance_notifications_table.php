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
        Schema::create('maintenance_notifications', function (Blueprint $table) {
            $table->id();
            // Nullable and cascade-free, same as `housekeeping_notifications`.
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category'); // new_work_order | urgent_work_order
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
        Schema::dropIfExists('maintenance_notifications');
    }
};
