<?php

require_once './vendor/autoload.php';

use AliMPay\Core\CodePay;
use AliMPay\Utils\Logger;
use AliMPay\Core\AlipayClient;
use AliMPay\Core\BillQuery;
use AliMPay\Core\PaymentMonitor;
use AliMPay\Core\WebAuthn;
use AliMPay\Core\AppInfo;

ob_start();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * 生成 CSRF Token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF Token
 */
function validateCsrfToken(): bool
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($sessionToken)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * 需要 CSRF 验证的操作列表
 */
function requiresCsrfValidation(string $action): bool
{
    $csrfProtectedActions = [
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
    ];
    return in_array($action, $csrfProtectedActions, true);
}

function respondJson(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    // 在响应中包含 CSRF token（用于前端获取）
    if (isset($_SESSION['csrf_token'])) {
        $payload['_csrf_token'] = $_SESSION['csrf_token'];
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$codePay = new CodePay();
$logger = Logger::getInstance();
$db = $codePay->getDb();
$orderTimeoutSeconds = orderTimeoutSeconds();
$expiredOrderThreshold = expiredOrderThreshold($orderTimeoutSeconds);

// Load merchant config for password check
$merchantConfigFile = './config/codepay.json';
$merchantConfig = json_decode(file_get_contents($merchantConfigFile), true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function saveMerchantConfig(string $file, array $config): void
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

function isDefaultAdminPassword(array $config): bool
{
    if (array_key_exists('admin_password_is_default', $config)) {
        return (bool)$config['admin_password_is_default'];
    }

    return isset($config['admin_password']) && hash_equals((string)$config['admin_password'], 'admin');
}

function verifyAdminPassword(string $password, array &$config): bool
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

function merchantAuthConfig(array $config): array
{
    $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
    $auth['password_login_enabled'] = array_key_exists('password_login_enabled', $auth)
        ? (bool)$auth['password_login_enabled']
        : true;
    $auth['passkeys'] = WebAuthn::validPasskeys(is_array($auth['passkeys'] ?? null) ? $auth['passkeys'] : []);

    return $auth;
}

function passkeySummaries(array $config): array
{
    return array_map([WebAuthn::class, 'publicKeySummary'], merchantAuthConfig($config)['passkeys']);
}

function passwordLoginAllowed(array $config): bool
{
    $auth = merchantAuthConfig($config);
    return $auth['password_login_enabled'] || count($auth['passkeys']) === 0;
}

function authStatusPayload(array $config): array
{
    $auth = merchantAuthConfig($config);
    return [
        'password_login_enabled' => passwordLoginAllowed($config),
        'password_login_configured' => $auth['password_login_enabled'],
        'passkey_enabled' => count($auth['passkeys']) > 0,
        'passkey_count' => count($auth['passkeys']),
        'passkeys' => passkeySummaries($config),
        'rp_id' => WebAuthn::relyingPartyId(),
    ];
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        throw new Exception('Invalid JSON request body');
    }

    return $data;
}

function isPublicAdminAction(string $action): bool
{
    return in_array($action, ['login', 'auth_status', 'passkey_login_options', 'passkey_login_verify'], true);
}

function runtimePathStatus(string $path): array
{
    return [
        'exists' => file_exists($path),
        'is_dir' => is_dir($path),
        'readable' => is_readable($path),
        'writable' => is_writable($path),
    ];
}

function recentLogTail(string $file, int $maxLines = 30): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    return array_slice($lines, -$maxLines);
}

function buildDiagnosticsPayload(\Medoo\Medoo $db, array $merchantConfig): array
{
    $alipayConfig = loadAlipayConfigFresh();
    $selfCheckFile = __DIR__ . '/data/self_check_status.json';
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
                'config' => runtimePathStatus(__DIR__ . '/config'),
                'data' => runtimePathStatus(__DIR__ . '/data'),
                'logs' => runtimePathStatus(__DIR__ . '/logs'),
                'qrcode' => runtimePathStatus(__DIR__ . '/qrcode'),
            ],
        ],
        'config' => [
            'alipay' => summarizeAlipayConfig($alipayConfig),
            'merchant' => summarizeMerchantConfig($merchantConfig),
            'auth' => array_diff_key(authStatusPayload($merchantConfig), ['passkeys' => true]),
        ],
        'orders' => [
            'total' => $db->count('codepay_orders'),
            'unpaid' => $db->count('codepay_orders', ['status' => 0]),
            'paid' => $db->count('codepay_orders', ['status' => 1]),
            'expired' => $db->count('codepay_orders', ['status' => 2]),
        ],
        'self_check' => is_array($selfCheck) ? $selfCheck : ['status' => 'unknown'],
        'logs' => [
            'error_tail' => recentLogTail(__DIR__ . '/logs/error.log'),
            'info_tail' => recentLogTail(__DIR__ . '/logs/info.log', 20),
        ],
    ];
}

