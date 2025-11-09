<?php
require_once __DIR__ . '/config.php';

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
                }
            }
        }
    }
}

// Get copyright content for this language
   $copyright_content = '';
    $copyright_enabled = false;
    $res = $conn->query("SELECT content FROM language_pages WHERE language_id=$lang_id AND page_name='Copyright' LIMIT 1");
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