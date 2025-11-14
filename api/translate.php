<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'Method Not Allowed']);
	exit;
}

require_once __DIR__ . '/../includes/config.php';

/**
 * Maps common language codes used by the frontend to readable names.
 * Falls back to returning the original code/name if not mapped.
 */
function map_language_label(string $codeOrName): string {
	$map = [
		'en' => 'English',
		'es' => 'Spanish',
		'fr' => 'French',
		'de' => 'German',
		'ar' => 'Arabic',
		'hi' => 'Hindi',
		'id' => 'Indonesian',
		'it' => 'Italian',
		'ja' => 'Japanese',
		'ko' => 'Korean',
		'my' => 'Myanmar',
		'ms' => 'Malay',
		'tl' => 'Filipino',
		'pt' => 'Portuguese',
		'ru' => 'Russian',
		'th' => 'Thai',
		'tr' => 'Turkish',
		'vi' => 'Vietnamese',
		'zh-cn' => 'Chinese (Simplified)',
		'zh-tw' => 'Chinese (Traditional)',
		'bn' => 'Bengali',
	];
	$key = strtolower(trim($codeOrName));
	return $map[$key] ?? $codeOrName;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
	http_response_code(400);
	echo json_encode(['error' => 'Invalid JSON body']);
	exit;
}

$text = isset($payload['text']) ? (string)$payload['text'] : '';
$target = isset($payload['target_lang']) ? (string)$payload['target_lang'] : 'en';
$source = isset($payload['source_lang']) ? (string)$payload['source_lang'] : 'en';

if ($text === '') {
	http_response_code(400);
	echo json_encode(['error' => 'Missing text']);
	exit;
}

// If no translation needed, short-circuit
if (strtolower($target) === strtolower($source) || strtolower($target) === 'en') {
	echo json_encode(['translatedText' => $text]);
	exit;
}

$targetLabel = map_language_label($target);
$sourceLabel = map_language_label($source);

$result = translateText($text, $targetLabel, $sourceLabel);
if (!is_array($result) || empty($result['success'])) {
	http_response_code(200);
	echo json_encode([
		'translatedText' => $text,
		'error' => $result['error'] ?? 'Translation failed',
	]);
	exit;
}

echo json_encode(['translatedText' => (string)$result['response']]);
exit;