function summarizeAlipayConfig(array $config): array
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

function summarizeMerchantConfig(array $config): array
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

function streamDiagnostics(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $fileName = 'alimpay_diagnostics_' . date('Ymd_His') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function formatAlipayTestError(string $message): string
{
    if (stripos($message, 'sign check fail') !== false) {
        return '本地校验支付宝响应签名失败：账单接口已经返回响应，但当前“支付宝公钥”无法验签。请填写支付宝开放平台提供的“支付宝公钥”，不要填写应用公钥、商户密钥或 32 位通信密钥。';
    }

    if (stripos($message, 'Invalid Alipay configuration') !== false) {
        return '支付宝配置无效：请补全 AppID、应用私钥、支付宝公钥和网关地址。';
    }

    $message = preg_replace('/sign=[^,\\]]+/i', 'sign=***', $message);
    $message = preg_replace('/respBody=\\{.*\\}/s', 'respBody={...}', $message);

    return mb_strlen($message) > 240 ? mb_substr($message, 0, 240) . '...' : $message;
}

function normalizeAlipayKeyValue(string $value): string
{
    $value = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----/', '', $value);
    return preg_replace('/\s+/', '', $value ?? '');
}

function alipayConfigPath(): string
{
    return __DIR__ . '/config/alipay.php';
}

function invalidateAlipayConfigCache(): void
{
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate(alipayConfigPath(), true);
    }

    clearstatcache(true, alipayConfigPath());
}

function loadAlipayConfigFresh(): array
{
    invalidateAlipayConfigCache();
    return require alipayConfigPath();
}

function updateMonitorStatusFileFromAdmin(string $status, string $message, ?string $error = null): void
{
    $dataDir = __DIR__ . '/data';
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

function orderTimeoutSeconds(): int
{
    $alipayConfig = require __DIR__ . '/config/alipay.php';
    return max(1, (int)($alipayConfig['payment']['order_timeout'] ?? 300));
}

function expiredOrderThreshold(int $timeoutSeconds): string
{
    return date('Y-m-d H:i:s', time() - $timeoutSeconds);
}

function isOrderExpiredForAdmin(array $order, int $timeoutSeconds): bool
{
    $status = (int)($order['status'] ?? 0);
    if ($status === 2) {
        return true;
    }

    if ($status !== 0 || empty($order['add_time'])) {
        return false;
    }

    $createdAt = strtotime((string)$order['add_time']);
    return $createdAt !== false && $createdAt < time() - $timeoutSeconds;
}

function adminBaseUrl(): string
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function businessQrPath(): string
{
    $alipayConfig = require __DIR__ . '/config/alipay.php';
    $path = $alipayConfig['payment']['business_qr_mode']['qr_code_path'] ?? (__DIR__ . '/qrcode/business_qr.png');

    if ($path === '') {
        return __DIR__ . '/qrcode/business_qr.png';
    }

    // 规范化路径
    $path = realpath($path) ?: $path;

    // 如果是相对路径，转换为绝对路径
    if ($path === '' || $path[0] !== '/') {
        $path = __DIR__ . '/' . ltrim($path, './');
    }

    // 安全检查：确保路径在项目目录内
    $projectRoot = realpath(__DIR__);
    $resolvedPath = realpath(dirname($path)) ?: dirname($path);

    // 防止路径穿越
    if (strpos($resolvedPath, $projectRoot) !== 0) {
        // 路径在项目目录外，使用默认路径
        return __DIR__ . '/qrcode/business_qr.png';
    }

    // 只允许图片扩展名
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($extension, ['png', 'jpg', 'jpeg', 'gif'], true)) {
        return __DIR__ . '/qrcode/business_qr.png';
    }

    return $path;
}

