<?php
// Direct database fix with correct password
$host = 'localhost';
$user = 'root';
$pass = 'qwerty';
$db = 'tiktokio';

echo "Connecting to database...\n";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("ERROR: Cannot connect - " . $conn->connect_error . "\n");
}

echo "✓ Connected successfully!\n\n";

echo "=== BEFORE UPDATE ===\n";
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

echo "\nUpdating providers...\n";
$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp'");
echo "YTDLP: " . ($conn->affected_rows >= 0 ? "✓ ENABLED" : "✗ FAILED") . "\n";

$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='cobalt'");
echo "Cobalt: " . ($conn->affected_rows >= 0 ? "✓ ENABLED" : "✗ FAILED") . "\n";

$conn->query("UPDATE site_settings SET active_api_provider='ytdlp'");
echo "Default: " . ($conn->affected_rows >= 0 ? "✓ SET TO YTDLP" : "✗ FAILED") . "\n";

echo "\n=== AFTER UPDATE ===\n";
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? '✓ ENABLED' : '✗ DISABLED');
}

$result = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1");
$row = $result->fetch_assoc();
echo "\nDefault provider: {$row['active_api_provider']}\n";

$conn->close();

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ DATABASE UPDATED SUCCESSFULLY!\n";
echo str_repeat("=", 50) . "\n\n";

echo "NOW DO THESE 2 STEPS:\n\n";
echo "1. RESTART FASTAPI SERVER:\n";
echo "   - In FastAPI terminal, press Ctrl+C\n";
echo "   - Then run: .\\start_fastapi.bat\n";
echo "   - Wait for 'Application startup complete'\n\n";

echo "2. CLEAR BROWSER CACHE:\n";
echo "   - Go to http://localhost:8000\n";
echo "   - Press Ctrl+F5 (hard refresh)\n\n";

echo "Then test both YTDLP and Cobalt!\n";
?>

