<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../includes/config.php';

header('Content-Type: application/json');

$setting = $_POST['setting'] ?? '';
$enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;

if ($setting !== 'mp3_page_enabled') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid setting']);
    exit;
}

// Ensure column exists
$check = $conn->query("SHOW COLUMNS FROM site_settings LIKE 'mp3_page_enabled'");
if (!$check || $check->num_rows == 0) {
    $conn->query("ALTER TABLE site_settings ADD COLUMN mp3_page_enabled TINYINT(1) DEFAULT 1");
}

// Update setting
$result = $conn->query("UPDATE site_settings SET mp3_page_enabled = $enabled LIMIT 1");

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
exit;

