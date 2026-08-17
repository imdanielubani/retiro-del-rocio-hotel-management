<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One command a scene fires against one device, in `sort_order`. `command`
 * takes the same normalized shape a direct device command does, e.g.
 * {"switch": true, "bright": 80}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_scene_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smart_scene_id')->constrained('smart_scenes')->cascadeOnDelete();
            $table->foreignId('smart_device_id')->constrained('smart_devices')->cascadeOnDelete();
            $table->json('command');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_scene_actions');
    }
};
