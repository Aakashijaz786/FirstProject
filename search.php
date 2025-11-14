<?php
session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api_client.php';
require_once __DIR__ . '/includes/jwt_helper.php';
require_once __DIR__ . '/includes/ads_helper.php';

if (!isset($_SESSION['search_sessions']) || !is_array($_SESSION['search_sessions'])) {
    $_SESSION['search_sessions'] = [];
}

/**
 * Cleanup helper to avoid stale session data.
 */
function purge_session_bucket($bucket, $ttlSeconds = 900)
{
    if (!isset($_SESSION[$bucket]) || !is_array($_SESSION[$bucket])) {
        return;
    }
    $now = time();
    foreach ($_SESSION[$bucket] as $key => $payload) {
        $created = $payload['created_at'] ?? 0;
        if ($created < ($now - $ttlSeconds)) {
            unset($_SESSION[$bucket][$key]);
        }
    }
}

purge_session_bucket('search_sessions', 900);

$settings = get_site_settings_cached($conn);
$jwt_secret = $settings['jwt_secret'] ?? 'change-me';
$search_token = $_GET['token'] ?? '';
$search_payload = null;
$error_message = '';
$page_data = [
    'meta_title' => 'Search results',
    'meta_description' => 'Search results for your request'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_query = trim($_POST['query'] ?? $_POST['page'] ?? '');
    if ($user_query === '') {
        $error_message = 'Please enter a valid URL or keyword.';
    } else {
        $provider = get_active_api_provider($conn);
        $api_response = media_api_search($conn, $user_query, $provider, 6, true);
        if (!$api_response['success']) {
            $error_message = 'Failed to fetch results: ' . ($api_response['error'] ?? 'Unknown error');
        } else {
            $search_id = bin2hex(random_bytes(16));
            $_SESSION['search_sessions'][$search_id] = [
                'query' => $user_query,
                'provider' => $provider,
                'results' => $api_response['data']['items'] ?? [],
                'raw' => $api_response['data'],
                'created_at' => time(),
            ];
            $search_token = create_jwt_token([
                'type' => 'search',
                'sid' => $search_id
            ], $jwt_secret, 900);

            header('Location: /search.php?token=' . urlencode($search_token));
            exit;
        }
    }
}

if (!$error_message && $search_token) {
    try {
        $payload = decode_jwt_token($search_token, $jwt_secret);
        if (($payload['type'] ?? '') !== 'search' || empty($payload['sid'])) {
            $error_message = 'Invalid search token.';
        } else {
            $sid = $payload['sid'];
            if (isset($_SESSION['search_sessions'][$sid])) {
                $search_payload = $_SESSION['search_sessions'][$sid];
                $search_payload['sid'] = $sid;
                $page_data['meta_title'] = 'Search results for ' . htmlspecialchars($search_payload['query']);
                $page_data['meta_description'] = 'Download options for ' . htmlspecialchars($search_payload['query']);
            } else {
                $error_message = 'Search session expired.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Session expired. Please search again.';
    }
} elseif (!$error_message && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$current_slug = 'search';
include __DIR__ . '/includes/header.php';
?>
<div id="legacy-navigation" aria-hidden="true">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
</div>
<?php include __DIR__ . '/includes/yt_nav.php'; ?>
<?php
date_default_timezone_set('Asia/Karachi');
$search_server_time = date('d M Y H:i:s');
?>

<main class="yt-shell yt-secondary">
    <section class="yt-card">
        <h1>Search YouTube</h1>
        <p class="yt-insight">Server time: <?php echo htmlspecialchars($search_server_time); ?></p>
        <form method="post" action="/search.php" class="yt-form">
            <input type="text" name="query" placeholder="Paste video URL or type keywords"
                   value="<?php echo isset($search_payload['query']) ? htmlspecialchars($search_payload['query']) : ''; ?>"
                   required>
            <button type="submit" class="yt-btn">Search</button>
        </form>
    </section>

    <?php if ($error_message): ?>
        <section class="yt-card">
            <div class="yt-error"><?php echo htmlspecialchars($error_message); ?></div>
        </section>
    <?php elseif ($search_payload): ?>
        <section class="yt-card">
            <header>
                <h2>Results for “<?php echo htmlspecialchars($search_payload['query']); ?>” · <?php echo strtoupper($search_payload['provider']); ?></h2>
                <div class="yt-ad-slot">
                    <?php render_ad_slot($conn, 'search_inline'); ?>
                </div>
            </header>

            <?php if ($search_payload['provider'] === 'iframe'): ?>
                <?php
                $first = $search_payload['results'][0] ?? null;
                $iframe_url = $first['extra']['iframe_url'] ?? $first['url'] ?? '';
                if ($iframe_url):
                ?>
                    <div class="yt-hero__visual">
                        <iframe src="<?php echo htmlspecialchars($iframe_url); ?>" loading="lazy" style="width:100%;height:360px;border:none;border-radius:20px;" allowfullscreen></iframe>
                    </div>
                <?php else: ?>
                    <p class="yt-muted-card">Iframe provider enabled but no embed URL configured.</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="yt-result-grid">
                    <?php foreach ($search_payload['results'] as $index => $item): ?>
                        <article class="yt-result-card">
                            <?php if (!empty($item['thumbnail'])): ?>
                                <img src="<?php echo htmlspecialchars($item['thumbnail']); ?>" alt="Thumbnail">
                            <?php endif; ?>
                            <div class="yt-result-card__body">
                                <h3><?php echo htmlspecialchars($item['title'] ?? 'Untitled video'); ?></h3>
                                <?php if (!empty($item['author'])): ?>
                                    <p>By <?php echo htmlspecialchars($item['author']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($item['duration'])): ?>
                                    <p>Duration: <?php echo gmdate('i:s', (int)$item['duration']); ?></p>
                                <?php endif; ?>
                                <form method="post" action="/download.php">
                                    <input type="hidden" name="search_token" value="<?php echo htmlspecialchars($search_token); ?>">
                                    <input type="hidden" name="item_index" value="<?php echo (int)$index; ?>">
                                    <button type="submit" name="format" value="mp3" class="yt-btn yt-btn--ghost">Download MP3</button>
                                    <button type="submit" name="format" value="mp4" class="yt-btn">Download MP4</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="yt-card">
            <p class="yt-muted-card">Submit a link or keyword to start searching.</p>
        </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
