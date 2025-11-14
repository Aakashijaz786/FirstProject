<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Debug: Check if we're in translation mode
if (isset($_POST['translate_now'])) {
    echo "<!-- DEBUG: Translation mode detected -->";
}

// Helper function to get translation text from API response
function getTranslationText($text, $target_lang) {
    $result = translateText($text, $target_lang);
    if (is_array($result) && isset($result['success']) && $result['success'] && isset($result['response'])) {
        return $result['response'];
    }
    return $text; // Return original text if translation fails
}
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

$feature_message = 'Custom page creation has been disabled. Existing content remains accessible for reference only.';
include 'includes/header.php';
?>
<div class="main-content" id="mainContent">
    <div class="alert alert-info m-4"><?php echo $feature_message; ?></div>
</div>
<?php
include 'includes/footer.php';
exit;

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

$page_name = '';
$slug = '';
$meta_title = '';
$meta_description = '';
$meta_header = '';
$header = '';
$heading = '';
$description = '';
$success = '';
$error = '';
$english_template = null;

// Check if template parameter is provided (coming from language_pages.php)
$template_name = isset($_GET['template']) ? trim($_GET['template']) : '';
$english_content_flag = isset($_GET['english_content']) ? intval($_GET['english_content']) : 0;

if ($template_name && $english_content_flag) {
    // Fetch English page with this name
    $template_res = $conn->query("SELECT * FROM custom_pages WHERE language_id=41 AND page_name='" . $conn->real_escape_string($template_name) . "' LIMIT 1");
    if ($template_res && $template_res->num_rows > 0) {
        $english_template = $template_res->fetch_assoc();
        // Pre-fill with English content as defaults (without slug)
        $page_name = $english_template['page_name'];
        $meta_title = $english_template['meta_title'];
        $meta_description = $english_template['meta_description'];
        $meta_header = $english_template['meta_header'];
        $header = $english_template['header'];
        $heading = $english_template['heading'];
        $description = $english_template['description'];
        // Note: slug is intentionally left empty
    }
}

