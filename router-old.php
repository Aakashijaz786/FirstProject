<?php
require_once __DIR__ . '/includes/config.php';

// Helper: get all languages and default
$languages = [];
$default_lang = null;
$default_lang_id = null;
$res = $conn->query("SELECT * FROM languages");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $languages[$row['code']] = $row;
        if ($row['is_default']) {
            $default_lang = $row;
            $default_lang_id = $row['id'];
        }
    }
}
if (!$default_lang) {
    // fallback: first language
    $default_lang = reset($languages);
    $default_lang_id = $default_lang['id'];
}

// Parse URI
$original_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($original_uri, '/');
$segments = $uri ? explode('/', $uri) : [];

// Handle URLs ending with // - redirect to active slug FIRST (before any other processing)
if (substr($original_uri, -2) === '//') {
    // Remove trailing slashes and get clean slug
    $clean_uri = rtrim($uri, '/');
    
    // Find the active slug for this page by checking all tables
    $active_slug = '';
    
    // Check languages_home table for active slug
    $stmt = $conn->prepare("SELECT slug FROM languages_home WHERE slug = ? AND status = 'active' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $clean_uri);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $active_slug = $row['slug'];
        }
        $stmt->close();
    }
    
    // Check language_pages table for active slug
    if (empty($active_slug)) {
        $stmt = $conn->prepare("SELECT slug FROM language_pages WHERE slug = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $clean_uri);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $active_slug = $row['slug'];
            }
            $stmt->close();
        }
    }
    
    // Check custom_pages table for active slug
    if (empty($active_slug)) {
        $stmt = $conn->prepare("SELECT slug FROM custom_pages WHERE slug = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $clean_uri);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $active_slug = $row['slug'];
            }
            $stmt->close();
        }
    }
    
    // Check other page tables for active slug
    $page_tables = [
        'how_page_slugs' => 'how_page_id',
        'mp3_page_slugs' => 'mp3_page_id', 
        'stories_page_slugs' => 'stories_page_id'
    ];
    
    foreach ($page_tables as $table => $field) {
        if (empty($active_slug)) {
            $stmt = $conn->prepare("SELECT slug FROM $table WHERE slug = ? AND status = 'active' LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('s', $clean_uri);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $active_slug = $row['slug'];
                }
                $stmt->close();
            }
        }
    }
    
    // If we found an active slug, redirect directly to it
    if (!empty($active_slug)) {
        $redirect_url = '/' . $active_slug;
        header("Location: $redirect_url", true, 301);
        exit;
    } else {
        // If no active slug found, show 404 page
        http_response_code(404);
        include '404.php';
        exit;
    }
}

// Remove empty segments at the end (e.g., from trailing slash)
while (end($segments) === '') {
    array_pop($segments);
}

// Default values
$lang_code = $default_lang['code'];
$lang_id = $default_lang_id;
$slug = '';

