<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

// Get language id and fetch language info
$lang_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$lang = null;
$lang_code = '';
if ($lang_id) {
    $res = $conn->query("SELECT * FROM languages WHERE id=$lang_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $lang = $res->fetch_assoc();
        $lang_code = $lang['code'];
    }
}
if (!$lang) {
    die('Language not found.');
}

$page_name = 'Create New';
$slug = 'create-new';
$success = '';
$error = '';

// Try to find the create new page for this language
$page = null;
$sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%create%' OR LOWER(slug) LIKE '%create%') LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $page = $res->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_name = trim($_POST['page_name'] ?? 'Create New');
    $slug = trim($_POST['slug'] ?? 'create-new');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_header = trim($_POST['meta_header'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($page) {
        // Update by id
        $page_id = $page['id'];
        $sql = "UPDATE language_pages SET page_name='" . $conn->real_escape_string($page_name) .
            "', slug='" . $conn->real_escape_string($slug) .
            "', meta_title='" . $conn->real_escape_string($meta_title) .
            "', meta_description='" . $conn->real_escape_string($meta_description) .
            "', meta_header='" . $conn->real_escape_string($meta_header) .
            "', content='" . $conn->real_escape_string($content) .
            "' WHERE id=$page_id";
        if ($conn->query($sql)) {
            $success = 'Create New page updated.';
        } else {
            $error = 'Failed to update page.';
        }
    } else {
        // Insert only if not exists
        $sql = "INSERT INTO language_pages (language_id, page_name, slug, meta_title, meta_description, meta_header, content) VALUES ("
            . "$lang_id, '" . $conn->real_escape_string($page_name) . "', '" . $conn->real_escape_string($slug) . "', '" . $conn->real_escape_string($meta_title) . "', '"
            . $conn->real_escape_string($meta_description) . "', '"
            . $conn->real_escape_string($meta_header) . "', '"
            . $conn->real_escape_string($content) . "')";
        if ($conn->query($sql)) {
            $success = 'Create New page added.';
        } else {
            $error = 'Failed to add page.';
        }
    }
    // Always fetch the latest page after update/insert
    $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%create%' OR LOWER(slug) LIKE '%create%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
    }
}
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="page-section">
            <h4>Create New Page (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
            <div class="url-preview">URL: <b>/<?php echo htmlspecialchars($lang_code); ?>/<?php echo htmlspecialchars($page['slug'] ?? 'create-new'); ?></b></div>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Page Name</label>
                    <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name'] ?? 'Create New'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? 'create-new'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Title</label>
                    <input type="text" class="form-control" name="meta_title" value="<?php echo htmlspecialchars($page['meta_title'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Description</label>
                    <textarea class="form-control" name="meta_description" rows="2"><?php echo htmlspecialchars($page['meta_description'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Header (Meta Tags)</label>
                    <textarea class="form-control" name="meta_header" rows="2"><?php echo htmlspecialchars($page['meta_header'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Page Content</label>
                    <textarea class="form-control" id="summernote" name="content"><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('mainContent');
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
        // CKEditor
        $(document).ready(function() {
            CKEDITOR.replace('summernote', {
                height: 220,
                filebrowserUploadUrl: 'upload-clean.php',
                filebrowserUploadMethod: 'form'
            });
        });
    </script>
</body>
</html> 