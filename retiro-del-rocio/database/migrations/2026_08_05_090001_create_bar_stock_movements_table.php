<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every stock movement — receiving new stock, issuing it out (sale,
     * damage, complimentary, transfer, expired — consumption is an "out"
     * movement with `reason = sale` and a `linked_order`), and manual
     * adjustments — is a row here. `bar_inventory_items.current_stock` is
     * derived from this ledger (see BarStockMovement::booted()), so the two
     * can never drift apart the way separate mutable counters would.
     */
    public function up(): void
    {
        Schema::create('bar_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bar_inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // in | out | adjustment_increase | adjustment_decrease
            $table->unsignedInteger('quantity');
            $table->string('reason')->nullable(); // stock in: free text; stock out: sale|damage|complimentary|transfer|expired
            $table->unsignedInteger('unit_cost')->nullable(); // naira, stock in
            $table->string('reference')->nullable(); // invoice/reference, stock in
            $table->string('supplier')->nullable(); // stock in
            $table->string('staff_name')->nullable();
            $table->string('linked_order')->nullable(); // consumption tracking
            $table->text('notes')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['bar_inventory_item_id', 'type']);
            $table->index('occurred_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bar_stock_movements');
    }
};
