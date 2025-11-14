<?php
/**
 * Test download endpoint to see actual error
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';

// Test with a simple YouTube URL
$test_url = "https://youtu.be/-3KT1f7WZIo";
$provider = get_active_api_provider($conn);
$settings = get_site_settings_cached($conn);

echo "Testing download endpoint...\n";
echo "URL: $test_url\n";
echo "Provider: $provider\n";
echo "Format: mp3\n";
echo "Quality: 320\n\n";

$api_response = media_api_download(
    $conn,
    $test_url,
    $provider,
    'mp3',
    '320',
    null,
    $settings['site_name'] ?? 'YT1s Downloader'
);

echo "Response:\n";
print_r($api_response);

if (!$api_response['success']) {
    echo "\n\nERROR DETAILS:\n";
    echo "Error: " . ($api_response['error'] ?? 'Unknown error') . "\n";
    if (isset($api_response['body'])) {
        echo "Response body: " . substr($api_response['body'], 0, 500) . "\n";
    }
}

