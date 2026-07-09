<?php

use App\Models\RestaurantTable;
use Illuminate\Database\Migrations\Migration;

/**
 * Give every existing dining table / lounge space a photo so the guest-facing
 * picker and the admin cards show images instead of the fallback icon.
 *
 * These four files live in public/images (tracked in git, so they ship inside
 * the Docker image) and are stored as "images/…" paths, which
 * RestaurantTable::imageUrl() resolves via asset() + WebP.
 *
 * Only rows with no image are touched, so a photo uploaded through
 * Admin → Restaurant → Tables/Lounge is never overwritten by a re-run.
 */
return new class extends Migration
{
    private const IMAGES = [
        'images/3365.jpg',
        'images/1494.jpg',
        'images/2724.jpg',
        'images/12375.jpg',
    ];

    public function up(): void
    {
        $rows = RestaurantTable::whereNull('image')
            ->orWhere('image', '')
            ->orderBy('area')->orderBy('sort_order')->orderBy('id')
            ->get();

        foreach ($rows->values() as $i => $table) {
            $table->forceFill(['image' => self::IMAGES[$i % count(self::IMAGES)]])->save();
        }
    }

    public function down(): void
    {
        // Clear only the images this migration could have set; leave uploads alone.
        RestaurantTable::whereIn('image', self::IMAGES)->update(['image' => null]);
    }
};