// FIRST: Check for inactive slug redirects BEFORE checking for active custom page slugs
if (count($segments) > 0) {
    $potential_slug = implode('/', $segments); // Join all segments as one slug
    
    // Check if this is an inactive slug that needs redirecting
    $inactive_redirect_found = false;
    
    // Check all page types for inactive slugs first
    $page_types = [
        'custom' => [
            'slug_table' => 'custom_page_slugs', 
            'page_id_field' => 'custom_page_id',
            'page_table' => 'custom_pages'
        ],
        'how' => [
            'slug_table' => 'how_page_slugs',
            'page_id_field' => 'how_page_id',
            'page_table' => 'how_pages'
        ],
        'mp3' => [
            'slug_table' => 'mp3_page_slugs',
            'page_id_field' => 'mp3_page_id',
            'page_table' => 'mp3_pages'
        ],
        'stories' => [
            'slug_table' => 'stories_page_slugs',
            'page_id_field' => 'stories_page_id',
            'page_table' => 'stories_pages'
        ]
    ];

    foreach ($page_types as $type => $config) {
        // Check if this slug exists as an inactive slug for ANY language
        $stmt = $conn->prepare("SELECT s.{$config['page_id_field']}, p.language_id, l.code FROM {$config['slug_table']} s 
                                JOIN {$config['page_table']} p ON s.{$config['page_id_field']} = p.id 
                                JOIN languages l ON p.language_id = l.id 
                                WHERE s.slug=? AND s.status='inactive' LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
            continue;
        }
        
        $stmt->bind_param('s', $potential_slug);
        $stmt->execute();
        $stmt->bind_result($page_id, $redirect_lang_id, $redirect_lang_code);
        
        if ($stmt->fetch()) {
            $stmt->close();
            
            // Found inactive slug, now find the active slug for this page
            $stmt2 = $conn->prepare("SELECT slug FROM {$config['slug_table']} WHERE {$config['page_id_field']}=? AND status='active' LIMIT 1");
            if ($stmt2 === false) {
                error_log("Prepare failed: " . $conn->error);
                continue;
            }
            
            $stmt2->bind_param('i', $page_id);
            $stmt2->execute();
            $stmt2->bind_result($active_slug);
            
            if ($stmt2->fetch()) {
                $stmt2->close();
                
                // Always redirect to the slug without language code for inactive slug redirects
                $redirect_url = '/' . $active_slug;
                
                // 301 redirect to active slug
                header("Location: $redirect_url", true, 301);
                exit;
            }
            $stmt2->close();
            $inactive_redirect_found = true;
            break; // Found and redirected, no need to check other types
        } else {
            $stmt->close();
        }
    }
    
    // Only if no inactive redirect was found, check for active custom page slugs
    if (!$inactive_redirect_found) {
        // First, check if the current URL starts with any active slug (handles extra content after active slugs)
        $page_tables_check = [
            'how_page_slugs' => ['table' => 'how_page_slugs', 'join' => 'how_pages', 'join_field' => 'how_page_id'],
            'mp3_page_slugs' => ['table' => 'mp3_page_slugs', 'join' => 'mp3_pages', 'join_field' => 'mp3_page_id'],
            'stories_page_slugs' => ['table' => 'stories_page_slugs', 'join' => 'stories_pages', 'join_field' => 'stories_page_id']
        ];

        $all_active_slugs_check = [];

        foreach ($page_tables_check as $config) {
            $stmt = $conn->prepare("SELECT ps.slug FROM {$config['table']} ps 
                                    JOIN {$config['join']} p ON ps.{$config['join_field']} = p.id 
                                    WHERE ps.status = 'active'");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $all_active_slugs_check[] = $row['slug'];
                }
                $stmt->close();
            }
        }

        // Sort by length (longest first) to match the most specific slug
        usort($all_active_slugs_check, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        // Check if current URL starts with any active slug
        foreach ($all_active_slugs_check as $active_slug) {
            if (strpos($potential_slug, $active_slug) === 0) {
                // Found an active slug that the current URL starts with
                // If there's extra content after the slug, show 404 instead of redirecting
                if (strlen($potential_slug) > strlen($active_slug)) {
                    http_response_code(404);
                    include '404.php';
                    exit;
                }
            }
        }
        // Check if this slug exists in how_page_slugs table
        $stmt = $conn->prepare("SELECT hp.id, hp.language_id, l.code FROM how_pages hp 
                                JOIN how_page_slugs hps ON hp.id = hps.how_page_id 
                                JOIN languages l ON hp.language_id = l.id 
                                WHERE hps.slug=? AND hps.status='active' LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param('s', $potential_slug);
            $stmt->execute();
            $stmt->bind_result($how_page_id, $how_lang_id, $how_lang_code);
            if ($stmt->fetch()) {
                $stmt->close();
                // Found a custom how page slug, set language and include how-download.php
                $_GET['lang'] = $how_lang_code;
                $_GET['slug'] = $potential_slug;
                include 'how-download.php';
                exit;
            }
            $stmt->close();
        }
        
        // Check if this slug exists in mp3_page_slugs table
        $stmt = $conn->prepare("SELECT mp.id, mp.language_id, l.code FROM mp3_pages mp 
                                JOIN mp3_page_slugs mps ON mp.id = mps.mp3_page_id 
                                JOIN languages l ON mp.language_id = l.id 
                                WHERE mps.slug=? AND mps.status='active' LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param('s', $potential_slug);
            $stmt->execute();
            $stmt->bind_result($mp3_page_id, $mp3_lang_id, $mp3_lang_code);
            if ($stmt->fetch()) {
                $stmt->close();
                // Found a custom mp3 page slug, set language and include download-tiktok-mp3.php
                $_GET['lang'] = $mp3_lang_code;
                $_GET['slug'] = $potential_slug;
                include 'download-tiktok-mp3.php';
                exit;
            }
            $stmt->close();
        }
        
        // Check if this slug exists in stories_page_slugs table
        $stmt = $conn->prepare("SELECT sp.id, sp.language_id, l.code FROM stories_pages sp 
                                JOIN stories_page_slugs sps ON sp.id = sps.stories_page_id 
                                JOIN languages l ON sp.language_id = l.id 
                                WHERE sps.slug=? AND sps.status='active' LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param('s', $potential_slug);
            $stmt->execute();
            $stmt->bind_result($stories_page_id, $stories_lang_id, $stories_lang_code);
            if ($stmt->fetch()) {
                $stmt->close();
                // Found a custom stories page slug, set language and include download-tiktok-story.php
                $_GET['lang'] = $stories_lang_code;
                $_GET['slug'] = $potential_slug;
                include 'download-tiktok-story.php';
                exit;
            }
            $stmt->close();
        }
        
        // Check if this slug exists in custom_page_slugs table
        $stmt = $conn->prepare("SELECT cp.id, cp.language_id, l.code FROM custom_pages cp 
                                JOIN custom_page_slugs cps ON cp.id = cps.custom_page_id 
                                JOIN languages l ON cp.language_id = l.id 
                                WHERE cps.slug=? AND cps.status='active' LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param('s', $potential_slug);
            $stmt->execute();
            $stmt->bind_result($custom_page_id, $custom_lang_id, $custom_lang_code);
            if ($stmt->fetch()) {
                $stmt->close();
                // Found a custom page slug, set language and include custom-page.php
                $_GET['lang'] = $custom_lang_code;
                $_GET['slug'] = $potential_slug;
                include 'custom-page.php';
                exit;
            }
            $stmt->close();
        }
        
        // Check if this slug exists in language_pages table (for pages.php)
        $stmt = $conn->prepare("SELECT lp.id, lp.language_id, l.code FROM language_pages lp 
                                JOIN languages l ON lp.language_id = l.id 
                                WHERE lp.slug=? LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param('s', $potential_slug);
            $stmt->execute();
            $stmt->bind_result($lang_page_id, $lang_page_lang_id, $lang_page_lang_code);
            if ($stmt->fetch()) {
                $stmt->close();
                // Found a language page slug, set language and include page.php
                $_GET['lang'] = $lang_page_lang_code;
                $_GET['slug'] = $potential_slug;
                include 'page.php';
                exit;
            }
            $stmt->close();
        }
    }
}

