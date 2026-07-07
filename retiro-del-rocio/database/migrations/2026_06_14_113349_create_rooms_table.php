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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->nullable();
            $table->unsignedInteger('price')->default(0); // naira
            $table->unsignedSmallInteger('beds')->default(1);
            $table->unsignedSmallInteger('guests')->default(2);
            $table->unsignedSmallInteger('sqft')->default(0);
            $table->unsignedSmallInteger('bathrooms')->default(1);
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->json('amenities')->nullable();       // [{label, icon}]
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable();          // [path, ...]
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
