<?php
// Handle trailing slash redirects - add this at the very top of each file (after <?php)
// if (substr($_SERVER['REQUEST_URI'], -1) === '/') {
//     // Remove trailing slash and redirect immediately
//     $clean_uri = rtrim($_SERVER['REQUEST_URI'], '/');
    
//     // Force redirect with 301 status
//     http_response_code(301);
//     header("Location: $clean_uri");
//     header("HTTP/1.1 301 Moved Permanently");
//     exit();
// }

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
if ($lang_code) {
    setcookie('site_lang', $lang_code, time() + (86400 * 30), '/'); // 30 days
}

// Fetch site settings early (needed for redirects)
$site_title = 'site';
$site_url = '';
$res = $conn->query("SELECT site_name, site_url FROM site_settings LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    if (!empty($row['site_name'])) {
        $site_title = $row['site_name'];
    }
    if (!empty($row['site_url'])) {
        $site_url = rtrim($row['site_url'], '/');
    }
}

// Find the page - First try current language
$page_data = null;
$should_redirect = false;

if ($lang_id && $current_slug) {
    $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND slug='" . $conn->real_escape_string($current_slug) . "' LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page_data = $res->fetch_assoc();
        
        // Check if this is an FAQ page - always redirect to 404
        $page_name_lower = isset($page_data['page_name']) ? strtolower($page_data['page_name']) : '';
        if ($page_name_lower === 'faq' || $page_name_lower === 'faqs' || stripos($page_name_lower, 'faq') !== false || stripos($current_slug, 'faq') !== false) {
            http_response_code(404);
            include '404.php';
            exit;
        }
        
        // Check if this is a copyright page - always redirect to 404
        if ($page_name_lower === 'copyright' || $current_slug === 'c1-py' || stripos($page_name_lower, 'copyright') !== false) {
            http_response_code(404);
            include '404.php';
            exit;
        }
        
        // Check if this is a contact page for non-default language - redirect to 404
        $res_default = $conn->query("SELECT code FROM languages WHERE is_default=1 LIMIT 1");
        if ($res_default && $res_default->num_rows > 0) {
            $default_lang_data = $res_default->fetch_assoc();
            $page_name_contact = isset($page_data['page_name']) ? strtolower($page_data['page_name']) : '';
            if (($page_name_contact === 'contact' || stripos($page_name_contact, 'contact') !== false || stripos($current_slug, 'contact') !== false) && $lang_code !== $default_lang_data['code']) {
                http_response_code(404);
                include '404.php';
                exit;
            }
        }
    } else {
        // Page not found in current language, check if it exists in default language
        $should_redirect = true;
    }
}

// Fallback: try to get the page for the default language
if (!$page_data && $current_slug) {
    $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $default_lang = $res->fetch_assoc();
        $default_lang_id = $default_lang['id'];
        $sql = "SELECT * FROM language_pages WHERE language_id=$default_lang_id AND slug='" . $conn->real_escape_string($current_slug) . "' LIMIT 1";
        $res2 = $conn->query($sql);
        if ($res2 && $res2->num_rows > 0) {
            $page_data = $res2->fetch_assoc();
            
            // Check if this is an FAQ page - always redirect to 404
            $page_name_lower = isset($page_data['page_name']) ? strtolower($page_data['page_name']) : '';
            if ($page_name_lower === 'faq' || $page_name_lower === 'faqs' || stripos($page_name_lower, 'faq') !== false || stripos($current_slug, 'faq') !== false) {
                http_response_code(404);
                include '404.php';
                exit;
            }
            
            // Check if this is a copyright page - always redirect to 404
            if ($page_name_lower === 'copyright' || $current_slug === 'c1-py' || stripos($page_name_lower, 'copyright') !== false) {
                http_response_code(404);
                include '404.php';
                exit;
            }
        } else {
            // Page doesn't exist in default language either, redirect to 404 page
            header("HTTP/1.1 404 Not Found");
            header("Location: " . $site_url . "/404.php");
            exit();
        }
    }
}

