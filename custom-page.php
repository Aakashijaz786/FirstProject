<?php
// Fetch site URL early for trailing slash redirects
require_once 'includes/config.php';
$site_url = '';
$res = $conn->query("SELECT site_url FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $site_url = rtrim($row['site_url'], '/');
}

http_response_code(410);
include 'includes/header.php';
include 'includes/navigation.php';
?>
<main class="container py-5">
    <div class="alert alert-info">
        Custom landing pages have been retired. Please return to the homepage for the latest tools.
    </div>
</main>
<?php
include 'includes/footer.php';
exit;

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
        require_once 'includes/download_logger.php';
        
        // Log the URL submission (when user submits the form)
        logDownload($tiktok_url, 'URL Submitted', $conn);
        
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
                    'Video (No Watermark)' => $result['play'] ?? '',
                    'Video (With Watermark)' => $result['wmplay'] ?? '',
                    'Video Cover Image' => $result['cover'] ?? '',
                    'Video Music (MP3)' => $result['music'] ?? ''
                ],
                'desc' => $result['desc'] ?? ''
            ]);
            exit;
        } else {
            echo json_encode(['error' => 'Wrong link format. Paste correct link and try again']);
            exit;
        }
    }
    
    // Handle download logging requests
    if (isset($_POST['log_download']) && $_POST['log_download'] === '1' && !empty($_POST['url'])) {
        require_once 'includes/config.php';
        require_once 'includes/download_logger.php';
        
        $url = trim($_POST['url']);
        $download_type = isset($_POST['download_type']) ? trim($_POST['download_type']) : 'Unknown';
        
        // Log the download attempt
        logDownload($url, $download_type, $conn);
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

require_once __DIR__ . '/includes/config.php';

// Ensure custom_page_slugs table exists
$conn->query("CREATE TABLE IF NOT EXISTS custom_page_slugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_page_id INT NOT NULL,
    slug VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_page_id) REFERENCES custom_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

// Get current slug from ?slug= or fallback
$current_slug = isset($_GET['slug']) ? $_GET['slug'] : '';

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

// Fetch meta title, meta description, and slug from custom_pages for current language and slug
$meta_title = $site_title;
$meta_description = '';
$canonical_url = '';
$alternate_links = [];
$page_name = '';

// Get custom_pages row for current language and slug using custom_page_slugs table
$res = $conn->query("SELECT cp.id, cp.meta_title, cp.meta_description, cp.page_name, cps.slug 
                     FROM custom_pages cp 
                     JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
                     WHERE cp.language_id=$current_lang_id AND cps.slug='" . $conn->real_escape_string($current_slug) . "' AND cps.status='active' LIMIT 1");
$custom_page_id = null;
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $custom_page_id = $row['id'];
    if (!empty($row['meta_title'])) $meta_title = $row['meta_title'];
    if (!empty($row['meta_description'])) $meta_description = $row['meta_description'];
    $page_name = $row['page_name'];
    $current_slug = $row['slug'];
}

// Build canonical and alternate links using slug from custom_pages for each language
$alternate_links = [];
$canonical_url = '';
$language_count = count($languages);

foreach ($languages as $lang) {
    $lang_code = $lang['code'];
    $lang_id = $lang['id'];
    $is_default = $lang['is_default'];
    $slug = '';

    // Get active slug from custom_page_slugs based on language and page_name
    $res2 = $conn->query("SELECT cps.slug FROM custom_pages cp 
                         JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
                         WHERE cp.language_id = $lang_id AND cp.page_name = '" . $conn->real_escape_string($page_name) . "' AND cps.status='active' LIMIT 1");
    if ($res2 && $res2->num_rows > 0) {
        $row2 = $res2->fetch_assoc();
        $slug = $row2['slug'];
    }

    if (!empty($slug)) {
        $url = $site_url. '/' . ltrim($slug, '/');
        //   $url = $site_url . '/' . ($is_default ? '' : $lang_code . '/') . ltrim($slug, '/');
        // Set canonical for default language
        if ($is_default) {
            $canonical_url = $url;
        }

        // Add alternate only if more than one language
        if ($language_count > 1) {
            $alternate_links[] = [
                'url' => $url,
                'hreflang' => $lang_code
            ];
        }
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
<meta property="og:title" content="<?php echo htmlspecialchars($meta_title); ?>" />
<meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>" />
<meta property="og:type" content="article" />
<?php
$og_url = $site_url; // Initialize with base URL

// Use the actual slug from the URL path for OG URL
if (!empty($current_slug)) {
    $og_url .= '/' . $current_slug;
}
?>
<meta property="og:url" content="<?= htmlspecialchars($og_url) ?>" />
<?php if ($favicons['favicon_16']): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" />
<?php endif; ?>
<meta property="og:site_name" content="<?php echo htmlspecialchars($site_title); ?>" />
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
                'Video (No Watermark)' => $result['play'] ?? '',
                'Video (With Watermark)' => $result['wmplay'] ?? '',
                'Cover Image' => $result['cover'] ?? '',
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

require_once 'includes/config.php';

// Get language and slug from URL parameters (set by .htaccess)
$lang_code = $_GET['lang'] ?? '';
$current_slug = $_GET['slug'] ?? '';

// Initialize language variables
$current_lang = null;
$lang_id = 0;

// Handle language detection
if ($lang_code && $lang_code !== 'default') {
    // Specific language requested
    $res = $conn->query("SELECT * FROM languages WHERE code='" . $conn->real_escape_string($lang_code) . "' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_lang = $res->fetch_assoc();
        $lang_id = $current_lang['id'];
    }
}

// Fallback to default language if no language found or default requested
if (!$current_lang) {
    $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_lang = $res->fetch_assoc();
        $lang_id = $current_lang['id'];
    }
}

// Set cookie for language preference
// if ($lang_code) {
//     setcookie('site_lang', $lang_code, time() + (86400 * 30), '/'); // 30 days
// }

// Find the page - First try current language
$page = null;

if ($lang_id && $current_slug) {
    $sql = "SELECT cp.*, cps.slug FROM custom_pages cp 
            JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
            WHERE cp.language_id=$lang_id AND cps.slug='" . $conn->real_escape_string($current_slug) . "' AND cps.status='active' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
    }
}

// Fallback: try to get the page for the default language
if (!$page && $current_slug) {
    $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $default_lang = $res->fetch_assoc();
        $default_lang_id = $default_lang['id'];
        $sql = "SELECT cp.*, cps.slug FROM custom_pages cp 
                JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
                WHERE cp.language_id=$default_lang_id AND cps.slug='" . $conn->real_escape_string($current_slug) . "' AND cps.status='active' LIMIT 1";
        $res2 = $conn->query($sql);
        if ($res2 && $res2->num_rows > 0) {
            $page = $res2->fetch_assoc();
        }
    }
}

// If still not found, show 404
if (!$page) {
    http_response_code(404);
    echo "<h1>Page not found</h1>";
    echo "<p>The page you are looking for does not exist.</p>";
    exit;
}

// Helper function to safely get array values
function safe_get($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}

// Get page content
$page_header = safe_get($page, 'header', safe_get($page, 'page_name', 'Download TikTok Video'));
$page_description = safe_get($page, 'description', '');
$page_heading = safe_get($page, 'heading', '');

// Helper function to safely escape HTML
function h($v) { return htmlspecialchars($v ?? ''); }

// Set header variable for the template
$header = $page_header;

// Initialize error variable
$error = '';

include 'includes/navigation.php';
?>
<style>
    
   .text h2 {
    font-style: normal;
    font-size: 27px; /* Desktop default */
    line-height: 1.2em;
    color: #555;
    text-align: center;
}

/* Mobile view (max-width: 768px) */
@media screen and (max-width: 768px) {
    .text h2 {
        font-size: 23px;
    }
}
</style>
<main>
    <section>
      <div class="splash-container" id="splash" hx-ext="include-vals">
                    <div id="splash_wrapper" class="splash" style="max-width: 1680px;">
                        <h1 class="splash-head hide-after-request" id="bigmessage">
                            <?php echo $header; ?> </h1>
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
                                $download_label = isset($current_lang['download_label']) && $current_lang['download_label'] !== '' ? h($current_lang['download_label']) : 'Download';
                                $paste_label = isset($current_lang['paste_label']) && $current_lang['paste_label'] !== '' ? h($current_lang['paste_label']) : 'Paste';
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
                                 <?php if ($error): ?>
                                    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
                                <?php endif; ?>
                        </div>
                    </form>
                       <div id="target" class="">
                  
            
        </div>
                        <div id="downloadOptions" style="display:none; margin-top:20px;"></div>
                        
                                 </div> </div>

        <div class="content-visibility">
            <section class="text">
                <div class="text__container">
                    <?php if (!empty($page_heading)): ?>
                        <h2><?php echo htmlspecialchars($page_heading); ?></h2>
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
                    <?php endif; ?>
                    <div class="text__desc">
                        <?php echo $page_description; ?>
                    </div>
                </div>
            </section>
        </div>
    </section>
</main>
<?php

// Get language from URL parameter or cookie
$lang_code = $_GET['lang'] ?? $_COOKIE['site_lang'] ?? '';
$current_slug = $_GET['slug'] ?? '';

// Initialize language variables
$current_lang = null;
$lang_id = 0;

// Handle language detection
if ($lang_code && $lang_code !== 'default') {
    // Specific language requested via URL parameter or cookie
    $res = $conn->query("SELECT * FROM languages WHERE code='" . $conn->real_escape_string($lang_code) . "' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_lang = $res->fetch_assoc();
        $lang_id = $current_lang['id'];
    }
}

// Fallback to default language if no language found or default requested
if (!$current_lang) {
    $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_lang = $res->fetch_assoc();    
        $lang_id = $current_lang['id'];
    }
}

