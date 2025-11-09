<?php

require_once __DIR__ . '/config.php';

$logo_dark = '/assets/images/AdkX0jQgyEFk.svg'; // fallback

$sql = "SELECT logo_dark FROM logo_and_favicon WHERE id=1 LIMIT 1";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {

    $row = $res->fetch_assoc();

    if (!empty($row['logo_dark'])) {

        $logo_dark = '/' . ltrim($row['logo_dark'], '/');

    }

   

}

?>

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



// Define header pages with their special handling

$header_pages = [

    [

        'label' => $current_lang['stories'] ?? 'Stories', 

        'key' => 'stories', 

        'enabled_field' => 'stories_enabled',

        'page_like' => 'stories',

        'is_special_page' => true, // This is a special page with its own file

        'file' => 'download-tiktok-story.php',

        'slug_table' => 'stories_page_slugs',

        'page_table' => 'stories_pages',

        'page_id_field' => 'stories_page_id',

    ],

    [

        'label' => $current_lang['download_mp3'] ?? 'Download TikTok MP3', 

        'key' => 'mp3', 

        'enabled_field' => 'mp3_enabled',

        'page_like' => 'mp3',

        'is_special_page' => true, // This is a special page with its own file

        'file' => 'download-tiktok-mp3.php',

        'slug_table' => 'mp3_page_slugs',

        'page_table' => 'mp3_pages',

        'page_id_field' => 'mp3_page_id',

    ]

];



// Get enabled status for each page from languages table

$lang_row = $current_lang;

$enabled_header_links = [];

foreach ($header_pages as $page) {

    $enabled = isset($lang_row[$page['enabled_field']]) ? $lang_row[$page['enabled_field']] : 0;

    echo '<!-- ' . $page['label'] . ' enabled: ' . $enabled . ' -->';

    if ($enabled) {

        $slug = '';

        // Try to get the active slug for this language

        if (isset($page['slug_table']) && isset($page['page_table']) && isset($page['page_id_field'])) {

            // Get the page id for this language

            $sql_page = "SELECT id FROM {$page['page_table']} WHERE language_id=$lang_id LIMIT 1";

            $res_page = $conn->query($sql_page);

            if ($res_page && $res_page->num_rows > 0) {

                $row_page = $res_page->fetch_assoc();

                $page_id = $row_page['id'];

                // Get the active slug

                $sql_slug = "SELECT slug FROM {$page['slug_table']} WHERE {$page['page_id_field']}=$page_id AND status='active' LIMIT 1";

                $res_slug = $conn->query($sql_slug);

                if ($res_slug && $res_slug->num_rows > 0) {

                    $row_slug = $res_slug->fetch_assoc();

                    $slug = $row_slug['slug'];

                }

            }

        }

        // Fallback to key if no slug found

        if (!$slug) {

            $slug = $page['key'];

        }

        // Build URL - for other pages, use language code + slug format (except default language)

        if ($current_lang['is_default']) {

            $url = '/' . $slug;

        } else {

            $url = '/' . $slug; // Use full slug without language code prefix

        }

        // Output only the link with the page label

        echo ' ';

    }

}

// Fetch other pages for this language (not in main menu)

$main_slugs = [];

foreach ($header_pages as $page) {

    $main_slugs[] = strtolower($page['key']);

}

$other_pages = [];

$sql = "SELECT slug, page_name FROM custom_pages WHERE language_id=$lang_id ORDER BY id DESC";

$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {

    while ($row = $res->fetch_assoc()) {

        $slug_lc = strtolower($row['slug']);

        $name_lc = strtolower($row['page_name']);

        if (

            !in_array($slug_lc, ['mp3', 'stories', 'how', 'privacy', 'contact', 'home', 'faqs', 'terms', 'copyright']) &&

            !in_array($name_lc, ['mp3-file', 'stories-file', 'how-file', 'privacy', 'contact', 'home', 'faqs', 'terms', 'copyright']) &&

            !in_array($slug_lc, $main_slugs)

        ) {

            $other_pages[] = $row;

        }

    }

}

