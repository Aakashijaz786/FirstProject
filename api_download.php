<?php
/**
 * API endpoint for frontend to download YouTube videos
 * Returns JSON with download link
 */

// Suppress any output before JSON
ob_start();

session_start();

// Set JSON header early
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';
require_once __DIR__ . '/includes/jwt_helper.php';
require_once __DIR__ . '/includes/download_logger.php';

// Enable CORS for frontend
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

$search_id = $_POST['search_id'] ?? $_GET['search_id'] ?? '';
$item_index = (int)($_POST['item_index'] ?? $_GET['item_index'] ?? 0);
$format = strtolower(trim($_POST['format'] ?? $_GET['format'] ?? 'mp3'));
$quality = $_POST['quality'] ?? $_GET['quality'] ?? null;

if (empty($search_id)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing search_id'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Initialize sessions if needed
if (!isset($_SESSION['search_sessions']) || !is_array($_SESSION['search_sessions'])) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Search session not found'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

if (!isset($_SESSION['download_sessions']) || !is_array($_SESSION['download_sessions'])) {
    $_SESSION['download_sessions'] = [];
}

$search_session = $_SESSION['search_sessions'][$search_id] ?? null;
if (!$search_session) {
    ob_clean();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Search session expired'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

$results = $search_session['results'] ?? [];
if (!isset($results[$item_index])) {
    ob_clean();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Item not found'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

$item = $results[$item_index];
$media_url = $item['url'] ?? '';

if (empty($media_url)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Media URL missing'], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

$provider = $search_session['provider'];
$settings = get_site_settings_cached($conn);

try {
    // Set longer timeout for downloads
    set_time_limit(180); // 3 minutes
    
    $api_response = media_api_download(
        $conn,
        $media_url,
        $provider,
        $format,
        $quality,
        null,
        $settings['site_name'] ?? 'YT1s Downloader'
    );
    
    if (!$api_response['success']) {
        ob_clean();
        http_response_code(500);
        $error_msg = $api_response['error'] ?? 'Failed to prepare download';
        
        // Log detailed error for debugging
        error_log("Download API error: " . print_r($api_response, true));
        
        echo json_encode([
            'success' => false,
            'error' => $error_msg,
            'details' => $api_response['error'] ?? null,
            'status' => $api_response['status'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        ob_end_flush();
        exit;
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    error_log("Download exception: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
} catch (Error $e) {
    ob_clean();
    http_response_code(500);
    error_log("Download fatal error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Fatal error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

$download_id = bin2hex(random_bytes(16));
$download_payload = $api_response['data'];

$_SESSION['download_sessions'][$download_id] = [
    'search_id' => $search_id,
    'provider' => $provider,
    'item' => $item,
    'download' => $download_payload,
    'created_at' => time(),
    'format' => $format,
];

// Log download
$log_id = logDownload(
    $media_url,
    strtoupper($format),
    $conn,
    [
        'file_name' => $download_payload['file_name'] ?? null,
        'file_type' => $download_payload['mime_type'] ?? null,
        'file_size_bytes' => $download_payload['file_size_bytes'] ?? null,
        'provider_key' => $provider,
    ]
);

$_SESSION['download_sessions'][$download_id]['log_id'] = $log_id;

// Create media proxy token (wraps the FastAPI download token)
$jwt_secret = $settings['jwt_secret'] ?? 'change-me';
$media_token = create_jwt_token([
    'type' => 'media',
    'session_id' => $download_id,
    'download_token' => $download_payload['download_token'] ?? null,
    'download_signature' => $download_payload['signature'] ?? null
], $jwt_secret, 1800);

$download_url = '/media.php?token=' . urlencode($media_token);

// Clear any output buffer
ob_clean();

echo json_encode([
    'success' => true,
    'download_url' => $download_url,
    'file_name' => $download_payload['file_name'] ?? 'download',
    'file_size' => $download_payload['human_size'] ?? null,
    'mime_type' => $download_payload['mime_type'] ?? null,
    'metadata' => $download_payload['metadata'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

ob_end_flush();
exit;

