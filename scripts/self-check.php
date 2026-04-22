#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use AliMPay\Core\CodePay;

date_default_timezone_set('Asia/Shanghai');

const SELF_CHECK_STATUS_FILE = __DIR__ . '/../data/self_check_status.json';

$options = getopt('', ['base-url:', 'mode::', 'write-status']);
$mode = (string)($options['mode'] ?? 'full');
$writeStatus = array_key_exists('write-status', $options);

if (!in_array($mode, ['startup', 'full'], true)) {
    fwrite(STDERR, "Invalid --mode value. Allowed: startup, full\n");
    exit(1);
}

$baseUrl = normalizeBaseUrl((string)($options['base-url'] ?? 'http://127.0.0.1'));
$report = createReport($mode, $baseUrl);

println(sprintf('AliMPay self-check started [%s] %s', strtoupper($mode), $baseUrl));

$alipayConfigPath = projectPath('config/alipay.php');
$merchantConfigPath = projectPath('config/codepay.json');
$runtimeTargets = [
    'config 目录' => projectPath('config'),
    'data 目录' => projectPath('data'),
    'logs 目录' => projectPath('logs'),
    'qrcode 目录' => projectPath('qrcode'),
    'login_attempts.json' => projectPath('login_attempts.json'),
];

foreach ($runtimeTargets as $name => $path) {
    $writable = isRuntimeWritable($path);
    addCheck(
        $report,
        $name . ' 可写',
        $writable ? 'healthy' : 'error',
        $writable ? '运行时写入权限正常' : '当前进程无法写入该路径，请检查容器挂载目录权限',
        ['path' => redactProjectPath($path)]
    );
}

$alipayConfig = loadPhpConfig($alipayConfigPath);
if ($alipayConfig === null) {
    addCheck($report, '支付宝配置文件', 'error', 'config/alipay.php 不存在或无法解析');
    finish($report, $writeStatus);
}

addCheck($report, '支付宝配置文件', 'healthy', 'config/alipay.php 已加载');

$requiredConfigFields = [
    'server_url' => '网关地址',
    'app_id' => 'APP ID',
    'private_key' => '应用私钥',
    'alipay_public_key' => '支付宝公钥',
];

foreach ($requiredConfigFields as $field => $label) {
    $hasValue = trim((string)($alipayConfig[$field] ?? '')) !== '';
    addCheck(
        $report,
        $label,
        $hasValue ? 'healthy' : 'error',
        $hasValue ? $label . ' 已配置' : $label . ' 为空，请先在后台补全'
    );
}

$businessModeEnabled = (bool)($alipayConfig['payment']['business_qr_mode']['enabled'] ?? false);
$businessQrPath = resolveBusinessQrPath($alipayConfig);

if ($businessModeEnabled) {
    $qrExists = is_file($businessQrPath) && is_readable($businessQrPath);
    addCheck(
        $report,
        '经营码图片',
        $qrExists ? 'healthy' : 'error',
        $qrExists ? '经营码图片存在且可读取' : '经营码模式已开启，但未找到可用经营码图片',
        ['path' => redactProjectPath($businessQrPath)]
    );
} else {
    $hasTransferUserId = trim((string)($alipayConfig['transfer_user_id'] ?? '')) !== '';
    addCheck(
        $report,
        '转账用户 ID',
        $hasTransferUserId ? 'healthy' : 'error',
        $hasTransferUserId ? '转账模式所需的收款用户 ID 已配置' : '当前为转账模式，但 transfer_user_id 为空'
    );
}

$codePay = null;
try {
    $codePay = new CodePay();
    $merchantInfo = $codePay->getMerchantInfo();
    addCheck($report, '商户配置与订单库', 'healthy', '商户配置、SQLite 数据库初始化正常');

    $merchantConfig = loadJsonFile($merchantConfigPath);
    if ($merchantConfig === null) {
        addCheck($report, '商户配置文件', 'warning', 'config/codepay.json 尚未生成，系统会在首次初始化时自动创建');
    } else {
        addCheck($report, '商户配置文件', 'healthy', 'config/codepay.json 已就绪');
    }
} catch (Throwable $e) {
    addCheck($report, '商户配置与订单库', 'error', '初始化失败：' . limitMessage($e->getMessage()));
    finish($report, $writeStatus);
}

$healthResponse = httpRequest('GET', $baseUrl . '/health.php?action=status');
if ($healthResponse['network_error'] ?? false) {
    addCheck($report, '健康检查接口', 'error', '无法访问 health.php?action=status：' . $healthResponse['error']);
    finish($report, $writeStatus);
}

