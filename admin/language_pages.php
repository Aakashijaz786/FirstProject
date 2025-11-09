<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

// Handle delete custom page (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_custom_page_id'])) {
    $delete_id = intval($_POST['delete_custom_page_id']);
    $conn->query("DELETE FROM custom_pages WHERE id=$delete_id");
    echo json_encode(['success' => true]);
    exit;
}

// Get language id and fetch language info
$lang_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$lang_name = 'English';
if ($lang_id) {
    $res = $conn->query("SELECT * FROM languages WHERE id=$lang_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $lang = $res->fetch_assoc();
        $lang_name = $lang['name'];
    }
}
if (!$lang_id) {
    die('Language not found.');
}
$pages = [
    ['label' => 'Home', 'icon' => 'fa-home', 'link' => "home.php?id=$lang_id", 'key' => 'home_enabled'],
    ['label' => 'Mp3-file', 'icon' => 'fa-home', 'link' => "mp3-file.php?id=$lang_id", 'key' => 'mp3_enabled'],
    ['label' => 'Stories-file', 'icon' => 'fa-home', 'link' => "stories-file.php?id=$lang_id", 'key' => 'stories_enabled'],
    ['label' => 'Stories-Viewer ', 'icon' => 'fa-home', 'link' => "how-file.php?id=$lang_id", 'key' => 'how_enabled'],
    ['label' => 'Copyright', 'icon' => 'fa-window-maximize', 'link' => "copyright.php?id=$lang_id", 'key' => 'copyright_enabled'],
    ['label' => 'Terms', 'icon' => 'fa-window-maximize', 'link' => "terms.php?id=$lang_id", 'key' => 'terms_enabled'],
    // ['label' => 'Contact', 'icon' => 'fa-window-maximize', 'link' => "contact.php?id=$lang_id", 'key' => 'contact_enabled'],
    ['label' => 'Privacy', 'icon' => 'fa-window-maximize', 'link' => "privacy.php?id=$lang_id", 'key' => 'privacy_enabled'],
    ['label' => 'Faqs', 'icon' => 'fa-window-maximize', 'link' =>  "faqs.php?id=$lang_id", 'key' => 'faqs_enabled'],
    ['label' => 'Create New', 'icon' => 'fa-file-circle-plus', 'link' => "create-new-file.php?id=$lang_id", 'key' => 'create_new_enabled'],
];
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <h4 class="fw-bold mb-4"><?php echo $lang_name; ?> Pages</h4>
             <?php if (isset($_GET['deleted']) && $_GET['deleted'] == '1'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> The page has been deleted successfully.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="page-cards-row">
                <?php foreach ($pages as $page): ?>
                    <?php
                        $slug = '';
                        if (preg_match('/([a-zA-Z0-9\-_]+)\.php/', $page['link'], $m)) {
                            $slug = strtolower($m[1]);
                        }
                        $exists = isset($existing_pages[$slug]) || isset($existing_pages[strtolower($page['label'])]);
                    ?>
                    <?php if (!$exists): ?>
                        <div class="page-card-wrapper" style="display:inline-block; margin:10px; vertical-align:top;">
                            <a href="<?php echo $page['link']; ?>" class="page-card">
                                <span class="page-icon"><i class="fa-solid <?php echo $page['icon']; ?>"></i></span>
                                <span class="page-label"><?php echo $page['label']; ?></span>
                                <div class="form-check form-switch mt-2 text-center">
                                    <input class="form-check-input page-toggle" type="checkbox" id="toggle_<?php echo $page['key']; ?>" data-pagekey="<?php echo $page['key']; ?>" data-langid="<?php echo $lang_id; ?>" <?php echo (isset($lang[$page['key']]) && $lang[$page['key']]) ? 'checked' : ''; ?> >
                                    <label class="form-check-label" for="toggle_<?php echo $page['key']; ?>">
                                        <?php echo (isset($lang[$page['key']]) && $lang[$page['key']]) ? 'Enabled' : 'Disabled'; ?>
                                    </label>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php
                // Build a map of existing pages by slug and page_name
                $existing_pages = [];
                $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id";
                $res = $conn->query($sql);
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        $existing_pages[strtolower($row['slug'])] = $row;
                        $existing_pages[strtolower($row['page_name'])] = $row;
                    }
                }
                // Build a set of static slugs and names from $pages
                $static_slugs = [];
                $static_names = [];
                foreach ($pages as $p) {
                    if (preg_match('/([a-zA-Z0-9\-_]+)\.php/', $p['link'], $m)) {
                        $static_slugs[] = strtolower($m[1]);
                    }
                    $static_names[] = strtolower($p['label']);
                }
                ?>
                <?php
                // Get custom pages from current language
                $custom_pages = [];
                $sql = "SELECT * FROM custom_pages WHERE language_id=$lang_id ORDER BY id DESC";
                $res = $conn->query($sql);
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        $custom_pages[$row['page_name']] = $row;
                    }
                }
                
                // Get English (language_id = 41) custom pages
                $english_custom_pages = [];
                $sql_english = "SELECT * FROM custom_pages WHERE language_id=41 ORDER BY id DESC";
                $res_english = $conn->query($sql_english);
                if ($res_english && $res_english->num_rows > 0) {
                    while ($row = $res_english->fetch_assoc()) {
                        $english_custom_pages[$row['page_name']] = $row;
                    }
                }
                
                // Merge: show English pages AND current language pages
                $all_custom_pages = [];
                
                // First, add all English pages (as templates if not in current language)
                foreach ($english_custom_pages as $page_name => $eng_page) {
                    if (isset($custom_pages[$page_name])) {
                        // Page exists in current language - use current language version
                        $all_custom_pages[$page_name] = $custom_pages[$page_name];
                        $all_custom_pages[$page_name]['exists_in_lang'] = true;
                        $all_custom_pages[$page_name]['is_english_template'] = false;
                    } else {
                        // Page only exists in English - show as template
                        $all_custom_pages[$page_name] = $eng_page;
                        $all_custom_pages[$page_name]['exists_in_lang'] = false;
                        $all_custom_pages[$page_name]['is_english_template'] = true;
                    }
                }
                
                // Then, add any current language pages that don't exist in English
                foreach ($custom_pages as $page_name => $current_page) {
                    if (!isset($english_custom_pages[$page_name])) {
                        // This page exists in current language but not in English
                        $all_custom_pages[$page_name] = $current_page;
                        $all_custom_pages[$page_name]['exists_in_lang'] = true;
                        $all_custom_pages[$page_name]['is_english_template'] = false;
                    }
                }
                ?>
                <?php if (!empty($all_custom_pages)): ?>
                    <?php foreach ($all_custom_pages as $cp): ?>
                        <?php if ($cp['exists_in_lang']): ?>
                            <!-- Page exists in current language -->
                            <div class="page-card-wrapper" id="custom-page-card-<?php echo $cp['id']; ?>" style="display:inline-block; margin:10px; vertical-align:top;">
                                <a href="edit-custom-page.php?id=<?php echo $cp['id']; ?>" class="page-card">
                                    <span class="page-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                                    <span class="page-label"><?php echo htmlspecialchars($cp['page_name']); ?></span>
                                    <div class="form-check form-switch mt-2 text-center">
                                        <input class="form-check-input" type="checkbox" checked disabled>
                                        <?php if ($cp['is_english_template']): ?>
                                            <label class="form-check-label">Custom</label>
                                        <?php else: ?>
                                            <label class="form-check-label"><?php echo $lang_name; ?> Only</label>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <button class="btn btn-danger btn-sm mt-2 w-100" onclick="deleteCustomPage(<?php echo $cp['id']; ?>)">Delete</button>
                            </div>
                        <?php else: ?>
                            <!-- Page only exists in English, show create option with English content -->
                            <div class="page-card-wrapper" style="display:inline-block; margin:10px; vertical-align:top;">
                                <a href="create-new-file.php?id=<?php echo $lang_id; ?>&template=<?php echo urlencode($cp['page_name']); ?>&english_content=1" class="page-card" style="opacity: 0.7; border: 2px dashed #dee2e6;">
                                    <span class="page-icon"><i class="fa-solid fa-file-circle-plus"></i></span>
                                    <span class="page-label"><?php echo htmlspecialchars($cp['page_name']); ?></span>
                                    <div class="form-check form-switch mt-2 text-center">
                                        <span class="badge bg-secondary">Not Created</span>
                                    </div>
                                    <div class="mt-2 text-center">
                                        <small class="text-muted">English content available</small>
                                    </div>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('mainContent');
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });

        document.querySelectorAll('.page-toggle').forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                var pageKey = this.getAttribute('data-pagekey');
                var langId = this.getAttribute('data-langid');
                var enabled = this.checked ? 1 : 0;
                var label = this.parentElement.querySelector('label[for="' + this.id + '"]');
                // Optimistically update label
                label.textContent = enabled ? 'Enabled' : 'Disabled';
                // Send AJAX request
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'toggle_page_status.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status !== 200) {
                        // Revert label and toggle if error
                        toggle.checked = !enabled;
                        label.textContent = !enabled ? 'Enabled' : 'Disabled';
                        alert('Failed to update status.');
                    }
                };
                xhr.send('lang_id=' + encodeURIComponent(langId) + '&page_key=' + encodeURIComponent(pageKey) + '&enabled=' + encodeURIComponent(enabled));
            });
        });

        function deleteCustomPage(id) {
            if (!confirm('Are you sure you want to delete this page?')) return;
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'delete_custom_page_id=' + encodeURIComponent(id)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('custom-page-card-' + id).remove();
                    showAlert('Page deleted successfully.', 'success');
                } else {
                    showAlert('Failed to delete page.', 'danger');
                }
            });
        }
        function showAlert(message, type) {
            let alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-' + type;
            alertDiv.textContent = message;
            document.querySelector('.main-content').prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 2000);
        }
    </script>
</body>
</html> 