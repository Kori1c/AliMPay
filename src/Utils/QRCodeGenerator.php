<?php

namespace AliMPay\Utils;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use AliMPay\Utils\Logger;

class QRCodeGenerator
{
    private $logger;
    private $config;
    
    public function __construct(array $config = [])
    {
        $this->logger = Logger::getInstance();
        $this->config = $config ?: $this->loadConfig();
    }

    private function loadConfig(): array
    {
        $configPath = __DIR__ . '/../../config/alipay.php';
        return file_exists($configPath) ? require $configPath : [];
    }
    
    /**
     * Check if GD extension is available
     * 
     * @return bool
     */
    private function isGDAvailable(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreate');
    }
    
    /**
     * Generate QR code for transfer link
     * 
     * @param string $transferUrl Transfer URL
     * @param string $savePath File save path
     * @param int $size QR code size
     * @return string Generated QR code file path
     */
    public function generateQRCode(string $transferUrl, string $savePath = null, int $size = 300): string
    {
        try {
            // Create QR codes directory if it doesn't exist
            $qrCodeDir = __DIR__ . '/../../qrcodes';
            if (!is_dir($qrCodeDir)) {
                mkdir($qrCodeDir, 0755, true);
            }
            
            // Generate file path if not provided
            if (!$savePath) {
                $fileName = 'qrcode_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.png';
                $savePath = $qrCodeDir . '/' . $fileName;
            }
            
            if (!$this->isGDAvailable()) {
                throw new \Exception('GD extension is required for local QR code generation');
            }

            return $this->generateQRCodeLocal($transferUrl, $savePath, $size);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate QR code', [
                'error' => $e->getMessage(),
                'url' => $transferUrl
            ]);
            
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate QR code using local library
     * 
     * @param string $transferUrl
     * @param string $savePath
     * @param int $size
     * @return string
     */
    private function generateQRCodeLocal(string $transferUrl, string $savePath, int $size): string
    {
        // Generate QR code using endroid/qr-code
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($transferUrl)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::High)
            ->size($size)
            ->margin(10)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();
        
        // Save QR code
        $result->saveToFile($savePath);
        
        $this->logger->info('QR code generated successfully (local)', [
            'url' => $transferUrl,
            'file_path' => $savePath,
            'size' => $size
        ]);
        
        return $savePath;
    }
    
    /**
     * Generate base64 encoded QR code
     * 
     * @param string $transferUrl
     * @param int $size
     * @return string Base64 encoded QR code
     */
    public function generateQRCodeBase64(string $transferUrl, int $size = 300): string
    {
        try {
            if (!$this->isGDAvailable()) {
                throw new \Exception('GD extension is required for local QR code generation');
            }

            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($transferUrl)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size($size)
                ->margin(10)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->build();

            return base64_encode($result->getString());

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate QR code base64', [
                'error' => $e->getMessage(),
                'url' => $transferUrl
            ]);
            
            throw new \Exception('QR code generation failed: ' . $e->getMessage());
        }
    }
    
    public function generateQRCodeUrl(string $transferUrl, int $size = 300): string
    {
        throw new \Exception('Online QR code URL generation is disabled for privacy');
    }

    /**
     * 生成二维码图片的base64字符串
     * @param string $text
     * @return string
     */
    public function generate(string $text, ?int $size = null, ?int $margin = null): string
    {
        $size = $size ?? (int)($this->config['payment']['qr_code_size'] ?? 200);
        $margin = $margin ?? (int)($this->config['payment']['qr_code_margin'] ?? 10);

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->size(max(120, min($size, 800)))
            ->margin(max(0, min($margin, 50)))
            ->build();

        // 获取二维码图片内容并转为base64
        $data = $result->getString();
        return base64_encode($data);
    }
}
