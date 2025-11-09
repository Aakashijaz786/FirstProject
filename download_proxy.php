<?php
if (!isset($_GET['url']) || empty($_GET['url'])) {
    http_response_code(400);
    echo 'Missing url parameter.';
    exit;
}
$url = $_GET['url'];
$url = filter_var($url, FILTER_VALIDATE_URL);
if (!$url) {
    http_response_code(400);
    echo 'Invalid url parameter.';
    exit;
}

// Check if this is a metadata request
if (isset($_GET['metadata']) && $_GET['metadata'] === '1') {
    header('Content-Type: application/json');
    
    // Get remote headers to fetch file size
    $headers = @get_headers($url, 1);
    $fileSize = 0;
    $contentType = 'application/octet-stream';
    $remoteFilename = '';
    
    if ($headers !== false) {
        // Content-Length
        if (isset($headers['Content-Length'])) {
            $fileSize = is_array($headers['Content-Length']) ? 
                (int)$headers['Content-Length'][count($headers['Content-Length']) - 1] : 
                (int)$headers['Content-Length'];
        }
        
        // Content-Type
        if (isset($headers['Content-Type'])) {
            $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
        }
        
        // Content-Disposition filename
        if (isset($headers['Content-Disposition'])) {
            if (preg_match('/filename="?([^";]+)"?/i', $headers['Content-Disposition'], $matches)) {
                $remoteFilename = $matches[1];
            }
        }
    }
    
    // Fallback: get filename from URL
    if (!$remoteFilename) {
        $remoteFilename = basename(parse_url($url, PHP_URL_PATH));
    }
    
    // Fallback: generic name
    if (!$remoteFilename) {
        $remoteFilename = 'downloaded_file';
    }
    
    // Add extension if missing
    $ext = pathinfo($remoteFilename, PATHINFO_EXTENSION);
    if (!$ext) {
        // Guess extension from content type
        $mime_map = [
            'video/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-flv' => 'flv',
            'audio/mp4' => 'm4a',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'video/mpeg' => 'mpeg',
        ];
        if (isset($mime_map[$contentType])) {
            $remoteFilename .= '.' . $mime_map[$contentType];
        }
    }
    
    // Format file size
    function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    echo json_encode([
        'success' => true,
        'size' => $fileSize,
        'sizeFormatted' => formatFileSize($fileSize),
        'filename' => $remoteFilename,
        'contentType' => $contentType
    ]);
    exit;
}

// Get remote headers
$headers = @get_headers($url, 1);
$contentType = 'application/octet-stream';
$remoteFilename = '';
$contentLength = 0;
if ($headers !== false) {
    // Content-Type
    if (isset($headers['Content-Type'])) {
        $contentType = is_array($headers['Content-Type']) ? $headers['Content-Type'][0] : $headers['Content-Type'];
    }
    // Content-Length for browser progress display
    if (isset($headers['Content-Length'])) {
        $contentLength = is_array($headers['Content-Length']) ? 
            (int)$headers['Content-Length'][count($headers['Content-Length']) - 1] : 
            (int)$headers['Content-Length'];
    }
    // Content-Disposition filename
    if (isset($headers['Content-Disposition'])) {
        if (preg_match('/filename="?([^";]+)"?/i', $headers['Content-Disposition'], $matches)) {
            $remoteFilename = $matches[1];
        }
    }
}
// Fallback: get filename from URL
if (!$remoteFilename) {
    $remoteFilename = basename(parse_url($url, PHP_URL_PATH));
}
// Fallback: generic name
if (!$remoteFilename) {
    $remoteFilename = 'downloaded_file';
}
// Add extension if missing
$ext = pathinfo($remoteFilename, PATHINFO_EXTENSION);
if (!$ext) {
    // Guess extension from content type
    $mime_map = [
        'video/mp4' => 'mp4',
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/quicktime' => 'mov',
        'video/x-msvideo' => 'avi',
        'video/x-flv' => 'flv',
        'audio/mp4' => 'm4a',
        'audio/wav' => 'wav',
        'audio/ogg' => 'ogg',
        'video/mpeg' => 'mpeg',
    ];
    if (isset($mime_map[$contentType])) {
        $remoteFilename .= '.' . $mime_map[$contentType];
    }
}

// Set headers to force download with correct type and filename
header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $remoteFilename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: 0');
header('Pragma: no-cache');

// Include Content-Length header for browser progress display
if ($contentLength > 0) {
    header('Content-Length: ' . $contentLength);
}

// Stream the file with progress tracking
$fp = fopen($url, 'rb');
if ($fp) {
    $downloaded = 0;
    $chunkSize = 8192; // 8KB chunks
    
    while (!feof($fp)) {
        $chunk = fread($fp, $chunkSize);
        if ($chunk !== false) {
            echo $chunk;
            $downloaded += strlen($chunk);
            flush();
            
            // Optional: Add a small delay to prevent overwhelming the server
            // usleep(1000); // 1ms delay
        }
    }
    fclose($fp);
} else {
    http_response_code(500);
    echo 'Failed to download file.';
} 
?>