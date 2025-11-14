    <?php
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit;
    }
require_once '../includes/config.php';

include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="alert alert-info m-4">
            The standalone MP3 editor has been discontinued. Please manage content via the new FastAPI workflow.
        </div>
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

    // Fetch the MP3 page for this language
    $page = null;
    $sql = "SELECT * FROM mp3_pages WHERE language_id=$lang_id LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
    }

    // If no page exists, fetch English (ID 41) content as default
    $english_page = null;
    if (!$page && $lang_id != 41) {
        $sql = "SELECT * FROM mp3_pages WHERE language_id=41 LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $english_page = $res->fetch_assoc();
        }
    }

    $page_name = 'MP3';
    $success = '';
    $error = '';

    // Define table constant first
    define('MP3_DYNAMIC_TABLE', 'language_mp3_titles');

    // Handle translate request - DO NOT SAVE, just prepare translated content
    $translated_data = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['translate_content'])) {
        // Fetch English page if not already loaded
        if (!$english_page) {
            $sql = "SELECT * FROM mp3_pages WHERE language_id=41 LIMIT 1";
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) {
                $english_page = $res->fetch_assoc();
            }
        }
        
        if ($english_page) {
            $target_language = $lang['name'];
            error_log("Translating MP3 page from English to {$target_language}");
            
            // Translate page fields
            $meta_title_result = translateText($english_page['meta_title'], $target_language);
            $meta_description_result = translateText($english_page['meta_description'], $target_language);
            $meta_header_result = translateText($english_page['meta_header'], $target_language);
            $header_result = translateText($english_page['header'], $target_language);
            $heading_result = translateText($english_page['heading'], $target_language);
            $description_result = translateText($english_page['description'], $target_language);
            
            // Fetch English titles
            $english_titles_trans = [];
            $sql = "SELECT * FROM `".MP3_DYNAMIC_TABLE."` WHERE language_id=41 ORDER BY id ASC";
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $english_titles_trans[] = $row;
                }
            }
            
            // Translate titles and descriptions
            $translated_titles = [];
            foreach ($english_titles_trans as $title_row) {
                $title_result = translateText($title_row['title'], $target_language);
                $desc_result = translateText($title_row['description'], $target_language);
                
                $translated_titles[] = [
                    'title' => $title_result['success'] ? $title_result['response'] : $title_row['title'],
                    'description' => $desc_result['success'] ? $desc_result['response'] : $title_row['description']
                ];
                
                if (!$title_result['success']) error_log("Title translation failed: " . $title_result['error']);
                if (!$desc_result['success']) error_log("Description translation failed: " . $desc_result['error']);
            }
            
            $translated_data = [
                'meta_title' => $meta_title_result['success'] ? $meta_title_result['response'] : $english_page['meta_title'],
                'meta_description' => $meta_description_result['success'] ? $meta_description_result['response'] : $english_page['meta_description'],
                'meta_header' => $meta_header_result['success'] ? $meta_header_result['response'] : $english_page['meta_header'],
                'header' => $header_result['success'] ? $header_result['response'] : $english_page['header'],
                'heading' => $heading_result['success'] ? $heading_result['response'] : $english_page['heading'],
                'description' => $description_result['success'] ? $description_result['response'] : $english_page['description'],
                'slug' => $page ? $page['slug'] : 'mp3',
                'titles' => $translated_titles
            ];
            
            $success = "Content and titles translated from English to {$target_language}! Please review and click Save to store the translation.";
            
            if (!$meta_title_result['success']) {
                error_log("Meta Title translation failed: " . $meta_title_result['error']);
                $error = "Some translations failed. Using English content where translation failed.";
            }
            if (!$meta_description_result['success']) error_log("Meta Description translation failed: " . $meta_description_result['error']);
            if (!$meta_header_result['success']) error_log("Meta Header translation failed: " . $meta_header_result['error']);
            if (!$header_result['success']) error_log("Header translation failed: " . $header_result['error']);
            if (!$heading_result['success']) error_log("Heading translation failed: " . $heading_result['error']);
            if (!$description_result['success']) error_log("Description translation failed: " . $description_result['error']);
            
        } else {
            $error = 'No English content found to translate. Please ensure English (ID 41) MP3 page exists.';
        }
    }

    // Fetch mp3 dynamic titles/descriptions
    if (!$conn->query("SHOW TABLES LIKE '".MP3_DYNAMIC_TABLE."'")) {
        $conn->query("CREATE TABLE IF NOT EXISTS `".MP3_DYNAMIC_TABLE."` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `language_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }
    // Use translated titles if available, otherwise fetch from database
    if ($translated_data && isset($translated_data['titles'])) {
        $mp3_titles = $translated_data['titles'];
    } else {
        $mp3_titles = [];
        $sql = "SELECT * FROM `".MP3_DYNAMIC_TABLE."` WHERE language_id=$lang_id ORDER BY id ASC";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $mp3_titles[] = $row;
            }
        }
    }

    // If no titles exist, fetch English titles as default
    $english_titles = [];
    if (empty($mp3_titles) && $lang_id != 41) {
        $sql = "SELECT * FROM `".MP3_DYNAMIC_TABLE."` WHERE language_id=41 ORDER BY id ASC";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $english_titles[] = $row;
            }
        }
    }

    if (empty($mp3_titles)) {
        $mp3_titles[] = ['id'=>0, 'title'=>'', 'description'=>''];
    }

    $slug = 'mp3'; // default

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['translate_content'])) {
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $page_name = trim($_POST['page_name'] ?? 'MP3');
        $slug = trim($_POST['slug'] ?? 'mp3');
        $meta_title = trim($_POST['meta_title'] ?? '');
        $meta_description = trim($_POST['meta_description'] ?? '');
        $meta_header = trim($_POST['meta_header'] ?? '');
        $header = trim($_POST['header'] ?? '');
        $heading = trim($_POST['heading'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $titles = $_POST['title'] ?? [];
        $descriptions = $_POST['title_description'] ?? [];
        $title_ids = $_POST['title_id'] ?? [];

        if ($page_id) {
            // Update existing
            $sql = "UPDATE mp3_pages SET page_name='" . $conn->real_escape_string($page_name) .
                "', slug='" . $conn->real_escape_string($slug) .
                "', meta_title='" . $conn->real_escape_string($meta_title) .
                "', meta_description='" . $conn->real_escape_string($meta_description) .
                "', meta_header='" . $conn->real_escape_string($meta_header) .
                "', header='" . $conn->real_escape_string($header) .
                "', heading='" . $conn->real_escape_string($heading) .
                "', description='" . $conn->real_escape_string($description) .
                "' WHERE id=$page_id";
            if ($conn->query($sql)) {
                $success = 'MP3 page updated.';
            } else {
                $error = 'Failed to update MP3 page: ' . $conn->error;
            }
        } else {
            // Insert new
            $sql = "INSERT INTO mp3_pages (language_id, page_name, slug, meta_title, meta_description, meta_header, header, heading, description) VALUES ("
                . "$lang_id, '" . $conn->real_escape_string($page_name) . "', '" . $conn->real_escape_string($slug) . "', '" . $conn->real_escape_string($meta_title) . "', '"
                . $conn->real_escape_string($meta_description) . "', '"
                . $conn->real_escape_string($meta_header) . "', '"
                . $conn->real_escape_string($header) . "', '"
                . $conn->real_escape_string($heading) . "', '"
                . $conn->real_escape_string($description) . "')";
            if ($conn->query($sql)) {
                $success = 'MP3 page added.';
            } else {
                $error = 'Failed to add MP3 page: ' . $conn->error;
            }
        }

        // Always fetch the latest page after update/insert
        $sql = "SELECT * FROM mp3_pages WHERE language_id=$lang_id LIMIT 1";
        $res = $conn->query($sql);
        $page = null;
        if ($res && $res->num_rows > 0) {
            $page = $res->fetch_assoc();
            $slug = $page['slug'];
        }
        // --- Handle dynamic titles/descriptions: update existing, insert new, delete removed ---
        // Only process titles if they were actually submitted in the form
        if (!empty($titles) || !empty($descriptions)) {
            // Fetch current mp3_titles
            $existing_titles = [];
            $sql = "SELECT * FROM `".MP3_DYNAMIC_TABLE."` WHERE language_id=$lang_id ORDER BY id ASC";
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $existing_titles[$row['id']] = $row;
                }
            }
            $handled_ids = [];
            for ($i = 0; $i < count($titles); $i++) {
                $t = trim($titles[$i]);
                $d = trim($descriptions[$i]);
                $tid = isset($title_ids[$i]) ? intval($title_ids[$i]) : 0;
                if ($t && $d) {
                    $t_esc = $conn->real_escape_string($t);
                    $d_esc = $conn->real_escape_string($d);
                    if ($tid && isset($existing_titles[$tid])) {
                        $sql = "UPDATE `".MP3_DYNAMIC_TABLE."` SET title='$t_esc', description='$d_esc' WHERE id=$tid AND language_id=$lang_id";
                        $conn->query($sql);
                        $handled_ids[] = $tid;
                    } else {
                        $sql = "INSERT INTO `".MP3_DYNAMIC_TABLE."` (language_id, title, description) VALUES ($lang_id, '$t_esc', '$d_esc')";
                        $conn->query($sql);
                        $handled_ids[] = $conn->insert_id;
                    }
                }
            }
            // Delete removed titles only if titles were actually submitted in the form
            foreach ($existing_titles as $id => $row) {
                if (!in_array($id, $handled_ids)) {
                    $conn->query("DELETE FROM `".MP3_DYNAMIC_TABLE."` WHERE id=$id");
                }
            }
            
            // Refresh mp3_titles after saving to show updated values
            $mp3_titles = [];
            $sql = "SELECT * FROM `".MP3_DYNAMIC_TABLE."` WHERE language_id=$lang_id ORDER BY id ASC";
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $mp3_titles[] = $row;
                }
            }
            if (empty($mp3_titles)) {
                $mp3_titles[] = ['id'=>0, 'title'=>'', 'description'=>''];
            }
        }
    } else {
        // On GET, fetch the first MP3 page for this language (if any)
        $sql = "SELECT * FROM mp3_pages WHERE language_id=$lang_id LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $page = $res->fetch_assoc();
            $slug = $page['slug'];
        }
    }

    // After updating/inserting the mp3_pages row (only when form is submitted):
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // SAFELY get the ID from $page
        $mp3_page_id = isset($page['id']) ? (int)$page['id'] : 0;
        $new_slug = $slug;

        if ($mp3_page_id > 0 && !empty($new_slug)) {
            // Set all previous slugs for this page to inactive
            $conn->query("UPDATE mp3_page_slugs SET status='inactive' WHERE mp3_page_id=$mp3_page_id");

            // Escape the slug to avoid SQL issues
            $escaped_slug = mysqli_real_escape_string($conn, $new_slug);

            // Check if the new slug already exists
            $res = $conn->query("SELECT * FROM mp3_page_slugs WHERE mp3_page_id=$mp3_page_id AND slug='$escaped_slug'");
            if ($res && $res->num_rows > 0) {
                // Update to active
                $conn->query("UPDATE mp3_page_slugs SET status='active' WHERE mp3_page_id=$mp3_page_id AND slug='$escaped_slug'");
            } else {
                // Insert as active
                $conn->query("INSERT INTO mp3_page_slugs (mp3_page_id, slug, status) VALUES ($mp3_page_id, '$escaped_slug', 'active')");
            }
        }
    }


    // Handle delete redirect (AJAX)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_redirect_id'])) {
        $delete_id = intval($_POST['delete_redirect_id']);
        // Only allow deleting inactive slugs from mp3_page_slugs
        $result = $conn->query("DELETE FROM mp3_page_slugs WHERE id=$delete_id AND status='inactive'");
        
        if ($result) {
            // Fetch updated slugs after deletion
            $mp3_page_id = $page['id'] ?? 0;
            $updated_slugs = [];
            if ($mp3_page_id) {
                $res = $conn->query("SELECT * FROM mp3_page_slugs WHERE mp3_page_id=$mp3_page_id ORDER BY id ASC");
                if ($res && $res->num_rows > 0) {
                    while ($row = $res->fetch_assoc()) {
                        $updated_slugs[] = $row;
                    }
                }
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Redirect deleted successfully.',
                'updated_slugs' => $updated_slugs
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete redirect.']);
        }
        exit;
    }

    // Fetch all redirects for this page
    $redirects = [];
    if ($page) {
        $res = $conn->query("SELECT * FROM language_page_redirects WHERE page_id=" . intval($page['id']) . " ORDER BY id ASC");
        while ($row = $res->fetch_assoc()) {
            $redirects[] = $row;
        }
    }
    ?>
    <?php include 'includes/header.php'; ?>
    <style>
        .note-editor {
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .note-editor:focus-within {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .note-editable {
            min-height: 300px;
            padding: 15px;
            font-size: 14px;
            line-height: 1.6;
        }
        .note-toolbar {
            border-bottom: 1px solid #dee2e6;
            border-radius: 4px 4px 0 0;
        }
        /* Table styling */
        .note-editable table {
            border-collapse: collapse;
            margin: 1em 0;
            width: 100%;
        }
        .note-editable table td,
        .note-editable table th {
            border: 1px solid #ccc;
            padding: 8px;
        }
        .note-editable table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
    </style>
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
                <h4>MP3 Page (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
                <!--<div class="url-preview">URL: <b><?php echo htmlspecialchars($page['slug'] ?? 'mp3'); ?></b></div>-->
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="mb-3" style="display:none;">
                        <label class="form-label">Page Name</label>
                        <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name'] ?? 'MP3'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug <?php if ($translated_data): ?><small class="text-success">(Not translated)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                        <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($translated_data ? $translated_data['slug'] : ($page['slug'] ?? ($english_page['slug'] ?? 'mp3'))); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Title <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                        <input type="text" class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_title" value="<?php echo htmlspecialchars($translated_data ? $translated_data['meta_title'] : ($page['meta_title'] ?? ($english_page['meta_title'] ?? ''))); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Meta Description <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                        <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_description" rows="2"><?php echo htmlspecialchars($translated_data ? $translated_data['meta_description'] : ($page['meta_description'] ?? ($english_page['meta_description'] ?? ''))); ?></textarea>
                    </div>
                    <div class="mb-3" style="display:none;">
                        <label class="form-label">Header (Meta Tags) <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php endif; ?></label>
                        <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_header" rows="2"><?php echo htmlspecialchars($translated_data ? $translated_data['meta_header'] : ($page['meta_header'] ?? '')); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Header <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                        <input type="text" class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="header" value="<?php echo htmlspecialchars($translated_data ? $translated_data['header'] : ($page['header'] ?? ($english_page['header'] ?? ''))); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Heading <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                        <input type="text" class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="heading" value="<?php echo htmlspecialchars($translated_data ? $translated_data['heading'] : ($page['heading'] ?? ($english_page['heading'] ?? ''))); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                        <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" id="editor" name="description"><?php echo htmlspecialchars($translated_data ? $translated_data['description'] : ($page['description'] ?? ($english_page['description'] ?? ''))); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Titles & Descriptions</label>
                        <div id="titlesContainer">
                            <?php 
                            $display_titles = $mp3_titles;
                            $is_translated = $translated_data && isset($translated_data['titles']);
                            
                            if (empty($mp3_titles) || (count($mp3_titles) == 1 && empty($mp3_titles[0]['title']))) {
                                if (!empty($english_titles)) {
                                    $display_titles = $english_titles;
                                }
                            }
                            
                            foreach ($display_titles as $i => $row): 
                                $is_english_default = (!$page && $english_page && !empty($english_titles) && !$is_translated);
                            ?>
                            <div class="title-row row align-items-end">
                                <input type="hidden" name="title_id[]" value="<?php echo isset($row['id']) ? (int)$row['id'] : 0; ?>">
                                <div class="col-md-12 mb-2">
                                    <label class="form-label">Title <?php if ($is_translated): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif ($is_english_default): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                                    <input type="text" class="form-control <?php echo $is_translated ? 'border-success' : ''; ?>" name="title[]" value="<?php echo htmlspecialchars($row['title']); ?>">
                                </div>
                                <div class="col-md-12 mb-2">
                                    <label class="form-label">Description <?php if ($is_translated): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif ($is_english_default): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                                    <textarea type="text" class="form-control <?php echo $is_translated ? 'border-success' : ''; ?>" name="title_description[]"><?php echo htmlspecialchars($row['description']); ?></textarea>
                                </div>
                                <div class="col-md-2 mb-2 text-end">
                                    <?php if ($i > 0): ?>
                                    <span class="remove-title-btn" title="Remove" onclick="removeTitleRow(this)"><i class="fa fa-trash"></i></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-secondary mb-3" onclick="addTitleRow()"><i class="fa fa-plus"></i> Add More</button>
                    </div>
                    <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id'] ?? ''); ?>">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save</button>
                        <?php if ($lang_id != 41): ?>
                            <button type="submit" name="translate_content" class="btn btn-success" onclick="return confirm('Translate all content and titles from English to <?php echo htmlspecialchars($lang['name']); ?>? This will use ChatGPT API.')">
                                <i class="fa fa-language"></i> Translate from English
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
        
            <div class="mt-4">
                <h5>Slugs</h5>
                <div id="redirect-alert"></div>
                <?php
        // Fetch all slugs for this MP3 page
        $mp3_page_id = $page['id'] ?? 0;
        $slugs = [];
        if ($mp3_page_id) {
            $res = $conn->query("SELECT * FROM mp3_page_slugs WHERE mp3_page_id=$mp3_page_id ORDER BY id ASC");
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $slugs[] = $row;
                }
            }
        }
        $sno = 1;
    ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Sno.</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($slugs as $r): ?>
            <tr id="redirect-row-<?php echo $r['id']; ?>">
                <td><?php echo $sno++; ?></td>
                <td><b><?php echo htmlspecialchars($r['slug']); ?></b></td>
                <td>
                    <?php if ($r['status'] === 'active'): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['status'] !== 'active'): ?>
                        <button class="btn btn-danger btn-sm" onclick="deleteRedirect(<?php echo $r['id']; ?>)">Delete</button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
            </div>
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
            function addTitleRow() {
                const html = `
                
                <div class="title-row row align-items-end">
                    <input type="hidden" name="title_id[]" value="0">
                    <div class="col-md-12 mb-2">
                        <input type="text" class="form-control" name="title[]" placeholder="Title">
                    </div>
                    <div class="col-md-11 mb-2">
                        <textarea type="text" class="form-control" name="title_description[]" placeholder="Description"></textarea>
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
                $('#redirect-alert').html('<div class="alert alert-success">' + (data.message || 'Redirect deleted successfully.') + '</div>');
                
                // Update the table with the new slug data
                if (data.updated_slugs) {
                    updateSlugsTable(data.updated_slugs);
                }
            } else {
                $('#redirect-alert').html('<div class="alert alert-danger">' + (data.message || 'Failed to delete redirect.') + '</div>');
            }
        });
    }

    function updateSlugsTable(slugs) {
        let tbody = $('.table tbody');
        tbody.empty();
        
        slugs.forEach(function(slug, index) {
            let sno = index + 1;
            let statusBadge = slug.status === 'active' ? 
                '<span class="badge bg-success">Active</span>' : 
                '<span class="badge bg-secondary">Inactive</span>';
            
            let deleteButton = slug.status !== 'active' ? 
                '<button class="btn btn-danger btn-sm" onclick="deleteRedirect(' + slug.id + ')">Delete</button>' : 
                '';
            
            let row = `
                <tr id="redirect-row-${slug.id}">
                    <td>${sno}</td>
                    <td><b>${slug.slug}</b></td>
                    <td>${statusBadge}</td>
                    <td>${deleteButton}</td>
                </tr>
            `;
            tbody.append(row);
        });
    }
        </script>
    </body>
    </html> 
