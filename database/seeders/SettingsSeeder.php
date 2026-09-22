<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General Company Information
            'company_name' => ['value' => 'مجموعة الحسيني لتجارة وتوزيع بطاريات السيارات', 'group' => 'general'],
            'company_short_name' => ['value' => 'الحسيني Al-Husseini', 'group' => 'general'],
            'company_tax_id' => ['value' => '456-789-123', 'group' => 'general'],
            'company_cr_id' => ['value' => '18920-دمياط', 'group' => 'general'],
            'company_phone' => ['value' => '0572400000', 'group' => 'general'],
            'company_mobile' => ['value' => '01011111111', 'group' => 'general'],
            'company_email' => ['value' => 'info@alhusseini.com', 'group' => 'general'],
            'company_address' => ['value' => 'شارع المحجوب، دمياط الجديدة، محافظة دمياط', 'group' => 'general'],
            'currency' => ['value' => 'ج.م', 'group' => 'general'],

            // HR & Working Schedule Defaults
            'default_grace_period' => ['value' => '15', 'group' => 'hr'],
            'default_shift_start' => ['value' => '09:00', 'group' => 'hr'],
            'default_shift_end' => ['value' => '17:00', 'group' => 'hr'],
            'monthly_working_days' => ['value' => '26', 'group' => 'hr'],
            'daily_working_hours' => ['value' => '8', 'group' => 'hr'],
            'overtime_rate_multiplier' => ['value' => '1.5', 'group' => 'hr'],

            // Sales & Financial Policies
            'vat_percentage' => ['value' => '14', 'group' => 'sales'],
            'invoice_prefix' => ['value' => 'INV-', 'group' => 'sales'],
            'scrap_prefix' => ['value' => 'SCR-', 'group' => 'sales'],
            'warranty_months_default' => ['value' => '12', 'group' => 'sales'],
            'allow_negative_stock' => ['value' => '0', 'group' => 'sales'],

            // Security & Notifications
            'lateness_alert_enabled' => ['value' => '1', 'group' => 'security'],
            'session_timeout_minutes' => ['value' => '120', 'group' => 'security'],
            'email_notifications_enabled' => ['value' => '1', 'group' => 'security'],
        ];

        foreach ($settings as $key => $data) {
            Setting::firstOrCreate(
                ['key' => $key],
                [
                    'value' => $data['value'],
                    'group' => $data['group'],
                ]
            );
        }
    }
}