// Handle translation request
$is_translated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['translate_now'])) {
    if ($english_template) {
        // Get target language info
        $target_lang = $lang['name'];
        
        // Translate all fields from English
        $page_name = !empty($english_template['page_name']) ? getTranslationText($english_template['page_name'], $target_lang) : '';
        $meta_title = !empty($english_template['meta_title']) ? getTranslationText($english_template['meta_title'], $target_lang) : '';
        $meta_description = !empty($english_template['meta_description']) ? getTranslationText($english_template['meta_description'], $target_lang) : '';
        $meta_header = !empty($english_template['meta_header']) ? getTranslationText($english_template['meta_header'], $target_lang) : '';
        $header = !empty($english_template['header']) ? getTranslationText($english_template['header'], $target_lang) : '';
        $heading = !empty($english_template['heading']) ? getTranslationText($english_template['heading'], $target_lang) : '';
        $description = !empty($english_template['description']) ? getTranslationText($english_template['description'], $target_lang) : '';
        
        $is_translated = true;
        $success = 'Content translated to ' . htmlspecialchars($target_lang) . '! Please review and click Save to create the page.';
    } else {
        $error = 'No English template found for translation.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['translate_now'])) {
    $page_name = trim($_POST['page_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_header = trim($_POST['meta_header'] ?? '');
    $header = trim($_POST['header'] ?? '');
    $heading = trim($_POST['heading'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Insert new page
    $sql = "INSERT INTO custom_pages 
    (language_id, page_name, slug, meta_title, meta_description, meta_header, header, heading, description)
    VALUES (
        $lang_id,
        '".$conn->real_escape_string($page_name)."',
        '".$conn->real_escape_string($slug)."',
        '".$conn->real_escape_string($meta_title)."',
        '".$conn->real_escape_string($meta_description)."',
        '".$conn->real_escape_string($meta_header)."',
        '".$conn->real_escape_string($header)."',
        '".$conn->real_escape_string($heading)."',
        '".$conn->real_escape_string($description)."'
    )";
if ($conn->query($sql)) {
    $new_page_id = $conn->insert_id;
    // Insert slug into custom_page_slugs as active
    if ($slug) {
        // Ensure custom_page_slugs table exists
        $conn->query("CREATE TABLE IF NOT EXISTS custom_page_slugs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            custom_page_id INT NOT NULL,
            slug VARCHAR(255) NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (custom_page_id) REFERENCES custom_pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        // Set all previous slugs for this page to inactive (should be none, but for safety)
        $conn->query("UPDATE custom_page_slugs SET status='inactive' WHERE custom_page_id=$new_page_id");
        // Insert as active
        $conn->query("INSERT INTO custom_page_slugs (custom_page_id, slug, status) VALUES ($new_page_id, '".$conn->real_escape_string($slug)."', 'active')");
    }
    $success = 'New page added successfully to custom_pages.';
    // Reset form fields
    $page_name = $slug = $meta_title = $meta_description = $meta_header = $header = $heading = $description = '';
} else {
    $error = 'Failed to add new page to custom_pages: ' . $conn->error;
}
}

// Fetch dynamic titles/descriptions
define('CREATE_NEW_DYNAMIC_TABLE', 'language_create_new_titles');
if (!$conn->query("SHOW TABLES LIKE '".CREATE_NEW_DYNAMIC_TABLE."'")) {
    $conn->query("CREATE TABLE IF NOT EXISTS `".CREATE_NEW_DYNAMIC_TABLE."` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `language_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
$create_new_titles = [];
$sql = "SELECT * FROM `create_new_titles` WHERE language_id=$lang_id ORDER BY id ASC";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $create_new_titles[] = $row;
    }
}
if (empty($create_new_titles)) {
    $create_new_titles[] = ['id'=>0, 'title'=>'', 'description'=>''];
}

// After the form and alert, display a list of all pages for this language
$sql = "SELECT id, page_name FROM custom_pages WHERE language_id=$lang_id ORDER BY id DESC";
$res = $conn->query($sql);
$pages = [];
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $pages[] = $row;
    }
}
?>
<?php include 'includes/header.php'; ?>
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
            <h4>Create New Page (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
            <!--<div class="url-preview">URL: <b>/<?php echo htmlspecialchars($lang_code); ?>/<?php echo htmlspecialchars($slug); ?></b></div>-->
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" style="display: block !important;">
                <?php
                $field_class = $is_translated ? 'border-success' : '';
                $label_suffix = $is_translated ? ' <span class="text-success">(Translated - Review & Save)</span>' : '';
                ?>
                
                <!-- Debug: Field class = <?php echo $field_class; ?> -->
                <!-- Debug: Label suffix = <?php echo $label_suffix; ?> -->
                <!-- Debug: Page name = <?php echo htmlspecialchars(is_string($page_name) ? $page_name : 'NOT_STRING'); ?> -->
                
                <div class="mb-3">
                    <label class="form-label">Page Name<?php echo $label_suffix; ?></label>
                    <input type="text" class="form-control <?php echo $field_class; ?>" name="page_name" value="<?php echo htmlspecialchars(is_string($page_name) ? $page_name : ''); ?>" style="display: block !important; width: 100% !important; visibility: visible !important;">
                    <!-- DEBUG: Input should be visible above -->
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($slug); ?>" style="display: block !important; width: 100% !important; visibility: visible !important;">
                    <small class="text-muted">Create a unique slug for this language</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Title<?php echo $label_suffix; ?></label>
                    <input type="text" class="form-control <?php echo $field_class; ?>" name="meta_title" value="<?php echo htmlspecialchars(is_string($meta_title) ? $meta_title : ''); ?>" style="display: block !important; width: 100% !important; visibility: visible !important;">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Description<?php echo $label_suffix; ?></label>
                    <textarea class="form-control <?php echo $field_class; ?>" name="meta_description" rows="2" style="display: block !important; width: 100% !important; visibility: visible !important;"><?php echo htmlspecialchars(is_string($meta_description) ? $meta_description : ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Header (Meta Tags)<?php echo $label_suffix; ?></label>
                    <textarea class="form-control <?php echo $field_class; ?>" name="meta_header" rows="2" style="display: block !important; width: 100% !important; visibility: visible !important;"><?php echo htmlspecialchars(is_string($meta_header) ? $meta_header : ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Header<?php echo $label_suffix; ?></label>
                    <input type="text" class="form-control <?php echo $field_class; ?>" name="header" value="<?php echo htmlspecialchars(is_string($header) ? $header : ''); ?>" style="display: block !important; width: 100% !important; visibility: visible !important;">
                </div>
                <div class="mb-3">
                    <label class="form-label">Heading<?php echo $label_suffix; ?></label>
                    <input type="text" class="form-control <?php echo $field_class; ?>" name="heading" value="<?php echo htmlspecialchars(is_string($heading) ? $heading : ''); ?>" style="display: block !important; width: 100% !important; visibility: visible !important;">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description<?php echo $label_suffix; ?></label>
                    <textarea class="form-control <?php echo $field_class; ?>" id="editor" name="description" style="display: block !important; width: 100% !important; visibility: visible !important;"><?php echo htmlspecialchars(is_string($description) ? $description : ''); ?></textarea>
                </div>
                <!--<div class="mb-3">-->
                <!--    <label class="form-label">Titles & Descriptions</label>-->
                <!--    <div id="titlesContainer">-->
                <!--        <?php foreach ($create_new_titles as $i => $row): ?>-->
                <!--        <div class="title-row row align-items-end">-->
                <!--            <input type="hidden" name="title_id[]" value="<?php echo isset($row['id']) ? (int)$row['id'] : 0; ?>">-->
                <!--            <div class="col-md-12 mb-2">-->
                <!--                <input type="text" class="form-control" name="title[]" placeholder="Title" value="<?php echo htmlspecialchars($row['title']); ?>">-->
                <!--            </div>-->
                <!--            <div class="col-md-12 mb-2">-->
                <!--                <input type="text" class="form-control" name="title_description[]" placeholder="Description" value="<?php echo htmlspecialchars($row['description']); ?>">-->
                <!--            </div>-->
                <!--            <div class="col-md-2 mb-2 text-end">-->
                <!--                <?php if ($i > 0): ?>-->
                <!--                <span class="remove-title-btn" title="Remove" onclick="removeTitleRow(this)"><i class="fa fa-trash"></i></span>-->
                <!--                <?php endif; ?>-->
                <!--            </div>-->
                <!--        </div>-->
                <!--        <?php endforeach; ?>-->
                <!--    </div>-->
                <!--    <button type="button" class="btn btn-secondary mb-3" onclick="addTitleRow()"><i class="fa fa-plus"></i> Add More</button>-->
                <!--</div>-->
                <button type="submit" class="btn btn-primary" style="display: block !important; visibility: visible !important;">Save</button>
            </form>
            
            <?php if ($english_template && $lang_id != 41): ?>
            <hr class="my-4">
            <div class="card">
                <div class="card-body">
                    <form method="post">
                        <button type="submit" name="translate_now" class="btn btn-info">
                            <i class="fa fa-language"></i> Translate Now
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            CKEDITOR.replace('editor', {
                height: 300,
                filebrowserUploadUrl: 'upload-clean.php',
                filebrowserUploadMethod: 'form'
            });
        });
    </script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('mainContent');
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });
        // CKEditor for description field
        $(document).ready(function() {
            // CKEditor handles content automatically on form submission
        });
        function addTitleRow() {
            const html = `<div class="title-row row align-items-end">
                <input type="hidden" name="title_id[]" value="0">
                <div class="col-md-12 mb-2">
                    <input type="text" class="form-control" name="title[]" placeholder="Title">
                </div>
                <div class="col-md-11 mb-2">
                    <input type="text" class="form-control" name="title_description[]" placeholder="Description">
                </div>
                <div class="col-md-1 mb-2 text-end">
                    <span class="remove-title-btn" title="Remove" onclick="removeTitleRow(this)"><i class="fa fa-trash"></i></span>
                </div>
            </div>`;
            $('#titlesContainer').append(html);
        }
        function removeTitleRow(el) {
            $(el).closest('.title-row').remove();
        }
        function deleteRedirect(id) {
            if (!confirm('Are you sure you want to delete this redirect?')) return;
            $.post('', {delete_redirect_id: id}, function(resp) {
                let data = {};
                try { data = JSON.parse(resp); } catch(e) {}
                if (data.success) {
                    $('#redirect-row-' + id).remove();
                    $('#redirect-alert').html('<div class="alert alert-success">Redirect deleted successfully.</div>');
                } else {
                    $('#redirect-alert').html('<div class="alert alert-danger">Failed to delete redirect.</div>');
                }
            });
        }
    </script>
</body>
</html> 