function backupStorageDir(): string
{
    $dir = __DIR__ . '/data/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    return $dir;
}

function backupSourceMap(): array
{
    return [
        'config/alipay.php' => __DIR__ . '/config/alipay.php',
        'config/codepay.json' => __DIR__ . '/config/codepay.json',
        'data/codepay.db' => __DIR__ . '/data/codepay.db',
        'qrcode/business_qr.png' => businessQrPath(),
    ];
}

function sanitizeBackupName(string $name): string
{
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'backup.zip';
}

function createProjectBackup(string $reason = 'manual'): array
{
    if (!class_exists('ZipArchive')) {
        throw new Exception('当前环境未启用 ZipArchive，无法创建备份');
    }

    $timestamp = date('Ymd_His');
    $fileName = sprintf('alimpay_backup_%s_%s.zip', $reason, $timestamp);
    $filePath = backupStorageDir() . '/' . $fileName;

    $zip = new ZipArchive();
    if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('创建备份文件失败');
    }

    $includedFiles = [];
    foreach (backupSourceMap() as $entryName => $sourcePath) {
        if (!file_exists($sourcePath)) {
            if ($entryName === 'qrcode/business_qr.png') {
                continue;
            }
            $zip->close();
            @unlink($filePath);
            throw new Exception("备份失败，缺少文件：{$entryName}");
        }

        if (!$zip->addFile($sourcePath, $entryName)) {
            $zip->close();
            @unlink($filePath);
            throw new Exception("备份失败，无法写入文件：{$entryName}");
        }

        $includedFiles[] = $entryName;
    }

    $manifest = [
        'project' => 'AliMPay',
        'version' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'reason' => $reason,
        'files' => $includedFiles,
    ];
    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $zip->close();
    @chmod($filePath, 0640);

    return [
        'file_name' => $fileName,
        'file_path' => $filePath,
        'size' => filesize($filePath) ?: 0,
    ];
}

function removeRuntimeLocks(): void
{
    foreach (glob(__DIR__ . '/data/*.lock') ?: [] as $file) {
        @unlink($file);
    }
    foreach (glob(__DIR__ . '/data/order_locks/*.lock') ?: [] as $file) {
        @unlink($file);
    }
}

function restoreProjectBackup(string $uploadedFilePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new Exception('当前环境未启用 ZipArchive，无法恢复备份');
    }

    $zip = new ZipArchive();
    if ($zip->open($uploadedFilePath) !== true) {
        throw new Exception('备份文件无法打开，请确认 zip 是否完整');
    }

    $requiredEntries = [
        'config/alipay.php',
        'config/codepay.json',
        'data/codepay.db',
    ];
    foreach ($requiredEntries as $entry) {
        if ($zip->locateName($entry) === false) {
            $zip->close();
            throw new Exception("备份文件缺少必要内容：{$entry}");
        }
    }

    $tempDir = sys_get_temp_dir() . '/alimpay_restore_' . bin2hex(random_bytes(8));
    if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
        $zip->close();
        throw new Exception('创建恢复临时目录失败');
    }

    try {
        if (!$zip->extractTo($tempDir)) {
            throw new Exception('解压备份文件失败');
        }
        $zip->close();

        $preRestoreBackup = createProjectBackup('pre_restore');

        $targetMap = [
            'config/alipay.php' => __DIR__ . '/config/alipay.php',
            'config/codepay.json' => __DIR__ . '/config/codepay.json',
            'data/codepay.db' => __DIR__ . '/data/codepay.db',
            'qrcode/business_qr.png' => businessQrPath(),
        ];

        foreach ($targetMap as $entryName => $targetPath) {
            $sourcePath = $tempDir . '/' . $entryName;
            if (!file_exists($sourcePath)) {
                continue;
            }

            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new Exception("恢复失败，无法写入文件：{$entryName}");
            }

            @chmod($targetPath, str_ends_with($targetPath, '.php') ? 0644 : 0640);
        }

        invalidateAlipayConfigCache();
        clearstatcache();
        removeRuntimeLocks();

        return [
            'pre_restore_backup' => $preRestoreBackup['file_name'],
        ];
    } finally {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($tempDir);
    }
}

