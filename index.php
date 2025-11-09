<?php
// Fetch site URL early for trailing slash redirects
require_once 'includes/config.php';
$site_url = '';
$res = $conn->query("SELECT site_url FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $site_url = rtrim($row['site_url'], '/');
}

// Get default language info
$default_lang = null;
$res = $conn->query("SELECT * FROM languages WHERE is_default = 1 LIMIT 1");
if ($res && $res->num_rows > 0) {
    $default_lang = $res->fetch_assoc();
}

// Handle trailing slash redirects - only for non-default languages
if (substr($_SERVER['REQUEST_URI'], -1) === '/') {
    $current_uri = $_SERVER['REQUEST_URI'];
    
    // Check if this is the default language home page
    $is_default_language = false;
    
    if ($default_lang) {
        // Get default language slug
        $res = $conn->query("SELECT slug FROM languages_home WHERE language_id = {$default_lang['id']} LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $default_slug = $res->fetch_assoc()['slug'];
            
            // Check if current URI is the root with trailing slash (for default language)
            // or matches the default language slug with trailing slash
            if ($current_uri === '/' || $current_uri === '/' . $default_slug . '/') {
                $is_default_language = true;
            }
        }
    }
    
    // Only redirect if it's NOT the default language home page
    if (!$is_default_language) {
        // Remove trailing slash and redirect immediately
        $clean_uri = rtrim($current_uri, '/');
        
        // Use full site URL for redirect
        $full_redirect_url = $site_url . $clean_uri;
        
        // Force redirect with 301 status
        http_response_code(301);
        header("Location: $full_redirect_url");
        header("HTTP/1.1 301 Moved Permanently");
        exit();
    }
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

    

    // Check if download app section is enabled

    $download_app_enabled = true; // Default to enabled

    $check_download_app = $conn->query("SELECT download_app_enabled FROM site_settings LIMIT 1");

    if ($check_download_app && $check_download_app->num_rows > 0) {

        $settings = $check_download_app->fetch_assoc();

        $download_app_enabled = isset($settings['download_app_enabled']) ? (bool)$settings['download_app_enabled'] : true;

    }

    

    // Function to process content for display (similar to admin panel)

    function processContentForDisplay($content) {

        // Decode HTML entities

        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        

        // Clean up extra whitespace and line breaks

        $content = preg_replace('/\s+/', ' ', $content);

        

        // Ensure proper paragraph structure

        $content = preg_replace('/<p>\s*<\/p>/', '', $content);

        

        // Fix bullet points and lists

        $content = preg_replace('/<ul>\s*<li>/', '<ul><li>', $content);

        $content = preg_replace('/<\/li>\s*<\/ul>/', '</li></ul>', $content);

        

        // Clean up empty paragraphs

        $content = preg_replace('/<p>\s*<\/p>/', '', $content);

        

        // Trim whitespace

        $content = trim($content);

        

        return $content;

    }

    

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

    

    // Detect current slug from URL path

    $current_url_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    $current_slug = $current_url_path;

    

    // Find language by home slug

    $current_lang = null;

    $current_lang_id = null;

    $current_lang_code = null;

    

    if (!empty($current_slug)) {

        // Try to find language by slug

        $res = $conn->query("SELECT l.* FROM languages l 

                            INNER JOIN languages_home lh ON l.id = lh.language_id 

                            WHERE lh.slug = '" . $conn->real_escape_string($current_slug) . "' LIMIT 1");

        if ($res && $res->num_rows > 0) {

            $current_lang = $res->fetch_assoc();

            $current_lang_id = $current_lang['id'];

            $current_lang_code = $current_lang['code'];

        }

        

    }

    

    // If no language found by slug, check for language preference from cookie or default

    if (!$current_lang) {

        $preferred_lang_code = $_COOKIE['site_lang'] ?? $default_lang['code'];

        $preferred_lang = null;

        foreach ($languages as $lang) {

            if ($lang['code'] === $preferred_lang_code) {

                $preferred_lang = $lang;

                break;

            }

        }

        if (!$preferred_lang) {

            $preferred_lang = $default_lang;

        }

        

        // If at root (/) and preferred language has a home slug, redirect to /slug

        if ($_SERVER['REQUEST_URI'] === '/') {

            $preferred_slug = '';

            $res = $conn->query("SELECT slug FROM languages_home WHERE language_id={$preferred_lang['id']} LIMIT 1");

            if ($res && $res->num_rows > 0) {

                $row = $res->fetch_assoc();

                $preferred_slug = $row['slug'];

            }

            

            if ($preferred_slug !== '') {

                header("Location: /$preferred_slug", true, 301);

                exit;

            }

        }

        

        // Use preferred language as current

        $current_lang = $preferred_lang;

        $current_lang_id = $preferred_lang['id'];

        $current_lang_code = $preferred_lang['code'];

    }

    

    // Also handle ?lang= parameter for language switching

    if (isset($_GET['lang'])) {

        foreach ($languages as $lang) {

            if ($lang['code'] == $_GET['lang']) {

                $current_lang = $lang;

                $current_lang_id = $lang['id'];

                $current_lang_code = $lang['code'];

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

    

    // Fetch meta title and description from languages_home for current language

    $meta_title = $site_title;
    $meta_description = '';
    $canonical_url = '';
    $alternate_links = [];

    // Get the actual slug from the URL path, not from database

    $actual_slug = $current_slug;


    // Fetch meta information for the detected language

    $res = $conn->query("SELECT meta_title, meta_description, slug FROM languages_home WHERE language_id=$current_lang_id LIMIT 1");

    if ($res && $res->num_rows > 0) {

        $row = $res->fetch_assoc();

        if (!empty($row['meta_title'])) $meta_title = $row['meta_title'];

        if (!empty($row['meta_description'])) $meta_description = $row['meta_description'];

        // Use the actual slug from URL, not from database

        $current_slug = $actual_slug;

    }

    

    // Build canonical and alternate links from languages_home for all languages

   // Build canonical and alternate links from languages_home for all languages

    foreach ($languages as $lang) {

        $lang_code = $lang['code'];

        $lang_id = $lang['id'];

        $is_default = $lang['is_default'];

        $slug = '';

        $res2 = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1");

        if ($res2 && $res2->num_rows > 0) {

            $row2 = $res2->fetch_assoc();

            $slug = $row2['slug'];

        }

        

        // Build URL based on the same logic as footer/navigation

        if (!empty($slug)) {

            // Slug exists - use slug without language code

            $url = $site_url . '/' . ltrim($slug, '/');

        } else {

            // No slug exists

            if ($is_default) {

                // Default language - no language code needed

                $url = $site_url;

            } else {

                // Non-default language - use language code

                $url = $site_url . '/' . $lang_code;

            }

        }

        

        if ($is_default) {

            $canonical_url = $url;

        } else {

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
<html lang="<?php echo htmlspecialchars($current_lang_code); ?>" dir="<?php echo htmlspecialchars($current_lang['direction'] ?? 'ltr'); ?>">
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
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" >
<?php endif; ?>
<?php if ($favicons['favicon_32']): ?>
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo htmlspecialchars($favicons['favicon_32']); ?>" >
<?php endif; ?>
<?php if ($favicons['favicon_16']): ?>
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" >
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
<?php
$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM languages");
$row = mysqli_fetch_assoc($result);
$language_count = $row['cnt'];
if ($canonical_url && $language_count > 1): ?>
<link rel="alternate" href="<?php echo htmlspecialchars($canonical_url); ?>" hreflang="<?php echo htmlspecialchars($languages[0]['code']); ?>"  />
<?php endif; ?>
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
<link rel="stylesheet" href="/assets/css/index.css">
<link rel="stylesheet" href="/assets/css/navigation.css">
<?php if (!empty($global_header_content)) echo $global_header_content; ?>
</head>
<body>
    <?php

    // We're on index.php (home page) - no routing needed, just display content for detected language

    

    // Fetch homepage content from languages_home

    $home = null;

    if ($current_lang_id) {

        $res = $conn->query("SELECT * FROM languages_home WHERE language_id=$current_lang_id LIMIT 1");

        if ($res && $res->num_rows > 0) {

            $home = $res->fetch_assoc();

        }

    }

    

    // Parse add_columns JSON for steps

    $add_columns_steps = [];

    if (!empty($home['add_columns'])) {

        $json = $home['add_columns'];

        $steps = json_decode($json, true);

        if (is_array($steps)) {

            $add_columns_steps = $steps;

        }

    }

    

    // Fallbacks for all fields

    function h($v) { return htmlspecialchars($v ?? ''); }

    $header = h($home['header'] ?? 'TikTok Video Downloader');

    $title1 = h($home['title1'] ?? 'Unlimited');

    $description1 = processContentForDisplay($home['description1'] ?? 'Save TikTok videos as much as you need - without any limits.');

    $title2 = h($home['title2'] ?? 'No Watermark!');

    $description2 = processContentForDisplay($home['description2'] ?? 'Download TikTok video in mp4 or remove a TT logo.');

    $title3 = h($home['title3'] ?? 'MP4 and MP3');

    $description3 = processContentForDisplay($home['description3'] ?? 'Save files in HD quality, convert TikTok to mp4 or mp3.');

    $heading2 = h($home['heading2'] ?? 'Download TikTok videos online');

    $heading2_description = processContentForDisplay($home['heading2_description'] ?? 'company name is a free TikTok download tool that helps you download TikTok videos without watermark online. Save TT videos with the highest quality in an MP4 file format and HD resolution. To find out how to use the TikTok watermark remover, follow the instructions below.');

    $how_to_download_heading = h($home['how_to_download_heading'] ?? 'How to download TikTok without watermark?');

    $add_columns = h($home['add_columns'] ?? 'Find a TT');

    $description_bottom = processContentForDisplay($home['description_bottom'] ?? 'Copy the link');

    

    // Handle multiple images from images column

    $images_data = [];

    if (!empty($home['images'])) {

        $images_data = json_decode($home['images'], true);

    } elseif (!empty($home['image'])) {

        // Convert old single image to new format for backward compatibility

        $images_data = [[

            'image' => $home['image'],

            'description' => $home['image_description'] ?? ''

        ]];

    }

    

    $image = h($home['image'] ?? 'assets/images/zAWAlPZps1od.svg');
    $image_description = processContentForDisplay($home['image_description'] ?? 'TikTok download is the perfect solution for post-editing and publishing!');
    $title1_2 = h($home['title1_2'] ?? 'This is how you can use our tiktok:');
    $pink_title1_2 = h($home['pink_title1_2'] ?? '');
    $description1_2 = processContentForDisplay($home['description1_2'] ?? 'Download TikTok video on your mobile phone');
    $image1 = h($home['image1'] ?? 'assets/images/hyiN6VP1TSNv.svg');
    $title2_2 = h($home['title2_2'] ?? 'TikTok video downloader for PC');
    $description2_2 = processContentForDisplay($home['description2_2'] ?? 'To use the tik tok video download in hd app on PC, lap...');
    $image2 = h($home['image2'] ?? 'assets/images/Pp4JMR9kwYBZ.svg');

    ?>

    <?php
   

    include 'includes/navigation.php';
    

    $qualities = [];
    $error = '';
    $data = null;
    $showModal = false;    

    ?>
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
                                    <?php echo $download_label; ?>
                                </button>
                                 <?php if ($error): ?>
                                    <div class="alert alert-danger mt-3"><?php echo $error; ?></div>
                                <?php endif; ?>
                            </div>
                        </form>
                       <div id="target" class="">
    			</div>
                        <div id="downloadOptions" style="display:none; margin-top:20px;"></div>
                    </div>
                </div>
            </section>
            <!--  -->
            <?php if ($download_app_enabled): ?>
            <div class="text__container mt-10">
                            <div class="ig-section">
                        <div class="ig-gr-content section-app-insta">
                            <div class="shapes-right"></div>
                            <div class="shapes-left"></div>
                            <div class="section-app-download">
                                <div class="app-text">
                                    <h2 class="title-white"><?php echo isset($current_lang['download_app_title']) && $current_lang['download_app_title'] !== '' ? htmlspecialchars($current_lang['download_app_title']) : 'Download the TikTokio  app for Android'; ?></h2>
                                    <p><?php echo isset($current_lang['download_app_description']) && $current_lang['download_app_description'] !== '' ? htmlspecialchars($current_lang['download_app_description']) : 'We have developed an application for Android devices. It helps to download images, videos from Instagram in just one step.'; ?></p>
                                </div>
                                <div class="center"><a

                                        href=""

                                        target="_blank" rel="nofollow noopener"><img src="assets/images/play.png" style="width:200px;" alt="Google Playstore"></a></div>

                            </div>
                        </div>
                    </div>
                        </div>
            <?php endif; ?>
            <!--  -->
            <div >
                <div class="content-visibility">
                    <section class="text">
                        <div class="text__container">
                            <h2> <?php echo $title1; ?></h2>
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

                                echo '<p style="color:#252e69;font-size: 1em;font-weight:400;">Last updated: ' . date('d M Y H:i:s') . '</p>';

                            }

                            ?>
                            <div class="text__desc" style="margin-top:10px">
                                <?php echo $description1; ?> 
                            </div>
                        </div>
                    </section>
                    <section class="text">
                        <div class="b-blk" >
                            <h3><?php echo $how_to_download_heading; ?></h3>
<ol>
    <?php if (!empty($add_columns_steps)): ?>
        <?php foreach ($add_columns_steps as $step): ?>
            <li itemprop="step" itemscope itemtype="https://schema.org/HowToStep">
                <b itemprop="name"><?php echo htmlspecialchars($step['heading'] ?? ''); ?></b> - 
                <span itemprop="text">
                    <?php 
                        // Check if the description has an image reference or normal text
                        $description = $step['description'] ?? '';
                        if (strpos($description, 'image:') !== false): 
                            // Extract image reference and display it inside span
                            $image_url = str_replace('image:', '', $description);
                            echo '<img src="' . htmlspecialchars($image_url) . '" alt="Image Reference">';
                        else:
                            // If no image reference, just print the normal text inside span
                            echo processContentForDisplay($description); 
                        endif; 
                    ?>
                </span>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li><b><?php echo $add_columns; ?></b> - <span></span></li>
    <?php endif; ?>
</ol>
                        </div>
                    </section>
                    <section id="six__items">
                    
    <div class="container">
        <div class="six__items_header">
            <?php echo $description_bottom; ?>
        </div>
        <div class="six-i-1w">
            <?php if (!empty($images_data)): ?>
                <?php foreach ($images_data as $index => $img_data): ?>
                    <div class="six-i-i">
                        <div class="six-i-i-img">
                            <img src="/admin/<?php echo htmlspecialchars($img_data['image']); ?>" 
                                 alt="<?php echo htmlspecialchars(basename($img_data['image'])); ?>" 
                                 height="110" width="110">
                        </div>
                        <div class="six-i-t">
                            <?php echo processContentForDisplay($img_data['description'] ?? ''); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Optional: You can display a message or empty state here if needed -->
            <?php endif; ?>
        </div> <!-- Close div for six-i-1w -->
    </div> <!-- Close div for container -->
</section> <!-- Close section -->

                       <section>
                            <div class="main__content">
                            <div class="main-c-c">
                                <div class="info-arrow">
                                    <?php if (!empty($pink_title1_2)): ?>
                                        <span><?php echo $pink_title1_2; ?></span><img src="assets/images/zTSA0a37KlUL.svg"
                                        alt="" width="54">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="main-c-c u-m-bottom d-flex">
                                <div class="u-smaller-text flex-1 align-items-center text text--text">
                                <h3><?php echo $title1_2; ?> </h3>
                                <?php echo $description1_2; ?>
                                </div>
                                <div class="d-flex align-items-center text--image">
                                    <div class="flex-1 img-w">
                                        <img src="/admin/<?php echo $image1; ?>" width="416" height="289" alt="tiktok android">
                                    </div>
                                </div>
                            </div>
                            <div class="main-c-c u-m-bottom d-flex">
                                <div class="d-flex text-pr align-items-center text--image">
                                    <div class="flex-1 img-w">
                                        <img src="/admin/<?php echo $image2; ?>" width="368" height="321" alt="download tiktok video pc">
                                    </div>
                                </div>
                                <div class="u-smaller-text flex-1 align-items-center text text--text">
                                    <h3><?php echo $title2_2; ?></h3>
                                    <?php echo $description2_2; ?>
                                </div>
                            </div>
                        </div>
                    </section>
                     <section class="text">
                        <div class="text__container">
                            <h2> <?php echo $title2; ?></h2>
                            <div class="text__desc">
                                <?php echo $description2; ?>
                            </div>
                        </div>
                    </section>

                   <?php

                    $faq_lang_id = $current_lang_id; // Use current language ID for FAQs

                    $faqs = [];

                    // Check if FAQs are enabled for this language

                    $lang_check = $conn->query("SELECT faqs_enabled FROM languages WHERE id=$faq_lang_id LIMIT 1");

                    if ($lang_check && $lang_check->num_rows > 0) {

                        $lang_data = $lang_check->fetch_assoc();

                        if ($lang_data['faqs_enabled'] == 1) { // only proceed if enabled

                            $faq_res = $conn->query("SELECT question, answer FROM language_faqs WHERE language_id=$faq_lang_id ORDER BY id ASC");

                            if ($faq_res && $faq_res->num_rows > 0) {

                                while ($faq = $faq_res->fetch_assoc()) {

                                    $faqs[] = $faq;

                                }

                            }

                        }

                    }
                    ?>
                    <section id="accordion">
                        <div class="accordion__container" id="dynamicFaqContainer">
                            <!-- Dynamic FAQ will be rendered here -->
                        </div>
                        <div style="margin-top:20px;">
                            <button id="nextFaqBtn" style="display:none;">Next</button>
                        </div>
                    </section>
                    <script>
                    const faqs = <?php echo json_encode($faqs); ?>;
                    function renderFaqs() {
                        const container = document.getElementById('dynamicFaqContainer');
                        if (!faqs.length) {
                            container.innerHTML = '<div>No FAQs available.</div>';
                            return;
                        }
                        let html = '';
                        for (let idx = 0; idx < faqs.length; idx++) {
                            const faq = faqs[idx];
                            const qid = 'faq-drawer-' + idx;
                            const panelid = 'panel-' + idx;
                            html += `
                                <div  class="faq-drawer">
                                    <input class="faq-d-t" id="${qid}" type="checkbox" />
                                    <label class="faq-drawer__title accordion" for="${qid}" itemprop="name">${faq.question}</label>
                                    <div class="panel faq-d-w" data-panel="${panelid}" >
                                        <div itemprop="text" class="faq-drawer__content">${faq.answer}</div>
                                    </div>
                                </div>
                            `;

                        }

                        container.innerHTML = html;

                    }

                    document.addEventListener('DOMContentLoaded', function() {

                        renderFaqs();

                    });

                    </script>
                </div>
            </div>
        </main>
    <?php

    // Use the language variables already set at the top of index.php

    $current_lang = $current_lang; // Already set from slug detection

    

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



    

    // Languages are already fetched at the top of index.php

    

    // For index.php, we're on the home page

    $page_name = 'Home';

    

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

                                            // Try how_pages

                                            $sql = "SELECT slug FROM how_pages WHERE language_id=" . intval($lang['id']) . " AND page_name='" . $conn->real_escape_string($page_name) . "' LIMIT 1";

                                            $res = $conn->query($sql);

                                            if ($res && $res->num_rows > 0) {

                                                $row = $res->fetch_assoc();

                                                $translated_slug = $row['slug'];

                                            } else {

                                                // Try mp3_pages

                                                $sql = "SELECT slug FROM mp3_pages WHERE language_id=" . intval($lang['id']) . " AND page_name='" . $conn->real_escape_string($page_name) . "' LIMIT 1";

                                                $res = $conn->query($sql);

                                                if ($res && $res->num_rows > 0) {

                                                    $row = $res->fetch_assoc();

                                                    $translated_slug = $row['slug'];

                                                } else {

                                                    // Try stories_pages

                                                    $sql = "SELECT slug FROM stories_pages WHERE language_id=" . intval($lang['id']) . " AND page_name='" . $conn->real_escape_string($page_name) . "' LIMIT 1";

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

                                // Build URL based on conditions

                                if ($is_home_page && !empty($translated_slug)) {

                                    // Home page with slug - use slug without language code

                                    $lang_url = '/' . htmlspecialchars($translated_slug);

                                } elseif (!empty($translated_slug)) {

                                    // Non-home page with slug - always use language code + slug

                                    if ($lang['is_default']) {

                                        $lang_url = '/' . htmlspecialchars($translated_slug);

                                    } else {

                                        $lang_url = '/' . htmlspecialchars($lang['code']) . '/' . htmlspecialchars($translated_slug);

                                    }

                                } else {

                                    // No slug exists

                                    if ($lang['is_default']) {

                                        $lang_url = '/';

                                    } else {

                                        $lang_url = '/' . htmlspecialchars($lang['code']);

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
    <script src="/assets/js/index.js" defer></script>
     <script src="/assets/js/navigation.js" defer></script>
    <?php
    // Fetch global_footer content

    $global_footer_content = '';

    $res = $conn->query("SELECT content FROM global_footer LIMIT 1");

    if ($res && $res->num_rows > 0) {

        $row = $res->fetch_assoc();

        $global_footer_content = $row['content'];

    }
    ?>
    <script>
        document.querySelectorAll('p').forEach(p => {
        if (!p.textContent.trim()) {
            p.remove();
        }
        });
    </script>
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
      "description": "Tiktokio offer to download TikTok videos without watermark in HD or MP3 for free. Fast, unlimited TikTok downloader - save videos online in one click, no app required.",
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
<?php
// Fetch FAQs for current language
$faq_schema_data = [];
if ($current_lang_id) {
    $faq_res = $conn->query("SELECT question, answer FROM language_faqs WHERE language_id=$current_lang_id ORDER BY id ASC LIMIT 10");
    if ($faq_res && $faq_res->num_rows > 0) {
        while ($faq_row = $faq_res->fetch_assoc()) {
            $faq_schema_data[] = [
                "@type" => "Question",
                "name" => $faq_row['question'],
                "acceptedAnswer" => [
                    "@type" => "Answer",
                    "text" => $faq_row['answer']
                ]
            ];
        }
    }
}

// Only output FAQ schema if we have FAQ data
if (!empty($faq_schema_data)) {
?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": <?php echo json_encode($faq_schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
}
</script>
<?php } ?>
    </body>
    </html>