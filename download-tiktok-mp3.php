<?php
// Fetch site URL early for trailing slash redirects
require_once 'includes/config.php';
$site_url = '';
$res = $conn->query("SELECT site_url FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $site_url = rtrim($row['site_url'], '/');
}

// Handle trailing slash redirects - add this at the very top of each file (after <?php)
if (substr($_SERVER['REQUEST_URI'], -1) === '/') {
    // Remove trailing slash and redirect immediately
    $clean_uri = rtrim($_SERVER['REQUEST_URI'], '/');
    
    // Use full site URL for redirect
    $full_redirect_url = $site_url . $clean_uri;
    
    // Force redirect with 301 status
    http_response_code(301);
    header("Location: $full_redirect_url");
    header("HTTP/1.1 301 Moved Permanently");
    exit();
}

// Handle AJAX TikTok download request before any output
if (isset($_POST['ajax']) && $_POST['ajax'] === '1' && !empty($_POST['page'])) {
    header('Content-Type: application/json');
    $tiktok_url = trim($_POST['page']);
    require_once 'includes/config.php';
    // Fetch API URL from api_settings for provider tikwm
    $api_url = '';
    $res = $conn->query("SELECT api_url FROM api_settings WHERE provider='tikwm' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $api_url = $row['api_url'];
    }
    if (!$api_url) {
        $api_url = 'https://tikwm.com/api/'; // fallback
    }
    $api_url = rtrim($api_url, '/') . '/?url=' . urlencode($tiktok_url);
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo json_encode(['error' => 'Curl error: ' . curl_error($ch)]);
        exit;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        $result = $data['data'];
        echo json_encode([
            'success' => true,
            'links' => [
                'Music (MP3)' => $result['music'] ?? ''
            ],
            'desc' => $result['desc'] ?? ''
        ]);
        exit;
    } else {
        echo json_encode(['error' => 'Wrong link format. Paste correct link and try again']);
        exit;
    }
}

require_once __DIR__ . '/includes/config.php';

// Fetch favicons
$favicons = [
    'favicon_16' => null,
    'favicon_32' => null,
    'favicon_192' => null,
    'favicon_512' => null
];
$sql = "SELECT favicon_16, favicon_32, favicon_192, favicon_512 FROM logo_and_favicon WHERE id=1 LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    foreach ($favicons as $key => $_) {
        if (!empty($row[$key])) {
            $favicons[$key] = '/' . ltrim($row[$key], '/');
        }
    }
}

// Fetch all languages and default language
$default_lang_id = null;
$languages = [];
$res = $conn->query("SELECT * FROM languages");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $languages[] = $row;
        if ($row['is_default']) {
            $default_lang_id = $row['id'];
            $default_lang = $row;
        }
    }
}
if (!isset($default_lang)) $default_lang = $languages[0];

// Detect current language from ?lang=code or default
$current_lang_id = $default_lang_id;
$current_lang_code = $default_lang['code'];
$current_lang_direction = $default_lang['direction'] ?? 'ltr';
if (isset($_GET['lang'])) {
    foreach ($languages as $lang) {
        if ($lang['code'] == $_GET['lang']) {
            $current_lang_id = $lang['id'];
            $current_lang_code = $lang['code'];
            $current_lang_direction = $lang['direction'] ?? 'ltr';
            break;
        }
    }
}

// Fetch site URL and name
$site_url = '';
$site_title = 'site';
$res = $conn->query("SELECT site_url, site_name FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $site_url = rtrim($row['site_url'], '/');
    if (!empty($row['site_name'])) {
        $site_title = $row['site_name'];
    }
}

// Fetch meta title, meta description, and active slug from mp3_pages and mp3_page_slugs for current language
$meta_title = $site_title;
$meta_description = '';
$canonical_url = '';
$alternate_links = [];
$current_slug = '';

// Get mp3_pages row for current language
$res = $conn->query("SELECT id, meta_title, meta_description, page_name FROM mp3_pages WHERE language_id=$current_lang_id LIMIT 1");
$mp3_page_id = null;
$page_name = '';
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $mp3_page_id = $row['id'];
    if (!empty($row['meta_title'])) $meta_title = $row['meta_title'];
    if (!empty($row['meta_description'])) $meta_description = $row['meta_description'];
    $page_name = $row['page_name'];
}
// Get active slug for this mp3_page_id
if ($mp3_page_id) {
    $res2 = $conn->query("SELECT slug FROM mp3_page_slugs WHERE mp3_page_id=$mp3_page_id AND status='active' LIMIT 1");
    if ($res2 && $res2->num_rows > 0) {
        $row2 = $res2->fetch_assoc();
        $current_slug = $row2['slug'];
    }
}

