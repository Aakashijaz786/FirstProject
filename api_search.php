<?php
/**
 * API endpoint for frontend to search YouTube videos
 * Returns JSON for AJAX calls
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';

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

$query = trim($_GET['q'] ?? $_POST['q'] ?? '');
$format = strtolower(trim($_GET['format'] ?? $_POST['format'] ?? 'mp3'));

if (empty($query)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a YouTube URL or search query']);
    exit;
}

// Initialize search sessions if needed
if (!isset($_SESSION['search_sessions']) || !is_array($_SESSION['search_sessions'])) {
    $_SESSION['search_sessions'] = [];
}

// Cleanup old sessions
$now = time();
foreach ($_SESSION['search_sessions'] ?? [] as $key => $payload) {
    $created = $payload['created_at'] ?? 0;
    if ($created < ($now - 900)) {
        unset($_SESSION['search_sessions'][$key]);
    }
}

$provider = get_active_api_provider($conn);
$api_response = media_api_search($conn, $query, $provider, 5, true);

if (!$api_response['success']) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $api_response['error'] ?? 'Failed to search for video'
    ]);
    exit;
}

$items = $api_response['data']['items'] ?? [];
if (empty($items)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'No results found'
    ]);
    exit;
}

// Store in session for download
$search_id = bin2hex(random_bytes(16));
$_SESSION['search_sessions'][$search_id] = [
    'query' => $query,
    'provider' => $provider,
    'results' => $items,
    'raw' => $api_response['data'],
    'created_at' => $now,
];

// Return first result (or let frontend choose)
$first_item = $items[0];

echo json_encode([
    'success' => true,
    'search_id' => $search_id,
    'provider' => $provider,
    'items' => $items,
    'selected' => [
        'id' => $first_item['id'] ?? null,
        'title' => $first_item['title'] ?? 'Unknown',
        'url' => $first_item['url'] ?? null,
        'thumbnail' => $first_item['thumbnail'] ?? null,
        'author' => $first_item['author'] ?? null,
        'duration' => $first_item['duration'] ?? null,
    ]
], JSON_UNESCAPED_UNICODE);

