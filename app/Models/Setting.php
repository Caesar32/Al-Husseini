<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'group',
    ];

    /**
     * جلب قيمة إعداد معين مع دعم التخزين المؤقت (Cache)
     */
    /**
     * Numeric setting with a safe fallback: returns $default when the stored value is missing,
     * non-numeric or outside [$min, $max]. Defaults equal the values the code used before the
     * setting was wired, so an unconfigured system behaves exactly as before.
     */
    public static function number(string $key, float $default, float $min, float $max): float
    {
        $value = static::get($key);

        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            return $default;
        }

        return (float) $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Only the stored value is cached; a missing key is not cached (null), so the caller's
        // default applies per call instead of the first caller's default being cached forever.
        $value = Cache::rememberForever("setting.{$key}", fn () => static::where('key', $key)->value('value'));

        return $value ?? $default;
    }

    /**
     * حفظ أو تحديث إعداد معين وتحديث الذاكرة المؤقتة
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'group' => $group,
            ]
        );

        Cache::forget("setting.{$key}");
        Cache::forget("settings.group.{$group}");

        return $setting;
    }

    /**
     * جلب كافة إعدادات مجموعة معينة
     */
    public static function getGroup(string $group): array
    {
        return Cache::rememberForever("settings.group.{$group}", function () use ($group) {
            return static::where('group', $group)->pluck('value', 'key')->toArray();
        });
    }

    /**
     * حفظ مصفوفة إعدادات دفعة واحدة
     */
    public static function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value, $group);
        }
    }
}
