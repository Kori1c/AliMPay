<?php

namespace AliMPay\Core;

class AppInfo
{
    public static function get(): array
    {
        return [
            'name' => 'AliMPay',
            'version' => self::envValue('ALIMPAY_VERSION', 'dev'),
            'commit' => self::envValue('ALIMPAY_COMMIT', 'unknown'),
            'build_time' => self::envValue('ALIMPAY_BUILD_TIME', 'unknown'),
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'timezone' => date_default_timezone_get(),
        ];
    }

    private static function envValue(string $key, string $fallback): string
    {
        $value = getenv($key);
        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }
}