// Build canonical and alternate links using active slug for each language
// Now we'll use the full slug as provided by admin (no language code prefix)
$alternate_links = [];
$canonical_url = '';
$language_count = count($languages);

foreach ($languages as $lang) {
    $lang_code = $lang['code'];
    $lang_id = $lang['id'];
    $is_default = $lang['is_default'];
    $slug = '';

    // Get mp3_pages row for this language
    $res3 = $conn->query("SELECT id FROM mp3_pages WHERE language_id = $lang_id LIMIT 1");
    $mp3_page_id_alt = null;

    if ($res3 && $res3->num_rows > 0) {
        $row3 = $res3->fetch_assoc();
        $mp3_page_id_alt = $row3['id'];
    }

    // Get active slug for this mp3_page_id
    if ($mp3_page_id_alt) {
        $res4 = $conn->query("SELECT slug FROM mp3_page_slugs WHERE mp3_page_id = $mp3_page_id_alt AND status = 'active' LIMIT 1");
        if ($res4 && $res4->num_rows > 0) {
            $row4 = $res4->fetch_assoc();
            $slug = $row4['slug'];
        }
    }

    // Build the full URL - now using the full slug as provided by admin
    $url = $site_url . '/' . ltrim($slug, '/');

    // Set canonical if default
    if ($is_default) {
        $canonical_url = $url;
    }

    // Add to alternate if more than 1 language
    if ($language_count > 1) {
        $alternate_links[] = [
            'url' => $url,
            'hreflang' => $lang_code
        ];
    }
}

// Fetch global_header content
$global_header_content = '';
$res = $conn->query("SELECT content FROM global_header LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $global_header_content = $row['content'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang_code); ?>" dir="<?php echo htmlspecialchars($current_lang_direction); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> 
<title><?php echo htmlspecialchars($meta_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>" />
<meta name="author" content="<?php echo htmlspecialchars($meta_title); ?>" />
<meta name="robots" content="index, follow">
<meta property="og:title" content="<?php echo htmlspecialchars($meta_title); ?>" />
<meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>" />
<meta property="og:type" content="article" />
<?php
// Use the current slug directly for og:url (no language code)
$og_url = $site_url . '/' . ltrim($current_slug, '/');
?>
<meta property="og:url" content="<?= htmlspecialchars($og_url) ?>" />
<?php if ($favicons['favicon_16']): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" />
<?php endif; ?>
<?php if ($canonical_url): ?>
<meta property="og:site_name" content="<?php echo htmlspecialchars($site_title); ?>" />
<?php endif; ?>
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="<?php echo htmlspecialchars($meta_title); ?>" />
<meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description); ?>" />
<?php if ($favicons['favicon_16']): ?>
<meta name="twitter:image:src" content="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" />
<?php endif; ?>
<meta name="twitter:site" content="<?= htmlspecialchars($og_url) ?>" />
<link rel="preconnect" href="//www.google-analytics.com">
<link rel="dns-prefetch" href="//www.google-analytics.com">
<link rel="preconnect" href="//pagead2.googlesyndication.com" crossorigin>
<?php if ($favicons['favicon_16']): ?>
<link rel="shortcut icon" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" type="image/x-icon">
<link rel="icon" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" type="image/x-icon">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" alt="favicon">
<?php endif; ?>
<?php if ($favicons['favicon_32']): ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($favicons['favicon_32']); ?>" alt="favicon">
<?php endif; ?>
<?php if ($favicons['favicon_16']): ?>
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" alt="favicon">
<link rel="apple-touch-icon-precomposed" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>">
<link rel="apple-touch-icon-precomposed" sizes="72x72" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>">
<?php endif; ?>
<?php if ($favicons['favicon_32']): ?>
<link rel="apple-touch-icon-precomposed" sizes="76x76" href="<?php echo htmlspecialchars($favicons['favicon_32']); ?>">
<link rel="apple-touch-icon-precomposed" sizes="114x114" href="<?php echo htmlspecialchars($favicons['favicon_32']); ?>">
<?php endif; ?>
<?php if ($favicons['favicon_192']): ?>
<link rel="apple-touch-icon-precomposed" sizes="120x120" href="<?php echo htmlspecialchars($favicons['favicon_192']); ?>">
<?php endif; ?>
<?php if ($favicons['favicon_512']): ?>
<link rel="apple-touch-icon-precomposed" sizes="144x144" href="<?php echo htmlspecialchars($favicons['favicon_512']); ?>">
<link rel="apple-touch-icon-precomposed" sizes="152x152" href="<?php echo htmlspecialchars($favicons['favicon_512']); ?>">
<link rel="apple-touch-icon-precomposed" sizes="180x180" href="<?php echo htmlspecialchars($favicons['favicon_512']); ?>">
<link rel="icon" sizes="192x192" href="<?php echo htmlspecialchars($favicons['favicon_512']); ?>">
<link rel="icon" sizes="512x512" href="<?php echo htmlspecialchars($favicons['favicon_512']); ?>">
<?php endif; ?>
<link rel="canonical" href="<?= htmlspecialchars($og_url) ?>"  />
<?php foreach ($alternate_links as $alt): ?>
<link rel="alternate" href="<?php echo htmlspecialchars($alt['url']); ?>" hreflang="<?php echo htmlspecialchars($alt['hreflang']); ?>" />
<?php endforeach; ?>
<?php
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM languages");
$row = mysqli_fetch_assoc($result);
$language_count = $row['cnt'];
if ($canonical_url && $language_count > 1): ?>
<link rel="alternate" href="<?php echo htmlspecialchars($canonical_url); ?>" hreflang="x-default"  />
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/Oyf3H4i0HaSN.css">
<link rel="stylesheet" href="/assets/css/navigation.css">
<link rel="stylesheet" href="/assets/css/index.css">
<?php if (!empty($global_header_content)) echo $global_header_content; ?>
</head>
<body class="bg-gray">
<?php

require_once 'includes/config.php';

// Get slug from URL parameters (set by .htaccess) - no language code needed
$current_slug = $_GET['slug'] ?? '';

// Initialize language variables
$lang = null;
$lang_id = 0;

// Handle language detection - now we'll detect language from the slug or use default
// First try to get language from GET parameter if available
if (isset($_GET['lang'])) {
    $lang_code = $_GET['lang'];
    $res = $conn->query("SELECT * FROM languages WHERE code='" . $conn->real_escape_string($lang_code) . "' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $lang = $res->fetch_assoc();
        $lang_id = $lang['id'];
    }
}

// If no language found, try to detect from slug by checking all languages
// Treat the entire slug as one unit (e.g., "es1/download-tiktok-mp3")
if (!$lang) {
    // Search for the exact slug in mp3_page_slugs table
    $res = $conn->query("SELECT mps.mp3_page_id, l.* FROM mp3_page_slugs mps 
                         JOIN mp3_pages mp ON mps.mp3_page_id = mp.id 
                         JOIN languages l ON mp.language_id = l.id 
                         WHERE mps.slug = '" . $conn->real_escape_string($current_slug) . "' 
                         AND mps.status = 'active' 
                         LIMIT 1");
    
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $lang = $row;
        $lang_id = $row['id'];
    }
}

// Fallback to default language if no language found
if (!$lang) {
    $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $lang = $res->fetch_assoc();
        $lang_id = $lang['id'];
    }
}

