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
        Schema::create('parts_requests', function (Blueprint $table) {
            $table->id();
            // Cascades — same as `work_order_attachments`: a parts request has
            // no meaning once its order is gone.
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('part_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('note')->nullable();
            $table->string('status')->default('pending'); // pending | fulfilled | denied
            $table->string('requested_by')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts_requests');
    }
};
