<?php

namespace AliMPay\Admin;

use AliMPay\Core\AppInfo;
use Medoo\Medoo;

class DiagnosticsService
{
    public static function buildPayload(Medoo $db, array $merchantConfig): array
    {
        $root = AdminConfigService::projectRoot();
        $alipayConfig = AdminConfigService::loadAlipayConfigFresh();
        $selfCheckFile = $root . '/data/self_check_status.json';
        $selfCheck = is_file($selfCheckFile) ? json_decode((string)file_get_contents($selfCheckFile), true) : null;
        if (is_array($selfCheck)) {
            unset($selfCheck['checks'], $selfCheck['base_url']);
        }

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'app' => AppInfo::get(),
            'runtime' => [
                'extensions' => [
                    'gd' => extension_loaded('gd'),
                    'openssl' => extension_loaded('openssl'),
                    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
                    'zip' => extension_loaded('zip'),
                ],
                'paths' => [
                    'config' => self::runtimePathStatus($root . '/config'),
                    'data' => self::runtimePathStatus($root . '/data'),
                    'logs' => self::runtimePathStatus($root . '/logs'),
                    'qrcode' => self::runtimePathStatus($root . '/qrcode'),
                ],
            ],
            'config' => [
                'alipay' => self::summarizeAlipayConfig($alipayConfig),
                'merchant' => self::summarizeMerchantConfig($merchantConfig),
                'auth' => array_diff_key(MerchantConfigService::authStatusPayload($merchantConfig), ['passkeys' => true]),
            ],
            'orders' => [
                'total' => $db->count('codepay_orders'),
                'unpaid' => $db->count('codepay_orders', ['status' => 0]),
                'paid' => $db->count('codepay_orders', ['status' => 1]),
                'expired' => $db->count('codepay_orders', ['status' => 2]),
            ],
            'self_check' => is_array($selfCheck) ? $selfCheck : ['status' => 'unknown'],
            'logs' => [
                'error_tail' => self::recentLogTail($root . '/logs/error.log'),
                'info_tail' => self::recentLogTail($root . '/logs/info.log', 20),
            ],
        ];
    }

    private static function runtimePathStatus(string $path): array
    {
        return [
            'exists' => file_exists($path),
            'is_dir' => is_dir($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
        ];
    }

    private static function recentLogTail(string $file, int $maxLines = 30): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        return is_array($lines) ? array_slice($lines, -$maxLines) : [];
    }

    private static function summarizeAlipayConfig(array $config): array
    {
        return [
            'server_url' => $config['server_url'] ?? '',
            'app_id_configured' => trim((string)($config['app_id'] ?? '')) !== '',
            'private_key_configured' => trim((string)($config['private_key'] ?? '')) !== '',
            'alipay_public_key_configured' => trim((string)($config['alipay_public_key'] ?? '')) !== '',
            'transfer_user_id_configured' => trim((string)($config['transfer_user_id'] ?? '')) !== '',
            'sign_type' => $config['sign_type'] ?? '',
            'payment' => [
                'business_qr_mode_enabled' => (bool)($config['payment']['business_qr_mode']['enabled'] ?? false),
                'anti_risk_url_enabled' => (bool)($config['payment']['anti_risk_url']['enabled'] ?? false),
                'auto_cleanup' => (bool)($config['payment']['auto_cleanup'] ?? false),
                'order_timeout' => (int)($config['payment']['order_timeout'] ?? 0),
                'check_interval' => (int)($config['payment']['check_interval'] ?? 0),
            ],
        ];
    }

    private static function summarizeMerchantConfig(array $config): array
    {
        return [
            'schema_version' => (int)($config['schema_version'] ?? 0),
            'merchant_id_configured' => trim((string)($config['merchant_id'] ?? '')) !== '',
            'merchant_key_configured' => trim((string)($config['merchant_key'] ?? '')) !== '',
            'admin_password_configured' => trim((string)($config['admin_password_hash'] ?? $config['admin_password'] ?? '')) !== '',
            'status' => (int)($config['status'] ?? 0),
            'rate' => (string)($config['rate'] ?? ''),
            'created_at' => $config['created_at'] ?? '',
            'last_login' => $config['last_login'] ?? '',
        ];
    }
}
