<?php
/**
 * Schema bootstrapper
 * Ensures new tables/columns needed by the hybrid PHP + FastAPI stack exist.
 * Kept lightweight so it can safely run on every request.
 */

if (!function_exists('bootstrap_core_schema')) {
    /**
     * Execute a CREATE TABLE IF NOT EXISTS statement.
     */
    function ensure_table($conn, $table, $createSql)
    {
        $safeTable = $conn->real_escape_string($table);
        $exists = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        if ($exists && $exists->num_rows > 0) {
            return;
        }

        $conn->query($createSql);
    }

    /**
     * Add the column if it is missing.
     */
    function ensure_column($conn, $table, $column, $definition)
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $exists = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if ($exists && $exists->num_rows > 0) {
            return;
        }

        $conn->query("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
    }

    /**
     * Generate a random secret string.
     */
    function bootstrap_random_secret($bytes = 32)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($bytes));
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($bytes));
        }

        // Fallback to mt_rand
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $secret = '';
        for ($i = 0; $i < $bytes * 2; $i++) {
            $secret .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }
        return $secret;
    }

    /**
     * Insert default rows for a table/unique key pair.
     */
    function bootstrap_default_rows($conn, $table, $uniqueField, $rows)
    {
        foreach ($rows as $row) {
            if (!isset($row[$uniqueField])) {
                continue;
            }

            $key = $conn->real_escape_string($row[$uniqueField]);
            $exists = $conn->query("SELECT {$uniqueField} FROM `{$table}` WHERE {$uniqueField} = '{$key}' LIMIT 1");
            if ($exists && $exists->num_rows > 0) {
                continue;
            }

            $columns = array_keys($row);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $colSql = implode(', ', array_map(static function ($col) {
                return "`{$col}`";
            }, $columns));

            $stmt = $conn->prepare("INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})");
            if (!$stmt) {
                continue;
            }

            $types = str_repeat('s', count($columns));
            $values = array_values($row);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
        }
    }

    function bootstrap_core_schema($conn)
    {
        // Ensure site_settings base table exists before we touch anything else.
        ensure_table(
            $conn,
            'site_settings',
            "CREATE TABLE `site_settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `site_name` VARCHAR(255) NOT NULL,
                `site_url` VARCHAR(255) NOT NULL,
                `site_email` VARCHAR(255) NOT NULL,
                `site_phone` VARCHAR(50) DEFAULT NULL,
                `site_status` TINYINT(1) DEFAULT 1,
                `download_app_enabled` TINYINT(1) DEFAULT 1,
                `jwt_secret` VARCHAR(255) DEFAULT NULL,
                `fastapi_base_url` VARCHAR(255) DEFAULT 'http://127.0.0.1:8000',
                `fastapi_auth_key` VARCHAR(255) DEFAULT NULL,
                `active_api_provider` VARCHAR(50) DEFAULT 'ytdlp',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        ensure_column($conn, 'site_settings', 'jwt_secret', " `jwt_secret` VARCHAR(255) DEFAULT NULL AFTER `download_app_enabled` ");
        ensure_column($conn, 'site_settings', 'fastapi_base_url', " `fastapi_base_url` VARCHAR(255) DEFAULT 'http://127.0.0.1:8000' AFTER `jwt_secret` ");
        ensure_column($conn, 'site_settings', 'fastapi_auth_key', " `fastapi_auth_key` VARCHAR(255) DEFAULT NULL AFTER `fastapi_base_url` ");
        ensure_column($conn, 'site_settings', 'active_api_provider', " `active_api_provider` VARCHAR(50) DEFAULT 'ytdlp' AFTER `fastapi_auth_key` ");
        // Home FAQ visibility toggle
        ensure_column($conn, 'site_settings', 'faq_enabled', " `faq_enabled` TINYINT(1) DEFAULT 1 AFTER `download_app_enabled` ");
        // MP3 page visibility toggle
        ensure_column($conn, 'site_settings', 'mp3_page_enabled', " `mp3_page_enabled` TINYINT(1) DEFAULT 1 AFTER `download_app_enabled` ");

        // Per-language Home page FAQs support
        ensure_column($conn, 'languages_home', 'faqs_enabled', " `faqs_enabled` TINYINT(1) DEFAULT 1 ");
        ensure_column($conn, 'languages_home', 'faqs', " `faqs` MEDIUMTEXT DEFAULT NULL ");

        // Minimal tables for MP3/MP4 pages (simple content + meta)
        ensure_table(
            $conn,
            'languages_mp3',
            "CREATE TABLE `languages_mp3` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `language_id` INT NOT NULL,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` VARCHAR(255) DEFAULT NULL,
                `header` VARCHAR(255) DEFAULT NULL,
                `title1` VARCHAR(255) DEFAULT NULL,
                `description1` MEDIUMTEXT DEFAULT NULL,
                `heading2_description` MEDIUMTEXT DEFAULT NULL,
                INDEX (`language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        ensure_column($conn, 'languages_mp3', 'content_json', " `content_json` LONGTEXT DEFAULT NULL ");
        ensure_table(
            $conn,
            'languages_mp4',
            "CREATE TABLE `languages_mp4` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `language_id` INT NOT NULL,
                `meta_title` VARCHAR(255) DEFAULT NULL,
                `meta_description` VARCHAR(255) DEFAULT NULL,
                `header` VARCHAR(255) DEFAULT NULL,
                `title1` VARCHAR(255) DEFAULT NULL,
                `description1` MEDIUMTEXT DEFAULT NULL,
                `heading2_description` MEDIUMTEXT DEFAULT NULL,
                INDEX (`language_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        ensure_column($conn, 'languages_mp4', 'content_json', " `content_json` LONGTEXT DEFAULT NULL ");

        // Ensure API provider tables.
        ensure_table(
            $conn,
            'api_providers',
            "CREATE TABLE `api_providers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `provider_key` VARCHAR(50) NOT NULL UNIQUE,
                `display_name` VARCHAR(150) NOT NULL,
                `is_enabled` TINYINT(1) DEFAULT 0,
                `config_payload` TEXT DEFAULT NULL,
                `updated_by` VARCHAR(100) DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        ensure_table(
            $conn,
            'api_proxies',
            "CREATE TABLE `api_proxies` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `provider_key` VARCHAR(50) NOT NULL,
                `proxy_label` VARCHAR(150) DEFAULT NULL,
                `proxy_uri` VARCHAR(255) NOT NULL,
                `auth_username` VARCHAR(150) DEFAULT NULL,
                `auth_password` VARCHAR(150) DEFAULT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `last_used_at` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_provider_key` (`provider_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        ensure_table(
            $conn,
            'ads_slots',
            "CREATE TABLE `ads_slots` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `slot_key` VARCHAR(100) NOT NULL UNIQUE,
                `slot_name` VARCHAR(150) NOT NULL,
                `description` VARCHAR(255) DEFAULT NULL,
                `placement_hint` VARCHAR(150) DEFAULT NULL,
                `ad_code` MEDIUMTEXT DEFAULT NULL,
                `is_enabled` TINYINT(1) DEFAULT 0,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        ensure_table(
            $conn,
            'yt_page_content',
            "CREATE TABLE `yt_page_content` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `language_id` INT NOT NULL,
                `page_key` VARCHAR(32) NOT NULL,
                `content_json` LONGTEXT DEFAULT NULL,
                `updated_by` VARCHAR(100) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_lang_page` (`language_id`, `page_key`),
                KEY `idx_page_key` (`page_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );

        // Upgrade downloads table with richer metadata.
        ensure_column($conn, 'downloads', 'file_name', " `file_name` VARCHAR(255) DEFAULT NULL AFTER `download_type` ");
        ensure_column($conn, 'downloads', 'file_type', " `file_type` VARCHAR(50) DEFAULT NULL AFTER `file_name` ");
        ensure_column($conn, 'downloads', 'file_size_bytes', " `file_size_bytes` BIGINT DEFAULT NULL AFTER `file_type` ");
        ensure_column($conn, 'downloads', 'provider_key', " `provider_key` VARCHAR(50) DEFAULT NULL AFTER `file_size_bytes` ");

        // Seed API providers if missing.
        bootstrap_default_rows($conn, 'api_providers', 'provider_key', [
            [
                'provider_key' => 'ytdlp',
                'display_name' => 'YTDLP Engine',
                'is_enabled' => '1'
            ],
            [
                'provider_key' => 'cobalt',
                'display_name' => 'Cobalt API',
                'is_enabled' => '0'
            ],
            [
                'provider_key' => 'iframe',
                'display_name' => 'Internal Iframe',
                'is_enabled' => '0'
            ]
        ]);

        // Seed ad slots (blank code by default).
        bootstrap_default_rows($conn, 'ads_slots', 'slot_key', [
            [
                'slot_key' => 'global_header',
                'slot_name' => 'Global Header',
                'description' => 'Renders below the global navigation.',
                'placement_hint' => 'header',
                'is_enabled' => '0'
            ],
            [
                'slot_key' => 'search_inline',
                'slot_name' => 'Search Inline',
                'description' => 'Shown on /search results before cards.',
                'placement_hint' => 'search',
                'is_enabled' => '0'
            ],
            [
                'slot_key' => 'download_sidebar',
                'slot_name' => 'Download Sidebar',
                'description' => 'Shown near the download CTA.',
                'placement_hint' => 'download',
                'is_enabled' => '0'
            ]
        ]);

        // Ensure JWT secret + FastAPI auth tokens exist.
        $settingsRes = $conn->query("SELECT id, jwt_secret, fastapi_auth_key, active_api_provider FROM site_settings ORDER BY id ASC LIMIT 1");
        if ($settingsRes && $settingsRes->num_rows > 0) {
            $settings = $settingsRes->fetch_assoc();
            $needsUpdate = false;
            $newSecret = $settings['jwt_secret'];
            $newFastApiKey = $settings['fastapi_auth_key'];

            if (!$settings['jwt_secret']) {
                $newSecret = bootstrap_random_secret(32);
                $needsUpdate = true;
            }

            if (!$settings['fastapi_auth_key']) {
                $newFastApiKey = bootstrap_random_secret(24);
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $stmt = $conn->prepare("UPDATE site_settings SET jwt_secret = ?, fastapi_auth_key = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('ssi', $newSecret, $newFastApiKey, $settings['id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $secret = bootstrap_random_secret(32);
            $fastKey = bootstrap_random_secret(24);
            $defaults = [
                'site_name' => 'TikTok Downloader',
                'site_url' => 'https://example.com',
                'site_email' => 'hello@example.com',
                'site_phone' => '',
                'site_status' => '1',
                'download_app_enabled' => '1',
                'jwt_secret' => $secret,
                'fastapi_base_url' => 'http://127.0.0.1:8000',
                'fastapi_auth_key' => $fastKey,
                'active_api_provider' => 'ytdlp'
            ];
            bootstrap_default_rows($conn, 'site_settings', 'site_url', [$defaults]);
        }
    }
}

bootstrap_core_schema($conn);
