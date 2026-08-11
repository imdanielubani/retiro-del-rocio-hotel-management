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
        Schema::create('bar_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('sku')->nullable();
            $table->string('unit')->default('bottle'); // bottle | can | carton | ml | litre
            $table->string('brand')->nullable();
            $table->string('supplier')->nullable();
            $table->unsignedInteger('cost_price')->default(0); // naira
            $table->unsignedInteger('selling_price')->default(0); // naira
            $table->unsignedInteger('current_stock')->default(0);
            $table->unsignedInteger('minimum_stock_level')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_inventory_items');
    }
};
