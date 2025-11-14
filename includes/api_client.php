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

if (!function_exists('is_api_provider_enabled')) {
    function is_api_provider_enabled($conn, $provider_key) {
        if (!$provider_key) {
            return false;
        }
        $stmt = $conn->prepare("SELECT is_enabled FROM api_providers WHERE provider_key=? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $provider_key);
        $stmt->execute();
        $stmt->bind_result($is_enabled);
        $has_row = $stmt->fetch();
        $stmt->close();
        return $has_row ? (int)$is_enabled === 1 : false;
    }
}

if (!function_exists('choose_fallback_provider')) {
    function choose_fallback_provider($conn) {
        $res = $conn->query("
            SELECT provider_key
            FROM api_providers
            WHERE is_enabled = 1
            ORDER BY CASE provider_key
                WHEN 'cobalt' THEN 1
                WHEN 'ytdlp' THEN 2
                WHEN 'iframe' THEN 3
                ELSE 4
            END
            LIMIT 1
        ");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            return $row['provider_key'];
        }
        return null;
    }
}

if (!function_exists('get_active_api_provider')) {
    function get_active_api_provider($conn, $force_refresh = false) {
        if ($force_refresh) {
            refresh_site_settings_cache();
        }
        $settings = get_site_settings_cached($conn);
        $current = strtolower($settings['active_api_provider'] ?? 'ytdlp');

        if (is_api_provider_enabled($conn, $current)) {
            return $current;
        }

        // Pick the first enabled provider (prefer cobalt -> ytdlp -> iframe)
        $fallback = choose_fallback_provider($conn);
        if (!$fallback) {
            // As a last resort, re-enable YTDLP
            $conn->query("UPDATE api_providers SET is_enabled=1 WHERE provider_key='ytdlp' LIMIT 1");
            $fallback = 'ytdlp';
        }

        // Persist the new active provider so PHP + FastAPI stay in sync
        if ($fallback !== $current) {
            $stmt = $conn->prepare("UPDATE site_settings SET active_api_provider=? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $fallback);
                $stmt->execute();
                $stmt->close();
                refresh_site_settings_cache();
            }
        }

        return $fallback;
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
