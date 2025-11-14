<?php
/**
 * Check Database Tables
 */

require_once __DIR__ . '/includes/config.php';

echo "=== Checking Database Tables ===\n\n";

// Get all tables
$result = $conn->query("SHOW TABLES");
$tables = [];
if ($result) {
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
}

echo "Found " . count($tables) . " tables:\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}

echo "\n";

// Check for important tables
$important_tables = [
    'languages',
    'admin_users',
    'languages_home',
    'languages_mp3',
    'languages_mp4',
    'contact_messages',
    'downloads'
];

echo "Checking for important tables:\n";
$missing = [];
foreach ($important_tables as $table) {
    if (in_array($table, $tables)) {
        echo "  ✓ $table exists\n";
    } else {
        echo "  ✗ $table MISSING\n";
        $missing[] = $table;
    }
}

if (!empty($missing)) {
    echo "\n⚠ Missing tables detected. The database import may be incomplete.\n";
    echo "Missing tables: " . implode(', ', $missing) . "\n";
} else {
    echo "\n✓ All important tables exist!\n";
}

