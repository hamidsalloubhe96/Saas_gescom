<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

/** Réglages de la plateforme (clé / valeur), avec petit cache. */
class PlatformSetting extends CentralModel
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('platform_settings', 60, fn () => static::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return in_array((string) static::get($key, $default ? '1' : '0'), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        Cache::forget('platform_settings');
    }
}
