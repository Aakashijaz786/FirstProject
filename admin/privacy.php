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

$page_name = 'Privacy';
$slug = 'privacy';
$success = '';
$error = '';

// Try to find the privacy page for this language
$page = null;
$sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%privacy%' OR LOWER(slug) LIKE '%privacy%') LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $page = $res->fetch_assoc();
}

// If no page exists, fetch English (ID 41) content as default
$english_page = null;
if (!$page && $lang_id != 41) {
    $sql = "SELECT * FROM language_pages WHERE language_id=41 AND (LOWER(page_name) LIKE '%privacy%' OR LOWER(slug) LIKE '%privacy%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $english_page = $res->fetch_assoc();
    }
}

// Handle translate request - DO NOT SAVE, just prepare translated content
$translated_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['translate_content'])) {
    if (!$english_page) {
        $sql = "SELECT * FROM language_pages WHERE language_id=41 AND (LOWER(page_name) LIKE '%privacy%' OR LOWER(slug) LIKE '%privacy%') LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $english_page = $res->fetch_assoc();
        }
    }
    
    if ($english_page) {
        $target_language = $lang['name'];
        error_log("Translating Privacy page from English to {$target_language}");
        
        $meta_title_result = translateText($english_page['meta_title'], $target_language);
        $meta_description_result = translateText($english_page['meta_description'], $target_language);
        $meta_header_result = translateText($english_page['meta_header'], $target_language);
        $content_result = translateText($english_page['content'], $target_language);
        
        $translated_data = [
            'meta_title' => $meta_title_result['success'] ? $meta_title_result['response'] : $english_page['meta_title'],
            'meta_description' => $meta_description_result['success'] ? $meta_description_result['response'] : $english_page['meta_description'],
            'meta_header' => $meta_header_result['success'] ? $meta_header_result['response'] : $english_page['meta_header'],
            'content' => $content_result['success'] ? $content_result['response'] : $english_page['content'],
            'slug' => $page ? $page['slug'] : 'privacy'
        ];
        
        $success = "Content translated from English to {$target_language}! Please review and click Save to store the translation.";
        
        if (!$meta_title_result['success']) error_log("Meta Title translation failed: " . $meta_title_result['error']);
        if (!$meta_description_result['success']) error_log("Meta Description translation failed: " . $meta_description_result['error']);
        if (!$meta_header_result['success']) error_log("Meta Header translation failed: " . $meta_header_result['error']);
        if (!$content_result['success']) error_log("Content translation failed: " . $content_result['error']);
    } else {
        $error = 'No English content found to translate. Please ensure English (ID 41) Privacy page exists.';
    }
}

// Handle delete request - moved here after $page is defined
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page'])) {
    if ($page) {
        $delete_page_id = $page['id'];  // Use different variable name to avoid conflict
        $sql = "DELETE FROM language_pages WHERE id=$delete_page_id AND language_id=$lang_id AND (LOWER(page_name) LIKE '%privacy%' OR LOWER(slug) LIKE '%privacy%')";
        if ($conn->query($sql)) {
            // Redirect back to language pages after successful deletion
            header("Location: language_pages.php?id=$lang_id&deleted=1");
            exit;
        } else {
            $error = 'Failed to delete page.';
        }
    } else {
        $error = 'No page found to delete.';
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_name = trim($_POST['page_name'] ?? 'Privacy');
    $slug = trim($_POST['slug'] ?? 'privacy');
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
            $success = 'Privacy page updated.';
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
            $success = 'Privacy page added.';
        } else {
            $error = 'Failed to add page.';
        }
    }
    // Always fetch the latest page after update/insert
    $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%privacy%' OR LOWER(slug) LIKE '%privacy%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
    }
    
    // Fetch English default if no page exists
    if (!$page && $lang_id != 41) {
        $sql = "SELECT * FROM language_pages WHERE language_id=41 AND (LOWER(page_name) LIKE '%privacy%' OR LOWER(slug) LIKE '%privacy%') LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $english_page = $res->fetch_assoc();
        }
    }
}
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <?php
            // Get the ID from the URL
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            ?>
            
            <!-- Back Button -->
            <a href="language_pages.php?id=<?php echo $id; ?>" class="btn btn-secondary">
                ← Back
            </a>
        <div class="page-section">
            <h4>Privacy Page (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
            <!--<div class="url-preview">URL: <b>/<?php echo htmlspecialchars($lang_code); ?>/<?php echo htmlspecialchars($page['slug'] ?? 'privacy'); ?></b></div>-->
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Page Name</label>
                    <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name'] ?? 'Privacy'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug <?php if ($translated_data): ?><small class="text-success">(Not translated)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($translated_data ? $translated_data['slug'] : ($page['slug'] ?? ($english_page['slug'] ?? 'privacy'))); ?>" placeholder="<?php echo htmlspecialchars($english_page['slug'] ?? 'privacy'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Title <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_title" value="<?php echo htmlspecialchars($translated_data ? $translated_data['meta_title'] : ($page['meta_title'] ?? ($english_page['meta_title'] ?? ''))); ?>" placeholder="<?php echo htmlspecialchars($english_page['meta_title'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Description <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_description" rows="2" placeholder="<?php echo htmlspecialchars($english_page['meta_description'] ?? ''); ?>"><?php echo htmlspecialchars($translated_data ? $translated_data['meta_description'] : ($page['meta_description'] ?? ($english_page['meta_description'] ?? ''))); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Heading <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_header" value="<?php echo htmlspecialchars($translated_data ? $translated_data['meta_header'] : ($page['meta_header'] ?? ($english_page['meta_header'] ?? ''))); ?>" placeholder="<?php echo htmlspecialchars($english_page['meta_header'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Page Content <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" id="editor" name="content"><?php echo htmlspecialchars($translated_data ? $translated_data['content'] : ($page['content'] ?? ($english_page['content'] ?? ''))); ?></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <?php if ($lang_id != 41): ?>
                        <button type="submit" name="translate_content" class="btn btn-success" onclick="return confirm('Translate all content from English to <?php echo htmlspecialchars($lang['name']); ?>? This will use ChatGPT API.')">
                            <i class="fa fa-language"></i> Translate from English
                        </button>
                    <?php endif; ?>
                    <?php if ($page): ?>
                        <button type="submit" name="delete_page" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this Privacy page? This action cannot be undone.')">Delete Page</button>
                    <?php endif; ?>
                </div>
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
            CKEDITOR.replace('editor', {
                height: 220,
                filebrowserUploadUrl: 'upload-clean.php',
                filebrowserUploadMethod: 'form'
            });
        });
    </script>
</body>
</html> 