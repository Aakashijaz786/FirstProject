<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/config.php';

function get_language_id_by_code($conn, $code) {
	$code = $conn->real_escape_string(strtolower(trim($code ?: 'en')));
	$res = $conn->query("SELECT id FROM languages WHERE LOWER(code)='{$code}' LIMIT 1");
	if ($res && $res->num_rows) {
		$row = $res->fetch_assoc();
		return (int)$row['id'];
	}
	// Fallback to English id if known, else first row
	$res = $conn->query("SELECT id FROM languages WHERE LOWER(name)='english' OR LOWER(code)='en' LIMIT 1");
	if ($res && $res->num_rows) {
		$row = $res->fetch_assoc();
		return (int)$row['id'];
	}
	$res = $conn->query("SELECT id FROM languages ORDER BY id ASC LIMIT 1");
	if ($res && $res->num_rows) {
		$row = $res->fetch_assoc();
		return (int)$row['id'];
	}
	return 0;
}

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'en';
$lang_id = get_language_id_by_code($conn, $lang);

$payload = [
	'faqs_enabled' => 1,
	'faqs' => [],
];

if ($lang_id > 0) {
	$res = $conn->query("SELECT faqs_enabled, faqs FROM languages_home WHERE language_id={$lang_id} LIMIT 1");
	if ($res && $res->num_rows) {
		$row = $res->fetch_assoc();
		$payload['faqs_enabled'] = (int)$row['faqs_enabled'];
		$payload['faqs'] = $row['faqs'] ? json_decode($row['faqs'], true) : [];
	} else {
		// Fallback to English home if present (ID 41 or English by name)
		$enId = get_language_id_by_code($conn, 'en');
		$enRes = $conn->query("SELECT faqs_enabled, faqs FROM languages_home WHERE language_id={$enId} LIMIT 1");
		if ($enRes && $enRes->num_rows) {
			$row = $enRes->fetch_assoc();
			$payload['faqs_enabled'] = (int)$row['faqs_enabled'];
			$payload['faqs'] = $row['faqs'] ? json_decode($row['faqs'], true) : [];
		}
	}
}

echo json_encode($payload);
exit;


