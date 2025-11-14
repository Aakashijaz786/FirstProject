<?php

require_once __DIR__ . '/config.php';

if (!function_exists('get_site_settings_cached')) {
    $GLOBALS['_site_settings_cache'] = null;
    function get_site_settings_cached($conn) {
        global $_site_settings_cache;
        if ($_site_settings_cache !== null) {
            return $_site_settings_cache;
        }

        $res = $conn->query("SELECT * FROM site_settings LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $_site_settings_cache = $res->fetch_assoc();
        } else {
            $_site_settings_cache = [
                'site_name' => 'TikTok Downloader',
                'fastapi_base_url' => 'http://127.0.0.1:8000',
                'fastapi_auth_key' => 'change-me',
                'jwt_secret' => 'change-me',
                'active_api_provider' => 'ytdlp',
            ];
        }
        return $_site_settings_cache;
    }
}

if (!function_exists('refresh_site_settings_cache')) {
    function refresh_site_settings_cache() {
        $GLOBALS['_site_settings_cache'] = null;
    }
}

if (!function_exists('get_active_api_provider')) {
    function get_active_api_provider($conn) {
        $settings = get_site_settings_cached($conn);
        return $settings['active_api_provider'] ?? 'ytdlp';
    }
}

if (!function_exists('media_api_call')) {
    function media_api_call($conn, $endpoint, array $payload) {
        $settings = get_site_settings_cached($conn);
        $baseUrl = rtrim($settings['fastapi_base_url'] ?? 'http://127.0.0.1:8000', '/');
        $authKey = $settings['fastapi_auth_key'] ?? '';

        $url = $baseUrl . $endpoint;
        $ch = curl_init($url);
        
        // Longer timeout for download endpoint
        $timeout = ($endpoint === '/download') ? 180 : 120;
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Internal-Key: ' . $authKey,
                'User-Agent: TikTokIO-MediaBridge/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => $curlError];
        }

        // Log the raw response for debugging
        error_log("FastAPI Response (Status $statusCode): " . substr($response, 0, 500));

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response from backend',
                'body' => substr($response, 0, 200),
                'status' => $statusCode
            ];
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                'success' => false,
                'error' => $decoded['detail'] ?? ($decoded['error'] ?? 'FastAPI error'),
                'status' => $statusCode,
                'details' => $decoded
            ];
        }

        return ['success' => true, 'data' => $decoded];
    }
}

if (!function_exists('media_api_search')) {
    function media_api_search($conn, $query, $provider, $limit = 5, $preferAudio = true) {
        $payload = [
            'query' => $query,
            'provider' => $provider,
            'limit' => $limit,
            'prefer_audio' => $preferAudio,
        ];
        return media_api_call($conn, '/search', $payload);
    }
}

if (!function_exists('media_api_download')) {
    function media_api_download(
        $conn,
        $url,
        $provider,
        $format,
        $quality,
        $titleOverride,
        $siteName = null
    ) {
        $payload = [
            'url' => $url,
            'provider' => $provider,
            'format' => $format,
            'quality' => $quality,
            'title_override' => $titleOverride,
            'site_name' => $siteName,
        ];
        return media_api_call($conn, '/download', $payload);
    }
}
