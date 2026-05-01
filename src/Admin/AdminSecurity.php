<?php

namespace AliMPay\Admin;

use Exception;

class AdminSecurity
{
    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        return $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);
    }

    public static function requiresCsrfValidation(string $action): bool
    {
        return in_array($action, [
            'save_config',
            'save_merchant',
            'regenerate_merchant_key',
            'update_order_status',
            'upload_qrcode',
            'create_backup',
            'restore_backup',
            'passkey_register_options',
            'passkey_register_verify',
            'passkey_delete',
            'save_auth_settings',
            'logout',
        ], true);
    }

    public static function isPublicAction(string $action): bool
    {
        return in_array($action, ['login', 'auth_status', 'passkey_login_options', 'passkey_login_verify'], true);
    }

    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);
        if (!is_array($data)) {
            throw new Exception('Invalid JSON request body');
        }

        return $data;
    }
}
