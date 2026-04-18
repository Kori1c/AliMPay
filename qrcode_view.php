<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit;
}

$qrPath = __DIR__ . '/qrcode/business_qr.png';
$config = require __DIR__ . '/config/alipay.php';
$configuredPath = $config['payment']['business_qr_mode']['qr_code_path'] ?? $qrPath;
if ($configuredPath !== '' && $configuredPath[0] !== '/') {
    $configuredPath = __DIR__ . '/' . ltrim($configuredPath, './');
}
$qrPath = $configuredPath;

if (!file_exists($qrPath)) {
    http_response_code(404);
    exit;
}

$imageInfo = getimagesize($qrPath);
header('Content-Type: ' . ($imageInfo['mime'] ?? 'application/octet-stream'));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
readfile($qrPath);