$healthPayload = decodeJsonBody($healthResponse['body']);
$healthOk = $healthResponse['status_code'] === 200 && is_array($healthPayload) && ($healthPayload['success'] ?? false) === true;
addCheck(
    $report,
    '健康检查接口',
    $healthOk ? 'healthy' : 'error',
    $healthOk ? '健康检查接口响应正常' : 'health.php?action=status 返回异常结果',
    $healthOk ? [] : ['http_status' => $healthResponse['status_code']]
);

if ($businessModeEnabled) {
    $qrToken = md5('qrcode_access_' . date('Y-m-d'));
    $qrEndpoint = $baseUrl . '/qrcode.php?type=business&token=' . $qrToken;
    $qrResponse = httpRequest('GET', $qrEndpoint);
    $qrOk = !($qrResponse['network_error'] ?? false)
        && $qrResponse['status_code'] === 200
        && str_starts_with((string)($qrResponse['content_type'] ?? ''), 'image/');

    addCheck(
        $report,
        '经营码访问端点',
        $qrOk ? 'healthy' : 'error',
        $qrOk ? '二维码访问端点可正常返回图片' : 'qrcode.php 无法返回有效二维码图片',
        ['http_status' => $qrResponse['status_code'] ?? 0]
    );
} else {
    addCheck($report, '经营码访问端点', 'skipped', '当前为转账模式，跳过经营码图片访问检查');
}

if ($mode === 'full') {
    if ($report['status'] === 'error') {
        addCheck($report, '完整下单链路', 'skipped', '基础检查未通过，已跳过测试订单流程');
    } else {
        runFullOrderFlowCheck($report, $codePay, $merchantInfo, $baseUrl, $businessModeEnabled);
    }
}

finish($report, $writeStatus);

function runFullOrderFlowCheck(array &$report, CodePay $codePay, array $merchantInfo, string $baseUrl, bool $businessModeEnabled): void
{
    $outTradeNo = 'SELFCHK' . date('YmdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $params = [
        'pid' => (string)$merchantInfo['id'],
        'type' => 'alipay',
        'device' => 'pc',
        'out_trade_no' => $outTradeNo,
        'notify_url' => $baseUrl . '/health.php?action=status',
        'return_url' => $baseUrl . '/',
        'name' => 'AliMPay 自检订单',
        'money' => '0.01',
        'sitename' => 'AliMPay Self Check',
        'sign_type' => 'MD5',
    ];
    $params['sign'] = buildCodePaySign($params, (string)$merchantInfo['key']);

    $orderId = null;
    $response = httpRequest(
        'POST',
        $baseUrl . '/api.php?action=create&format=json',
        ['Content-Type' => 'application/x-www-form-urlencoded'],
        http_build_query($params)
    );

    $payload = decodeJsonBody($response['body']);
    $createOk = !($response['network_error'] ?? false)
        && $response['status_code'] === 200
        && is_array($payload)
        && (int)($payload['code'] ?? 0) === 1;

    addCheck(
        $report,
        '测试订单创建',
        $createOk ? 'healthy' : 'error',
        $createOk ? '测试订单创建成功' : '测试订单创建失败：' . extractApiMessage($payload, $response),
        $createOk ? [] : ['http_status' => $response['status_code']]
    );

    if (!$createOk || !is_array($payload)) {
        return;
    }

    $orderId = (string)($payload['trade_no'] ?? '');
    $statusToken = (string)($payload['status_token'] ?? '');

    $resumeResponse = httpRequest(
        'GET',
        $baseUrl . '/submit.php?resume_payment=1&out_trade_no=' . rawurlencode($outTradeNo) . '&status_token=' . rawurlencode($statusToken)
    );
    $resumeOk = !($resumeResponse['network_error'] ?? false)
        && $resumeResponse['status_code'] === 200
        && str_contains((string)$resumeResponse['body'], $outTradeNo);

    addCheck(
        $report,
        '支付页恢复',
        $resumeOk ? 'healthy' : 'error',
        $resumeOk ? '已生成可恢复的支付页' : 'submit.php 恢复支付页失败',
        $resumeOk ? [] : ['http_status' => $resumeResponse['status_code']]
    );

    $merchantQuery = httpRequest(
        'GET',
        $baseUrl . '/api.php?action=order&pid=' . rawurlencode((string)$merchantInfo['id'])
        . '&key=' . rawurlencode((string)$merchantInfo['key'])
        . '&out_trade_no=' . rawurlencode($outTradeNo)
    );
    $merchantQueryPayload = decodeJsonBody($merchantQuery['body']);
    $merchantQueryOk = !($merchantQuery['network_error'] ?? false)
        && $merchantQuery['status_code'] === 200
        && is_array($merchantQueryPayload)
        && (int)($merchantQueryPayload['code'] ?? 0) === 1;

    addCheck(
        $report,
        '商户订单查询',
        $merchantQueryOk ? 'healthy' : 'error',
        $merchantQueryOk ? '商户接口可查询测试订单' : '商户订单查询失败',
        $merchantQueryOk ? [] : ['http_status' => $merchantQuery['status_code']]
    );

    $statusQuery = httpRequest(
        'GET',
        $baseUrl . '/api.php?action=order&pid=' . rawurlencode((string)$merchantInfo['id'])
        . '&out_trade_no=' . rawurlencode($outTradeNo)
        . '&status_token=' . rawurlencode($statusToken)
    );
    $statusQueryPayload = decodeJsonBody($statusQuery['body']);
    $statusQueryOk = !($statusQuery['network_error'] ?? false)
        && $statusQuery['status_code'] === 200
        && is_array($statusQueryPayload)
        && (int)($statusQueryPayload['code'] ?? 0) === 1;

    addCheck(
        $report,
        '支付页轮询查询',
        $statusQueryOk ? 'healthy' : 'error',
        $statusQueryOk ? '支付页轮询接口可正常读取订单状态' : 'status_token 查询失败',
        $statusQueryOk ? [] : ['http_status' => $statusQuery['status_code']]
    );

    if ($businessModeEnabled) {
        $qrUrl = (string)($payload['qr_code_url'] ?? '');
        $qrResponse = $qrUrl !== '' ? httpRequest('GET', $qrUrl) : ['network_error' => true, 'error' => '缺少 qr_code_url', 'status_code' => 0];
        $qrOk = $qrUrl !== ''
            && !($qrResponse['network_error'] ?? false)
            && $qrResponse['status_code'] === 200
            && str_starts_with((string)($qrResponse['content_type'] ?? ''), 'image/');

        addCheck(
            $report,
            '测试订单二维码',
            $qrOk ? 'healthy' : 'error',
            $qrOk ? '测试订单二维码图片加载正常' : '测试订单二维码加载失败',
            ['http_status' => $qrResponse['status_code'] ?? 0]
        );
    } else {
        $qrCode = (string)($payload['qr_code'] ?? '');
        $decodedQr = base64_decode($qrCode, true);
        $qrOk = $decodedQr !== false && str_starts_with($decodedQr, "\x89PNG");
        addCheck(
            $report,
            '测试订单二维码',
            $qrOk ? 'healthy' : 'error',
            $qrOk ? '测试订单二维码 base64 数据正常' : '测试订单未返回有效二维码数据'
        );
    }

    if ($orderId !== null && $orderId !== '') {
        try {
            $codePay->getDb()->delete('codepay_orders', ['id' => $orderId]);
            addCheck($report, '测试订单清理', 'healthy', '测试订单已清理，不会污染正式订单数据');
        } catch (Throwable $e) {
            addCheck($report, '测试订单清理', 'warning', '测试订单清理失败，请手动删除：' . $outTradeNo);
        }
    }
}

