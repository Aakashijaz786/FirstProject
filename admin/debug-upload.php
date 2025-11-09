<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    die('Not logged in');
}

echo "<h2>Upload Debug Test</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Direct Upload Test</h3>";
    
    // Test the upload.php directly
    $upload_url = 'upload.php';
    
    if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
        echo "<p>File uploaded successfully to debug script.</p>";
        echo "<p>Now testing upload.php...</p>";
        
        // Create a copy of the uploaded file for testing
        $temp_file = tempnam(sys_get_temp_dir(), 'debug_upload');
        copy($_FILES['upload']['tmp_name'], $temp_file);
        
        // Test upload.php with cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $upload_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'upload' => new CURLFile($temp_file, $_FILES['upload']['type'], $_FILES['upload']['name'])
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: ' . session_name() . '=' . session_id()
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        unlink($temp_file);
        
        echo "<h4>Upload.php Response:</h4>";
        echo "<p><strong>HTTP Code:</strong> " . $http_code . "</p>";
        if ($curl_error) {
            echo "<p><strong>cURL Error:</strong> " . $curl_error . "</p>";
        }
        echo "<p><strong>Raw Response:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
        echo htmlspecialchars($response);
        echo "</pre>";
        
        // Try to decode JSON
        $json_data = json_decode($response, true);
        if ($json_data) {
            echo "<p><strong>JSON Decoded Successfully:</strong></p>";
            echo "<pre style='background: #e8f5e8; padding: 10px; border: 1px solid #4caf50;'>";
            print_r($json_data);
            echo "</pre>";
        } else {
            echo "<p style='color: red;'><strong>JSON Decode Failed:</strong> " . json_last_error_msg() . "</p>";
            
            // Check for common issues
            if (strpos($response, '<') !== false) {
                echo "<p style='color: red;'>Response contains HTML - this might be an error page.</p>";
            }
            if (strpos($response, 'Warning') !== false || strpos($response, 'Error') !== false) {
                echo "<p style='color: red;'>Response contains PHP warnings/errors.</p>";
            }
        }
        
        // Test what Summernote expects
        echo "<h4>Summernote Expected Format:</h4>";
        echo "<p>Summernote expects one of these formats:</p>";
        echo "<ol>";
        echo "<li><code>{'url': '/path/to/image.jpg'}</code> (simple format)</li>";
        echo "<li><code>{'uploaded': true, 'url': '/path/to/image.jpg'}</code> (with status)</li>";
        echo "</ol>";
        
        // Show what we're actually returning
        echo "<h4>Current Response Analysis:</h4>";
        if ($json_data) {
            if (isset($json_data['url'])) {
                echo "<p style='color: green;'>✓ Response contains 'url' field</p>";
            } else {
                echo "<p style='color: red;'>✗ Response missing 'url' field</p>";
            }
            if (isset($json_data['uploaded'])) {
                echo "<p style='color: green;'>✓ Response contains 'uploaded' field</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Response missing 'uploaded' field (optional)</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>File upload failed: " . ($_FILES['upload']['error'] ?? 'No file') . "</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        pre { overflow-x: auto; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="upload" accept="image/*" required>
        <br><br>
        <button type="submit">Test Upload</button>
    </form>
</body>
</html>
