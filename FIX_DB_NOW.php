<?php
// Fix database with correct credentials
$host = 'localhost';
$user = 'root';
$pass = 'qwerty';
$db = 'tiktokio.mobi'; // From API settings.py

echo "Connecting to database '$db'...\n";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    // Try without .mobi
    $db = 'tiktokio';
    $conn = new mysqli($host, $user, $pass, $db);
    if ($conn->connect_error) {
        die("ERROR: Cannot find database. Tried 'tiktokio.mobi' and 'tiktokio'\n");
    }
}

echo "✓ Connected to '$db'!\n\n";

echo "=== BEFORE UPDATE ===\n";
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
if (!$result) {
    die("ERROR: Table 'api_providers' not found in database '$db'\n");
}
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? 'ENABLED' : 'DISABLED');
}

echo "\n=== UPDATING ===\n";
$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp'");
echo "YTDLP updated (affected rows: " . $conn->affected_rows . ")\n";

$conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='cobalt'");
echo "Cobalt updated (affected rows: " . $conn->affected_rows . ")\n";

$conn->query("UPDATE site_settings SET active_api_provider='ytdlp'");
echo "Default set to YTDLP\n";

echo "\n=== AFTER UPDATE ===\n";
$result = $conn->query("SELECT provider_key, is_enabled FROM api_providers ORDER BY provider_key");
while ($row = $result->fetch_assoc()) {
    printf("%-15s: %s\n", $row['provider_key'], $row['is_enabled'] ? '✓ ENABLED' : '✗ DISABLED');
}

$result = $conn->query("SELECT active_api_provider FROM site_settings LIMIT 1");
$row = $result->fetch_assoc();
echo "\nDefault provider: {$row['active_api_provider']}\n";

$conn->close();

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ DATABASE FIXED!\n";
echo str_repeat("=", 60) . "\n\n";

echo "NEXT STEPS:\n\n";
echo "1. RESTART FASTAPI (in FastAPI terminal):\n";
echo "   Ctrl+C then: .\\start_fastapi.bat\n\n";
echo "2. CLEAR BROWSER CACHE:\n";
echo "   Press Ctrl+F5 on http://localhost:8000\n\n";
?>

