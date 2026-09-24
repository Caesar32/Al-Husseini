<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ScrapPricingTier;

class ScrapPricingTiersSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'capacity_min_ah'     => 40,
                'capacity_max_ah'     => 55,
                'tier_name'           => 'بطاريات ملاكي صغيرة (40-55 أمبير)',
                'default_scrap_price' => 600.00,
                'is_active'           => true,
            ],
            [
                'capacity_min_ah'     => 56,
                'capacity_max_ah'     => 75,
                'tier_name'           => 'بطاريات ملاكي قياسية ومتوسطة (56-75 أمبير)',
                'default_scrap_price' => 800.00,
                'is_active'           => true,
            ],
            [
                'capacity_min_ah'     => 76,
                'capacity_max_ah'     => 100,
                'tier_name'           => 'بطاريات سيارات دفع رباعي وميكروباص (76-100 أمبير)',
                'default_scrap_price' => 1100.00,
                'is_active'           => true,
            ],
            [
                'capacity_min_ah'     => 101,
                'capacity_max_ah'     => 150,
                'tier_name'           => 'بطاريات نصف نقل وجامبو (101-150 أمبير)',
                'default_scrap_price' => 1700.00,
                'is_active'           => true,
            ],
            [
                'capacity_min_ah'     => 151,
                'capacity_max_ah'     => 225,
                'tier_name'           => 'بطاريات نقل ثقيل وتريلات ومعدات (151-225 أمبير)',
                'default_scrap_price' => 2500.00,
                'is_active'           => true,
            ],
        ];

        foreach ($tiers as $tier) {
            ScrapPricingTier::updateOrCreate(
                [
                    'capacity_min_ah' => $tier['capacity_min_ah'],
                    'capacity_max_ah' => $tier['capacity_max_ah'],
                ],
                $tier
            );
        }
    }
}
