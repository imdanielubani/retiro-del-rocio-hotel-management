<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            ['label' => 'Fitness Lounge', 'icon' => 'fitness'],
            ['label' => '2 Beds', 'icon' => 'bed'],
            ['label' => 'Wifi', 'icon' => 'wifi'],
            ['label' => 'Pool', 'icon' => 'pool'],
            ['label' => 'Restaurant', 'icon' => 'restaurant'],
            ['label' => 'Parking', 'icon' => 'parking'],
            ['label' => 'Complimentary Breakfast', 'icon' => 'breakfast'],
        ];

        $gallery = [
            'images/image 7bg.jpg', 'images/images 2.jpg', 'images/images 8.jpg',
            'images/images 10.jpg', 'images/images 9.jpg', 'images/images 12.jpg',
        ];

        $description = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed sed lacus luctus dolor volutpat aliquet eget ut dolor. Duis diam nisl, maximus ac egestas eget, porta eget ipsum. Fusce tristique erat elit, sit amet pretium nisi consectetur sed. Integer sagittis fermentum semper. Duis metus erat,';

        $rooms = [
            ['name' => "Pandora's Suite", 'type' => 'Rocio Loft Residence', 'price' => 350000, 'featured_image' => 'images/image 8.jpg'],
            ['name' => 'Alba Suite',      'type' => 'Alba Suites',          'price' => 420000, 'featured_image' => 'images/image 9.jpg'],
            ['name' => 'Rocio Loft',      'type' => 'Rocio Rooms',          'price' => 300000, 'featured_image' => 'images/image 10.jpg'],
            ['name' => 'Brisa Residence', 'type' => 'Brisa Residence',      'price' => 510000, 'featured_image' => 'images/image 2.jpg'],
        ];

        foreach ($rooms as $i => $data) {
            Room::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                array_merge($data, [
                    'beds' => 2,
                    'guests' => 2,
                    'sqft' => 45,
                    'bathrooms' => 1,
                    'short_description' => 'Luxury '.$data['type'].' with curated comfort.',
                    'description' => $description,
                    'amenities' => $amenities,
                    'gallery' => $gallery,
                    'is_published' => true,
                    'sort_order' => $i,
                ])
            );
        }
    }
}
