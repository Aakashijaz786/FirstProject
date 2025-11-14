<?php
/**
 * Test YouTube Download Setup
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';

$settings = get_site_settings_cached($conn);

echo "=== YouTube Download Setup Check ===\n\n";
echo "FastAPI Base URL: " . ($settings['fastapi_base_url'] ?? 'NOT SET') . "\n";
echo "FastAPI Auth Key: " . (empty($settings['fastapi_auth_key']) || $settings['fastapi_auth_key'] === 'change-me' ? 'NOT SET (will use default)' : 'SET') . "\n";
echo "Active Provider: " . ($settings['active_api_provider'] ?? 'ytdlp') . "\n\n";

// Test search endpoint
echo "Testing search endpoint...\n";
$test_url = "https://youtu.be/-3KT1f7WZIo";
$result = media_api_search($conn, $test_url, 'ytdlp', 1, true);

if ($result['success']) {
    echo "✓ Search API is working!\n";
    $items = $result['data']['items'] ?? [];
    if (!empty($items)) {
        echo "  Found " . count($items) . " result(s)\n";
        $first = $items[0];
        echo "  First result: " . ($first['title'] ?? 'Unknown') . "\n";
    }
} else {
    echo "✗ Search API failed: " . ($result['error'] ?? 'Unknown error') . "\n";
    echo "\nMake sure:\n";
    echo "1. FastAPI backend is running (python -m uvicorn main:app --reload in api/backend/)\n";
    echo "2. FastAPI base URL is correct in site_settings\n";
}

echo "\n=== Setup Complete ===\n";

