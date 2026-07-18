<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Helper đọc/ghi cài đặt (bảng `settings`) — cache trong request, flush khi ghi.
 *
 * Giá trị lưu dạng JSON (hỗ trợ bool/int/string/array). Đọc qua get/getBool.
 */
class Settings
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (static::$cache === null) {
            static::$cache = Setting::pluck('value', 'key')->all();
        }

        return static::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        static::flush();
    }

    public static function flush(): void
    {
        static::$cache = null;
    }
}