// If first segment is a language code (default or non-default)
if (count($segments) > 0 && isset($languages[$segments[0]])) {
    $lang_code = $segments[0];
    $lang_id = $languages[$lang_code]['id'];
    $is_default_lang = $languages[$segments[0]]['is_default'];
    $slug = isset($segments[1]) ? $segments[1] : '';
    
    // If there's a slug after the language code, check if it's for index.php
    if (!empty($slug)) {
        // Check if this slug is for index.php (home page)
        $is_index_slug = false;
        $stmt = $conn->prepare("SELECT id FROM languages_home WHERE language_id=? AND slug=? LIMIT 1");
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt->bind_param('is', $lang_id, $slug);
            $stmt->execute();
            $stmt->store_result();
            $is_index_slug = ($stmt->num_rows > 0);
            $stmt->close();
        }
        
        // If it's an index.php slug, redirect to active slug without language code
        if ($is_index_slug) {
            $active_slug = '';
            $stmt = $conn->prepare("SELECT slug FROM languages_home WHERE language_id=? LIMIT 1");
            if ($stmt === false) {
                error_log("Prepare failed: " . $conn->error);
            } else {
                $stmt->bind_param('i', $lang_id);
                $stmt->execute();
                $stmt->bind_result($active_slug);
                $stmt->fetch();
                $stmt->close();
            }
            
            if (!empty($active_slug)) {
                // Redirect to active slug without language code (for index.php only)
                header("Location: /" . $active_slug, true, 301);
                exit;
            }
        }
        
        // If it's the default language, redirect to slug without language code for any page
        if ($is_default_lang) {
            header("Location: /" . $slug, true, 301);
            exit;
        }
        
        // For other pages with non-default language, continue with normal processing (language code will be preserved)
    }
} else {
    $slug = isset($segments[0]) ? $segments[0] : '';
}

