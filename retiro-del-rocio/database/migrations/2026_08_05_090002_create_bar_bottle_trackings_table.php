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
        Schema::create('bar_bottle_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bar_inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('bottle_size'); // e.g. "750ml", "1L"
            $table->boolean('opened')->default(false);
            $table->unsignedTinyInteger('remaining_percent')->default(100);
            $table->timestamps();

            $table->index('bar_inventory_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_bottle_trackings');
    }
};
