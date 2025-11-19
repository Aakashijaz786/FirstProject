<?php
if (!isset($languages) || !is_array($languages) || empty($languages)) {
    $languages = [];
    $res = $conn->query("SELECT * FROM languages");
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $languages[] = $row;
        }
    }
}

if (!isset($current_lang) || !is_array($current_lang) || empty($current_lang)) {
    $lang_code = $_GET['lang'] ?? $_COOKIE['site_lang'] ?? '';
    if ($lang_code && $lang_code !== 'default') {
        $res = $conn->query("SELECT * FROM languages WHERE code='" . $conn->real_escape_string($lang_code) . "' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $current_lang = $res->fetch_assoc();
        }
    }
    if (empty($current_lang)) {
        $res = $conn->query("SELECT * FROM languages WHERE is_default=1 LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $current_lang = $res->fetch_assoc();
        } elseif (!empty($languages)) {
            $current_lang = $languages[0];
        } else {
            $current_lang = ['id' => 0, 'code' => 'en', 'name' => 'English'];
        }
    }
}

$active_lang = $current_lang['code'] ?? ($languages[0]['code'] ?? 'en');
$active_lang_name = $current_lang['name'] ?? ($languages[0]['name'] ?? 'English');

$lang_id = isset($current_lang['id']) ? (int)$current_lang['id'] : 0;
$home_href = '/';
if ($lang_id > 0) {
    $res = $conn->query("SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (!empty($row['slug'])) {
            $home_href = '/' . ltrim($row['slug'], '/');
        } elseif (empty($current_lang['is_default']) || !$current_lang['is_default']) {
            $home_href = '/' . ($current_lang['code'] ?? 'en');
        }
    } elseif (empty($current_lang['is_default']) || !$current_lang['is_default']) {
        $home_href = '/' . ($current_lang['code'] ?? 'en');
    }
}

if ($home_href === '') {
    $home_href = '/';
}

$label_home = trim($current_lang['tiktok_downloaders'] ?? '') ?: 'YouTube Downloader';
$label_mp3 = trim($current_lang['how_to_save'] ?? '') ?: 'YouTube to MP3';
$label_mp4 = trim($current_lang['stories'] ?? '') ?: 'YouTube to MP4';

$nav_items = [
    ['key' => 'home', 'label' => $label_home, 'href' => $home_href],
    ['key' => 'search', 'label' => $label_mp3, 'href' => '/search.php'],
    ['key' => 'download', 'label' => $label_mp4, 'href' => '/download.php'],
];

$format_lang_image = static function (?string $path): string {
    if (empty($path)) {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return '/' . ltrim($path, '/');
};

$active_lang_image = $format_lang_image($current_lang['image'] ?? '');
$active_lang_code = strtoupper($active_lang ?? 'EN');

if (!isset($yt_active_page)) {
    $slug_lookup = $current_slug ?? trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    if ($slug_lookup === 'search') {
        $yt_active_page = 'search';
    } elseif ($slug_lookup === 'download') {
        $yt_active_page = 'download';
    } else {
        $yt_active_page = 'home';
    }
}

$logo_path = '/assets/images/logo.svg';
$logo_res = $conn->query("SELECT logo_dark FROM logo_and_favicon WHERE id=1 LIMIT 1");
if ($logo_res && $logo_res->num_rows > 0) {
    $logo_row = $logo_res->fetch_assoc();
    if (!empty($logo_row['logo_dark'])) {
        $logo_path = '/' . ltrim($logo_row['logo_dark'], '/');
    }
}
?>
<div class="index-module--mainHeader--f8726">
    <a class="index-module--logo--686d8" href="/" aria-current="page">
        <div class="index-module--logoWrap--caa21">
            <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="<?php echo htmlspecialchars($site_title ?? 'Logo'); ?>" width="32" height="32">
        </div>
        <h1 class="index-module--LinkTitle--4d382"><?php echo htmlspecialchars($site_title ?? 'YT1S'); ?></h1>
    </a>
</div>
<div class="index-module--navigation--4eb66">
    <?php foreach ($nav_items as $item): ?>
        <div class="index-module--menuList--a9873">
            <a class="index-module--menuLink--13234 <?php echo ($item['key'] === $yt_active_page) ? 'index-module--activeLink--4db98' : ''; ?>"
               href="<?php echo htmlspecialchars($item['href']); ?>">
                <?php echo htmlspecialchars($item['label']); ?>
            </a>
        </div>
    <?php endforeach; ?>
    <div class="index-module--menuList--a9873" style="position:relative;">
        <button class="index-module--menuLink--13234 index-module--language--3dda5" type="button" data-language-toggle>
            <?php if ($active_lang_image): ?>
                <img src="<?php echo htmlspecialchars($active_lang_image); ?>" alt="<?php echo htmlspecialchars($active_lang_name); ?>" width="18" height="18" style="border-radius:50%;margin-right:6px;object-fit:cover;">
            <?php endif; ?>
            <?php echo htmlspecialchars($active_lang_name); ?> (<?php echo htmlspecialchars($active_lang_code); ?>)
        </button>
        <div class="index-module--languageDropdown--c120a" hidden>
            <?php foreach ($languages as $language): ?>
                <?php $lang_image = $format_lang_image($language['image'] ?? ''); ?>
                <button type="button" data-lang="<?php echo htmlspecialchars($language['code']); ?>">
                    <?php if ($lang_image): ?>
                        <img src="<?php echo htmlspecialchars($lang_image); ?>" alt="<?php echo htmlspecialchars($language['name']); ?>" width="18" height="18" style="border-radius:50%;margin-right:6px;object-fit:cover;">
                    <?php endif; ?>
                    <?php echo htmlspecialchars($language['name']); ?> (<?php echo htmlspecialchars(strtoupper($language['code'] ?? '')); ?>)
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