// Handle /lang (no slug) for non-default language
if (
    count($segments) == 1 &&
    isset($languages[$segments[0]]) &&
    !$languages[$segments[0]]['is_default']
) {
    $lang_code = $segments[0];
    $lang_id = $languages[$lang_code]['id'];
    // Check home slug for this language
    $stmt = $conn->prepare("SELECT slug FROM languages_home WHERE language_id=? LIMIT 1");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        $home_slug = '';
    } else {
        $stmt->bind_param('i', $lang_id);
        $stmt->execute();
        $stmt->bind_result($home_slug);
        $stmt->fetch();
        $stmt->close();
    }

    if (!empty($home_slug)) {
        // Always redirect to the current home slug if it exists
        header('Location: /' . $home_slug, true, 301);
        //  . $lang_code . '/'
        exit;
    } else {
        // Show the home page for this language (no slug)
        $_GET['lang'] = $lang_code;
        $_GET['slug'] = '';
        include 'index.php';
        exit;
    }
}

// Handle root (/) for default language
if ($uri === '' || $uri === false) {
    // Check home slug for default language
    $stmt = $conn->prepare("SELECT slug FROM languages_home WHERE language_id=? LIMIT 1");
    $stmt->bind_param('i', $default_lang_id);
    $stmt->execute();
    $stmt->bind_result($home_slug);
    $stmt->fetch();
    $stmt->close();

    if (!empty($home_slug)) {
        // Always redirect to the current home slug if it exists
        header('Location: /' . $home_slug, true, 301);
        exit;
    } else {
        // Show the home page for the default language (no slug)
        $_GET['lang'] = $default_lang['code'];
        $_GET['slug'] = '';
        include 'index.php';
        exit;
    }
}

// Inactive slug redirects are now handled at the beginning of the routing process

// Try to match special pages first (stories, mp3, how, terms, contact, privacy)
$special_pages = [
    'stories' => 'download-tiktok-story.php',
    'mp3' => 'download-tiktok-mp3.php',
    'how' => 'how-download.php',
    'terms' => 'page.php',
    'privacy' => 'page.php',
    'contact' => 'contact.php',
];

// Then check for exact special page matches
if (isset($special_pages[$slug])) {
    $_GET['lang'] = $lang_code;
    $_GET['slug'] = $slug;
    include $special_pages[$slug];
    exit;
}

// Function removed - inactive slug redirects are now handled at the beginning of routing

// Numeric slug: custom-page.php
if (is_numeric($slug)) {
    $_GET['lang'] = $lang_code;
    $_GET['slug'] = $slug;
    include 'custom-page.php';
    exit;
}

