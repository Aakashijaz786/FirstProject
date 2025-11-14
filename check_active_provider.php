<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';

header('Content-Type: text/plain');

echo "=== Active Provider Check ===\n\n";

// Force clear cache
refresh_site_settings_cache();

// Get active provider
$provider = get_active_api_provider($conn);
echo "Active Provider: " . $provider . "\n\n";

// Get full settings
$settings = get_site_settings_cached($conn);
echo "Full Settings:\n";
echo "  active_api_provider: " . ($settings['active_api_provider'] ?? 'NOT SET') . "\n";
echo "  fastapi_base_url: " . ($settings['fastapi_base_url'] ?? 'NOT SET') . "\n\n";

// Check database directly
$res = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo "Database Value: " . ($row['active_api_provider'] ?? 'NOT SET') . "\n";
} else {
    echo "Database Value: NO ROW FOUND\n";
}

echo "\n=== Provider Status ===\n";
$res = $conn->query("SELECT provider_key, display_name, is_enabled FROM api_providers ORDER BY provider_key");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $status = $row['is_enabled'] ? 'ENABLED' : 'DISABLED';
        $marker = ($row['provider_key'] === $provider) ? ' <-- ACTIVE' : '';
        echo "  {$row['provider_key']}: {$status}{$marker}\n";
    }
}

