<?php
/**
 * Reset and re-seed default YT1s FAQs for the default language.
 * Run: php reset_home_faqs.php
 */

require_once __DIR__ . '/includes/config.php';

echo "=== Resetting default YT1s FAQs ===\n\n";

// Find default language id
$res = $conn->query("SELECT id, name, code FROM languages WHERE is_default=1 LIMIT 1");
if (!$res || $res->num_rows === 0) {
    $res = $conn->query("SELECT id, name, code FROM languages ORDER BY id ASC LIMIT 1");
}
if (!$res || $res->num_rows === 0) {
    echo "ERROR: No languages found.\n";
    exit(1);
}
$lang = $res->fetch_assoc();
$langId = (int)$lang['id'];

echo "Using language ID {$langId} ({$lang['name']} / {$lang['code']})\n";

// Delete existing FAQs
$conn->query("DELETE FROM language_faqs WHERE language_id={$langId}");
$deleted = $conn->affected_rows;
echo "Deleted {$deleted} existing FAQ(s).\n\n";

// Include seeder to insert defaults
require __DIR__ . '/seed_home_faqs.php';


