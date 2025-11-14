<?php
/**
 * Download Logger Helper Functions
 * Handles logging of download attempts and completions
 */

/**
 * Log a download attempt to the database
 * 
 * @param string $url The URL that was downloaded
 * @param string $download_type The type of download (e.g., 'Video (No Watermark)', 'Music (MP3)')
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function logDownload($url, $download_type, $conn, array $meta = []) {
    // Get user's IP address
    $ip_address = getClientIP();
    
    // Get user agent
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    // Get country from IP address
    $country = getCountryFromIP($ip_address);
    
    $file_name = $meta['file_name'] ?? null;
    $file_type = $meta['file_type'] ?? null;
    $file_size_bytes = isset($meta['file_size_bytes']) ? (int)$meta['file_size_bytes'] : null;
    $provider_key = $meta['provider_key'] ?? null;
    
    // Insert into database
    $sql = "INSERT INTO downloads (url, ip_address, user_agent, download_type, country, status, file_name, file_type, file_size_bytes, provider_key) 
            VALUES (?, ?, ?, ?, ?, 'started', ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param(
        "sssssssis",
        $url,
        $ip_address,
        $user_agent,
        $download_type,
        $country,
        $file_name,
        $file_type,
        $file_size_bytes,
        $provider_key
    );
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to log download: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $insertId = $stmt->insert_id ?: $conn->insert_id;
    $stmt->close();
    return $insertId ?: true;
}

/**
 * Update download status (completed/failed)
 * 
 * @param int $download_id The download record ID
 * @param string $status New status ('completed' or 'failed')
 * @param mysqli $conn Database connection
 * @return bool Success status
 */
function updateDownloadStatus($download_id, $status, $conn) {
    $sql = "UPDATE downloads SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Failed to prepare update statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("si", $status, $download_id);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to update download status: " . $stmt->error);
        return false;
    }
    
    $stmt->close();
    return true;
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function getClientIP() {
    $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get country from IP address using free IP geolocation service
 * 
 * @param string $ip IP address
 * @return string Country name or 'Unknown'
 */
function getCountryFromIP($ip) {
    // Skip local/private IPs
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'Local/Private IP';
    }
    
    // Use free IP geolocation service (ipapi.co)
    $url = "http://ip-api.com/json/{$ip}?fields=country,countryCode";
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 3, // 3 second timeout
                'user_agent' => 'Mozilla/5.0 (compatible; DownloadTracker/1.0)'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            $data = json_decode($response, true);
            if ($data && isset($data['country'])) {
                return $data['country'];
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get country for IP {$ip}: " . $e->getMessage());
    }
    
    // Fallback: try alternative service (ipapi.co)
    try {
        $url2 = "https://ipapi.co/{$ip}/json/";
        $context2 = stream_context_create([
            'http' => [
                'timeout' => 3,
                'user_agent' => 'Mozilla/5.0 (compatible; DownloadTracker/1.0)'
            ]
        ]);
        
        $response2 = @file_get_contents($url2, false, $context2);
        
        if ($response2 !== false) {
            $data2 = json_decode($response2, true);
            if ($data2 && isset($data2['country_name'])) {
                return $data2['country_name'];
            }
        }
    } catch (Exception $e) {
        error_log("Failed to get country for IP {$ip} from fallback service: " . $e->getMessage());
    }
    
    return 'Unknown';
}

/**
 * Get download statistics
 * 
 * @param mysqli $conn Database connection
 * @param int $limit Number of recent downloads to return
 * @return array Array of download records
 */
function getDownloadStats($conn, $limit = 100) {
    $sql = "SELECT url, ip_address, download_time, download_type, country, status 
            FROM downloads 
            ORDER BY download_time DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array();
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $downloads = array();
    while ($row = $result->fetch_assoc()) {
        $downloads[] = $row;
    }
    
    $stmt->close();
    return $downloads;
}

/**
 * Get download statistics by country
 * 
 * @param mysqli $conn Database connection
 * @return array Array of country statistics
 */
function getDownloadStatsByCountry($conn) {
    $sql = "SELECT country, COUNT(*) as download_count 
            FROM downloads 
            WHERE country != 'Unknown' AND country != 'Local/Private IP'
            GROUP BY country 
            ORDER BY download_count DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return array();
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = array();
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    $stmt->close();
    return $stats;
}
?>
