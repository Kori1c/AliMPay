<?php

namespace AliMPay\Admin;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class BackupService
{
    public static function create(string $reason = 'manual'): array
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('当前环境未启用 ZipArchive，无法创建备份');
        }

        $timestamp = date('Ymd_His');
        $fileName = sprintf('alimpay_backup_%s_%s.zip', $reason, $timestamp);
        $filePath = self::storageDir() . '/' . $fileName;

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception('创建备份文件失败');
        }

        $includedFiles = [];
        foreach (self::sourceMap() as $entryName => $sourcePath) {
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

    public static function restore(string $uploadedFilePath): array
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('当前环境未启用 ZipArchive，无法恢复备份');
        }

        $zip = new ZipArchive();
        if ($zip->open($uploadedFilePath) !== true) {
            throw new Exception('备份文件无法打开，请确认 zip 是否完整');
        }

        foreach (['config/alipay.php', 'config/codepay.json', 'data/codepay.db'] as $entry) {
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

            $preRestoreBackup = self::create('pre_restore');
            foreach (self::sourceMap() as $entryName => $targetPath) {
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

            AdminConfigService::invalidateAlipayConfigCache();
            clearstatcache();
            self::removeRuntimeLocks();

            return [
                'pre_restore_backup' => $preRestoreBackup['file_name'],
            ];
        } finally {
            self::removeDirectory($tempDir);
        }
    }

    public static function stream(string $fileName): void
    {
        $safeFileName = self::sanitizeName($fileName);
        $filePath = self::storageDir() . '/' . $safeFileName;
        if (!is_file($filePath)) {
            throw new Exception('备份文件不存在');
        }

        AdminResponse::zipDownload($filePath, $safeFileName);
    }

    private static function storageDir(): string
    {
        $dir = AdminConfigService::projectRoot() . '/data/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    private static function sourceMap(): array
    {
        $root = AdminConfigService::projectRoot();
        return [
            'config/alipay.php' => $root . '/config/alipay.php',
            'config/codepay.json' => $root . '/config/codepay.json',
            'data/codepay.db' => $root . '/data/codepay.db',
            'qrcode/business_qr.png' => AdminConfigService::businessQrPath(),
        ];
    }

    private static function sanitizeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'backup.zip';
    }

    private static function removeRuntimeLocks(): void
    {
        $root = AdminConfigService::projectRoot();
        foreach (glob($root . '/data/*.lock') ?: [] as $file) {
            @unlink($file);
        }
        foreach (glob($root . '/data/order_locks/*.lock') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
