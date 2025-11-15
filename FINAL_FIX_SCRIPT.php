<?php
// Use the actual config file to get DB credentials
require_once 'admin/includes/config.php';

echo "=== ENABLING PROVIDERS ===" . PHP_EOL;

$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp'");
echo "YTDLP: ENABLED" . PHP_EOL;

$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='cobalt'");
echo "Cobalt: ENABLED" . PHP_EOL;

$conn->query("UPDATE site_settings SET active_api_provider='ytdlp'");
echo "Default: SET TO YTDLP" . PHP_EOL;

echo PHP_EOL . "=== VERIFICATION ===" . PHP_EOL;
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-15s: %s" . PHP_EOL, $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

$result = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1");
$row = $result->fetch_assoc();
echo PHP_EOL . "Default: " . $row['active_api_provider'] . PHP_EOL;

echo PHP_EOL . "✅ DONE! Now restart FastAPI server!" . PHP_EOL;
?>

