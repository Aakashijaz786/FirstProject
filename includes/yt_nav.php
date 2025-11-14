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

$active_lang = $current_lang['code'] ?? ($languages[0]['code'] ?? 'en');
$active_lang_name = $current_lang['name'] ?? ($languages[0]['name'] ?? 'English');

$nav_items = [
    ['key' => 'home', 'label' => 'Youtube Downloader', 'href' => '/'],
    ['key' => 'search', 'label' => 'Youtube to MP3', 'href' => '/search.php'],
    ['key' => 'download', 'label' => 'Youtube to MP4', 'href' => '/download.php']
];

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
               href="<?php echo $item['href']; ?>">
                <?php echo $item['label']; ?>
            </a>
        </div>
    <?php endforeach; ?>
    <div class="index-module--menuList--a9873" style="position:relative;">
        <button class="index-module--menuLink--13234 index-module--language--3dda5" type="button" data-language-toggle>
            <?php echo htmlspecialchars($active_lang_name); ?>
        </button>
        <div class="index-module--languageDropdown--c120a" hidden>
            <?php foreach ($languages as $language): ?>
                <button type="button" data-lang="<?php echo htmlspecialchars($language['code']); ?>">
                    <?php echo htmlspecialchars($language['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>
