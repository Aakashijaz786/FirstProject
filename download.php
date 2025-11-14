<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';
require_once __DIR__ . '/includes/jwt_helper.php';
require_once __DIR__ . '/includes/download_logger.php';
require_once __DIR__ . '/includes/ads_helper.php';

if (!isset($_SESSION['download_sessions']) || !is_array($_SESSION['download_sessions'])) {
    $_SESSION['download_sessions'] = [];
}

function purge_download_sessions($ttlSeconds = 1800)
{
    if (!isset($_SESSION['download_sessions']) || !is_array($_SESSION['download_sessions'])) {
        return;
    }
    $now = time();
    foreach ($_SESSION['download_sessions'] as $key => $payload) {
        $created = $payload['created_at'] ?? 0;
        if ($created < ($now - $ttlSeconds)) {
            unset($_SESSION['download_sessions'][$key]);
        }
    }
}

purge_download_sessions();

$settings = get_site_settings_cached($conn);
$jwt_secret = $settings['jwt_secret'] ?? 'change-me';
$download_token = $_GET['token'] ?? '';
$download_payload = null;
$error_message = '';
$page_data = [
    'meta_title' => 'Download',
    'meta_description' => 'Download your media file'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_token = $_POST['search_token'] ?? '';
    $format = $_POST['format'] ?? 'mp3';
    $item_index = (int)($_POST['item_index'] ?? 0);

    if (!$search_token) {
        $error_message = 'Missing search token.';
    } else {
        try {
            $search_payload = decode_jwt_token($search_token, $jwt_secret);
            if (($search_payload['type'] ?? '') !== 'search' || empty($search_payload['sid'])) {
                $error_message = 'Invalid search token.';
            } else {
                $sid = $search_payload['sid'];
                $search_sessions = $_SESSION['search_sessions'][$sid] ?? null;
                if (!$search_sessions) {
                    $error_message = 'Search session expired. Please run the search again.';
                } elseif (!isset($search_sessions['results'][$item_index])) {
                    $error_message = 'Selected track is no longer available.';
                } else {
                    $item = $search_sessions['results'][$item_index];
                    $media_url = $item['url'] ?? '';
                    if (!$media_url) {
                        $error_message = 'Media URL missing.';
                    } else {
<<<<<<< HEAD
                        $provider = $search_sessions['provider'];
=======
                        // Use current active provider from database, not cached from search session
                        // Force refresh to ensure we get the latest value after admin changes
                        $provider = get_active_api_provider($conn, true);
>>>>>>> 8ebeba8d92f90dee34c5c4b2f95c8e1979a3f284
                        $api_response = media_api_download(
                            $conn,
                            $media_url,
                            $provider,
                            $format,
                            null,
                            $item['title'] ?? null,
                            $settings['site_name'] ?? 'TikTok Downloader'
                        );
                        if (!$api_response['success']) {
                            $error_message = 'Failed to prepare download: ' . ($api_response['error'] ?? 'Unknown error');
                        } else {
                            $download_id = bin2hex(random_bytes(16));
                            $download_payload = $api_response['data'];
                            $_SESSION['download_sessions'][$download_id] = [
                                'search_sid' => $sid,
                                'provider' => $provider,
                                'item' => $item,
                                'download' => $download_payload,
                                'created_at' => time(),
                                'format' => $format,
                            ];

                            $log_id = logDownload(
                                $media_url,
                                strtoupper($format),
                                $conn,
                                [
                                    'file_name' => $download_payload['file_name'] ?? null,
                                    'file_type' => $download_payload['mime_type'] ?? null,
                                    'file_size_bytes' => $download_payload['file_size_bytes'] ?? null,
                                    'provider_key' => $provider,
                                ]
                            );

                            $download_token = create_jwt_token([
                                'type' => 'download',
                                'did' => $download_id
                            ], $jwt_secret, 1800);

                            $_SESSION['download_sessions'][$download_id]['log_id'] = $log_id;

                            header('Location: /download.php?token=' . urlencode($download_token));
                            exit;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = 'Session expired. Please try again.';
        }
    }
}

if (!$error_message && $download_token) {
    try {
        $payload = decode_jwt_token($download_token, $jwt_secret);
        if (($payload['type'] ?? '') !== 'download' || empty($payload['did'])) {
            $error_message = 'Invalid download token.';
        } else {
            $did = $payload['did'];
            if (isset($_SESSION['download_sessions'][$did])) {
                $download_payload = $_SESSION['download_sessions'][$did];
                $download_payload['session_id'] = $did;
                $page_data['meta_title'] = 'Download ' . ($download_payload['download']['file_name'] ?? 'media');
                $page_data['meta_description'] = 'Download prepared file ' . ($download_payload['download']['file_name'] ?? '');
            } else {
                $error_message = 'Download session expired.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Session expired. Please restart your download.';
    }
} elseif (!$error_message && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$current_slug = 'download';
include __DIR__ . '/includes/header.php';
?>
<div id="legacy-navigation" aria-hidden="true">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
</div>
<?php include __DIR__ . '/includes/yt_nav.php'; ?>

<?php
date_default_timezone_set('Asia/Karachi');
$download_server_time = date('d M Y H:i:s');
?>

<main class="yt-shell yt-secondary">
    <section class="yt-card">
        <h1>Download</h1>
        <p class="yt-insight">Server time: <?php echo htmlspecialchars($download_server_time); ?></p>
    </section>

    <?php if ($error_message): ?>
        <section class="yt-card">
            <div class="yt-error"><?php echo htmlspecialchars($error_message); ?></div>
        </section>
    <?php elseif ($download_payload): ?>
        <?php $downloadData = $download_payload['download'] ?? $download_payload; ?>
        <section class="yt-card">
            <h2><?php echo htmlspecialchars($downloadData['file_name'] ?? 'Media file'); ?></h2>
            <ul class="yt-download-meta">
                <li><strong>Provider:</strong> <?php echo strtoupper($download_payload['provider'] ?? ''); ?></li>
                <li><strong>Format:</strong> <?php echo strtoupper($download_payload['format'] ?? ''); ?></li>
                <?php if (!empty($downloadData['human_size'])): ?>
                    <li><strong>File size:</strong> <?php echo htmlspecialchars($downloadData['human_size']); ?></li>
                <?php endif; ?>
                <?php if (!empty($downloadData['mime_type'])): ?>
                    <li><strong>File type:</strong> <?php echo htmlspecialchars($downloadData['mime_type']); ?></li>
                <?php endif; ?>
            </ul>
            <?php
            $mediaUrl = urlMediaProxy($download_payload['session_id'], $downloadData['download_token'], $downloadData['signature'], $settings);
            ?>
            <div class="yt-download-action">
                <a href="<?php echo htmlspecialchars($mediaUrl); ?>" class="yt-btn">Download Now</a>
            </div>
            <div class="yt-ad-slot" style="margin-top:24px;">
                <?php render_ad_slot($conn, 'download_sidebar'); ?>
            </div>
        </section>
    <?php else: ?>
        <section class="yt-card">
            <p class="yt-muted-card">Select a track from the search results to prepare your download.</p>
        </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Build internal media proxy URL.
 */
function urlMediaProxy($sessionId, $downloadToken, $signature, $settings)
{
    $proxy_token = create_jwt_token([
        'type' => 'media',
        'session_id' => $sessionId,
        'download_token' => $downloadToken,
        'download_signature' => $signature
    ], $settings['jwt_secret'] ?? 'change-me', 900);

    return '/media.php?token=' . urlencode($proxy_token);
}