// Redirect to default language if page doesn't exist in requested language
if ($should_redirect && $lang_code && $lang_code !== 'default') {
    // Get default language info
    $res = $conn->query("SELECT * FROM languages WHERE is_defau lt=1 LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $default_lang = $res->fetch_assoc();
        
        // Check if the page exists in default language
        $default_page_exists = false;
        $default_page_slug = '';
        
        if ($current_slug) {
            $sql = "SELECT * FROM language_pages WHERE language_id=" . $default_lang['id'] . " AND slug='" . $conn->real_escape_string($current_slug) . "' LIMIT 1";
            $res2 = $conn->query($sql);
            if ($res2 && $res2->num_rows > 0) {
                $temp_page_data = $res2->fetch_assoc();
                
                // Check if this is an FAQ page - always redirect to 404
                $temp_page_name_lower = isset($temp_page_data['page_name']) ? strtolower($temp_page_data['page_name']) : '';
                if ($temp_page_name_lower === 'faq' || $temp_page_name_lower === 'faqs' || stripos($temp_page_name_lower, 'faq') !== false || stripos($current_slug, 'faq') !== false) {
                    http_response_code(404);
                    include '404.php';
                    exit;
                }
                
                // Check if this is a copyright page - always redirect to 404
                if ($temp_page_name_lower === 'copyright' || $current_slug === 'c1-py' || stripos($temp_page_name_lower, 'copyright') !== false) {
                    http_response_code(404);
                    include '404.php';
                    exit;
                }
                
                $default_page_exists = true;
                $default_page_slug = $current_slug;
            }
        }
        
        if ($default_page_exists) {
            // Page exists in default language, redirect to it
            $redirect_url = $site_url;
            if (!empty($default_page_slug) && $default_page_slug !== '/') {
                $redirect_url .= '/' . ltrim($default_page_slug, '/');
            }
            
            // Perform redirect to default language page
            header("HTTP/1.1 301 Moved Permanently");
            header("Location: " . $redirect_url);
            exit();
        } else {
            // Page doesn't exist in default language either, redirect to 404 page
            header("HTTP/1.1 404 Not Found");
            header("Location: " . $site_url . "/404.php");
            exit();
        }
    }
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

// Fetch home page slug for current language
$home_slug = null;
if ($lang_id) {
    $res = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $home_slug = $row['slug'];
    }
}


// Set meta title and description
$meta_title = 'meta title here';
$meta_description = 'meta description here';

if ($page_data) {
    if (!empty($page_data['meta_title'])) {
        $meta_title = $page_data['meta_title'];
    }
    if (!empty($page_data['meta_description'])) {
        $meta_description = $page_data['meta_description'];
    }
} elseif ($lang_id && $current_slug) {
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

// Fetch all languages for canonical/alternate links
$languages = [];
$res = $conn->query("SELECT * FROM languages");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $languages[] = $row;
    }
}

// Build canonical URL for the current page - always use default language
$default_lang_id = null;
$res = $conn->query("SELECT id FROM languages WHERE is_default=1 LIMIT 1");
if ($res && $res->num_rows > 0) {
    $default_lang = $res->fetch_assoc();
    $default_lang_id = $default_lang['id'];
}

if ($current_slug === '/' || $current_slug === '') {
    $canonical_url = $site_url . '/';
} else {
    // For canonical URL, always use the default language version
    if ($page_data && !empty($page_data['page_name'])) {
        // Try to find the default language version of this page
        $stmt = $conn->prepare("SELECT slug FROM language_pages WHERE language_id=? AND page_name=? LIMIT 1");
        $stmt->bind_param("is", $default_lang_id, $page_data['page_name']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $canonical_url = $site_url . '/' . ltrim($row['slug'], '/');
        } else {
            $canonical_url = $site_url . '/' . ltrim($current_slug, '/');
        }
        $stmt->close();
    } else {
        $canonical_url = $site_url . '/' . ltrim($current_slug, '/');
    }
}

// Also add default language to alternate links
$default_lang_alt = null;
foreach ($languages as $lang) {
    if ($lang['is_default']) {
        $default_lang_alt = $lang;
        break;
    }
}

// Build alternate links for different languages
$alternate_links = [];

