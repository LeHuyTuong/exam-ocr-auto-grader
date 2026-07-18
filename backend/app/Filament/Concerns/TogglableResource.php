<?php

namespace App\Filament\Concerns;

use App\Support\Settings;

/**
 * Resource có thể bật/tắt từ trang Cài đặt.
 *
 * Khi toggle đang TẮT:
 *  - shouldRegisterNavigation() = false  -> ẩn khỏi sidebar.
 *  - canAccess() = false                  -> chặn cả URL trực tiếp (403).
 *
 * Resource dùng trait override 2 static method:
 *   protected static function navigationToggleKey(): ?string  -> setting key
 *   protected static function navigationToggleDefault(): bool -> giá trị khi chưa set
 *
 * (Dùng method thay vì property để tránh trait/class property collision trong PHP 8.3+.)
 */
trait TogglableResource
{
    protected static function navigationToggleKey(): ?string
    {
        return null;
    }

    protected static function navigationToggleDefault(): bool
    {
        return true;
    }

    protected static function isToggleEnabled(): bool
    {
        $key = static::navigationToggleKey();
        if ($key === null) {
            return true;
        }

        return Settings::getBool($key, static::navigationToggleDefault());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation() && static::isToggleEnabled();
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && static::isToggleEnabled();
    }
}