// Set cookie for language preference
// if ($lang && $lang['code'] !== $default_lang['code']) {
//     setcookie('site_lang', $lang['code'], time() + (86400 * 30), '/'); // 30 days
// }

// Fetch MP3 page content for current language using active slug from mp3_page_slugs
$mp3_page = null;
if ($lang_id) {
    // Get the active slug for this language's MP3 page
    $sql = "SELECT p.* FROM mp3_pages p
            JOIN mp3_page_slugs s ON s.mp3_page_id = p.id
            WHERE p.language_id = $lang_id AND s.status = 'active' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $mp3_page = $res->fetch_assoc();
    }
}

// Fallback content if no database content found
function h($v) { return htmlspecialchars($v ?? ''); }
$page_header = h($mp3_page['header'] ?? 'Download TikTok MP3');
$page_heading = h($mp3_page['heading'] ?? 'Download TikTok videos online');
$page_description = $mp3_page['description'] ?? 'company name is a free TikTok download tool that helps you download TikTok videos without watermark online. Save TT videos with the highest quality in an MP4 file format and HD resolution. To find out how to use the TikTok watermark remover, follow the instructions below.';

// Fetch TikTok download instructions for the current language
$tiktok_instructions = [];

if ($lang_id) {
    $stmt = $conn->prepare("SELECT title, description FROM language_mp3_titles WHERE language_id = ?");
    $stmt->bind_param("i", $lang_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $tiktok_instructions[] = $row;
    }

    $stmt->close();
}

