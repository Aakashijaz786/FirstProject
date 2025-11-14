<?php
/**
 * Direct test of FastAPI download endpoint
 */

$url = "http://127.0.0.1:8001/download";
$payload = [
    'url' => 'https://youtu.be/-3KT1f7WZIo',
    'provider' => 'ytdlp',
    'format' => 'mp3',
    'quality' => '320',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
    ],
]);

echo "Testing FastAPI /download endpoint directly...\n";
echo "URL: $url\n";
echo "Payload: " . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status Code: $statusCode\n";
echo "Response:\n";
echo $response . "\n\n";

if ($statusCode !== 200) {
    echo "\n*** ERROR: Got non-200 status code ***\n";
    echo "This means FastAPI is returning an error.\n";
    echo "Please check the FastAPI console window for the actual error message.\n";
}