// Define all possible footer pages with their enabled field and page name
$footer_pages = [
    [ 'label' => $current_lang['how_to_save'] ?? 'How to Save TikTok Video?', 'key' => 'how', 'enabled_field' => 'how_enabled', 'page_like' => 'How', 'special_page' => 'how' ],
    [ 'label' => $current_lang['terms_conditions'] ?? 'Terms & Conditions', 'key' => 'terms', 'enabled_field' => 'terms_enabled', 'page_like' => 'terms' ],
    [ 'label' => $current_lang['contact'] ?? 'Contact Us', 'key' => 'contact', 'enabled_field' => 'contact_enabled', 'page_like' => 'contact' ],
    [ 'label' => $current_lang['privacy_policy'] ?? 'Privacy Policy', 'key' => 'privacy', 'enabled_field' => 'privacy_enabled', 'page_like' => 'privacy' ],
   
    // Add more if needed (e.g. MP3, How)
];

// Get enabled status for each page from languages table
$lang_row = $current_lang;
$enabled_links = [];
foreach ($footer_pages as $page) {
    // Special handling for contact page - always show it
    if ($page['key'] === 'contact') {
        $url = '/contact';
        $enabled_links[] = [
            'label' => $page['label'],
            'url' => $url
        ];
        continue;
    }
    $enabled = isset($lang_row[$page['enabled_field']]) ? $lang_row[$page['enabled_field']] : 0;
    if ($enabled) {
        // Check if this is a special page (home, stories, mp3, how)
        if (isset($page['special_page']) && $page['special_page'] === 'home') {
            // For Home, get the home slug
            $slug = '';
            $sql = "SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1";
            $res_home = $conn->query($sql);
            if ($res_home && $res_home->num_rows > 0) {
                $row_home = $res_home->fetch_assoc();
                $slug = $row_home['slug'];
            }
            if (!$slug) {
                $slug = '';
            }
            // For home page - always use slug-only format
            $url = '/' . $slug;
        } elseif (isset($page['special_page']) && $page['special_page'] === 'how') {
            // For How, get the active slug from how_page_slugs
            $slug = '';
            $sql = "SELECT id FROM how_pages WHERE language_id=$lang_id AND page_name='How' LIMIT 1";
            $res_page = $conn->query($sql);
            if ($res_page && $res_page->num_rows > 0) {
                $row_page = $res_page->fetch_assoc();
                $how_page_id = $row_page['id'];
                $sql_slug = "SELECT slug FROM how_page_slugs WHERE how_page_id=$how_page_id AND status='active' LIMIT 1";
                $res_slug = $conn->query($sql_slug);
                if ($res_slug && $res_slug->num_rows > 0) {
                    $row_slug = $res_slug->fetch_assoc();
                    $slug = $row_slug['slug'];
                }
            }
            if (!$slug) {
                $slug = $page['key'];
            }
            // For how - use language code + slug format (except default language)
            if ($current_lang['is_default']) {
                $url = '/' . $slug;
            } else {
                $url = '/' . $slug; // Use full slug without language code prefix
            }
        } else {
            // For regular pages, get slug from language_pages
            $slug = '';
            $sql = "SELECT slug FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%" . $conn->real_escape_string($page['page_like']) . "%' OR LOWER(slug) LIKE '%" . $conn->real_escape_string($page['page_like']) . "%') LIMIT 1";
            $res2 = $conn->query($sql);
            if ($res2 && $res2->num_rows > 0) {
                $row2 = $res2->fetch_assoc();
                $slug = $row2['slug'];
            } else {
                // Try custom_pages with custom_page_slugs
                $sql = "SELECT cps.slug FROM custom_pages cp 
                        JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
                        WHERE cp.language_id=$lang_id AND (LOWER(cp.page_name) LIKE '%" . $conn->real_escape_string($page['page_like']) . "%' OR LOWER(cps.slug) LIKE '%" . $conn->real_escape_string($page['page_like']) . "%') AND cps.status='active' LIMIT 1";
                $res3 = $conn->query($sql);
                if ($res3 && $res3->num_rows > 0) {
                    $row3 = $res3->fetch_assoc();
                    $slug = $row3['slug'];
                }
            }
            // For other pages - use language code + slug format (except default language)
            if ($slug) {
                if ($current_lang['is_default']) {
                    $url = '/' . $slug;
                } else {
                    $url = '/' . $slug; // Use full slug without language code prefix
                }
            } else {
                if ($current_lang['is_default']) {
                    $url = '/' . $page['page_like'];
                } else {
                    $url = '/' . $page['page_like']; // Use page key without language code prefix
                }
            }
        }
        $enabled_links[] = [
            'label' => $page['label'],
            'url' => $url
        ];
    }
}

