<?php
require_once __DIR__ . '/config.php';

// Check if variables are already set by the calling page (like page.php)
if (!isset($current_lang)) {
    // Initialize current_lang to null
    $current_lang = null;
    
    // Fetch default language id if not already set
    $default_lang_id = null;
    $res = $conn->query("SELECT id FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $default_lang_id = $row['id'];
    }
    
    // Set current_lang if not already set
    if (!$current_lang && $default_lang_id) {
        $res = $conn->query("SELECT * FROM languages WHERE id=$default_lang_id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $current_lang = $res->fetch_assoc();
            $lang_id = $current_lang['id'];
        }
    }
}

// Ensure we have a current_lang
if (!$current_lang) {
    $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $current_lang = $res->fetch_assoc();
        $lang_id = $current_lang['id'];
    }
}

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

// Use current_slug if already set, otherwise detect from URL
if (!isset($current_slug)) {
    $current_slug = '/'; // default to home
    if (isset($_GET['slug']) && !empty($_GET['slug'])) {
        $current_slug = $_GET['slug'];
    } elseif (isset($_SERVER['REQUEST_URI'])) {
        // Parse URI to get slug (customize as per your routing)
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $current_slug = trim($uri, '/');
        if ($current_slug === '') $current_slug = '/';
    }
}

// Fetch home page slug for default language
$home_slug = null;
if (isset($lang_id) && $lang_id) {
    $res = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $home_slug = $row['slug'];
    }
}

// Fetch page_name from language_pages for the current page
$site_title = 'site';
$res = $conn->query("SELECT site_name FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if (!empty($row['site_name'])) {
        $site_title = $row['site_name'];
    }
}

$meta_title = 'meta title here';
$meta_description = 'meta description here';

// Use page_data if already set by calling page, otherwise fetch from database
if (isset($page_data) && $page_data) {
    if (!empty($page_data['meta_title'])) {
        $meta_title = $page_data['meta_title'];
    }
    if (!empty($page_data['meta_description'])) {
        $meta_description = $page_data['meta_description'];
    }
} elseif (isset($lang_id) && $lang_id && $current_slug) {
    // Try to fetch from language_pages first
    $stmt = $conn->prepare("SELECT meta_title, meta_description FROM language_pages WHERE language_id=? AND slug=? LIMIT 1");
    $stmt->bind_param("is", $lang_id, $current_slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['meta_title'])) {
            $meta_title = $row['meta_title'];
        }
        if (!empty($row['meta_description'])) {
            $meta_description = $row['meta_description'];
        }
    } else {
        // Fallback to home meta if not found
        $res = $conn->query("SELECT meta_title, meta_description FROM languages_home WHERE language_id=$lang_id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            if (!empty($row['meta_title'])) {
                $meta_title = $row['meta_title'];
            }
            if (!empty($row['meta_description'])) {
                $meta_description = $row['meta_description'];
            }
        }
    }
    $stmt->close();
}

// Fetch site URL from site_settings
$site_url = '';
$res = $conn->query("SELECT site_url FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $site_url = rtrim($row['site_url'], '/');
}

// Fetch all languages for canonical/alternate links
$languages = [];
$res = $conn->query("SELECT * FROM languages");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $languages[] = $row;
    }
}

// Build canonical URL for the current page
if ($current_slug === '/' || $current_slug === '') {
    $canonical_url = $site_url . '/';
} else {
    $canonical_url = $site_url . '/' . ltrim($current_slug, '/');
}

// Build alternate links for different languages
$alternate_links = [];
foreach ($languages as $lang) {
    $lang_code = $lang['code'];
    $is_default = $lang['is_default'];
    $lang_id = $lang['id'];
    
    // Skip default language for alternate links
    if ($is_default) {
        continue;
    }
    
    // Get slug for this language and current page
    $slug = '';
    
    if (empty($current_slug) || $current_slug === '/') {
        // Home page case
        $res2 = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1");
        if ($res2 && $res2->num_rows > 0) {
            $row2 = $res2->fetch_assoc();
            $slug = $row2['slug'];
        }
    } else {
        // Other pages case
        $stmt2 = $conn->prepare("SELECT slug FROM language_pages WHERE language_id=? AND page_name = 
                                (SELECT page_name FROM language_pages WHERE slug=? LIMIT 1) LIMIT 1");
        $stmt2->bind_param("is", $lang_id, $current_slug);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        if ($result2 && $result2->num_rows > 0) {
            $row2 = $result2->fetch_assoc();
            $slug = $row2['slug'];
        }
        $stmt2->close();
    }
    
    // Build URL structure
    $url = $site_url;
    
    // Add language code (since we skipped default, all remaining need language code)
    $url .= '/' . $lang_code;
    
    // Add slug if not home page
    if (!empty($slug) && $slug !== '/' && $slug !== 'home') {
        $url .= '/' . ltrim($slug, '/');
    } elseif (empty($slug) && !empty($current_slug) && $current_slug !== '/') {
        // Fallback for pages that might not have translations
        $url .= '/' . ltrim($current_slug, '/');
    }
    
    // Ensure trailing slash for home pages
    if (empty($slug) || $slug === '/' || $slug === 'home') {
        $url = rtrim($url, '/') . '/';
    }
    
    $alternate_links[] = [
        'url' => $url,
        'hreflang' => $lang_code
    ];
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
<html lang="<?php echo htmlspecialchars($current_lang['code'] ?? 'en'); ?>" dir="<?php echo htmlspecialchars($current_lang['direction'] ?? 'ltr'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> 
<title>Contact Us</title>
<meta name="description" content="We are here to answer any questions or inquiries that you may have. Reach out to us and we will respond as soon as possible." />
<meta name="author" content="Contact Us" />
<meta property="og:title" content="Contact Us" />
<meta property="og:description" content="We are here to answer any questions or inquiries that you may have. Reach out to us and we will respond as soon as possible." />
<meta property="og:type" content="article" />
<?php if ($canonical_url): ?>
<meta property="og:url" content="<?php echo htmlspecialchars($canonical_url); ?>" />
<?php endif; ?>
<?php if ($favicons['favicon_16']): ?>
<meta property="og:image" content="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" />
<?php endif; ?>
<meta property="og:site_name" content="<?php echo htmlspecialchars($site_title); ?>" />
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="Contact Us" />
<meta name="twitter:description" content="We are here to answer any questions or inquiries that you may have. Reach out to us and we will respond as soon as possible." />
<?php if ($favicons['favicon_16']): ?>
<meta name="twitter:image:src" content="<?php echo htmlspecialchars($favicons['favicon_16']); ?>" />
<?php endif; ?>
<meta name="twitter:site" content="<?php echo htmlspecialchars($canonical_url); ?>" />
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
<?php if ($canonical_url): ?>
<link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>"  />
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/Oyf3H4i0HaSN.css">
<link rel="stylesheet" href="/assets/css/navigation.css">
<link rel="stylesheet" href="/assets/css/index.css">
<?php if (!empty($global_header_content)) echo $global_header_content; ?>
</head>
<body class="bg-gray">