// After determining $lang_code, $lang_id, $slug
// Try to find the page_name in the default language if the slug is not found for the selected language
function find_page_name_by_slug($conn, $lang_id, $slug) {
    $stmt = $conn->prepare("SELECT page_name FROM language_pages WHERE language_id=? AND slug=? LIMIT 1");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param('is', $lang_id, $slug);
    $stmt->execute();
    $stmt->bind_result($page_name);
    if ($stmt->fetch()) {
        $stmt->close();
        return $page_name;
    }
    $stmt->close();
    return false;
}
function find_slug_by_page_name($conn, $lang_id, $page_name) {
    $stmt = $conn->prepare("SELECT slug FROM language_pages WHERE language_id=? AND page_name=? LIMIT 1");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param('is', $lang_id, $page_name);
    $stmt->execute();
    $stmt->bind_result($slug);
    if ($stmt->fetch()) {
        $stmt->close();
        return $slug;
    }
    $stmt->close();
    return false;
}

// Check if the slug exists for the selected language
$stmt = $conn->prepare("SELECT id FROM language_pages WHERE language_id=? AND slug=? LIMIT 1");
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
} else {
    $stmt->bind_param('is', $lang_id, $slug);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0 && !$default_lang['is_default']) {
        // Slug not found for this language, try to find the page_name in default language
        $page_name = find_page_name_by_slug($conn, $default_lang_id, $slug);
        if ($page_name) {
            // Find the slug for this page_name in the selected language
            $translated_slug = find_slug_by_page_name($conn, $lang_id, $page_name);
            if ($translated_slug) {
                // Redirect to the correct slug for this language
                $redirect_url = '/' . $lang_code . '/' . $translated_slug;
                header('Location: ' . $redirect_url, true, 301);
                exit;
            }
        }
    }
    $stmt->close();
}

// Check custom_pages table for this language and slug
$stmt = $conn->prepare("SELECT id FROM custom_pages WHERE language_id=? AND slug=? LIMIT 1");
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
} else {
    $stmt->bind_param('is', $lang_id, $slug);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_GET['lang'] = $lang_code;
        $_GET['slug'] = $slug;
        include 'custom-page.php';
        exit;
    }
    $stmt->close();
}

// Check language_pages table for this language and slug
$stmt = $conn->prepare("SELECT id FROM language_pages WHERE language_id=? AND slug=? LIMIT 1");
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
} else {
    $stmt->bind_param('is', $lang_id, $slug);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_GET['lang'] = $lang_code;
        $_GET['slug'] = $slug;
        include 'page.php';
        exit;
    }
    $stmt->close();
}

// index.php
$s = '';
$u = '';
if(isset($segments[1])){
    $s = $segments[1];
}else if($segments[0]){
    $s = $segments[0];
}

// First check if $s is a current valid slug in languages_home
$current_slug_check = $conn->query("SELECT language_id FROM languages_home WHERE slug = '" . mysqli_real_escape_string($conn, $s) . "' LIMIT 1");
if ($current_slug_check && $current_slug_check->num_rows > 0) {
    // This is a current valid slug, include index.php
    include 'index.php';
    exit;
}

// Only if it's NOT a current slug, check if it's an old slug that needs redirecting
$stmt = $conn->prepare("SELECT language_id FROM languages_home_redirects WHERE old_slug=? LIMIT 1");
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
} else {
    $stmt->bind_param('s', $s);
    $stmt->execute();
    $stmt->bind_result($redirect_lang_id);
    if ($stmt->fetch()) {
        $stmt->close();
        // Get the current home slug for the language that has this old slug
        $stmt2 = $conn->prepare("SELECT slug FROM languages_home WHERE language_id=? LIMIT 1");
        if ($stmt2 === false) {
            error_log("Prepare failed: " . $conn->error);
        } else {
            $stmt2->bind_param('i', $redirect_lang_id);
            $stmt2->execute();
            $stmt2->bind_result($current_home_slug);
            $stmt2->fetch();
            $stmt2->close();
            
            // Avoid infinite redirects - only redirect if the target is different
            if (!empty($current_home_slug) && $current_home_slug !== $s) {
                header("Location: /$current_home_slug", true, 301);
                exit;
            } else {
                // If target is the same as source, treat as 404
                http_response_code(404);
                include '404.php';
                exit;
            }
        }
    }
    $stmt->close();
}

