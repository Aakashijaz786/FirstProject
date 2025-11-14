<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'Method Not Allowed']);
	exit;
}

require_once __DIR__ . '/../includes/config.php';

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

$texts = isset($payload['texts']) && is_array($payload['texts']) ? $payload['texts'] : [];
$target = isset($payload['target_lang']) ? (string)$payload['target_lang'] : 'en';
$source = isset($payload['source_lang']) ? (string)$payload['source_lang'] : 'en';

if (empty($texts)) {
	http_response_code(400);
	echo json_encode(['error' => 'Missing texts']);
	exit;
}

// If no translation needed, short-circuit
if (strtolower($target) === strtolower($source) || strtolower($target) === 'en') {
	echo json_encode(['translations' => $texts]);
	exit;
}

$targetLabel = map_language_label($target);
$sourceLabel = map_language_label($source);

// Build a single JSON object to translate in one OpenAI call
$jsonPayload = json_encode($texts, JSON_UNESCAPED_UNICODE);
$prompt = "Translate the following JSON object from {$sourceLabel} to {$targetLabel}. "
	. "Return ONLY valid JSON with the same keys and translated string values, nothing else:\n\n{$jsonPayload}";
$system_message = "You are a professional translator. Return strictly valid JSON, no comments, no extra text.";

$res = chatGPT($prompt, $system_message);
if (is_array($res) && !empty($res['success'])) {
	$rawOut = $res['response'];
	$parsed = json_decode($rawOut, true);
	if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
		echo json_encode(['translations' => $parsed], JSON_UNESCAPED_UNICODE);
		exit;
	}
	// Fallback: try to extract JSON from free-form text
	if (preg_match('/\{[\s\S]*\}/', $rawOut, $m)) {
		$parsed = json_decode($m[0], true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
			echo json_encode(['translations' => $parsed], JSON_UNESCAPED_UNICODE);
			exit;
		}
	}
}

// Last resort: return originals (no delay)
echo json_encode(['translations' => $texts], JSON_UNESCAPED_UNICODE);
exit;


