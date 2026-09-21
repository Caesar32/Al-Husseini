<?php

namespace Database\Factories;

use App\Models\JobTitle;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JobTitle>
 */
class JobTitleFactory extends Factory
{
    protected $model = JobTitle::class;

    public function definition(): array
    {
        $titles = [
            'فني أول فحص وشحن بطاريات',
            'كهربائي سيارات وتشخيص دينامو',
            'فني إنقاذ بطاريات سريع (طوارئ طريق)',
            'مسؤول مبيعات واستقبال عملاء',
            'أمين مخزن بطاريات وكهنة',
            'مشرف فرع وصالة مبيعات',
            'محاسب فرع ومسؤول خزانة',
        ];

        return [
            'department_id' => Department::factory(),
            'title' => fake()->randomElement($titles),
            'min_salary' => 6000,
            'max_salary' => 20000,
        ];
    }
}
