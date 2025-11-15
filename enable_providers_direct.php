<?php
// Direct MySQL connection - no config file needed
$host = 'localhost';
$user = 'root';
$pass = ''; // Usually empty for XAMPP
$db = 'tiktokio';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    // Try with password
    $pass = 'root';
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("ERROR: Cannot connect to database. Please check MySQL is running.\n");
    }
}

echo "✓ Connected to database\n\n";

echo "Enabling providers...\n";
$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp'");
$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='cobalt'");
$conn->query("UPDATE site_settings SET active_api_provider='ytdlp'");

echo "\n=== CURRENT STATUS ===\n";
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

$result = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1");
$row = $result->fetch_assoc();
echo "\nDefault provider: {$row['active_api_provider']}\n";

$conn->close();

echo "\n✅ DONE!\n";
echo "\nNOW DO THESE 2 STEPS:\n";
echo "1. RESTART FASTAPI: Press Ctrl+C in FastAPI terminal, then run: .\\start_fastapi.bat\n";
echo "2. CLEAR BROWSER CACHE: Press Ctrl+F5 on http://localhost:8000\n";
?>

