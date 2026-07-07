<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->unsignedInteger('price')->default(0); // naira, per trip
            $table->unsignedSmallInteger('seats')->default(4);
            $table->unsignedSmallInteger('suitcases')->default(3);
            $table->string('status')->default('available'); // available | in_use | maintenance
            $table->boolean('free_cancellation')->default(true);
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