// Build home URL - get the home slug for current language

// Build home URL - get the home slug for current language

$home_url = '/';

$res = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1");

if ($res && $res->num_rows > 0) {

    $row = $res->fetch_assoc();

    if (!empty($row['slug'])) {

        // Slug exists - use slug without language code

        $home_url = '/' . $row['slug'];

    } else {

        // No slug exists

        if ($current_lang['is_default']) {

            // Default language - no language code needed

            $home_url = '/';

        } else {

            // Non-default language - use language code

            $home_url = '/' . $current_lang['code'];

        }

    }

} else {

    // No record found in languages_home

    if (!$current_lang['is_default']) {

        // Non-default language - use language code

        $home_url = '/' . $current_lang['code'];

    }

}

?>

<header class="text__container">

        <div class="home-menu menu menu-1">

            <div class="menu-w1">

               <a class="menu-heading" href="<?php echo htmlspecialchars($home_url); ?>">

                    <div class="sss">

                        <img src="<?php echo htmlspecialchars($logo_dark); ?>" width="150px"  alt="logo">

                    </div>

                </a>

                <div id="menu" class="menu menu-1">

                <ul class="menu-list u-oh">

                 <?php

            foreach (

                $header_pages as $page) {

                $enabled = isset($lang_row[$page['enabled_field']]) ? $lang_row[$page['enabled_field']] : 0;

                if ($enabled) {

                    $slug = '';

                    if (isset($page['slug_table']) && isset($page['page_table']) && isset($page['page_id_field'])) {

                        $sql_page = "SELECT id FROM {$page['page_table']} WHERE language_id=$lang_id LIMIT 1";

                        $res_page = $conn->query($sql_page);

                        if ($res_page && $res_page->num_rows > 0) {

                            $row_page = $res_page->fetch_assoc();

                            $page_id = $row_page['id'];

                            $sql_slug = "SELECT slug FROM {$page['slug_table']} WHERE {$page['page_id_field']}=$page_id AND status='active' LIMIT 1";

                            $res_slug = $conn->query($sql_slug);

                            if ($res_slug && $res_slug->num_rows > 0) {

                                $row_slug = $res_slug->fetch_assoc();

                                $slug = $row_slug['slug'];

                            }

                        }

                    }

                    if (!$slug) {

                        $slug = $page['key'];

                    }

                    // Build URL - for other pages, use language code + slug format (except default language)

                    if ($current_lang['is_default']) {

                        $url = '/' . $slug;

                    } else {

                        $url = '/' . $slug; // Use full slug without language code prefix

                    }

                    echo '<li class="menu-item">

                    <a href="' . htmlspecialchars($url) . '" class="menu-link">' . htmlspecialchars($page['label']) . '</a>

                    </li>';

                }

            }

            ?>

                    <?php if (!empty($other_pages)): ?>

                    <li class="dropdown">

                        <a href="#" class="menu-link dropdown-toggle"><?php echo htmlspecialchars($current_lang['tiktok_downloaders'] ?? 'TikTok Downloaders'); ?></a>

                        <div class="dropdown-content">

                            <?php foreach ($other_pages as $op): ?>

                            <?php if ($current_lang['is_default']): ?><a href="/<?php echo htmlspecialchars($op['slug']); ?>"><?php echo htmlspecialchars($op['page_name']); ?></a>

                            <?php else: ?><a href="/<?php echo htmlspecialchars($op['slug']); ?>"><?php echo htmlspecialchars($op['page_name']); ?></a>

                            <?php endif; ?><hr>

                            <?php endforeach; ?></div>

                    </li>

                    <?php endif; ?></ul>

                </div>

                <nav id="menu-t-ww" aria-label="Toggle Menu">

                    <div id="menuToggle">

                        <input type="checkbox" id="menu_toggler" aria-label="Toggle navigation menu">

                        <label for="menu_toggler"></label>

                        <span class="bar"></span>

                        <span class="bar"></span>

                        <span class="bar"></span>

                    </div>

                </nav>

            </div>

        </div>

    </header>

    