// Fetch all languages
$languages = [];
$res = $conn->query("SELECT * FROM languages");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $languages[] = $row;
    }
}

// Get current page_name for this slug and language
$page_name = '';
if ($current_slug && $lang_id) {
    // Try language_pages first
    $sql = "SELECT page_name FROM language_pages WHERE language_id=$lang_id AND slug='" . $conn->real_escape_string($current_slug) . "' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $page_name = $row['page_name'];
    } else {
        // Try how_page_slugs
        $sql = "SELECT hp.page_name FROM how_page_slugs hps 
                JOIN how_pages hp ON hps.how_page_id = hp.id 
                WHERE hp.language_id=$lang_id AND hps.slug='" . $conn->real_escape_string($current_slug) . "' AND hps.status='active' LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $page_name = $row['page_name'];
        } else {
            // Try mp3_page_slugs
            $sql = "SELECT mp.page_name FROM mp3_page_slugs mps 
                    JOIN mp3_pages mp ON mps.mp3_page_id = mp.id 
                    WHERE mp.language_id=$lang_id AND mps.slug='" . $conn->real_escape_string($current_slug) . "' AND mps.status='active' LIMIT 1";
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $page_name = $row['page_name'];
            } else {
                // Try stories_page_slugs
                $sql = "SELECT sp.page_name FROM stories_page_slugs sps 
                        JOIN stories_pages sp ON sps.stories_page_id = sp.id 
                        WHERE sp.language_id=$lang_id AND sps.slug='" . $conn->real_escape_string($current_slug) . "' AND sps.status='active' LIMIT 1";
                $res = $conn->query($sql);
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $page_name = $row['page_name'];
                } else {
                    // Try custom_page_slugs
                    $sql = "SELECT cp.page_name FROM custom_page_slugs cps 
                            JOIN custom_pages cp ON cps.custom_page_id = cp.id 
                            WHERE cp.language_id=$lang_id AND cps.slug='" . $conn->real_escape_string($current_slug) . "' AND cps.status='active' LIMIT 1";
                    $res = $conn->query($sql);
                    if ($res && $res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        $page_name = $row['page_name'];
                    }
                }
            }
        }
    }
}

