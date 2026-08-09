<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['name' => 'Continental Breakfast', 'category' => 'breakfast', 'price' => 6500, 'prep_minutes' => 15,
                'description' => 'Fresh pastries, seasonal fruit, yoghurt and your choice of juice.'],
            ['name' => 'Full English Breakfast', 'category' => 'breakfast', 'price' => 8500, 'prep_minutes' => 20,
                'description' => 'Eggs, bacon, sausage, grilled tomato, beans and toast.'],
            ['name' => 'Suya Spring Rolls', 'category' => 'starters', 'price' => 5500, 'prep_minutes' => 15,
                'description' => 'Crisp spring rolls with a spiced suya beef filling and pepper dip.'],
            ['name' => 'Peppered Snails', 'category' => 'starters', 'price' => 7000, 'prep_minutes' => 20,
                'description' => 'Grilled bush snails tossed in a fiery pepper sauce.'],
            ['name' => 'Pan-Seared Atlantic Salmon', 'category' => 'mains', 'price' => 9000, 'prep_minutes' => 25,
                'description' => 'Pan-seared salmon finished with a citrus butter sauce and seasonal vegetables.'],
            ['name' => 'Wagyu Beef Tenderloin', 'category' => 'mains', 'price' => 15000, 'prep_minutes' => 35,
                'description' => 'Premium A5 Wagyu tenderloin, slow-roasted and finished with black truffle jus and seasonal vegetables.'],
            ['name' => 'Jollof Rice & Grilled Chicken', 'category' => 'mains', 'price' => 8000, 'prep_minutes' => 25,
                'description' => 'Smoky party-style jollof rice with a whole grilled chicken thigh.'],
            ['name' => "Chef's Seafood Okra", 'category' => 'specials', 'price' => 12000, 'prep_minutes' => 30,
                'description' => "The chef's own recipe — fresh prawns, crab and okra in a rich palm-oil sauce, served with pounded yam."],
            ['name' => 'Retiro Signature Platter', 'category' => 'specials', 'price' => 18000, 'prep_minutes' => 40,
                'description' => "A rotating chef's-choice tasting platter, not on the regular menu — ask your server for tonight's selection."],
            ['name' => 'Chocolate Lava Cake', 'category' => 'desserts', 'price' => 4500, 'prep_minutes' => 15,
                'description' => 'Warm chocolate cake with a molten centre, served with vanilla ice cream.'],
            ['name' => 'Zobo Panna Cotta', 'category' => 'desserts', 'price' => 4000, 'prep_minutes' => 10,
                'description' => 'A silky panna cotta infused with hibiscus and ginger.'],
            ['name' => 'Fresh Chapman', 'category' => 'drinks', 'price' => 3500, 'prep_minutes' => 8,
                'description' => 'The classic Nigerian mocktail — citrus, grenadine and a splash of bitters.'],
            ['name' => 'House Red Wine', 'category' => 'drinks', 'price' => 6000, 'prep_minutes' => 5,
                'description' => 'A glass of our house-selected red wine.'],
            ['name' => 'Plantain Chips', 'category' => 'snacks', 'price' => 2500, 'prep_minutes' => 10,
                'description' => 'Crisp fried plantain chips, lightly salted.'],
            ['name' => 'Chicken Suya Skewers', 'category' => 'snacks', 'price' => 4500, 'prep_minutes' => 15,
                'description' => 'Grilled chicken skewers rubbed in traditional suya spice.'],
        ];

        foreach ($items as $i => $item) {
            MenuItem::updateOrCreate(
                ['slug' => Str::slug($item['name'])],
                [
                    'name' => $item['name'],
                    'category' => $item['category'],
                    'price' => $item['price'],
                    'prep_minutes' => $item['prep_minutes'],
                    'description' => $item['description'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ],
            );
        }
    }
}
