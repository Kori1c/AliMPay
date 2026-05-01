<?php

namespace AliMPay\Admin;

class AdminConfigService
{
    public static function alipayConfigPath(): string
    {
        return self::projectRoot() . '/config/alipay.php';
    }

    public static function invalidateAlipayConfigCache(): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate(self::alipayConfigPath(), true);
        }

        clearstatcache(true, self::alipayConfigPath());
    }

    public static function loadAlipayConfigFresh(): array
    {
        self::invalidateAlipayConfigCache();
        return require self::alipayConfigPath();
    }

    public static function normalizeAlipayKeyValue(string $value): string
    {
        $value = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----/', '', $value);
        return preg_replace('/\s+/', '', $value ?? '');
    }

    public static function formatAlipayTestError(string $message): string
    {
        if (stripos($message, 'sign check fail') !== false) {
            return '本地校验支付宝响应签名失败：账单接口已经返回响应，但当前“支付宝公钥”无法验签。请填写支付宝开放平台提供的“支付宝公钥”，不要填写应用公钥、商户密钥或 32 位通信密钥。';
        }

        if (stripos($message, 'Invalid Alipay configuration') !== false) {
            return '支付宝配置无效：请补全 AppID、应用私钥、支付宝公钥和网关地址。';
        }

        $message = preg_replace('/sign=[^,\]]+/i', 'sign=***', $message);
        $message = preg_replace('/respBody=\{.*\}/s', 'respBody={...}', $message);

        return mb_strlen($message) > 240 ? mb_substr($message, 0, 240) . '...' : $message;
    }

    public static function updateMonitorStatusFile(string $status, string $message, ?string $error = null): void
    {
        $dataDir = self::projectRoot() . '/data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $payload = [
            'status' => $status,
            'last_run' => time(),
            'last_run_formatted' => date('Y-m-d H:i:s'),
            'message' => $message,
            'updated_by' => 'admin_api',
        ];

        if ($error !== null) {
            $payload['last_error'] = $error;
        }

        file_put_contents($dataDir . '/monitor_status.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function orderTimeoutSeconds(): int
    {
        $alipayConfig = require self::alipayConfigPath();
        return max(1, (int)($alipayConfig['payment']['order_timeout'] ?? 300));
    }

    public static function expiredOrderThreshold(int $timeoutSeconds): string
    {
        return date('Y-m-d H:i:s', time() - $timeoutSeconds);
    }

    public static function adminBaseUrl(): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }

    public static function businessQrPath(): string
    {
        $alipayConfig = require self::alipayConfigPath();
        $path = $alipayConfig['payment']['business_qr_mode']['qr_code_path'] ?? (self::projectRoot() . '/qrcode/business_qr.png');

        if ($path === '') {
            return self::projectRoot() . '/qrcode/business_qr.png';
        }

        $path = realpath($path) ?: $path;
        if ($path === '' || $path[0] !== '/') {
            $path = self::projectRoot() . '/' . ltrim($path, './');
        }

        $projectRoot = realpath(self::projectRoot());
        $resolvedPath = realpath(dirname($path)) ?: dirname($path);
        if ($projectRoot === false || strpos($resolvedPath, $projectRoot) !== 0) {
            return self::projectRoot() . '/qrcode/business_qr.png';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif'], true)) {
            return self::projectRoot() . '/qrcode/business_qr.png';
        }

        return $path;
    }

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
