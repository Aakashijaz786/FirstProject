<?php
// Start output buffering to prevent any accidental output
ob_start();

session_start();

// Disable error display to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// Clear any output buffer content
ob_clean();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    ob_end_flush();
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

// Log the request for debugging (only to error log, not output)
error_log("Summernote Upload Debug - POST data: " . print_r($_POST, true));
error_log("Summernote Upload Debug - FILES data: " . print_r($_FILES, true));

// Check if file was uploaded
if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'No file uploaded or upload error occurred.';
    if (isset($_FILES['upload']['error'])) {
        switch ($_FILES['upload']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File is too large.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file was uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Missing temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'File upload stopped by extension.';
                break;
        }
    }
    http_response_code(400);
    echo json_encode(['error' => $error_message]);
    ob_end_flush();
    exit;
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$file_type = $_FILES['upload']['type'];
$file_extension = strtolower(pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

if (!in_array($file_type, $allowed_types) || !in_array($file_extension, $allowed_extensions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, WebP, and SVG files are allowed.']);
    ob_end_flush();
    exit;
}

// Validate file size (5MB max)
$max_size = 5 * 1024 * 1024; // 5MB
if ($_FILES['upload']['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['error' => 'File is too large. Maximum size is 5MB.']);
    ob_end_flush();
    exit;
}

// Create upload directory if it doesn't exist
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create upload directory.']);
        ob_end_flush();
        exit;
    }
}

// Create ckeditor subdirectory if it doesn't exist
$ckeditor_dir = $upload_dir . 'ckeditor/';
if (!is_dir($ckeditor_dir)) {
    if (!mkdir($ckeditor_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create ckeditor directory.']);
        ob_end_flush();
        exit;
    }
}

// Generate unique filename
$original_name = $_FILES['upload']['name'];
$filename = pathinfo($original_name, PATHINFO_FILENAME);
$extension = pathinfo($original_name, PATHINFO_EXTENSION);
$timestamp = time();
$random_string = substr(md5(uniqid()), 0, 8);
$new_filename = $filename . '_' . $timestamp . '_' . $random_string . '.' . $extension;

// Full path to the uploaded file
$target_path = $ckeditor_dir . $new_filename;

// Move uploaded file to target directory
if (!move_uploaded_file($_FILES['upload']['tmp_name'], $target_path)) {
    error_log("Summernote Upload Error: Failed to move uploaded file from {$_FILES['upload']['tmp_name']} to {$target_path}");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move uploaded file.']);
    ob_end_flush();
    exit;
}

// Get the URL for the uploaded file
$file_url = '/uploads/ckeditor/' . $new_filename;

// Return success response in Summernote format
echo json_encode([
    'uploaded' => true,
    'url' => $file_url,
    'fileName' => $new_filename,
    'originalName' => $original_name
]);

// Clean up output buffer and end
ob_end_flush();
exit;
?>
