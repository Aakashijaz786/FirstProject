<?php
$conn = new mysqli('localhost', 'root', 'qwerty', 'tiktokio.mobi');
if ($conn->connect_error) die("Connection failed");

echo "Enabling YTDLP...\n";
$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp'");
echo "Done!\n\n";

$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

$row = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1")->fetch_assoc();
echo "\nDefault: {$row['active_api_provider']}\n";

$conn->close();
?>