// First add the default language (without language code)
if ($default_lang_alt) {
    $default_slug = '';
    if (empty($current_slug) || $current_slug === '/') {
        // Home page case
        $res2 = $conn->query("SELECT slug FROM languages_home WHERE language_id=" . $default_lang_alt['id'] . " LIMIT 1");
        if ($res2 && $res2->num_rows > 0) {
            $row2 = $res2->fetch_assoc();
            $default_slug = $row2['slug'];
        }
    } else {
        // Other pages case
        if ($page_data && !empty($page_data['page_name'])) {
            $stmt2 = $conn->prepare("SELECT slug FROM language_pages WHERE language_id=? AND page_name=? LIMIT 1");
            $stmt2->bind_param("is", $default_lang_alt['id'], $page_data['page_name']);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($result2 && $result2->num_rows > 0) {
                $row2 = $result2->fetch_assoc();
                $default_slug = $row2['slug'];
            }
            $stmt2->close();
        }
    }
    
    // Build default language URL (no language code)
    $default_url = $site_url;
    if (!empty($default_slug) && $default_slug !== '/' && $default_slug !== 'home') {
        $default_url .= '/' . ltrim($default_slug, '/');
    }
    
    $alternate_links[] = [
        'url' => $default_url,
        'hreflang' => $default_lang_alt['code']
    ];
}

// Then add other languages
foreach ($languages as $lang) {
    $lang_code_alt = $lang['code'];
    $is_default = $lang['is_default'];
    $lang_id_alt = $lang['id'];
    
    // Skip default language (already added above)
    if ($is_default) {
        continue;
    }
    
    // Get slug for this language and current page
    $slug = '';
    
    if (empty($current_slug) || $current_slug === '/') {
        // Home page case
        $res2 = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id_alt LIMIT 1");
        if ($res2 && $res2->num_rows > 0) {
            $row2 = $res2->fetch_assoc();
            $slug = $row2['slug'];
        }
    } else {
        // Other pages case - improved logic to find corresponding pages
        if ($page_data && !empty($page_data['page_name'])) {
            // If we have page_data, use the page_name to find corresponding pages in other languages
            $stmt2 = $conn->prepare("SELECT slug FROM language_pages WHERE language_id=? AND page_name=? LIMIT 1");
            $stmt2->bind_param("is", $lang_id_alt, $page_data['page_name']);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2 && $result2->num_rows > 0) {
                $row2 = $result2->fetch_assoc();
                $slug = $row2['slug'];
            }
            $stmt2->close();
        } else {
            // Fallback: try to find by current slug in other language
            $stmt2 = $conn->prepare("SELECT slug FROM language_pages WHERE language_id=? AND slug=? LIMIT 1");
            $stmt2->bind_param("is", $lang_id_alt, $current_slug);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($result2 && $result2->num_rows > 0) {
                $row2 = $result2->fetch_assoc();
                $slug = $row2['slug'];
            }
            $stmt2->close();
        }
    }
    
    // Build URL structure
    $url = $site_url;
    
    // Add language code (since we skipped default, all remaining need language code)
    $url .= '/' . $lang_code_alt;
    
    // Add slug if not home page
    if (!empty($slug) && $slug !== '/' && $slug !== 'home') {
        $url .= '/' . ltrim($slug, '/');
    } else {
        // If no slug found for this language, just show site URL with language code
        // This means the page doesn't exist in this language
        $url = rtrim($url, '/');
    }
    
    // Remove any duplicate language codes in the URL
    $url = preg_replace('/\/' . preg_quote($lang_code_alt, '/') . '\/' . preg_quote($lang_code_alt, '/') . '/', '/' . $lang_code_alt, $url);
    
    $alternate_links[] = [
        'url' => $url,
        'hreflang' => $lang_code_alt
    ];
}

// Fetch global_header content
$global_header_content = '';
$res = $conn->query("SELECT content FROM global_header LIMIT 1");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $global_header_content = $row['content'];
}

