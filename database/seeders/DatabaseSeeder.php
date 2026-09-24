<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            InitialDataSeeder::class,
            HrFactorySeeder::class,
            SettingsSeeder::class,
            ScrapPricingTiersSeeder::class,
            SupplierProductsSeeder::class,
        ]);

        if (!app()->environment('testing')) {
            $this->call([
                SalesAndPosDataSeeder::class,
            ]);
        }
    }
}