include 'includes/navigation.php';
?>
    <main>
        <section>
            <div class="splash-container" id="splash" hx-ext="include-vals">
                <div id="splash_wrapper" class="splash" style="max-width: 1680px;">
                    <h1 class="splash-head hide-after-request" id="bigmessage">
                        <?php echo $page_header; ?>
                    </h1>
                    <div class="error-container-wrapper">
                        <div id="errorContainer"></div>
                    </div>
                    <form class="form g hide-after-request" id="tiktok_form" rel="nofollow">
                      <div class="loader loader--style8 htmx-indicator u-1" id="main_loader" style="display: none; position: relative; z-index: 1000;">
                            <div style="width: 50px; height: 50px; border: 5px solid #ffa703; border-top: 5px solid #002186; border-radius: 50%; animation: spin 1s linear infinite; margin: 20px auto;top: -11px;left: 50%;position: absolute;"></div>
                        </div>
                        <div class="relative u-fw">
                            <input id="main_page_text" name="page" type="text" class="form-control input-lg" placeholder="Just insert a link" value="<?php echo isset($_POST['page']) ? htmlspecialchars($_POST['page']) : ''; ?>">
                            <?php
                            $download_label = isset($lang['download_label']) && $lang['download_label'] !== '' ? h($lang['download_label']) : 'Download';
                            $paste_label = isset($lang['paste_label']) && $lang['paste_label'] !== '' ? h($lang['paste_label']) : 'Paste';
                            ?>
                            <button id="paste" type="button">
                                <svg data-v-611f7da7="" width="14" height="18" viewBox="0 0 14 18" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path data-v-611f7da7=""
                                        d="M4.75 2.9625C4.75 2.505 4.90804 2.06624 5.18934 1.74274C5.47064 1.41924 5.85218 1.2375 6.25 1.2375H7.75C8.14782 1.2375 8.52936 1.41924 8.81066 1.74274C9.09196 2.06624 9.25 2.505 9.25 2.9625M4.75 2.9625H3.25C2.85218 2.9625 2.47064 3.14424 2.18934 3.46774C1.90804 3.79124 1.75 4.23 1.75 4.6875V15.0375C1.75 15.495 1.90804 15.9338 2.18934 16.2573C2.47064 16.5808 2.85218 16.7625 3.25 16.7625H10.75C11.1478 16.7625 11.5294 16.5808 11.8107 16.2573C12.092 15.9338 12.25 15.495 12.25 15.0375V4.6875C12.25 4.23 12.092 3.79124 11.8107 3.46774C11.5294 3.14424 11.1478 2.9625 10.75 2.9625H9.25H4.75ZM4.75 2.9625C4.75 3.41999 4.90804 3.85875 5.18934 4.18225C5.47064 4.50576 5.85218 4.6875 6.25 4.6875H7.75C8.14782 4.6875 8.52936 4.50576 8.81066 4.18225C9.09196 3.85875 9.25 3.41999 9.25 2.9625H4.75Z"
                                        stroke="#383838" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round"></path>
                                </svg>
                                <span>
                                    <?php echo $paste_label; ?>
                                </span>
                            </button>
                            <button type="submit" id="submit" class="vignette_active button-primary" >
                                <span><?php echo $download_label; ?></span>
                            </button>
                        </div>
                    </form>
                    <div id="target"></div>
                    <div id="downloadOptions" style="display:none; margin-top:20px;"></div>
                </div>
            </div>
        </section>
        <div >
            <div class="content-visibility">
                <section class="text">
                    <div class="text__container">
                        <h2><?php echo $page_heading; ?></h2>
                          <?php
                            // Set timezone to Pakistan
                            date_default_timezone_set('Asia/Karachi');
                            
                            // Check if site is enabled
                            $site_enabled = true; // Default to enabled
                            $check_settings = $conn->query("SELECT site_status FROM site_settings LIMIT 1");
                            if ($check_settings && $check_settings->num_rows > 0) {
                                $settings = $check_settings->fetch_assoc();
                                $site_enabled = ($settings['site_status'] == 1);
                            }
                            
                            // Only show last updated time if site is enabled
                            if ($site_enabled) {
                                echo '<p style="color:#777;font-size: 1em;font-weight:400;">Last updated: ' . date('d M Y H:i:s') . '</p>';
                            }
                            ?>
                        <div class="text__desc">
                            <?php echo $page_description; ?>
                        </div>
                    </div>
                    <div class="b-blk" >
                    <?php if (!empty($tiktok_instructions)): ?>
                        <ol>
                        <?php foreach ($tiktok_instructions as $step): ?>
                            <li><b><?php echo htmlspecialchars($step['title']); ?></b> - <span><?php echo nl2br(htmlspecialchars($step['description'])); ?></span></li>
                                    <?php endforeach; ?>
                        </ol>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>
 <script src="/assets/js/mp3.js" defer></script>
   <?php
include 'includes/footer.php';
?>