function streamBackupFile(string $fileName): void
{
    $safeFileName = sanitizeBackupName($fileName);
    $filePath = backupStorageDir() . '/' . $safeFileName;
    if (!is_file($filePath)) {
        throw new Exception('备份文件不存在');
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/zip');
    header('Content-Length: ' . (string)filesize($filePath));
    header('Content-Disposition: attachment; filename="' . rawurlencode($safeFileName) . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($filePath);
    exit;
}

function markExpiredOrdersForAdmin(\Medoo\Medoo $db, string $expiredThreshold): void
{
    $db->update('codepay_orders', ['status' => 2], [
        'status' => 0,
        'add_time[<]' => $expiredThreshold
    ]);
}

function attachPaymentPageUrls(array $orders, int $timeoutSeconds): array
{
    foreach ($orders as &$order) {
        $isExpired = isOrderExpiredForAdmin($order, $timeoutSeconds);
        $isPaid = (int)($order['status'] ?? 0) === 1;

        $order['is_expired'] = $isExpired;
        $order['display_status'] = $isPaid ? 'paid' : ($isExpired ? 'expired' : 'pending');
        $order['status_label'] = $isPaid ? '已支付' : ($isExpired ? '已过期' : '待支付');
        $order['payment_page_url'] = null;
        if ($isExpired || $isPaid || empty($order['out_trade_no']) || empty($order['status_token'])) {
            continue;
        }

        $order['payment_page_url'] = adminBaseUrl() . '/submit.php?' . http_build_query([
            'resume_payment' => 1,
            'out_trade_no' => $order['out_trade_no'],
            'status_token' => $order['status_token']
        ]);
    }
    unset($order);

    return $orders;
}

// Login rate limiting
if ($action === 'login' || $action === 'passkey_login_verify') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $lockFile = __DIR__ . '/login_attempts.json';
    $attempts = file_exists($lockFile) ? json_decode(file_get_contents($lockFile), true) : [];
    $attempts = is_array($attempts) ? $attempts : [];

    $attempts[$ip] = array_filter($attempts[$ip] ?? [], fn($t) => $now - $t < 600);
    if (count($attempts[$ip]) >= 5) {
        $earliest = min($attempts[$ip]);
        $remaining = 600 - ($now - $earliest);
        respondJson(['success' => false, 'message' => "登录失败次数过多，请 {$remaining} 秒后再试"], 429);
    }
}

// Authentication check
if (!isPublicAdminAction($action) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    respondJson(['success' => false, 'message' => 'Unauthorized'], 401);
}

// CSRF validation for state-changing operations
if (!isPublicAdminAction($action) && requiresCsrfValidation($action)) {
    if (!validateCsrfToken()) {
        respondJson(['success' => false, 'message' => 'CSRF token validation failed, please refresh the page'], 403);
    }
}

// Generate CSRF token for logged-in users
if (!isPublicAdminAction($action) && $action !== '') {
    generateCsrfToken();
}

if (!isPublicAdminAction($action)) {
    markExpiredOrdersForAdmin($db, $expiredOrderThreshold);
}

try {
    switch ($action) {
        case 'auth_status':
            respondJson(['success' => true, 'data' => authStatusPayload($merchantConfig)]);
            break;

        case 'login':
            if (!passwordLoginAllowed($merchantConfig)) {
                respondJson(['success' => false, 'message' => '密码登录已关闭，请使用 Passkey 登录'], 403);
            }

            $password = $_POST['password'] ?? '';
            if (verifyAdminPassword($password, $merchantConfig)) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $merchantConfig['last_login'] = date('Y-m-d H:i:s');
                unset($attempts[$ip]);
                file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
                saveMerchantConfig($merchantConfigFile, $merchantConfig);
                respondJson(['success' => true, 'is_default_password' => isDefaultAdminPassword($merchantConfig)]);
            } else {
                $attempts[$ip][] = $now;
                file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
                $remaining = 5 - count($attempts[$ip]);
                respondJson(['success' => false, 'message' => "密码错误，还可尝试 {$remaining} 次"]);
            }
            break;

        case 'passkey_login_options':
            $auth = merchantAuthConfig($merchantConfig);
            if (count($auth['passkeys']) === 0) {
                respondJson(['success' => false, 'message' => '尚未注册 Passkey，请先使用密码登录后添加'], 400);
            }
            $challenge = WebAuthn::createChallenge();
            $_SESSION['passkey_login_challenge'] = $challenge;
            respondJson(['success' => true, 'data' => WebAuthn::authenticationOptions($challenge, $auth['passkeys'])]);
            break;

        case 'passkey_login_verify':
            $challenge = (string)($_SESSION['passkey_login_challenge'] ?? '');
            if ($challenge === '') {
                throw new Exception('Passkey 登录请求已过期，请重试');
            }
            $credential = readJsonBody();
            $auth = merchantAuthConfig($merchantConfig);
            try {
                $verified = WebAuthn::verifyAuthentication($credential, $auth['passkeys'], $challenge);
            } catch (Exception $e) {
                $attempts[$ip][] = $now;
                file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
                respondJson(['success' => false, 'message' => 'Passkey 登录失败，请重试'], 401);
            }
            unset($_SESSION['passkey_login_challenge']);

            foreach ($auth['passkeys'] as &$passkey) {
                if (hash_equals((string)$passkey['id'], $verified['id'])) {
                    $passkey['sign_count'] = $verified['sign_count'];
                    $passkey['last_used_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($passkey);

            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $merchantConfig['auth'] = $auth;
            $merchantConfig['last_login'] = date('Y-m-d H:i:s');
            unset($attempts[$ip]);
            file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
            saveMerchantConfig($merchantConfigFile, $merchantConfig);
            generateCsrfToken();
            respondJson(['success' => true, 'data' => authStatusPayload($merchantConfig)]);
            break;

        case 'logout':
            session_destroy();
            respondJson(['success' => true]);
            break;

        case 'get_stats':
            $today = date('Y-m-d 00:00:00');
            $yesterday = date('Y-m-d 00:00:00', strtotime('-1 day'));

            $stats = [
                'today_revenue' => $db->sum('codepay_orders', 'payment_amount', [
                    'status' => 1,
                    'pay_time[>=]' => $today
                ]) ?: 0,
                'yesterday_revenue' => $db->sum('codepay_orders', 'payment_amount', [
                    'status' => 1,
                    'pay_time[>=]' => $yesterday,
                    'pay_time[<]' => $today
                ]) ?: 0,
                'total_revenue' => $db->sum('codepay_orders', 'payment_amount', ['status' => 1]) ?: 0,
                'order_counts' => [
                    'unpaid' => $db->count('codepay_orders', ['status' => 0]),
                    'expired' => $db->count('codepay_orders', ['status' => 2]),
                    'paid' => $db->count('codepay_orders', ['status' => 1]),
                    'total' => $db->count('codepay_orders')
                ],
                'recent_orders' => attachPaymentPageUrls($db->select('codepay_orders', '*', [
                    'ORDER' => ['add_time' => 'DESC'],
                    'LIMIT' => 5
                ]), $orderTimeoutSeconds)
            ];
            respondJson(['success' => true, 'data' => $stats]);
            break;

        case 'get_orders':
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';

            $where = [];
            if ($search) {
                $where['OR'] = [
                    'out_trade_no[~]' => $search,
                    'id[~]' => $search,
                    'name[~]' => $search
                ];
            }
            if ($status === 'expired') {
                $where['status'] = 2;
            } elseif ($status === '0') {
                $where['status'] = 0;
            } elseif ($status !== '') {
                $where['status'] = (int)$status;
            }

            $totalCount = $db->count('codepay_orders', $where);

            $where['ORDER'] = ['add_time' => 'DESC'];
            $where['LIMIT'] = [($page - 1) * $limit, $limit];

            $orders = attachPaymentPageUrls($db->select('codepay_orders', '*', $where), $orderTimeoutSeconds);

            respondJson([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'pagination' => [
                        'total' => $totalCount,
                        'page' => $page,
                        'limit' => $limit,
                        'total_pages' => ceil($totalCount / $limit)
                    ]
                ]
            ]);
            break;

        case 'update_order_status':
            $id = $_POST['id'] ?? '';
            $status = (int)($_POST['status'] ?? 0);

            $db->update('codepay_orders', [
                'status' => $status,
                'pay_time' => $status === 1 ? date('Y-m-d H:i:s') : null
            ], ['id' => $id]);

            respondJson(['success' => true]);
            break;

        case 'get_config':
            $alipayConfig = loadAlipayConfigFresh();
            $maskedConfig = $alipayConfig;
            $maskedConfig['private_key'] = '********';
            $maskedConfig['alipay_public_key'] = '********';
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

            respondJson([
                'success' => true,
                'data' => [
                    'alipay' => $maskedConfig,
                    'merchant' => [
                        'merchant_id' => $merchantConfig['merchant_id'] ?? '',
                        'merchant_key' => '********',
                        'created_at' => $merchantConfig['created_at'] ?? '',
                        'rate' => $merchantConfig['rate'] ?? '96',
                        'admin_password' => '********',
                        'status' => $merchantConfig['status'] ?? 1,
                    ],
                    'auth' => authStatusPayload($merchantConfig)
                ]
            ]);
            break;

        case 'get_system_info':
            respondJson(['success' => true, 'data' => AppInfo::get()]);
            break;

        case 'download_diagnostics':
            streamDiagnostics(buildDiagnosticsPayload($db, $merchantConfig));
            break;

        case 'save_config':
            $newAlipayConfig = json_decode($_POST['config'] ?? '', true);
            if (empty($newAlipayConfig)) {
                throw new Exception('Invalid configuration data');
            }

            $currentConfig = loadAlipayConfigFresh();

            $skipKeys = ['private_key', 'alipay_public_key'];

            foreach ($newAlipayConfig as $key => $value) {
                if (in_array($key, $skipKeys) && $value === '********') {
                    continue;
                }
                if (in_array($key, $skipKeys, true) && is_string($value)) {
                    $value = normalizeAlipayKeyValue($value);
                }
                if (isset($currentConfig[$key]) && is_array($currentConfig[$key]) && is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        if (is_array($subValue)) {
                            foreach ($subValue as $ssKey => $ssValue) {
                                $currentConfig[$key][$subKey][$ssKey] = $ssValue;
                            }
                        } else {
                            $currentConfig[$key][$subKey] = $subValue;
                        }
                    }
                } else {
                    $currentConfig[$key] = $value;
                }
            }

            $configContent = "<?php\n\nreturn " . var_export($currentConfig, true) . ";\n";
            if (file_put_contents(alipayConfigPath(), $configContent, LOCK_EX) === false) {
                throw new Exception('Failed to save Alipay configuration');
            }
            invalidateAlipayConfigCache();

            respondJson([
                'success' => true,
                'data' => [
                    'business_qr_mode_enabled' => (bool)($currentConfig['payment']['business_qr_mode']['enabled'] ?? false),
                    'anti_risk_url_enabled' => (bool)($currentConfig['payment']['anti_risk_url']['enabled'] ?? false),
                    'auto_cleanup_enabled' => (bool)($currentConfig['payment']['auto_cleanup'] ?? false),
                ]
            ]);
            break;

        case 'regenerate_merchant_key':
            $newKey = bin2hex(random_bytes(16));
            $merchantConfig['merchant_key'] = $newKey;
            saveMerchantConfig($merchantConfigFile, $merchantConfig);
            respondJson(['success' => true, 'data' => ['merchant_key' => $newKey]]);
            break;

        case 'save_merchant':
            $newPassword = $_POST['admin_password'] ?? '';
            if (isset($_POST['rate'])) {
                $rate = (float)$_POST['rate'];
                if ($rate < 0 || $rate > 100) {
                    throw new Exception('商户费率必须在 0 到 100 之间');
                }
                $merchantConfig['rate'] = (string)$rate;
            }
            if (isset($_POST['status'])) {
                $merchantConfig['status'] = (int)$_POST['status'] === 1 ? 1 : 0;
            }
            if ($newPassword !== '' && $newPassword !== '********') {
                $merchantConfig['admin_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $merchantConfig['admin_password_is_default'] = hash_equals($newPassword, 'admin');
                unset($merchantConfig['admin_password']);
            }
            saveMerchantConfig($merchantConfigFile, $merchantConfig);
            respondJson(['success' => true]);
            break;

        case 'passkey_register_options':
            $auth = merchantAuthConfig($merchantConfig);
            $challenge = WebAuthn::createChallenge();
            $_SESSION['passkey_register_challenge'] = $challenge;
            respondJson(['success' => true, 'data' => WebAuthn::registrationOptions($challenge, $auth['passkeys'])]);
            break;

        case 'passkey_register_verify':
            $challenge = (string)($_SESSION['passkey_register_challenge'] ?? '');
            if ($challenge === '') {
                throw new Exception('Passkey 注册请求已过期，请重试');
            }

            $request = readJsonBody();
            $credential = is_array($request['credential'] ?? null) ? $request['credential'] : $request;
            $name = trim((string)($request['name'] ?? ''));
            $name = $name !== '' ? mb_substr($name, 0, 40) : 'Passkey';

            $registered = WebAuthn::verifyRegistration($credential, $challenge);
            unset($_SESSION['passkey_register_challenge']);

            $auth = merchantAuthConfig($merchantConfig);
            foreach ($auth['passkeys'] as $existing) {
                if (hash_equals((string)$existing['id'], $registered['id'])) {
                    throw new Exception('这个 Passkey 已经注册过');
                }
            }

            $auth['passkeys'][] = [
                'id' => $registered['id'],
                'name' => $name,
                'public_key' => $registered['public_key'],
                'sign_count' => $registered['sign_count'],
                'created_at' => date('Y-m-d H:i:s'),
                'last_used_at' => null,
            ];
            $merchantConfig['auth'] = $auth;
            saveMerchantConfig($merchantConfigFile, $merchantConfig);
            respondJson(['success' => true, 'data' => authStatusPayload($merchantConfig)]);
            break;

        case 'passkey_delete':
            $credentialId = (string)($_POST['id'] ?? '');
            if ($credentialId === '') {
                throw new Exception('缺少 Passkey ID');
            }
            $auth = merchantAuthConfig($merchantConfig);
            $auth['passkeys'] = array_values(array_filter(
                $auth['passkeys'],
                static fn($key) => !hash_equals((string)$key['id'], $credentialId)
            ));
            if (!$auth['password_login_enabled'] && count($auth['passkeys']) === 0) {
                throw new Exception('纯 Passkey 模式下至少保留一个 Passkey');
            }
            $merchantConfig['auth'] = $auth;
            saveMerchantConfig($merchantConfigFile, $merchantConfig);
            respondJson(['success' => true, 'data' => authStatusPayload($merchantConfig)]);
            break;

        case 'save_auth_settings':
            $passwordLoginEnabled = (int)($_POST['password_login_enabled'] ?? 1) === 1;
            $auth = merchantAuthConfig($merchantConfig);
            if (!$passwordLoginEnabled && count($auth['passkeys']) === 0) {
                throw new Exception('关闭密码登录前请至少注册一个 Passkey');
            }
            $auth['password_login_enabled'] = $passwordLoginEnabled;
            $merchantConfig['auth'] = $auth;
            saveMerchantConfig($merchantConfigFile, $merchantConfig);
            respondJson(['success' => true, 'data' => authStatusPayload($merchantConfig)]);
            break;

        case 'get_logs':
            $type = $_GET['type'] ?? 'info';
            $logFile = "./logs/{$type}.log";
            if (!file_exists($logFile)) {
                respondJson(['success' => true, 'data' => 'No logs found.']);
            }

            $lines = file($logFile);
            $lastLines = array_slice($lines, -100);
            respondJson(['success' => true, 'data' => implode('', $lastLines)]);
            break;

        case 'upload_qrcode':
            if (empty($_FILES['qrcode'])) {
                throw new Exception('请选择要上传的图片');
            }

            $file = $_FILES['qrcode'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new Exception('上传失败，请重新选择图片');
            }

            $imageInfo = getimagesize($file['tmp_name']);
            $allowedTypes = ['image/png', 'image/jpeg'];
            if (!$imageInfo || !in_array($imageInfo['mime'], $allowedTypes, true)) {
                throw new Exception('仅支持 PNG / JPG 格式');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new Exception('图片大小不能超过 2MB');
            }

            $targetPath = businessQrPath();
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('经营码保存失败，请检查目录权限');
            }
            @chmod($targetPath, 0640);

            clearstatcache(true, $targetPath);
            respondJson(['success' => true, 'message' => '经营码已更新']);
            break;

        case 'get_qrcode':
            $qrPath = businessQrPath();
            $exists = file_exists($qrPath);
            $size = $exists ? filesize($qrPath) : 0;
            $mtime = $exists ? date('Y-m-d H:i:s', filemtime($qrPath)) : null;
            $version = $exists ? filemtime($qrPath) . '-' . $size : time();
            respondJson([
                'success' => true,
                'data' => [
                    'exists' => $exists,
                    'size' => $size,
                    'modified' => $mtime,
                    'url' => $exists ? './qrcode_view.php?v=' . rawurlencode((string)$version) : null
                ]
            ]);
            break;

        case 'create_backup':
            $backup = createProjectBackup('manual');
            respondJson([
                'success' => true,
                'message' => '备份已生成',
                'data' => [
                    'file_name' => $backup['file_name'],
                    'size' => $backup['size'],
                    'download_url' => './admin_api.php?action=download_backup&file=' . rawurlencode($backup['file_name']),
                ]
            ]);
            break;

        case 'download_backup':
            streamBackupFile((string)($_GET['file'] ?? ''));
            break;

        case 'restore_backup':
            if (empty($_FILES['backup'])) {
                throw new Exception('请选择备份文件');
            }

            $file = $_FILES['backup'];
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new Exception('备份上传失败，请重新选择文件');
            }

            $originalName = (string)($file['name'] ?? 'backup.zip');
            if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
                throw new Exception('仅支持 zip 备份文件');
            }

            if (($file['size'] ?? 0) > 100 * 1024 * 1024) {
                throw new Exception('备份文件不能超过 100MB');
            }

            $restoreResult = restoreProjectBackup($file['tmp_name']);
            respondJson([
                'success' => true,
                'message' => '备份恢复成功',
                'data' => $restoreResult,
            ]);
            break;

        case 'trigger_monitor':
            $alipayClient = new AlipayClient();
            $billQuery = new BillQuery($alipayClient);
            $paymentMonitor = new PaymentMonitor($billQuery, $db, $codePay->getMerchantInfo());
            try {
                $paymentMonitor->runMonitoringCycle();
                updateMonitorStatusFileFromAdmin('completed', 'Manual monitoring cycle completed from admin panel');
            } catch (\Exception $e) {
                updateMonitorStatusFileFromAdmin('error', 'Manual monitoring cycle failed from admin panel', $e->getMessage());
                throw $e;
            }
            respondJson(['success' => true, 'message' => '账单轮询已完成']);
            break;

        case 'test_alipay':
            $result = ['checks' => []];
            try {
                $alipayClient = new AlipayClient();

                $configErrors = $alipayClient->validateConfigDetails();
                $configValid = empty($configErrors);
                $result['checks'][] = [
                    'name' => '配置完整性',
                    'status' => $configValid ? 'ok' : 'fail',
                    'message' => $configValid ? 'AppID、私钥、支付宝公钥格式检查通过' : implode(' ', $configErrors)
                ];

                if ($configValid) {
                    try {
                        $billQuery = new BillQuery($alipayClient);
                        $now = date('Y-m-d H:i:s');
                        $start = date('Y-m-d H:i:s', strtotime('-10 minutes'));
                        $queryResult = $billQuery->queryBills($start, $now);
                        $result['checks'][] = [
                            'name' => '账单 API',
                            'status' => $queryResult['success'] ? 'ok' : 'fail',
                            'message' => $queryResult['success']
                                ? '账单查询成功'
                                : ($queryResult['message'] ?? '查询失败')
                        ];
	                    } catch (\Exception $e) {
	                        $result['checks'][] = [
	                            'name' => '账单 API',
	                            'status' => 'fail',
	                            'message' => formatAlipayTestError($e->getMessage())
	                        ];
	                    }
                }

                $result['success'] = !empty(array_filter($result['checks'], fn($c) => $c['status'] === 'ok'));
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['checks'][] = [
	                    'name' => '初始化',
	                    'status' => 'fail',
	                    'message' => formatAlipayTestError($e->getMessage())
	                ];
            }
	            respondJson($result);

	        default:
	            respondJson(['success' => false, 'message' => 'Invalid action'], 400);
	    }
	} catch (Exception $e) {
	    respondJson(['success' => false, 'message' => $e->getMessage()], 500);
	}