// Get copyright content for this language
   $copyright_content = '';
    $copyright_enabled = false;
    $res = $conn->query("SELECT content FROM language_pages WHERE language_id=$current_lang_id AND page_name='Copyright' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $copyright_content = $row['content'];
        $copyright_enabled = true; // Copyright page exists, so it's enabled
    }
    
    // Check if copyright is enabled from languages table
    if ($copyright_enabled && $current_lang) {
        $copyright_enabled = isset($current_lang['copyright_enabled']) ? (bool)$current_lang['copyright_enabled'] : true;
    }
?>
<footer id="footer">
    <div class="footer__container d-flex justify-content-between">
        <div class="flex-1">
        </div>
        <div class="flex-2">
            <nav class="footer__navigation flex-column">
                <div class="footer-row row-1">
                   
                    <?php foreach ($enabled_links as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_self"><?php echo htmlspecialchars($link['label']); ?></a>
                    <?php endforeach; ?>
                   
                </div>
            </nav>
        </div>
        <div class="flex-1 lang-wrapper">
                <ul id="language-switcher">
                    <li class="">
                        <div id="menuLink1">
                            <?php
                        $active_lang = $current_lang;
                        // Find the translated slug for the active language
                        $translated_slug = '';
                        if ($page_name) {
                            $sql = "SELECT slug FROM language_pages WHERE language_id=" . intval($active_lang['id']) . " AND page_name='" . $conn->real_escape_string($page_name) . "' LIMIT 1";
                            $res = $conn->query($sql);
                            if ($res && $res->num_rows > 0) {
                                $row = $res->fetch_assoc();
                                $translated_slug = $row['slug'];
                            }
                        }
                        if (!$translated_slug) {
                            $sql = "SELECT slug FROM languages_home WHERE language_id=" . intval($active_lang['id']) . " LIMIT 1";
                            $res = $conn->query($sql);
                            if ($res && $res->num_rows > 0) {
                                $row = $res->fetch_assoc();
                                $translated_slug = $row['slug'];
                            }
                        }
                        // Display as lang_code / slug
                        if ($active_lang['is_default']) {
                            echo htmlspecialchars($active_lang['name']) ;
                        } else {
                            echo htmlspecialchars($active_lang['name']) ;
                        }
                        ?>
                        </div>
                        <ul class="menu-children u-smaller-text u-shadow--black">
                             <?php foreach ($languages as $lang): ?>
                                <?php
                                // Check if there's a translated slug for the current page in this language
                                $translated_slug = '';
                                $is_home_page = false;
                                
                                if ($page_name) {
                                    // Check if this is a home page by looking in languages_home table
                                    $sql = "SELECT slug FROM languages_home WHERE language_id=" . intval($lang['id']) . " AND page_name='" . $conn->real_escape_string($page_name) . "' LIMIT 1";
                                    $res = $conn->query($sql);
                                    if ($res && $res->num_rows > 0) {
                                        $row = $res->fetch_assoc();
                                        $translated_slug = $row['slug'];
                                        $is_home_page = true;
                                    } else {
                                        // Try other tables for non-home pages
                                        $sql = "SELECT slug FROM language_pages WHERE language_id=" . intval($lang['id']) . " AND page_name='" . $conn->real_escape_string($page_name) . "' LIMIT 1";
                                        $res = $conn->query($sql);
                                        if ($res && $res->num_rows > 0) {
                                            $row = $res->fetch_assoc();
                                            $translated_slug = $row['slug'];
                                        } else {
                                            // Try how_page_slugs
                                            $sql = "SELECT hps.slug FROM how_page_slugs hps 
                                                    JOIN how_pages hp ON hps.how_page_id = hp.id 
                                                    WHERE hp.language_id=" . intval($lang['id']) . " AND hp.page_name='" . $conn->real_escape_string($page_name) . "' AND hps.status='active' LIMIT 1";
                                            $res = $conn->query($sql);
                                            if ($res && $res->num_rows > 0) {
                                                $row = $res->fetch_assoc();
                                                $translated_slug = $row['slug'];
                                            } else {
                                                // Try mp3_page_slugs
                                                $sql = "SELECT mps.slug FROM mp3_page_slugs mps 
                                                        JOIN mp3_pages mp ON mps.mp3_page_id = mp.id 
                                                        WHERE mp.language_id=" . intval($lang['id']) . " AND mp.page_name='" . $conn->real_escape_string($page_name) . "' AND mps.status='active' LIMIT 1";
                                                $res = $conn->query($sql);
                                                if ($res && $res->num_rows > 0) {
                                                    $row = $res->fetch_assoc();
                                                    $translated_slug = $row['slug'];
                                                } else {
                                                    // Try stories_page_slugs
                                                    $sql = "SELECT sps.slug FROM stories_page_slugs sps 
                                                            JOIN stories_pages sp ON sps.stories_page_id = sp.id 
                                                            WHERE sp.language_id=" . intval($lang['id']) . " AND sp.page_name='" . $conn->real_escape_string($page_name) . "' AND sps.status='active' LIMIT 1";
                                                    $res = $conn->query($sql);
                                                    if ($res && $res->num_rows > 0) {
                                                        $row = $res->fetch_assoc();
                                                        $translated_slug = $row['slug'];
                                                    } else {
                                                        // Try custom_page_slugs
                                                        $sql = "SELECT cps.slug FROM custom_page_slugs cps 
                                                                JOIN custom_pages cp ON cps.custom_page_id = cp.id 
                                                                WHERE cp.language_id=" . intval($lang['id']) . " AND cp.page_name='" . $conn->real_escape_string($page_name) . "' AND cps.status='active' LIMIT 1";
                                                        $res = $conn->query($sql);
                                                        if ($res && $res->num_rows > 0) {
                                                            $row = $res->fetch_assoc();
                                                            $translated_slug = $row['slug'];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                // Build URL based on conditions
                                if ($is_home_page && !empty($translated_slug)) {
                                    // Home page with slug - use slug without language code
                                    $lang_url = '/' . htmlspecialchars($translated_slug);
                                } elseif (!empty($translated_slug)) {
                                    // Non-home page with slug - always use slug without language code
                                    $lang_url = '/' . htmlspecialchars($translated_slug);
                                } else {
                                    // No slug exists
                                    if ($lang['is_default']) {
                                        $lang_url = '/';
                                    } else {
                                        $lang_url = '/';
                                    }
                                }
                                ?>
                                <li class="menu-item <?php if ($lang['id'] == $active_lang['id']) echo ' active'; ?>">
                              
                                <a href="<?php echo $lang_url; ?>" 
                                       data-lang="<?php echo htmlspecialchars($lang['code']); ?>" 
                                       data-url="<?php echo $lang_url; ?>" 
                                       class="menu-link language-switch-link">
                                       <img src="/admin/<?php echo htmlspecialchars($lang['image']); ?>" alt="" style="width:20px">    
                                        <?php echo htmlspecialchars($lang['name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                </ul>
            </div>
    </div>
    <div class="text-center">
     <?php if ($copyright_enabled && $copyright_content): ?>
            <div class="author-info">
                <?php echo $copyright_content; ?>
            </div>
            <?php endif; ?>
    </div>
</footer>
<script src="/assets/js/yCLRZN5bZzQA.js" defer></script>
<script src="/assets/js/navigation.js" defer></script>
<script src="/assets/js/footer.js" defer></script>
<script src="/assets/js/custom.js" defer></script>
<?php
// Fetch global_footer content
$global_footer_content = '';
$res = $conn->query("SELECT content FROM global_footer LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $global_footer_content = $row['content'];
}
?>

<?php if (!empty($global_footer_content)) echo $global_footer_content; ?>
 <?php
    // Fetch site email and phone for JSON-LD schema
    $site_email = 'support@tiktokio.mobi'; // Default fallback
    $site_phone = ''; // Default fallback
    $contact_res = $conn->query("SELECT site_email, site_phone FROM site_settings LIMIT 1");
    if ($contact_res && $contact_res->num_rows > 0) {
        $contact_row = $contact_res->fetch_assoc();
        if (!empty($contact_row['site_email'])) {
            $site_email = $contact_row['site_email'];
        }
        if (!empty($contact_row['site_phone'])) {
            $site_phone = $contact_row['site_phone'];
        }
    }
    ?>
  <script type="application/ld+json">
{
  "@context": "http://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "name": "<?php echo htmlspecialchars($site_title); ?>",
      "url": "<?php echo htmlspecialchars($site_url); ?>",
      "logo": "<?php echo htmlspecialchars($site_url); ?>/uploads/logo_dark_1759078050.webp",
      "image": "<?php echo htmlspecialchars($site_url); ?>/uploads/logo_dark_1759078050.webp",
      "description": "TikTokio offers to download TikTok videos without watermark in HD or MP3 for free. Fast, unlimited TikTok downloader - save videos online in one click, no app required.",
      "email": "<?php echo htmlspecialchars($site_phone); ?>"
    },
    {
      "@type": "WebApplication",
      "name": "<?php echo htmlspecialchars($site_email); ?>",
      "applicationCategory": "EntertainmentApplication",
      "operatingSystem": "All",
      "url": "<?= htmlspecialchars($og_url) ?>",
      "image": "<?php echo htmlspecialchars($site_url); ?>/uploads/logo_dark_1759078050.webp",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      }
    }
  ]
}
</script>
</body>
</html>
