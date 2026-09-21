<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        $departments = ['المبيعات وصالة العرض', 'ورشة فحص وصيانة البطاريات', 'فنيو الكهرباء والتركيب', 'خدمة الطوارئ والإنقاذ المتنقل', 'المخازن وسلاسل الإمداد', 'الإدارة والحسابات'];

        return [
            'name' => fake()->randomElement($departments) . ' ' . fake()->unique()->numberBetween(1, 999),
            'code' => 'DEP-' . fake()->unique()->numberBetween(10, 999),
        ];
    }
}
