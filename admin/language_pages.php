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
    ['label' => 'Stories-file', 'icon' => 'fa-home', 'link' => "stories-file.php?id=$lang_id", 'key' => 'stories_enabled'],
    ['label' => 'Stories Viewer', 'icon' => 'fa-home', 'link' => "how-file.php?id=$lang_id", 'key' => 'how_enabled'],
    ['label' => 'Copyright', 'icon' => 'fa-window-maximize', 'link' => "copyright.php?id=$lang_id", 'key' => 'copyright_enabled'],
    ['label' => 'Terms', 'icon' => 'fa-window-maximize', 'link' => "terms.php?id=$lang_id", 'key' => 'terms_enabled'],
    ['label' => 'Privacy', 'icon' => 'fa-window-maximize', 'link' => "privacy.php?id=$lang_id", 'key' => 'privacy_enabled'],
	// FAQ tile removed from language pages; visibility is controlled via site settings below.
];
array_unshift($pages,
    ['label' => 'YT1S Home', 'icon' => 'fa-house', 'link' => "yt_front_page.php?id=$lang_id&page=home", 'key' => null],
    ['label' => 'YT1S MP3', 'icon' => 'fa-music', 'link' => "yt_mp3.php?id=$lang_id", 'key' => null],
    ['label' => 'YT1S MP4', 'icon' => 'fa-video', 'link' => "yt_mp4.php?id=$lang_id", 'key' => null]
);

$pages[3]['label'] = 'Legacy Home';

$existing_pages = [];
$sql = "SELECT * FROM language_pages WHERE language_id=$lang_id";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $existing_pages[strtolower($row['slug'])] = $row;
        $existing_pages[strtolower($row['page_name'])] = $row;
    }
}

?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <h4 class="fw-bold mb-4"><?php echo $lang_name; ?> Pages</h4>
			<div class="card mb-3">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span>YT1s Page Editors</span>
				</div>
				<div class="card-body">
					<div class="d-flex gap-2 flex-wrap">
						<a class="btn btn-sm btn-outline-primary" href="yt_mp3.php?id=<?php echo $lang_id; ?>">Edit YouTube to MP3</a>
						<a class="btn btn-sm btn-outline-primary" href="yt_mp4.php?id=<?php echo $lang_id; ?>">Edit YouTube to MP4</a>
					</div>
					<p class="text-muted small mb-0 mt-2">These editors update the dynamic texts shown on the YT1s MP3/MP4 pages without changing the frontend files.</p>
				</div>
			</div>
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
                                <?php if (!empty($page['key'])): ?>
                                    <div class="form-check form-switch mt-2 text-center">
                                        <input class="form-check-input page-toggle" type="checkbox" id="toggle_<?php echo $page['key']; ?>" data-pagekey="<?php echo $page['key']; ?>" data-langid="<?php echo $lang_id; ?>" <?php echo (isset($lang[$page['key']]) && $lang[$page['key']]) ? 'checked' : ''; ?> >
                                        <label class="form-check-label" for="toggle_<?php echo $page['key']; ?>">
                                            <?php echo (isset($lang[$page['key']]) && $lang[$page['key']]) ? 'Enabled' : 'Disabled'; ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                <div class="alert alert-info mt-4">
                    Custom pages are disabled in this build. Existing entries remain read-only.
                </div>
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