// If we reach here, the slug is neither current nor an old redirect slug
// Before checking page types, check if the current URL starts with any active slug
// This handles cases like /fr2/download-tiktok-mp3/extra-content

// Check all page tables for active slugs that the current URL starts with
// Get all active slugs and check if current URL starts with any of them
$page_tables = [
    'how_page_slugs' => ['table' => 'how_page_slugs', 'join' => 'how_pages', 'join_field' => 'how_page_id'],
    'mp3_page_slugs' => ['table' => 'mp3_page_slugs', 'join' => 'mp3_pages', 'join_field' => 'mp3_page_id'],
    'stories_page_slugs' => ['table' => 'stories_page_slugs', 'join' => 'stories_pages', 'join_field' => 'stories_page_id']
];

$all_active_slugs = [];

foreach ($page_tables as $config) {
    $stmt = $conn->prepare("SELECT ps.slug FROM {$config['table']} ps 
                            JOIN {$config['join']} p ON ps.{$config['join_field']} = p.id 
                            WHERE ps.status = 'active'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $all_active_slugs[] = $row['slug'];
        }
        $stmt->close();
    }
}

// Sort by length (longest first) to match the most specific slug
usort($all_active_slugs, function($a, $b) {
    return strlen($b) - strlen($a);
});

// Check if current URL starts with any active slug
foreach ($all_active_slugs as $active_slug) {
    if (strpos($s, $active_slug) === 0) {
        // Found an active slug that the current URL starts with
        // If there's extra content after the slug, show 404 instead of redirecting
        if (strlen($s) > strlen($active_slug)) {
            http_response_code(404);
            include '404.php';
            exit;
        }
    }
}

// If no active slug match found, try to determine what type of page this should be based on the URL structure
$page_type = '';
$base_slug = '';

// Check if this looks like an MP3 page URL
if (strpos($s, 'download-tiktok-mp3') !== false || strpos($s, 'mp3') !== false) {
    $page_type = 'mp3';
    $base_slug = 'download-tiktok-mp3';
}
// Check if this looks like a Stories page URL
elseif (strpos($s, 'download-tiktok-story') !== false || strpos($s, 'stories') !== false) {
    $page_type = 'stories';
    $base_slug = 'download-tiktok-story';
}
// Check if this looks like a How page URL
elseif (strpos($s, 'how') !== false) {
    $page_type = 'how';
    $base_slug = 'how';
}

if (!empty($page_type) && !empty($base_slug)) {
    // Check if there's an active slug for this page type in current language
    $active_page_slug = '';
    
    // Find the active slug for this page type in the current language
    switch ($page_type) {
        case 'mp3':
            $stmt = $conn->prepare("SELECT mps.slug FROM mp3_page_slugs mps 
                                    JOIN mp3_pages mp ON mps.mp3_page_id = mp.id 
                                    WHERE mp.language_id = ? AND mps.status = 'active' 
                                    LIMIT 1");
            break;
        case 'stories':
            $stmt = $conn->prepare("SELECT sps.slug FROM stories_page_slugs sps 
                                    JOIN stories_pages sp ON sps.stories_page_id = sp.id 
                                    WHERE sp.language_id = ? AND sps.status = 'active' 
                                    LIMIT 1");
            break;
        case 'how':
            $stmt = $conn->prepare("SELECT hps.slug FROM how_page_slugs hps 
                                    JOIN how_pages hp ON hps.how_page_id = hp.id 
                                    WHERE hp.language_id = ? AND hps.status = 'active' 
                                    LIMIT 1");
            break;
        default:
            $stmt = null;
    }
    
    if ($stmt) {
        $stmt->bind_param('i', $lang_id);
        $stmt->execute();
        $stmt->bind_result($active_page_slug);
        $stmt->fetch();
        $stmt->close();
    }
    
    // If no active slug found for this page type, show 404
    if (empty($active_page_slug)) {
        http_response_code(404);
        include '404.php';
        exit;
    }
}

// If we can't determine page type or no active slug found, show 404 page
http_response_code(404);
include '404.php';
exit;

?> 