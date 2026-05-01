#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['password::', 'clear-passkeys', 'help']);
if (isset($options['help'])) {
    printUsage();
    exit(0);
}

$configFile = __DIR__ . '/../config/codepay.json';
if (!is_file($configFile)) {
    fwrite(STDERR, "config/codepay.json not found. Start AliMPay once before running this script.\n");
    exit(1);
}

$config = json_decode((string)file_get_contents($configFile), true);
if (!is_array($config)) {
    fwrite(STDERR, "config/codepay.json is not valid JSON.\n");
    exit(1);
}

$newPassword = (string)($options['password'] ?? '');
$clearPasskeys = array_key_exists('clear-passkeys', $options);

$auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
$auth['password_login_enabled'] = true;
if (!isset($auth['passkeys']) || !is_array($auth['passkeys'])) {
    $auth['passkeys'] = [];
}
if ($clearPasskeys) {
    $auth['passkeys'] = [];
}

$config['auth'] = $auth;

if ($newPassword !== '') {
    if (strlen($newPassword) < 6) {
        fwrite(STDERR, "Password must be at least 6 characters.\n");
        exit(1);
    }

    $config['admin_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $config['admin_password_is_default'] = hash_equals($newPassword, 'admin');
    unset($config['admin_password']);
}

$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false || file_put_contents($configFile, $json, LOCK_EX) === false) {
    fwrite(STDERR, "Failed to save config/codepay.json.\n");
    exit(1);
}

@chmod($configFile, 0640);

echo "Admin password login is enabled.\n";
echo $clearPasskeys ? "All passkeys have been removed.\n" : "Existing passkeys were kept.\n";
echo $newPassword !== '' ? "Admin password has been updated.\n" : "Admin password was not changed.\n";

function printUsage(): void
{
    echo "Usage:\n";
    echo "  php scripts/reset-admin-auth.php [--password=NEW_PASSWORD] [--clear-passkeys]\n\n";
    echo "Examples:\n";
    echo "  php scripts/reset-admin-auth.php --password=admin123\n";
    echo "  php scripts/reset-admin-auth.php --password=admin123 --clear-passkeys\n";
}
