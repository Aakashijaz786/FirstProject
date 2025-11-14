<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';
require_once __DIR__ . '/includes/jwt_helper.php';
require_once __DIR__ . '/includes/download_logger.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    http_response_code(400);
    echo 'Missing token.';
    exit;
}

$settings = get_site_settings_cached($conn);
$jwt_secret = $settings['jwt_secret'] ?? 'change-me';

try {
    $payload = decode_jwt_token($token, $jwt_secret);
} catch (Exception $e) {
    http_response_code(403);
    echo 'Invalid token.';
    exit;
}

if (($payload['type'] ?? '') !== 'media' || empty($payload['session_id'])) {
    http_response_code(403);
    echo 'Invalid media token.';
    exit;
}

$session_id = $payload['session_id'];
$download_sessions = $_SESSION['download_sessions'][$session_id] ?? null;
if (!$download_sessions) {
    http_response_code(410);
    echo 'Download session expired.';
    exit;
}

$download_data = $download_sessions['download'] ?? [];
$download_token = $payload['download_token'] ?? ($download_data['download_token'] ?? null);
$download_signature = $payload['download_signature'] ?? ($download_data['signature'] ?? null);

if (!$download_token || !$download_signature) {
    http_response_code(400);
    echo 'Download token missing.';
    exit;
}

$fastapi_base = rtrim($settings['fastapi_base_url'] ?? 'http://127.0.0.1:8000', '/');
$media_url = $fastapi_base . '/media/' . $download_token . '?sig=' . urlencode($download_signature);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$responseHeaders = [];
$ch = curl_init($media_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => [
        'X-Internal-Key: ' . ($settings['fastapi_auth_key'] ?? ''),
    ],
    CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$responseHeaders) {
        $len = strlen($header);
        $parts = explode(':', $header, 2);
        if (count($parts) === 2) {
            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            $responseHeaders[$name] = $value;
        }
        return $len;
    },
    CURLOPT_WRITEFUNCTION => function ($ch, $data) {
        echo $data;
        flush();
        return strlen($data);
    }
]);

header('Content-Description: File Transfer');
if (isset($responseHeaders['content-type'])) {
    header('Content-Type: ' . $responseHeaders['content-type']);
} elseif (!empty($download_data['mime_type'])) {
    header('Content-Type: ' . $download_data['mime_type']);
} else {
    header('Content-Type: application/octet-stream');
}

$filename = $download_data['file_name'] ?? ('media-' . $download_token);
if (isset($responseHeaders['content-disposition'])) {
    header('Content-Disposition: ' . $responseHeaders['content-disposition']);
} else {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
}

if (isset($responseHeaders['content-length'])) {
    header('Content-Length: ' . $responseHeaders['content-length']);
}

$execResult = curl_exec($ch);
if ($execResult === false) {
    http_response_code(502);
    echo 'Failed to stream media.';
    curl_close($ch);
    exit;
}
curl_close($ch);

$log_id = $download_sessions['log_id'] ?? null;
if ($log_id && is_numeric($log_id)) {
    updateDownloadStatus((int)$log_id, 'completed', $conn);
}
