<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'tiktokio';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("ERROR: " . $conn->connect_error . "\n");
}

echo "=== ENABLING PROVIDERS ===" . PHP_EOL;

$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp'");
echo "YTDLP: " . ($conn->affected_rows >= 0 ? "UPDATED" : "FAILED") . PHP_EOL;

$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='cobalt'");
echo "Cobalt: " . ($conn->affected_rows >= 0 ? "UPDATED" : "FAILED") . PHP_EOL;

$conn->query("UPDATE site_settings SET active_api_provider='ytdlp'");
echo "Default: " . ($conn->affected_rows >= 0 ? "SET TO YTDLP" : "FAILED") . PHP_EOL;

echo PHP_EOL . "=== VERIFICATION ===" . PHP_EOL;
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-15s: %s" . PHP_EOL, $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

$result = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1");
$row = $result->fetch_assoc();
echo PHP_EOL . "Default: " . $row['active_api_provider'] . PHP_EOL;

$conn->close();
?>


