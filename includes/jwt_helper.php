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
