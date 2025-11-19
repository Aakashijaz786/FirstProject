<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/yt_frontend.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$action = $_GET['action'] ?? 'content';

try {
    if ($action === 'languages') {
        $languages = [];
        $res = $conn->query("SELECT id, name, code, is_default, image FROM languages ORDER BY name ASC");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $image = '';
                if (!empty($row['image'])) {
                    $image = '/' . ltrim($row['image'], '/');
                }
                $languages[] = [
                    'id' => (int)$row['id'],
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'is_default' => (int)$row['is_default'] === 1,
                    'image' => $image,
                ];
            }
        }
        echo json_encode(['languages' => $languages], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'content') {
        $page = strtolower($_GET['page'] ?? 'home');
        $available = yt_frontend_available_pages();
        if (!in_array($page, $available, true)) {
            throw new RuntimeException('Invalid page requested.');
        }

        $langCode = $_GET['lang'] ?? '';
        $language = null;
        if ($langCode !== '') {
            $language = yt_frontend_language_by_code($conn, $langCode);
        }
        if (!$language) {
            $defaultId = yt_frontend_default_language_id($conn);
            if ($defaultId > 0) {
                $res = $conn->query("SELECT * FROM languages WHERE id={$defaultId} LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $language = $res->fetch_assoc();
                }
            }
        }
        if (!$language) {
            throw new RuntimeException('Language not found.');
        }

        $strings = yt_frontend_resolve_strings($conn, (int)$language['id'], $page);

        // Override key navigation/footer strings with language table values
        $language_overrides = [
            'navDownloader' => $language['tiktok_downloaders'] ?? '',
            'navMP3' => $language['how_to_save'] ?? '',
            'navMP4' => $language['stories'] ?? '',
            'contact' => $language['contact'] ?? '',
            'privacy' => $language['privacy_policy'] ?? '',
            'terms' => $language['terms_conditions'] ?? '',
            'convertBtn' => $language['download_label'] ?? '',
            'convertNow' => $language['download_label'] ?? '',
        ];
        foreach ($language_overrides as $key => $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $strings[$key] = $value;
            }
        }

        // Fetch FAQs for all pages (home, mp3, mp4)
        $faqs = [];
        $langId = (int)$language['id'];
        $res = $conn->query("SELECT id, question, answer FROM language_faqs WHERE language_id={$langId} ORDER BY id ASC");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $faqs[] = [
                    'id' => (int)$row['id'],
                    'question' => $row['question'],
                    'answer' => $row['answer'] ?? '', // Ensure answer is always a string
                ];
            }
        }

        $response = [
            'page' => $page,
            'mode' => yt_frontend_mode($page),
            'language' => [
                'id' => (int)$language['id'],
                'code' => $language['code'],
                'name' => $language['name'],
                'image' => !empty($language['image']) ? '/' . ltrim($language['image'], '/') : '',
            ],
            'strings' => $strings,
        ];

        // Always include FAQs in response if they exist
        if (!empty($faqs)) {
            $response['faqs'] = $faqs;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
