<?php

namespace AliMPay\Core;

use Alipay\OpenAPISDK\Util\Model\AlipayConfig;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use AliMPay\Utils\Logger;

class AlipayClient
{
    private $config;
    private $alipayConfig;
    private $alipayConfigUtil;
    private $logger;
    
    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadConfig();
        $this->logger = Logger::getInstance();
        $this->initializeAlipayConfig();
    }
    
    private function loadConfig(): array
    {
        $configPath = __DIR__ . '/../../config/alipay.php';
        if (!file_exists($configPath)) {
            throw new \Exception('Alipay configuration file not found');
        }
        
        return require $configPath;
    }
    
    private function initializeAlipayConfig(): void
    {
        $this->alipayConfig = new AlipayConfig();
        $this->alipayConfig->setServerUrl($this->config['server_url']);
        $this->alipayConfig->setAppId($this->config['app_id']);
        $this->alipayConfig->setPrivateKey($this->config['private_key']);
        $this->alipayConfig->setAlipayPublicKey($this->config['alipay_public_key']);
        
        $this->alipayConfigUtil = new AlipayConfigUtil($this->alipayConfig);
        $this->alipayConfigUtil->setSignType($this->config['sign_type'] ?? 'RSA2');
        $this->alipayConfigUtil->setCharset($this->config['charset'] ?? 'UTF-8');
        $this->alipayConfigUtil->setFormat($this->config['format'] ?? 'json');
        
        $this->logger->info('Alipay client initialized', [
            'app_id' => $this->config['app_id'],
            'server_url' => $this->config['server_url'],
            'sign_type' => $this->config['sign_type'] ?? 'RSA2',
            'charset' => $this->config['charset'] ?? 'UTF-8',
            'format' => $this->config['format'] ?? 'json'
        ]);
    }
    
    public function getAlipayConfig(): AlipayConfig
    {
        return $this->alipayConfig;
    }
    
    public function getAlipayConfigUtil(): AlipayConfigUtil
    {
        return $this->alipayConfigUtil;
    }
    
    public function getConfig(): array
    {
        return $this->config;
    }
    
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->initializeAlipayConfig();
    }
    
    public function validateConfig(): bool
    {
        return empty($this->validateConfigDetails());
    }

    public function validateConfigDetails(): array
    {
        $errors = [];
        $required = ['app_id', 'private_key', 'alipay_public_key', 'server_url'];

        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                $errors[] = "缺少必要配置项：{$key}";
            }
        }

        if (!empty($this->config['private_key']) && !$this->isValidPrivateKey((string)$this->config['private_key'])) {
            $errors[] = '应用私钥格式无法被 OpenSSL 识别，请确认填写的是应用私钥而不是应用公钥或支付宝公钥。';
        }

        if (!empty($this->config['alipay_public_key'])) {
            $publicKey = $this->normalizeKey((string)$this->config['alipay_public_key']);
            if (strlen($publicKey) < 300 || !$this->isValidPublicKey($publicKey)) {
                $errors[] = '支付宝公钥格式异常：请填写支付宝开放平台提供的“支付宝公钥”，不要填写应用公钥、商户密钥或 32 位通信密钥。';
            }
        }

        foreach ($errors as $error) {
            $this->logger->error($error);
        }

        return $errors;
    }

    private function isValidPrivateKey(string $key): bool
    {
        $body = $this->normalizeKey($key);
        foreach (['PRIVATE KEY', 'RSA PRIVATE KEY'] as $type) {
            $pem = "-----BEGIN {$type}-----\n" . chunk_split($body, 64, "\n") . "-----END {$type}-----\n";
            if (@openssl_pkey_get_private($pem) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isValidPublicKey(string $key): bool
    {
        $body = $this->normalizeKey($key);
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($body, 64, "\n") . "-----END PUBLIC KEY-----\n";
        return @openssl_pkey_get_public($pem) !== false;
    }

    private function normalizeKey(string $key): string
    {
        $key = preg_replace('/-----BEGIN [^-]+-----|-----END [^-]+-----/', '', $key);
        return preg_replace('/\s+/', '', $key ?? '');
    }
} 
