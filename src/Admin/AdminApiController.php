<?php

namespace AliMPay\Admin;

use AliMPay\Core\AppInfo;
use AliMPay\Core\AlipayClient;
use AliMPay\Core\BillQuery;
use AliMPay\Core\CodePay;
use AliMPay\Core\PaymentMonitor;
use AliMPay\Core\WebAuthn;
use AliMPay\Utils\Logger;
use Exception;

class AdminApiController
{
    public function dispatch(string $action): void
    {
        $codePay = new CodePay();
        Logger::getInstance();
        $db = $codePay->getDb();
        $orderTimeoutSeconds = AdminConfigService::orderTimeoutSeconds();
        $expiredOrderThreshold = AdminConfigService::expiredOrderThreshold($orderTimeoutSeconds);

        $merchantConfigFile = AdminConfigService::projectRoot() . '/config/codepay.json';
        $merchantConfig = json_decode(file_get_contents($merchantConfigFile), true);

        $action = $action !== '' ? $action : ($_GET['action'] ?? $_POST['action'] ?? '');

        // Login rate limiting
        if ($action === 'login' || $action === 'passkey_login_verify') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $now = time();
            $lockFile = AdminConfigService::projectRoot() . '/login_attempts.json';
            $attempts = file_exists($lockFile) ? json_decode(file_get_contents($lockFile), true) : [];
            $attempts = is_array($attempts) ? $attempts : [];

    $attempts[$ip] = array_filter($attempts[$ip] ?? [], fn($t) => $now - $t < 600);
    if (count($attempts[$ip]) >= 5) {
        $earliest = min($attempts[$ip]);
        $remaining = 600 - ($now - $earliest);
        AdminResponse::json(['success' => false, 'message' => "登录失败次数过多，请 {$remaining} 秒后再试"], 429);
    }
}

// Authentication check
if (!AdminSecurity::isPublicAction($action) && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    AdminResponse::json(['success' => false, 'message' => 'Unauthorized'], 401);
}

// CSRF validation for state-changing operations
if (!AdminSecurity::isPublicAction($action) && AdminSecurity::requiresCsrfValidation($action)) {
    if (!AdminSecurity::validateCsrfToken()) {
        AdminResponse::json(['success' => false, 'message' => 'CSRF token validation failed, please refresh the page'], 403);
    }
}

// Generate CSRF token for logged-in users
if (!AdminSecurity::isPublicAction($action) && $action !== '') {
    AdminSecurity::generateCsrfToken();
}

if (!AdminSecurity::isPublicAction($action)) {
    OrderAdminService::markExpiredOrders($db, $expiredOrderThreshold);
}

try {
    switch ($action) {
        case 'auth_status':
            AdminResponse::json(['success' => true, 'data' => MerchantConfigService::authStatusPayload($merchantConfig)]);
            break;

        case 'login':
            if (!MerchantConfigService::passwordLoginAllowed($merchantConfig)) {
                AdminResponse::json(['success' => false, 'message' => '密码登录已关闭，请使用 Passkey 登录'], 403);
            }

            $password = $_POST['password'] ?? '';
            if (MerchantConfigService::verifyAdminPassword($password, $merchantConfig)) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $merchantConfig['last_login'] = date('Y-m-d H:i:s');
                unset($attempts[$ip]);
                file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
                MerchantConfigService::save($merchantConfigFile, $merchantConfig);
                AdminResponse::json(['success' => true, 'is_default_password' => MerchantConfigService::isDefaultAdminPassword($merchantConfig)]);
            } else {
                $attempts[$ip][] = $now;
                file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
                $remaining = 5 - count($attempts[$ip]);
                AdminResponse::json(['success' => false, 'message' => "密码错误，还可尝试 {$remaining} 次"]);
            }
            break;

        case 'passkey_login_options':
            $auth = MerchantConfigService::authConfig($merchantConfig);
            if (count($auth['passkeys']) === 0) {
                AdminResponse::json(['success' => false, 'message' => '尚未注册 Passkey，请先使用密码登录后添加'], 400);
            }
            $challenge = WebAuthn::createChallenge();
            $_SESSION['passkey_login_challenge'] = $challenge;
            AdminResponse::json(['success' => true, 'data' => WebAuthn::authenticationOptions($challenge, $auth['passkeys'])]);
            break;

        case 'passkey_login_verify':
            $challenge = (string)($_SESSION['passkey_login_challenge'] ?? '');
            if ($challenge === '') {
                throw new Exception('Passkey 登录请求已过期，请重试');
            }
            $credential = AdminSecurity::readJsonBody();
            $auth = MerchantConfigService::authConfig($merchantConfig);
            try {
                $verified = WebAuthn::verifyAuthentication($credential, $auth['passkeys'], $challenge);
            } catch (Exception $e) {
                $attempts[$ip][] = $now;
                file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
                AdminResponse::json(['success' => false, 'message' => 'Passkey 登录失败，请重试'], 401);
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
            MerchantConfigService::save($merchantConfigFile, $merchantConfig);
            AdminSecurity::generateCsrfToken();
            AdminResponse::json(['success' => true, 'data' => MerchantConfigService::authStatusPayload($merchantConfig)]);
            break;

        case 'logout':
            session_destroy();
            AdminResponse::json(['success' => true]);
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
                'recent_orders' => OrderAdminService::attachPaymentPageUrls($db->select('codepay_orders', '*', [
                    'ORDER' => ['add_time' => 'DESC'],
                    'LIMIT' => 5
                ]), $orderTimeoutSeconds)
            ];
            AdminResponse::json(['success' => true, 'data' => $stats]);
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

            $orders = OrderAdminService::attachPaymentPageUrls($db->select('codepay_orders', '*', $where), $orderTimeoutSeconds);

            AdminResponse::json([
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

            AdminResponse::json(['success' => true]);
            break;

        case 'get_config':
            $alipayConfig = AdminConfigService::loadAlipayConfigFresh();
            $maskedConfig = $alipayConfig;
            $maskedConfig['private_key'] = '********';
            $maskedConfig['alipay_public_key'] = '********';
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

            AdminResponse::json([
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
                    'auth' => MerchantConfigService::authStatusPayload($merchantConfig)
                ]
            ]);
            break;

        case 'get_system_info':
            AdminResponse::json(['success' => true, 'data' => AppInfo::get()]);
            break;

        case 'download_diagnostics':
            AdminResponse::jsonDownload(
                DiagnosticsService::buildPayload($db, $merchantConfig),
                'alimpay_diagnostics_' . date('Ymd_His') . '.json'
            );
            break;

        case 'save_config':
            $newAlipayConfig = json_decode($_POST['config'] ?? '', true);
            if (empty($newAlipayConfig)) {
                throw new Exception('Invalid configuration data');
            }

            $currentConfig = AdminConfigService::loadAlipayConfigFresh();

            $skipKeys = ['private_key', 'alipay_public_key'];

            foreach ($newAlipayConfig as $key => $value) {
                if (in_array($key, $skipKeys) && $value === '********') {
                    continue;
                }
                if (in_array($key, $skipKeys, true) && is_string($value)) {
                    $value = AdminConfigService::normalizeAlipayKeyValue($value);
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
            if (file_put_contents(AdminConfigService::alipayConfigPath(), $configContent, LOCK_EX) === false) {
                throw new Exception('Failed to save Alipay configuration');
            }
            AdminConfigService::invalidateAlipayConfigCache();

            AdminResponse::json([
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
            MerchantConfigService::save($merchantConfigFile, $merchantConfig);
            AdminResponse::json(['success' => true, 'data' => ['merchant_key' => $newKey]]);
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
            MerchantConfigService::save($merchantConfigFile, $merchantConfig);
            AdminResponse::json(['success' => true]);
            break;

        case 'passkey_register_options':
            $auth = MerchantConfigService::authConfig($merchantConfig);
            $challenge = WebAuthn::createChallenge();
            $_SESSION['passkey_register_challenge'] = $challenge;
            AdminResponse::json(['success' => true, 'data' => WebAuthn::registrationOptions($challenge, $auth['passkeys'])]);
            break;

        case 'passkey_register_verify':
            $challenge = (string)($_SESSION['passkey_register_challenge'] ?? '');
            if ($challenge === '') {
                throw new Exception('Passkey 注册请求已过期，请重试');
            }

            $request = AdminSecurity::readJsonBody();
            $credential = is_array($request['credential'] ?? null) ? $request['credential'] : $request;
            $name = trim((string)($request['name'] ?? ''));
            $name = $name !== '' ? mb_substr($name, 0, 40) : 'Passkey';

            $registered = WebAuthn::verifyRegistration($credential, $challenge);
            unset($_SESSION['passkey_register_challenge']);

            $auth = MerchantConfigService::authConfig($merchantConfig);
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
            MerchantConfigService::save($merchantConfigFile, $merchantConfig);
            AdminResponse::json(['success' => true, 'data' => MerchantConfigService::authStatusPayload($merchantConfig)]);
            break;

        case 'passkey_delete':
            $credentialId = (string)($_POST['id'] ?? '');
            if ($credentialId === '') {
                throw new Exception('缺少 Passkey ID');
            }
            $auth = MerchantConfigService::authConfig($merchantConfig);
            $auth['passkeys'] = array_values(array_filter(
                $auth['passkeys'],
                static fn($key) => !hash_equals((string)$key['id'], $credentialId)
            ));
            if (!$auth['password_login_enabled'] && count($auth['passkeys']) === 0) {
                throw new Exception('纯 Passkey 模式下至少保留一个 Passkey');
            }
            $merchantConfig['auth'] = $auth;
            MerchantConfigService::save($merchantConfigFile, $merchantConfig);
            AdminResponse::json(['success' => true, 'data' => MerchantConfigService::authStatusPayload($merchantConfig)]);
            break;

        case 'save_auth_settings':
            $passwordLoginEnabled = (int)($_POST['password_login_enabled'] ?? 1) === 1;
            $auth = MerchantConfigService::authConfig($merchantConfig);
            if (!$passwordLoginEnabled && count($auth['passkeys']) === 0) {
                throw new Exception('关闭密码登录前请至少注册一个 Passkey');
            }
            $auth['password_login_enabled'] = $passwordLoginEnabled;
            $merchantConfig['auth'] = $auth;
            MerchantConfigService::save($merchantConfigFile, $merchantConfig);
            AdminResponse::json(['success' => true, 'data' => MerchantConfigService::authStatusPayload($merchantConfig)]);
            break;

        case 'get_logs':
            $type = $_GET['type'] ?? 'info';
            $logFile = "./logs/{$type}.log";
            if (!file_exists($logFile)) {
                AdminResponse::json(['success' => true, 'data' => 'No logs found.']);
            }

            $lines = file($logFile);
            $lastLines = array_slice($lines, -100);
            AdminResponse::json(['success' => true, 'data' => implode('', $lastLines)]);
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

            $targetPath = AdminConfigService::businessQrPath();
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('经营码保存失败，请检查目录权限');
            }
            @chmod($targetPath, 0640);

            clearstatcache(true, $targetPath);
            AdminResponse::json(['success' => true, 'message' => '经营码已更新']);
            break;

        case 'get_qrcode':
            $qrPath = AdminConfigService::businessQrPath();
            $exists = file_exists($qrPath);
            $size = $exists ? filesize($qrPath) : 0;
            $mtime = $exists ? date('Y-m-d H:i:s', filemtime($qrPath)) : null;
            $version = $exists ? filemtime($qrPath) . '-' . $size : time();
            AdminResponse::json([
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
            $backup = BackupService::create('manual');
            AdminResponse::json([
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
            BackupService::stream((string)($_GET['file'] ?? ''));
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

            $restoreResult = BackupService::restore($file['tmp_name']);
            AdminResponse::json([
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
                AdminConfigService::updateMonitorStatusFile('completed', 'Manual monitoring cycle completed from admin panel');
            } catch (\Exception $e) {
                AdminConfigService::updateMonitorStatusFile('error', 'Manual monitoring cycle failed from admin panel', $e->getMessage());
                throw $e;
            }
            AdminResponse::json(['success' => true, 'message' => '账单轮询已完成']);
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
	                            'message' => AdminConfigService::formatAlipayTestError($e->getMessage())
	                        ];
	                    }
                }

                $result['success'] = !empty(array_filter($result['checks'], fn($c) => $c['status'] === 'ok'));
            } catch (\Exception $e) {
                $result['success'] = false;
                $result['checks'][] = [
	                    'name' => '初始化',
	                    'status' => 'fail',
	                    'message' => AdminConfigService::formatAlipayTestError($e->getMessage())
	                ];
            }
	            AdminResponse::json($result);

	        default:
	            AdminResponse::json(['success' => false, 'message' => 'Invalid action'], 400);
	    }
	} catch (Exception $e) {
	    AdminResponse::json(['success' => false, 'message' => $e->getMessage()], 500);
	}
    }
}
