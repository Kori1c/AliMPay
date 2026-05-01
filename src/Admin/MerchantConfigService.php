<?php

namespace AliMPay\Admin;

use AliMPay\Core\WebAuthn;
use Exception;

class MerchantConfigService
{
    public static function save(string $file, array $config): void
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new Exception('Failed to encode merchant configuration');
        }

        if (file_put_contents($file, $json, LOCK_EX) === false) {
            throw new Exception('Failed to save merchant configuration');
        }

        @chmod($file, 0640);
    }

    public static function isDefaultAdminPassword(array $config): bool
    {
        if (array_key_exists('admin_password_is_default', $config)) {
            return (bool)$config['admin_password_is_default'];
        }

        return isset($config['admin_password']) && hash_equals((string)$config['admin_password'], 'admin');
    }

    public static function verifyAdminPassword(string $password, array &$config): bool
    {
        if ($password === '') {
            return false;
        }

        if (!empty($config['admin_password_hash'])) {
            return password_verify($password, $config['admin_password_hash']);
        }

        $legacyPassword = (string)($config['admin_password'] ?? 'admin');
        if (!hash_equals($legacyPassword, $password)) {
            return false;
        }

        $config['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $config['admin_password_is_default'] = hash_equals($password, 'admin');
        unset($config['admin_password']);

        return true;
    }

    public static function authConfig(array $config): array
    {
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $auth['password_login_enabled'] = array_key_exists('password_login_enabled', $auth)
            ? (bool)$auth['password_login_enabled']
            : true;
        $auth['passkeys'] = WebAuthn::validPasskeys(is_array($auth['passkeys'] ?? null) ? $auth['passkeys'] : []);

        return $auth;
    }

    public static function passkeySummaries(array $config): array
    {
        return array_map([WebAuthn::class, 'publicKeySummary'], self::authConfig($config)['passkeys']);
    }

    public static function passwordLoginAllowed(array $config): bool
    {
        $auth = self::authConfig($config);
        return $auth['password_login_enabled'] || count($auth['passkeys']) === 0;
    }

    public static function authStatusPayload(array $config): array
    {
        $auth = self::authConfig($config);
        return [
            'password_login_enabled' => self::passwordLoginAllowed($config),
            'password_login_configured' => $auth['password_login_enabled'],
            'passkey_enabled' => count($auth['passkeys']) > 0,
            'passkey_count' => count($auth['passkeys']),
            'passkeys' => self::passkeySummaries($config),
            'rp_id' => WebAuthn::relyingPartyId(),
        ];
    }
}