function createReport(string $mode, string $baseUrl): array
{
    return [
        'status' => 'healthy',
        'mode' => $mode,
        'base_url' => $baseUrl,
        'checked_at' => date('Y-m-d H:i:s'),
        'checked_at_unix' => time(),
        'summary' => '',
        'counts' => [
            'healthy' => 0,
            'warning' => 0,
            'error' => 0,
            'skipped' => 0,
        ],
        'checks' => [],
    ];
}

function addCheck(array &$report, string $name, string $status, string $message, array $context = []): void
{
    $allowed = ['healthy', 'warning', 'error', 'skipped'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported check status: ' . $status);
    }

    $report['checks'][] = array_filter([
        'name' => $name,
        'status' => $status,
        'message' => $message,
        'context' => $context === [] ? null : $context,
    ], static fn($value) => $value !== null);

    $report['counts'][$status] = (int)($report['counts'][$status] ?? 0) + 1;

    if ($status === 'error') {
        $report['status'] = 'error';
    } elseif ($status === 'warning' && $report['status'] !== 'error') {
        $report['status'] = 'warning';
    }

    println(sprintf('[%s] %s: %s', strtoupper($status), $name, $message));
}

function finish(array $report, bool $writeStatus): void
{
    $report['summary'] = buildSummary($report);

    println('');
    println($report['summary']);

    if ($writeStatus) {
        writeStatusFile($report);
        println('状态文件已写入: data/self_check_status.json');
    }

    exit($report['status'] === 'error' ? 1 : 0);
}

