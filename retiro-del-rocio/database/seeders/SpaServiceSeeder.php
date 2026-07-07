<?php

namespace Database\Seeders;

use App\Models\SpaCategory;
use App\Models\SpaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpaServiceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Massage', 'color' => '#16a34a'],
            ['name' => 'Skin Care', 'color' => '#be185d'],
            ['name' => 'Sauna Baths', 'color' => '#d97706'],
            ['name' => 'Manicure', 'color' => '#7c3aed'],
        ];
        foreach ($categories as $i => $c) {
            SpaCategory::updateOrCreate(
                ['slug' => Str::slug($c['name'])],
                ['name' => $c['name'], 'color' => $c['color'], 'sort_order' => $i + 1],
            );
        }

        $cat = fn ($name) => SpaCategory::where('slug', Str::slug($name))->value('id');

        $services = [
            ['name' => 'Skin Care', 'price' => 50000, 'duration_minutes' => 60, 'category' => 'Skin Care', 'image' => 'images/skincare.png', 'icon' => 'images/products.png',
                'description' => 'Revitalising facials and skincare treatments to leave you glowing and refreshed.'],
            ['name' => 'Manicure & Pedicure', 'price' => 70000, 'duration_minutes' => 45, 'category' => 'Manicure', 'image' => 'images/manicure.png', 'icon' => 'images/treatments.png',
                'description' => 'Expert nail and hand care for beautifully groomed hands and feet.'],
            ['name' => 'Sauna Baths', 'price' => 50000, 'duration_minutes' => 45, 'category' => 'Sauna Baths', 'image' => 'images/sauna.jpg', 'icon' => 'images/ic_twotone-spa.png',
                'description' => 'Detoxify and unwind in our serene, heat-soothing sauna experience.'],
            ['name' => 'Massage', 'price' => 98000, 'duration_minutes' => 60, 'category' => 'Massage', 'image' => 'images/massage.png', 'icon' => 'images/temaki_spa.png',
                'description' => 'Therapeutic full-body massage to release tension and restore balance.'],
        ];

        foreach ($services as $i => $s) {
            SpaService::updateOrCreate(
                ['slug' => Str::slug($s['name'])],
                [
                    'name' => $s['name'],
                    'spa_category_id' => $cat($s['category']),
                    'price' => $s['price'],
                    'duration_minutes' => $s['duration_minutes'],
                    'description' => $s['description'],
                    'image' => $s['image'],
                    'icon' => $s['icon'],
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ],
            );
        }
    }
}
