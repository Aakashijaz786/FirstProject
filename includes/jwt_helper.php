<?php

if (!function_exists('base64url_encode')) {
    function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('base64url_decode_safe')) {
    function base64url_decode_safe(string $data): string {
        $padding = 4 - (strlen($data) % 4);
        if ($padding < 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('create_jwt_token')) {
    function create_jwt_token(array $payload, string $secret, int $ttlSeconds = 300): string {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $segments = [
            base64url_encode(json_encode($header)),
            base64url_encode(json_encode($payload))
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = base64url_encode($signature);
        return implode('.', $segments);
    }
}

if (!function_exists('decode_jwt_token')) {
    function decode_jwt_token(string $token, string $secret): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token structure');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signature = base64url_decode_safe($encodedSignature);
        $payloadJson = base64url_decode_safe($encodedPayload);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new Exception('Invalid token payload');
        }

        $expected = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true);
        if (!hash_equals($expected, $signature)) {
            throw new Exception('Signature mismatch');
        }

        if (isset($payload['exp']) && time() > (int)$payload['exp']) {
            throw new Exception('Token expired');
        }

        return $payload;
    }
}

/**
 * JWTHelper: thin OO wrapper around the low-level JWT helpers.
 *
 * Used by php_router.php to:
 *  - convert query strings (search / download) into short-lived JWT tokens
 *  - decode JWT tokens coming from SEO URLs (/search/{lang}/{jwt}/ etc.)
 *
 * Tokens are signed with the site_settings.jwt_secret value so that
 * all parts of the app (legacy PHP pages, router, FastAPI) share
 * the same secret.
 */
if (!class_exists('JWTHelper')) {
    class JWTHelper
    {
        /**
         * Resolve the signing secret from site_settings, falling back to
         * a safe default if the DB or helper functions are not available.
         */
        protected static function getSecret(): string
        {
            static $secret = null;
            if ($secret !== null) {
                return $secret;
            }

            // Try to load config + api_client to get get_site_settings_cached().
            $apiClientPath = __DIR__ . '/api_client.php';
            if (file_exists($apiClientPath)) {
                require_once $apiClientPath;
            }

            // Use cached settings if available, otherwise fall back.
            if (function_exists('get_site_settings_cached') && isset($GLOBALS['conn'])) {
                $settings = get_site_settings_cached($GLOBALS['conn']);
                $candidate = (string)($settings['jwt_secret'] ?? '');
                if ($candidate !== '') {
                    $secret = $candidate;
                    return $secret;
                }
            }

            // Fallback secret (development only).
            $secret = 'change-me';
            return $secret;
        }

        /**
         * Create a JWT from an arbitrary query string for a given type,
         * e.g. type = "search" or "download".
         */
        public static function convertQueryToJWT(string $query, string $type, int $ttlSeconds = 900): string
        {
            $payload = [
                'type' => $type,
                'q'    => $query,
            ];

            return create_jwt_token($payload, self::getSecret(), $ttlSeconds);
        }

        /**
         * Decode a JWT string; returns payload array on success,
         * or null if the token is invalid / expired.
         */
        public static function decode(string $token): ?array
        {
            if ($token === '') {
                return null;
            }

            try {
                return decode_jwt_token($token, self::getSecret());
            } catch (Exception $e) {
                return null;
            }
        }
    }
}
