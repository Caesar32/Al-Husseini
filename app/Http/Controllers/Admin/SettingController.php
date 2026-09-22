<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * عرض شاشة الإعدادات مصنفة بالتبويبات
     */
    public function index()
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        return view('admin.settings.index', compact('settings'));
    }

    /**
     * حفظ وتحديث الإعدادات
     */
    public function update(Request $request)
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_phone' => ['required', 'string', 'max:50'],
            'currency' => ['required', 'string', 'max:20'],
            'vat_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_grace_period' => ['nullable', 'integer', 'min:0', 'max:120'],
            'monthly_working_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            'daily_working_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
        ], [
            'company_name.required' => 'يرجى إدخال اسم المنشأة / الشركة.',
            'company_phone.required' => 'يرجى إدخال رقم الهاتف الرئيسي.',
            'currency.required' => 'يرجى إدخال رمز العملة المستخدمة.',
        ]);

        $settingsData = $request->except(['_token', '_method']);

        // معالجة مربعات الاختيار (Checkboxes) غير المحددة
        $checkboxes = ['allow_negative_stock', 'lateness_alert_enabled', 'email_notifications_enabled'];
        foreach ($checkboxes as $cb) {
            $settingsData[$cb] = $request->has($cb) ? '1' : '0';
        }

        foreach ($settingsData as $key => $value) {
            $group = match (true) {
                str_starts_with($key, 'company_') || $key === 'currency' => 'general',
                str_contains($key, 'grace') || str_contains($key, 'shift') || str_contains($key, 'working') || str_contains($key, 'overtime') => 'hr',
                str_contains($key, 'vat') || str_contains($key, 'invoice') || str_contains($key, 'scrap') || str_contains($key, 'warranty') || str_contains($key, 'stock') => 'sales',
                default => 'security',
            };

            Setting::set($key, $value, $group);
        }

        return redirect()->back()->with('status', 'تم حفظ وتحديث إعدادات النظام بنجاح.');
    }
}
