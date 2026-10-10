<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class ProductCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'بطاريات', 'slug' => 'batteries'],
            ['name' => 'زيوت وشحوم', 'slug' => 'oils-and-lubricants'],
            ['name' => 'قطع غيار', 'slug' => 'spare-parts'],
            ['name' => 'مياه تبريد', 'slug' => 'coolants'],
            ['name' => 'مياه مساحات', 'slug' => 'washer-fluid'],
            ['name' => 'إكسسوار', 'slug' => 'accessories'],
            ['name' => 'مساحات', 'slug' => 'wipers'],
            ['name' => 'لمبات', 'slug' => 'bulbs'],
            ['name' => 'فلاتر زيت', 'slug' => 'oil-filters'],
            ['name' => 'فلاتر هواء', 'slug' => 'air-filters'],
            ['name' => 'سيور', 'slug' => 'belts'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['name' => $cat['name']],
                ['slug' => $cat['slug']]
            );
        }
    }
}
