<?php

namespace AliMPay\Core;

class AppInfo
{
    public static function get(): array
    {
        return [
            'name' => 'AliMPay',
            'version' => self::envValue('ALIMPAY_VERSION', self::gitTag() ?: 'dev'),
            'commit' => self::envValue('ALIMPAY_COMMIT', self::gitCommit() ?: 'unknown'),
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

    private static function gitCommit(): ?string
    {
        $root = dirname(__DIR__, 2);
        $head = $root . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $headValue = trim((string)file_get_contents($head));
        if (preg_match('/^[0-9a-f]{40}$/i', $headValue)) {
            return substr($headValue, 0, 12);
        }

        if (str_starts_with($headValue, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($headValue, 5);
            if (is_file($refPath)) {
                $commit = trim((string)file_get_contents($refPath));
                return preg_match('/^[0-9a-f]{40}$/i', $commit) ? substr($commit, 0, 12) : null;
            }
        }

        return null;
    }

    private static function gitTag(): ?string
    {
        $ref = getenv('GITHUB_REF_NAME');
        if (is_string($ref) && preg_match('/^v\d+\.\d+\.\d+/', $ref)) {
            return $ref;
        }

        return null;
    }
}
