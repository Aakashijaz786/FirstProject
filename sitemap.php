<?php
require_once __DIR__ . '/includes/config.php';

// Set content type to XML
header('Content-Type: application/xml; charset=utf-8');

// Start output buffering to catch any errors
ob_start();

// Error handler to prevent XML corruption
function handleSitemapError($errno, $errstr, $errfile, $errline) {
    // Clear any output
    ob_clean();
    
    // Send proper XML error response
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '<url>' . "\n";
    echo '  <loc>https://tiktokio.lol</loc>' . "\n";
    echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
    echo '  <priority>1.00</priority>' . "\n";
    echo '</url>' . "\n";
    echo '</urlset>' . "\n";
    exit;
}

// Set error handler
set_error_handler('handleSitemapError');

// Get site URL from database or use default
$site_url = 'https://tiktokio.lol';
$site_settings = $conn->query("SELECT site_url FROM site_settings LIMIT 1");
if ($site_settings && $site_settings->num_rows > 0) {
    $settings = $site_settings->fetch_assoc();
    if (!empty($settings['site_url'])) {
        $site_url = rtrim($settings['site_url'], '/');
    }
}

// Get all languages
$languages = [];
$res = $conn->query("SELECT * FROM languages ORDER BY is_default DESC, id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $languages[] = $row;
    }
}

// Clear any output before starting XML
ob_clean();

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Add homepage
echo '<url>' . "\n";
echo '  <loc>' . $site_url . '</loc>' . "\n";
echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
echo '  <priority>1.00</priority>' . "\n";
echo '</url>' . "\n";

// Process each language
foreach ($languages as $lang) {
    $lang_code = $lang['code'];
    $lang_prefix = ($lang_code !== 'en') ? $lang_code : '';
    
    // Add language-specific homepage if not default language
    if ($lang_code !== 'en') {
        echo '<url>' . "\n";
        echo '  <loc>' . $site_url . '/' . $lang_prefix . '</loc>' . "\n";
        echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
        echo '  <priority>0.90</priority>' . "\n";
        echo '</url>' . "\n";
    }
    
    // MP3 Pages - only if enabled
    if (isset($lang['mp3_enabled']) && $lang['mp3_enabled']) {
        $stmt = $conn->prepare("SELECT mps.slug FROM mp3_page_slugs mps 
                               JOIN mp3_pages mp ON mps.mp3_page_id = mp.id 
                               WHERE mp.language_id = ? AND mps.status = 'active' 
                               ORDER BY mps.id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $lang['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                echo '<url>' . "\n";
                echo '  <loc>' . $site_url . '/' . htmlspecialchars($row['slug']) . '</loc>' . "\n";
                echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
                echo '  <priority>0.80</priority>' . "\n";
                echo '</url>' . "\n";
            }
            $stmt->close();
        }
    }
    
    // Stories Pages - only if enabled
    if (isset($lang['stories_enabled']) && $lang['stories_enabled']) {
        $stmt = $conn->prepare("SELECT sps.slug FROM stories_page_slugs sps 
                               JOIN stories_pages sp ON sps.stories_page_id = sp.id 
                               WHERE sp.language_id = ? AND sps.status = 'active' 
                               ORDER BY sps.id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $lang['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                echo '<url>' . "\n";
                echo '  <loc>' . $site_url . '/' . htmlspecialchars($row['slug']) . '</loc>' . "\n";
                echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
                echo '  <priority>0.80</priority>' . "\n";
                echo '</url>' . "\n";
            }
            $stmt->close();
        }
    }
    
    // How Pages - only if enabled
    if (isset($lang['how_enabled']) && $lang['how_enabled']) {
        $stmt = $conn->prepare("SELECT hps.slug FROM how_page_slugs hps 
                               JOIN how_pages hp ON hps.how_page_id = hp.id 
                               WHERE hp.language_id = ? AND hps.status = 'active' 
                               ORDER BY hps.id DESC");
        if ($stmt) {
            $stmt->bind_param('i', $lang['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                echo '<url>' . "\n";
                echo '  <loc>' . $site_url . '/' . htmlspecialchars($row['slug']) . '</loc>' . "\n";
                echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
                echo '  <priority>0.80</priority>' . "\n";
                echo '</url>' . "\n";
            }
            $stmt->close();
        }
    }
    
    // Custom Pages - always include (they're manually created)
    $stmt = $conn->prepare("SELECT cps.slug, cp.slug as page_slug FROM custom_page_slugs cps 
                           JOIN custom_pages cp ON cps.custom_page_id = cp.id 
                           WHERE cp.language_id = ? AND cps.status = 'active' 
                           ORDER BY cps.id DESC");
    if ($stmt) {
        $stmt->bind_param('i', $lang['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $slug_lower = strtolower($row['slug']);
            
            // Skip FAQ pages
            if (stripos($slug_lower, 'faq') !== false) {
                continue;
            }
            
            // Skip copyright page
            if ($slug_lower === 'c1-py' || stripos($slug_lower, 'copyright') !== false) {
                continue;
            }
            
            // Skip contact page for non-English languages
            if (stripos($slug_lower, 'contact') !== false && $lang_code !== 'en') {
                continue;
            }
            
            echo '<url>' . "\n";
            echo '  <loc>' . $site_url . '/' . htmlspecialchars($row['slug']) . '</loc>' . "\n";
            echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
            echo '  <priority>0.70</priority>' . "\n";
            echo '</url>' . "\n";
        }
        $stmt->close();
    }
    
    // Language Pages - exclude FAQ pages, contact only for English
    $stmt = $conn->prepare("SELECT slug, page_name FROM language_pages 
                           WHERE language_id = ? 
                           ORDER BY id DESC");
    if ($stmt) {
        $stmt->bind_param('i', $lang['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $page_name_lower = strtolower($row['page_name']);
            $slug_lower = strtolower($row['slug']);
            
            // Skip FAQ pages completely
            if ($page_name_lower === 'faq' || $page_name_lower === 'faqs' || 
                stripos($page_name_lower, 'faq') !== false || stripos($slug_lower, 'faq') !== false) {
                continue;
            }
            
            // Skip copyright page completely
            if ($page_name_lower === 'copyright' || $slug_lower === 'c1-py' || 
                stripos($page_name_lower, 'copyright') !== false) {
                continue;
            }
            
            // Skip contact page for non-English languages
            if (($page_name_lower === 'contact' || stripos($page_name_lower, 'contact') !== false || 
                stripos($slug_lower, 'contact') !== false) && $lang_code !== 'en') {
                continue;
            }
            
            echo '<url>' . "\n";
            echo '  <loc>' . $site_url . '/' . htmlspecialchars($row['slug']) . '</loc>' . "\n";
            echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
            echo '  <priority>0.65</priority>' . "\n";
            echo '</url>' . "\n";
        }
        $stmt->close();
    }
    
    // Static contact page - only for default English language
    if ($lang_code === 'en') {
        echo '<url>' . "\n";
        echo '  <loc>' . $site_url . '/contact</loc>' . "\n";
        echo '  <lastmod>' . date('c') . '</lastmod>' . "\n";
        echo '  <priority>0.60</priority>' . "\n";
        echo '</url>' . "\n";
    }
}

echo '</urlset>' . "\n";
?>