function buildSummary(array $report): string
{
    $counts = $report['counts'] ?? [];
    $healthy = (int)($counts['healthy'] ?? 0);
    $warning = (int)($counts['warning'] ?? 0);
    $error = (int)($counts['error'] ?? 0);
    $skipped = (int)($counts['skipped'] ?? 0);

    if (($report['status'] ?? 'healthy') === 'error') {
        return sprintf('自检失败：%d 项正常，%d 项警告，%d 项错误，%d 项跳过。', $healthy, $warning, $error, $skipped);
    }

    if (($report['status'] ?? 'healthy') === 'warning') {
        return sprintf('自检完成：%d 项正常，%d 项警告，%d 项跳过。', $healthy, $warning, $skipped);
    }

    return sprintf('自检通过：%d 项正常，%d 项跳过。', $healthy, $skipped);
}

function writeStatusFile(array $report): void
{
    $statusDir = dirname(SELF_CHECK_STATUS_FILE);
    if (!is_dir($statusDir)) {
        mkdir($statusDir, 0755, true);
    }

    file_put_contents(
        SELF_CHECK_STATUS_FILE,
        json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function normalizeBaseUrl(string $baseUrl): string
{
    $baseUrl = trim($baseUrl);
    if ($baseUrl === '') {
        return 'http://127.0.0.1';
    }

    return rtrim($baseUrl, '/');
}

function projectPath(string $relativePath): string
{
    return dirname(__DIR__) . '/' . ltrim($relativePath, '/');
}

function redactProjectPath(string $path): string
{
    $root = dirname(__DIR__);
    $normalizedPath = realpath($path) ?: $path;
    return str_starts_with($normalizedPath, $root) ? '.' . substr($normalizedPath, strlen($root)) : $normalizedPath;
}

function loadPhpConfig(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    try {
        $config = require $path;
        return is_array($config) ? $config : null;
    } catch (Throwable $e) {
        return null;
    }
}

function loadJsonFile(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function resolveBusinessQrPath(array $config): string
{
    $path = (string)($config['payment']['business_qr_mode']['qr_code_path'] ?? projectPath('qrcode/business_qr.png'));
    if ($path === '') {
        return projectPath('qrcode/business_qr.png');
    }

    if ($path[0] !== '/') {
        return projectPath(ltrim($path, './'));
    }

    return $path;
}

function isRuntimeWritable(string $path): bool
{
    if (file_exists($path)) {
        return is_writable($path);
    }

    $parent = dirname($path);
    return $parent !== '' && is_dir($parent) && is_writable($parent);
}

function buildCodePaySign(array $params, string $merchantKey): string
{
    unset($params['sign'], $params['sign_type']);
    $params = array_filter($params, static function ($value) {
        return $value !== '' && $value !== null;
    });

    ksort($params);
    $parts = [];
    foreach ($params as $key => $value) {
        $parts[] = $key . '=' . $value;
    }

    return md5(implode('&', $parts) . $merchantKey);
}

function httpRequest(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 8): array
{
    $headerLines = [
        'Connection: close',
        'User-Agent: AliMPay-SelfCheck/1.0',
    ];

    foreach ($headers as $name => $value) {
        if (is_int($name)) {
            $headerLines[] = (string)$value;
            continue;
        }

        $headerLines[] = $name . ': ' . $value;
    }

    $options = [
        'http' => [
            'method' => strtoupper($method),
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines),
        ],
    ];

    if ($body !== null) {
        $options['http']['content'] = $body;
    }

    $context = stream_context_create($options);
    $responseBody = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    $statusCode = 0;
    $contentType = '';

    if (isset($responseHeaders[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $responseHeaders[0], $matches)) {
        $statusCode = (int)$matches[1];
    }

    foreach ($responseHeaders as $headerLine) {
        if (stripos($headerLine, 'Content-Type:') === 0) {
            $contentType = trim(substr($headerLine, strlen('Content-Type:')));
            break;
        }
    }

    if ($responseBody === false && $statusCode === 0) {
        $error = error_get_last();
        return [
            'network_error' => true,
            'error' => limitMessage((string)($error['message'] ?? 'unknown error')),
            'status_code' => 0,
            'body' => '',
            'headers' => [],
            'content_type' => '',
        ];
    }

    return [
        'network_error' => false,
        'status_code' => $statusCode,
        'body' => (string)$responseBody,
        'headers' => $responseHeaders,
        'content_type' => strtolower($contentType),
    ];
}

function decodeJsonBody(string $body): ?array
{
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function extractApiMessage(?array $payload, array $response): string
{
    if (is_array($payload)) {
        $message = (string)($payload['msg'] ?? $payload['message'] ?? '');
        if ($message !== '') {
            return limitMessage($message);
        }
    }

    if (($response['network_error'] ?? false) === true) {
        return (string)($response['error'] ?? 'network error');
    }

    return 'HTTP ' . (int)($response['status_code'] ?? 0);
}

function limitMessage(string $message, int $limit = 180): string
{
    return mb_strlen($message) > $limit ? mb_substr($message, 0, $limit) . '...' : $message;
}

function println(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}
