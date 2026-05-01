<?php

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

echo json_encode([
    'success' => false,
    'message' => 'notify.php is a reserved path. Configure your own merchant notify_url in your business system.',
], JSON_UNESCAPED_UNICODE);
