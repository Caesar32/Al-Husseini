<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $branchNames = [
            'الفرع الرئيسي - جسر السويس',
            'فرع مدينة نصر - مكرم عبيد',
            'فرع التجمع الخامس - التسعين',
            'فرع المهندسين - شارع السودان',
            'فرع المعادي - اللاسلكي',
            'فرع الهرم - ترسا',
            'فرع الدقي - مصدق',
            'فرع شبرا الخيمة - الطريق الزراعي',
        ];

        return [
            'name' => fake()->randomElement($branchNames) . ' - ' . fake()->unique()->numberBetween(10, 999),
            'code' => 'BR-' . fake()->unique()->numberBetween(100, 999),
            'phone' => '01' . fake()->randomElement(['0', '1', '2', '5']) . fake()->numerify('########'),
            'address' => fake()->streetAddress() . '، القاهرة',
            'is_active' => true,
        ];
    }
}
