<?php

namespace App\Models\Suite;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'group'])]
class ResortSetting extends Model
{
    protected $table = 'room_resort_settings';

    /**
     * Get a setting value by key, with optional cache.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("resort_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set or create a setting value.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        Cache::forget("resort_setting_{$key}");

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group]
        );
    }
}
