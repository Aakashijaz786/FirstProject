<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo 'Not authorized';
    exit;
}

require_once '../includes/config.php';

$lang_id = isset($_POST['lang_id']) ? intval($_POST['lang_id']) : 0;
$page_key = isset($_POST['page_key']) ? $_POST['page_key'] : '';
$enabled = isset($_POST['enabled']) ? intval($_POST['enabled']) : 0;

// List of allowed columns to prevent SQL injection
$allowed_keys = [
    'home_enabled',
    'mp3_enabled',
    'stories_enabled',
    'how_enabled',
    'copyright_enabled',
    'terms_enabled',
    'contact_enabled',
    'privacy_enabled',
    'faqs_enabled',
    'create_new_enabled'
];

if (!$lang_id || !in_array($page_key, $allowed_keys)) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$sql = "UPDATE languages SET `$page_key` = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('ii', $enabled, $lang_id);
    if ($stmt->execute()) {
        echo 'success';
    } else {
        http_response_code(500);
        echo 'Failed to update';
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo 'Failed to prepare statement';
}
?>