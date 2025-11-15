<?php
$conn = new mysqli('localhost', 'root', 'qwerty', 'tiktokio.mobi');
if ($conn->connect_error) die("Connection failed");

echo "Enabling iframe provider...\n";
$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='iframe'");

// Set iframe_url in config if not set
$result = $conn->query("SELECT config_payload FROM api_providers WHERE provider_key='iframe'");
$row = $result->fetch_assoc();
$config = json_decode($row['config_payload'] ?? '{}', true);
if (empty($config['iframe_url'])) {
    $config['iframe_url'] = 'https://freeapi.cyou';
    $conn->query("UPDATE api_providers SET config_payload='" . json_encode($config) . "' WHERE provider_key='iframe'");
    echo "Set iframe_url to https://freeapi.cyou\n";
}

echo "Done!\n\n";

$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

$conn->close();
?>

