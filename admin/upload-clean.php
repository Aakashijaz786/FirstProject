<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload failed']);
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$file_type = $_FILES['upload']['type'];
$file_extension = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));

if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

// Create upload directory
$upload_dir = __DIR__ . '/../uploads/ckeditor/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate filename
$filename = time() . '_' . basename($_FILES['upload']['name']);
$target_path = $upload_dir . $filename;

// Move file
if (move_uploaded_file($_FILES['upload']['tmp_name'], $target_path)) {
    echo json_encode([
        'uploaded' => true,
        'url' => '/uploads/ckeditor/' . $filename
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file']);
}
?>
