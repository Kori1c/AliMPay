<?php

namespace AliMPay\Admin;

class AdminResponse
{
    public static function json(array $payload, int $statusCode = 200): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        if (isset($_SESSION['csrf_token'])) {
            $payload['_csrf_token'] = $_SESSION['csrf_token'];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonDownload(array $payload, string $fileName): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function zipDownload(string $filePath, string $fileName): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Length: ' . (string)filesize($filePath));
        header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        readfile($filePath);
        exit;
    }
}