// Helper function to safely get array values
function safe_get($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>
<?php
    $og_url = $site_url; // Initialize with base URL
    
    // Use the actual slug from the URL path for OG URL
    if (!empty($current_slug)) {
        $og_url .= '/' . $current_slug;
    }
    ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_lang['code'] ?? 'en'); ?>" dir="<?php echo htmlspecialchars($current_lang['direction'] ?? 'ltr'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"> 
<title><?php echo htmlspecialchars($meta_title); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>" />
<meta name="author" content="<?php echo htmlspecialchars($meta_title); ?>" />
<meta property="og:title" content="<?php echo htmlspecialchars($meta_title); ?>" />
<meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>" />
<meta property="og:type" content="article" />
<?php if ($canonical_url): ?>
<meta property="og:url" content="<?php echo htmlspecialchars($og_url); ?>" />
<?php endif; ?>
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
<meta name="twitter:site" content="<?php echo htmlspecialchars($og_url); ?>" />
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
<link rel="canonical" href="<?php echo htmlspecialchars($og_url); ?>"  />
<?php
// Output alternate links
foreach ($alternate_links as $alt) {
    echo '<link rel="alternate" href="' . htmlspecialchars($alt['url']) . '" hreflang="' . htmlspecialchars($alt['hreflang']) . '" />' . "\n";
}

// Debug output for alternate links and redirects (remove this in production)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<!-- Debug: Current slug: " . htmlspecialchars($current_slug) . " -->\n";
    echo "<!-- Debug: Current lang_id: " . $lang_id . " -->\n";
    echo "<!-- Debug: Current lang_code: " . htmlspecialchars($lang_code) . " -->\n";
    echo "<!-- Debug: Page data: " . ($page_data ? 'Yes' : 'No') . " -->\n";
    if ($page_data) {
        echo "<!-- Debug: Page name: " . htmlspecialchars($page_data['page_name']) . " -->\n";
    }
    echo "<!-- Debug: Should redirect: " . ($should_redirect ? 'Yes' : 'No') . " -->\n";
    echo "<!-- Debug: Canonical URL: " . htmlspecialchars($canonical_url) . " -->\n";
    echo "<!-- Debug: Alternate links count: " . count($alternate_links) . " -->\n";
    foreach ($alternate_links as $i => $alt) {
        echo "<!-- Debug: Alt " . ($i+1) . " - " . htmlspecialchars($alt['hreflang']) . " -> " . htmlspecialchars($alt['url']) . " -->\n";
    }
}

$result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM languages");
$row = mysqli_fetch_assoc($result);
$language_count = $row['cnt'];
if ($canonical_url && $language_count > 1): ?>
<link rel="alternate" href="<?php echo htmlspecialchars($canonical_url); ?>" hreflang="x-default" />
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/Oyf3H4i0HaSN.css">
<link rel="stylesheet" href="/assets/css/navigation.css">
<link rel="stylesheet" href="/assets/css/index.css">
<?php if (!empty($global_header_content)) echo $global_header_content; ?>
</head>
<body class="bg-gray">

<?php
include 'includes/navigation.php';

// If no page data found and we have a slug, redirect to 404
if (!$page_data && !empty($current_slug)) {
    header("HTTP/1.1 404 Not Found");
    header("Location: " . $site_url . "/404.php");
    exit();
}
?>
<style>
    .text h2 {
    font-style: normal;
    font-size: 32px;
    line-height: 1em;
    color: #555;
    text-align: left;
}
</style>
<main>
    <section>
        <div class="splash-container" id="splash" hx-ext="include-vals">
            <div id="splash_wrapper" class="splash" style="max-width: 1680px;">
               
                    <?php if ($page_data): ?>
                        <h1  class="splash-head hide-after-request" id="bigmessage" style="padding-bottom:0px;"><?php echo htmlspecialchars(safe_get($page_data, 'meta_header', 'Page Not Found')); ?></h1>
                         <?php else: ?>
                        <h1 style="font-size:2.2rem; font-weight:700; margin-bottom:1.5rem;">title Not Found</h1>
                    <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="text">
        <div class="text__container">
                <?php if ($page_data): ?>
                            <div class="text__desc" style="margin-top:70px">
                                <?php echo safe_get($page_data, 'content', '<p>Content not available.</p>'); ?>
                            </div>
                        <?php else: ?>
                            <div class="text__desc" style="margin-top:10px">
                                <p>The page you are looking for does not exist.</p>
                            </div>
                        <?php endif; ?>
            
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>