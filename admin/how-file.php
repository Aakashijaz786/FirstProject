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

// Fetch the How page for this language (most recently added)
$page = null;
$sql = "SELECT * FROM how_pages WHERE language_id=$lang_id ORDER BY id DESC LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $page = $res->fetch_assoc();
}

// If no page exists for this language, fetch English (ID 41) content as default
$english_page = null;
if (!$page && $lang_id != 41) {
    $sql = "SELECT * FROM how_pages WHERE language_id=41 ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $english_page = $res->fetch_assoc();
        // Don't set as $page, keep separate to show as placeholder
    }
}

$page_name = 'How';
$success = '';
$error = '';

// Fetch how dynamic titles/descriptions
define('HOW_DYNAMIC_TABLE', 'language_how_titles');
if (!$conn->query("SHOW TABLES LIKE '".HOW_DYNAMIC_TABLE."'")) {
    $conn->query("CREATE TABLE IF NOT EXISTS `".HOW_DYNAMIC_TABLE."` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `language_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
$how_titles = [];
$sql = "SELECT * FROM `".HOW_DYNAMIC_TABLE."` WHERE language_id=$lang_id ORDER BY id ASC";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $how_titles[] = $row;
    }
}

// If no titles exist for this language, fetch English (ID 41) titles as default
$english_titles = [];
if (empty($how_titles) && $lang_id != 41) {
    $sql = "SELECT * FROM `".HOW_DYNAMIC_TABLE."` WHERE language_id=41 ORDER BY id ASC";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $english_titles[] = $row;
        }
    }
}

if (empty($how_titles)) {
    $how_titles[] = ['id'=>0, 'title'=>'', 'description'=>''];
}

$slug = 'how'; // default

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete redirect (AJAX) - MUST BE FIRST to prevent other processing
    if (isset($_POST['delete_redirect_id'])) {
        $delete_id = intval($_POST['delete_redirect_id']);
        // Only allow deleting inactive slugs from how_page_slugs
        if ($conn->query("DELETE FROM how_page_slugs WHERE id=$delete_id AND status='inactive'")) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
    
    // Handle translation request (AJAX)
    if (isset($_POST['translate_content'])) {
        // Set headers to ensure JSON response
        header('Content-Type: application/json');
        
        // Catch any PHP errors and return as JSON
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
        
        try {
            $text = $_POST['text'] ?? '';
            $target_lang = $_POST['target_lang'] ?? 'en';
            
            // Log the request
            error_log("Translation request - Target: $target_lang, Text length: " . strlen($text));
            
            if (empty($text) || empty(trim($text))) {
                echo json_encode(['success' => false, 'error' => 'Empty text provided']);
                restore_error_handler();
                exit;
            }
            
            // Strip HTML tags for translation
            $isHtml = strip_tags($text) !== $text;
            $textToTranslate = $isHtml ? strip_tags($text) : $text;
            $textToTranslate = trim($textToTranslate);
            
            if (empty($textToTranslate)) {
                echo json_encode(['success' => false, 'error' => 'Text is empty after stripping HTML']);
                restore_error_handler();
                exit;
            }
            
            // Build URL with proper encoding
            $url = "https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=" . urlencode($target_lang) . "&dt=t&q=" . urlencode($textToTranslate);
            
            error_log("Translating to $target_lang: " . substr($textToTranslate, 0, 100));
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("Translation cURL error: " . $curlError);
                throw new Exception("Network error: " . $curlError);
            }
            
            if ($httpCode !== 200) {
                error_log("Translation HTTP error: $httpCode - Response: " . substr($response, 0, 200));
                throw new Exception("Translation service returned error code: " . $httpCode);
            }
            
            if ($response) {
                $result = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log("JSON decode error: " . json_last_error_msg());
                    throw new Exception("Invalid response from translation service");
                }
                
                if (isset($result[0]) && is_array($result[0])) {
                    $translated = '';
                    foreach ($result[0] as $translation) {
                        if (isset($translation[0])) {
                            $translated .= $translation[0];
                        }
                    }
                    
                    if (!empty($translated)) {
                        error_log("Translation successful: " . substr($translated, 0, 100));
                        echo json_encode(['success' => true, 'translated' => $translated, 'original_length' => strlen($text), 'translated_length' => strlen($translated)]);
                    } else {
                        error_log("Translation result was empty");
                        throw new Exception("Translation returned empty result");
                    }
                } else {
                    error_log("Unexpected response structure: " . print_r($result, true));
                    throw new Exception("Unexpected response from translation service");
                }
            } else {
                throw new Exception("No response from translation service");
            }
            
        } catch (Exception $e) {
            error_log("Translation exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (Error $e) {
            error_log("Translation error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            echo json_encode(['success' => false, 'error' => 'PHP Error: ' . $e->getMessage()]);
        }
        
        restore_error_handler();
        exit;
    }
    
    $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
    $page_name = trim($_POST['page_name'] ?? 'How');
    $slug = trim($_POST['slug'] ?? 'how');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_header = trim($_POST['meta_header'] ?? '');
    $header = trim($_POST['header'] ?? '');
    $heading = trim($_POST['heading'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $titles = $_POST['title'] ?? [];
    $descriptions = $_POST['title_description'] ?? [];
    $title_ids = $_POST['title_id'] ?? [];

    // Handle Section 1 image upload
    $section1_image = $page['section1_image'] ?? '';
    if (isset($_FILES['section1_image']) && $_FILES['section1_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['section1_image']['name'], PATHINFO_EXTENSION);
        $filename = 'section1_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $target = '../uploads/how_sections/' . $filename;
        if (move_uploaded_file($_FILES['section1_image']['tmp_name'], $target)) {
            $section1_image = $filename;
        }
    }
    // Handle Section 2 image upload
    $section2_image = $page['section2_image'] ?? '';
    if (isset($_FILES['section2_image']) && $_FILES['section2_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['section2_image']['name'], PATHINFO_EXTENSION);
        $filename = 'section2_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $target = '../uploads/how_sections/' . $filename;
        if (move_uploaded_file($_FILES['section2_image']['tmp_name'], $target)) {
            $section2_image = $filename;
        }
    }
    $section1_title = trim($_POST['section1_title'] ?? '');
    $section1_description = trim($_POST['section1_description'] ?? '');
    $section2_title = trim($_POST['section2_title'] ?? '');
    $section2_description = trim($_POST['section2_description'] ?? '');

    if ($page_id) {
        // Update existing
        $sql = "UPDATE how_pages SET page_name='" . $conn->real_escape_string($page_name) .
            "', slug='" . $conn->real_escape_string($slug) .
            "', meta_title='" . $conn->real_escape_string($meta_title) .
            "', meta_description='" . $conn->real_escape_string($meta_description) .
            "', meta_header='" . $conn->real_escape_string($meta_header) .
            "', header='" . $conn->real_escape_string($header) .
            "', heading='" . $conn->real_escape_string($heading) .
            "', description='" . $conn->real_escape_string($description) .
            "', section1_image='" . $conn->real_escape_string($section1_image) .
            "', section1_title='" . $conn->real_escape_string($section1_title) .
            "', section1_description='" . $conn->real_escape_string($section1_description) .
            "', section2_image='" . $conn->real_escape_string($section2_image) .
            "', section2_title='" . $conn->real_escape_string($section2_title) .
            "', section2_description='" . $conn->real_escape_string($section2_description) .
            "' WHERE id=$page_id";
        if ($conn->query($sql)) {
            $success = 'How page updated.';
        } else {
            $error = 'Failed to update How page: ' . $conn->error;
        }
    } else {
        // Insert new
        $sql = "INSERT INTO how_pages (language_id, page_name, slug, meta_title, meta_description, meta_header, header, heading, description, section1_image, section1_title, section1_description, section2_image, section2_title, section2_description) VALUES ("
            . "$lang_id, '" . $conn->real_escape_string($page_name) . "', '" . $conn->real_escape_string($slug) . "', '" . $conn->real_escape_string($meta_title) . "', '"
            . $conn->real_escape_string($meta_description) . "', '"
            . $conn->real_escape_string($meta_header) . "', '"
            . $conn->real_escape_string($header) . "', '"
            . $conn->real_escape_string($heading) . "', '"
            . $conn->real_escape_string($description) . "', '"
            . $conn->real_escape_string($section1_image) . "', '"
            . $conn->real_escape_string($section1_title) . "', '"
            . $conn->real_escape_string($section1_description) . "', '"
            . $conn->real_escape_string($section2_image) . "', '"
            . $conn->real_escape_string($section2_title) . "', '"
            . $conn->real_escape_string($section2_description) . "')";
        if ($conn->query($sql)) {
            $success = 'How page added.';
        } else {
            $error = 'Failed to add How page: ' . $conn->error;
        }
    }

    // Always fetch the latest page after update/insert
    $sql = "SELECT * FROM how_pages WHERE language_id=$lang_id ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    $page = null;
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
        $slug = $page['slug'];
    }
    // --- Handle dynamic titles/descriptions: update existing, insert new, delete removed ---
    // Fetch current how_titles
    $existing_titles = [];
    $sql = "SELECT * FROM `".HOW_DYNAMIC_TABLE."` WHERE language_id=$lang_id ORDER BY id ASC";
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
                $sql = "UPDATE `".HOW_DYNAMIC_TABLE."` SET title='$t_esc', description='$d_esc' WHERE id=$tid AND language_id=$lang_id";
                $conn->query($sql);
                $handled_ids[] = $tid;
            } else {
                $sql = "INSERT INTO `".HOW_DYNAMIC_TABLE."` (language_id, title, description) VALUES ($lang_id, '$t_esc', '$d_esc')";
                $conn->query($sql);
                $handled_ids[] = $conn->insert_id;
            }
        }
    }
    // Delete removed
    foreach ($existing_titles as $id => $row) {
        if (!in_array($id, $handled_ids)) {
            $conn->query("DELETE FROM `".HOW_DYNAMIC_TABLE."` WHERE id=$id");
        }
    }
    
    // Refresh how_titles after saving to show updated values
    $how_titles = [];
    $sql = "SELECT * FROM `".HOW_DYNAMIC_TABLE."` WHERE language_id=$lang_id ORDER BY id ASC";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $how_titles[] = $row;
        }
    }
    if (empty($how_titles)) {
        $how_titles[] = ['id'=>0, 'title'=>'', 'description'=>''];
    }
} else {
    // On GET, fetch the first How page for this language (if any)
    $sql = "SELECT * FROM how_pages WHERE language_id=$lang_id ORDER BY id DESC LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
        $slug = $page['slug'];
    }
}

// After updating/inserting the how_pages row:
$how_page_id = isset($page['id']) ? intval($page['id']) : 0;
$new_slug = $slug;

if ($how_page_id > 0) {
    // Ensure how_page_slugs table exists
    if (!$conn->query("SHOW TABLES LIKE 'how_page_slugs'")) {
        $conn->query("CREATE TABLE IF NOT EXISTS `how_page_slugs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `how_page_id` INT NOT NULL,
            `slug` VARCHAR(255) NOT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive',
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`how_page_id`) REFERENCES `how_pages`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // Set all previous slugs for this page to inactive
    $conn->query("UPDATE how_page_slugs SET status='inactive' WHERE how_page_id=$how_page_id");

    // Check if the new slug already exists
    $res = $conn->query("SELECT * FROM how_page_slugs WHERE how_page_id=$how_page_id AND slug='$new_slug'");
    if ($res && $res->num_rows > 0) {
        // Update to active
        $conn->query("UPDATE how_page_slugs SET status='active' WHERE how_page_id=$how_page_id AND slug='$new_slug'");
    } else {
        // Insert as active
        $conn->query("INSERT INTO how_page_slugs (how_page_id, slug, status) VALUES ($how_page_id, '$new_slug', 'active')");
    }
}

// Set all previous slugs for this page to inactive
// $conn->query("UPDATE how_page_slugs SET status='inactive' WHERE how_page_id=$how_page_id");

// // Check if the new slug already exists
// $res = $conn->query("SELECT * FROM how_page_slugs WHERE how_page_id=$how_page_id AND slug='$new_slug'");
// if ($res && $res->num_rows > 0) {
//     // Update to active
//     $conn->query("UPDATE how_page_slugs SET status='active' WHERE how_page_id=$how_page_id AND slug='$new_slug'");
// } else {
//     // Insert as active
//     $conn->query("INSERT INTO how_page_slugs (how_page_id, slug, status) VALUES ($how_page_id, '$new_slug', 'active')");
// }



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
            <h4>How Page (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
            <!--<div class="url-preview">URL: <b>/<?php echo htmlspecialchars($lang_code); ?>/<?php echo htmlspecialchars($page['slug'] ?? 'how'); ?></b></div>-->
            <div id="translation-alert" style="display:none;"></div>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Page Name</label>
                    <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name'] ?? 'How'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug <?php if (!$page && $english_page): ?><small class="text-muted">(English: <?php echo htmlspecialchars($english_page['slug'] ?? 'how'); ?>)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? ($english_page['slug'] ?? 'how')); ?>" placeholder="<?php echo htmlspecialchars($english_page['slug'] ?? 'how'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Title <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="meta_title" value="<?php echo htmlspecialchars($page['meta_title'] ?? ($english_page['meta_title'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['meta_title'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Description <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control" name="meta_description" rows="2" placeholder="<?php echo htmlspecialchars($english_page['meta_description'] ?? ''); ?>"><?php echo htmlspecialchars($page['meta_description'] ?? ($english_page['meta_description'] ?? '')); ?></textarea>
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Header (Meta Tags)</label>
                    <textarea class="form-control" name="meta_header" rows="2"><?php echo htmlspecialchars($page['meta_header'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Header <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="header" value="<?php echo htmlspecialchars($page['header'] ?? ($english_page['header'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['header'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Heading <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="heading" value="<?php echo htmlspecialchars($page['heading'] ?? ($english_page['heading'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['heading'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control" id="editor" name="description" placeholder="<?php echo htmlspecialchars($english_page['description'] ?? ''); ?>"><?php echo htmlspecialchars($page['description'] ?? ($english_page['description'] ?? '')); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Titles & Descriptions <?php if (!empty($english_titles) && (empty($how_titles) || (count($how_titles) == 1 && empty($how_titles[0]['title'])))): ?><small class="text-muted">(English default shown - ready to translate)</small><?php endif; ?></label>
                    <div id="titlesContainer">
                        <?php 
                        // Merge current language titles with English as fallback
                        $display_titles = [];
                        
                        // If current language has titles, use them
                        if (!empty($how_titles) && !(count($how_titles) == 1 && empty($how_titles[0]['title']))) {
                            $display_titles = $how_titles;
                        } 
                        // Otherwise use English titles as VALUES (not placeholders)
                        elseif (!empty($english_titles)) {
                            foreach ($english_titles as $eng_title) {
                                $display_titles[] = [
                                    'id' => 0, // New entry for this language
                                    'title' => $eng_title['title'],
                                    'description' => $eng_title['description']
                                ];
                            }
                        } 
                        // If nothing exists, show one empty row
                        else {
                            $display_titles[] = ['id' => 0, 'title' => '', 'description' => ''];
                        }
                        
                        foreach ($display_titles as $i => $row): 
                        ?>
                        <div class="title-row row align-items-end">
                            <input type="hidden" name="title_id[]" value="<?php echo isset($row['id']) ? (int)$row['id'] : 0; ?>">
                            <div class="col-md-12 mb-2">
                                <input type="text" class="form-control" name="title[]" placeholder="Title" value="<?php echo htmlspecialchars($row['title'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12 mb-2">
                                <textarea type="text" class="form-control" name="title_description[]" placeholder="Description"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
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
                <hr style="display:none;">
                <h5 style="display:none;">Section 1</h5>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Section 1 Image <?php if (!$page && $english_page && !empty($english_page['section1_image'])): ?><small class="text-muted">(English image available)</small><?php endif; ?></label>
                    <input type="file" class="form-control" name="section1_image">
                    <?php if (!empty($page['section1_image'])): ?>
                        <div class="mt-2"><img src="../uploads/how_sections/<?php echo htmlspecialchars($page['section1_image']); ?>" alt="Section 1 Image" style="max-width:150px;"></div>
                    <?php elseif (!empty($english_page['section1_image'])): ?>
                        <div class="mt-2"><img src="../uploads/how_sections/<?php echo htmlspecialchars($english_page['section1_image']); ?>" alt="English Section 1 Image" style="max-width:150px; opacity:0.6;"><br><small class="text-muted">English image (will be used if no new image uploaded)</small></div>
                    <?php endif; ?>
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Section 1 Title <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="section1_title" value="<?php echo htmlspecialchars($page['section1_title'] ?? ($english_page['section1_title'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['section1_title'] ?? ''); ?>">
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Section 1 Description <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control" name="section1_description" rows="2" placeholder="<?php echo htmlspecialchars($english_page['section1_description'] ?? ''); ?>"><?php echo htmlspecialchars($page['section1_description'] ?? ($english_page['section1_description'] ?? '')); ?></textarea>
                </div>

                <hr style="display:none;">
                <h5 style="display:none;">Section 2</h5>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Section 2 Image <?php if (!$page && $english_page && !empty($english_page['section2_image'])): ?><small class="text-muted">(English image available)</small><?php endif; ?></label>
                    <input type="file" class="form-control" name="section2_image">
                    <?php if (!empty($page['section2_image'])): ?>
                        <div class="mt-2"><img src="../uploads/how_sections/<?php echo htmlspecialchars($page['section2_image']); ?>" alt="Section 2 Image" style="max-width:150px;"></div>
                    <?php elseif (!empty($english_page['section2_image'])): ?>
                        <div class="mt-2"><img src="../uploads/how_sections/<?php echo htmlspecialchars($english_page['section2_image']); ?>" alt="English Section 2 Image" style="max-width:150px; opacity:0.6;"><br><small class="text-muted">English image (will be used if no new image uploaded)</small></div>
                    <?php endif; ?>
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Section 2 Title <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="section2_title" value="<?php echo htmlspecialchars($page['section2_title'] ?? ($english_page['section2_title'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['section2_title'] ?? ''); ?>">
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Section 2 Description <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control" name="section2_description" rows="2" placeholder="<?php echo htmlspecialchars($english_page['section2_description'] ?? ''); ?>"><?php echo htmlspecialchars($page['section2_description'] ?? ($english_page['section2_description'] ?? '')); ?></textarea>
                </div>
                <input type="hidden" name="page_id" value="<?php echo htmlspecialchars($page['id'] ?? ''); ?>">
                <button type="button" class="btn btn-info" id="translateBtn" onclick="translateAndSave()">
                    <i class="fa fa-language"></i> Translate
                </button>
                <!-- <button type="button" class="btn btn-secondary btn-sm" onclick="testTranslation()">
                    <i class="fa fa-flask"></i> Test Translation
                </button> -->
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
      
        <div class="mt-4">
            <h5>Slugs</h5>
            <div id="redirect-alert"></div>
            <?php
    // Fetch all slugs for this HOW page
    $how_page_id = $page['id'] ?? 0;
    $slugs = [];
    if ($how_page_id) {
        $res = $conn->query("SELECT * FROM how_page_slugs WHERE how_page_id=$how_page_id ORDER BY id ASC");
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
    
    $.ajax({
        url: '',
        type: 'POST',
        data: {delete_redirect_id: id},
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                $('#redirect-row-' + id).remove();
                $('#redirect-alert').html('<div class="alert alert-success">Slug deleted successfully.</div>');
                // Auto-hide the alert after 3 seconds
                setTimeout(function() {
                    $('#redirect-alert .alert').fadeOut();
                }, 3000);
            } else {
                $('#redirect-alert').html('<div class="alert alert-danger">Failed to delete slug: ' + (data.error || 'Unknown error') + '</div>');
            }
        },
        error: function() {
            $('#redirect-alert').html('<div class="alert alert-danger">Failed to delete slug. Please try again.</div>');
        }
    });
}

// Translation function
async function translateAndSave() {
    const langCode = '<?php echo $lang_code; ?>';
    const langName = '<?php echo htmlspecialchars($lang['name']); ?>';
    
    if (!confirm(`Translate all content to ${langName} (${langCode})?\n\nNote: Please review the translations before saving.`)) {
        return;
    }
    
    // Show loading
    const btn = $('#translateBtn');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Translating...');
    
    // Show progress alert
    $('#translation-alert').html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Translating content to ' + langName + '... Please wait.</div>').show();
    
    let translatedCount = 0;
    let failedCount = 0;
    
    // Get all fields that need translation
    const fields = [
        {name: 'meta_title', selector: 'input[name="meta_title"]'},
        {name: 'meta_description', selector: 'textarea[name="meta_description"]'},
        {name: 'header', selector: 'input[name="header"]'},
        {name: 'heading', selector: 'input[name="heading"]'},
        {name: 'description', selector: '#editor', isCKEditor: true},
        {name: 'section1_title', selector: 'input[name="section1_title"]'},
        {name: 'section1_description', selector: 'textarea[name="section1_description"]'},
        {name: 'section2_title', selector: 'input[name="section2_title"]'},
        {name: 'section2_description', selector: 'textarea[name="section2_description"]'}
    ];
    
    try {
        // Translate each field
        for (let field of fields) {
            let text;
            if (field.isCKEditor) {
                text = CKEDITOR.instances.editor.getData();
            } else {
                text = $(field.selector).val();
            }
            
            console.log(`Checking ${field.name}:`, text ? text.substring(0, 50) : 'empty');
            
            if (text && text.trim()) {
                try {
                    $('#translation-alert').html(`<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Translating ${field.name}...</div>`).show();
                    
                    const translated = await translateField(text, langCode);
                    console.log(`Translated ${field.name}:`, translated ? translated.substring(0, 50) : 'empty');
                    
                    if (translated && translated.trim()) {
                        if (field.isCKEditor) {
                            CKEDITOR.instances.editor.setData(translated);
                        } else {
                            $(field.selector).val(translated);
                        }
                        translatedCount++;
                    } else {
                        console.warn(`Empty translation for ${field.name}`);
                        failedCount++;
                    }
                } catch (error) {
                    console.error(`Failed to translate ${field.name}:`, error);
                    failedCount++;
                }
                // Small delay to avoid rate limiting
                await new Promise(resolve => setTimeout(resolve, 500));
            } else {
                console.log(`Skipping ${field.name}: no content`);
            }
        }
        
        // Translate dynamic titles
        const titleInputs = $('input[name="title[]"]');
        const descInputs = $('textarea[name="title_description[]"]');
        
        console.log(`Found ${titleInputs.length} title/description pairs to check`);
        
        for (let i = 0; i < titleInputs.length; i++) {
            const titleText = $(titleInputs[i]).val();
            console.log(`Checking title ${i + 1}:`, titleText ? titleText.substring(0, 50) : 'empty');
            
            if (titleText && titleText.trim()) {
                try {
                    $('#translation-alert').html(`<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Translating title ${i + 1}: "${titleText.substring(0, 30)}..."</div>`).show();
                    const translated = await translateField(titleText, langCode);
                    console.log(`Translated title ${i + 1}:`, translated ? translated.substring(0, 50) : 'empty');
                    
                    if (translated && translated.trim()) {
                        $(titleInputs[i]).val(translated);
                        translatedCount++;
                    }
                } catch (error) {
                    console.error(`Failed to translate title ${i + 1}:`, error);
                    failedCount++;
                }
                await new Promise(resolve => setTimeout(resolve, 500));
            }
            
            const descText = $(descInputs[i]).val();
            console.log(`Checking description ${i + 1}:`, descText ? descText.substring(0, 50) : 'empty');
            
            if (descText && descText.trim()) {
                try {
                    $('#translation-alert').html(`<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Translating description ${i + 1}: "${descText.substring(0, 30)}..."</div>`).show();
                    const translated = await translateField(descText, langCode);
                    console.log(`Translated description ${i + 1}:`, translated ? translated.substring(0, 50) : 'empty');
                    
                    if (translated && translated.trim()) {
                        $(descInputs[i]).val(translated);
                        translatedCount++;
                    }
                } catch (error) {
                    console.error(`Failed to translate description ${i + 1}:`, error);
                    failedCount++;
                }
                await new Promise(resolve => setTimeout(resolve, 500));
            }
        }
        
        // Reset button
        btn.prop('disabled', false).html(originalText);
        
        // Show success message
        if (translatedCount > 0) {
            let message = `<div class="alert alert-success">
                <strong><i class="fa fa-check-circle"></i> Translation Completed!</strong><br>
                ✓ ${translatedCount} field(s) translated successfully<br>
                ${failedCount > 0 ? '✗ ' + failedCount + ' field(s) failed<br>' : ''}
                <strong>Please review the translations below and click "Save" when ready.</strong>
            </div>`;
            $('#translation-alert').html(message).show();
            
            // Scroll to top to see the message
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        } else {
            let message = `<div class="alert alert-warning">
                <strong><i class="fa fa-exclamation-triangle"></i> No content was translated</strong><br>
                Possible reasons:<br>
                • Fields may be empty<br>
                • Language code may be incorrect (Current: ${langCode})<br>
                • Translation service may be unavailable<br>
                ${failedCount > 0 ? '• ' + failedCount + ' fields failed to translate<br>' : ''}
                <strong>Open browser console (F12) to see detailed logs.</strong>
            </div>`;
            $('#translation-alert').html(message).show();
            
            // Scroll to top to see the message
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        }
        
    } catch (error) {
        console.error('Translation error:', error);
        $('#translation-alert').html('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Translation failed:</strong> ' + error + '<br>Please try again or translate manually.</div>').show();
        btn.prop('disabled', false).html(originalText);
        
        // Scroll to top to see the error
        $('html, body').animate({ scrollTop: 0 }, 'fast');
    }
}

// Test translation function for debugging
async function testTranslation() {
    const langCode = '<?php echo $lang_code; ?>';
    const langName = '<?php echo htmlspecialchars($lang['name']); ?>';
    
    console.log('Testing translation with language:', langName, 'Code:', langCode);
    
    const testText = "Hello, this is a test translation";
    
    $('#translation-alert').html(`<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing translation to ${langName} (${langCode})...</div>`).show();
    
    try {
        const translated = await translateField(testText, langCode);
        console.log('Test translation result:', translated);
        
        $('#translation-alert').html(`<div class="alert alert-success">
            <strong><i class="fa fa-check-circle"></i> Translation Test Successful!</strong><br>
            <strong>Language:</strong> ${langName} (${langCode})<br>
            <strong>Original:</strong> ${testText}<br>
            <strong>Translated:</strong> ${translated}<br>
            <br>
            If the translation looks correct, the "Translate" button should work properly.
        </div>`).show();
    } catch (error) {
        console.error('Test translation failed:', error);
        $('#translation-alert').html(`<div class="alert alert-danger">
            <strong><i class="fa fa-exclamation-circle"></i> Translation Test Failed</strong><br>
            <strong>Language:</strong> ${langName} (${langCode})<br>
            <strong>Error:</strong> ${error}<br>
            <br>
            <strong>Possible solutions:</strong><br>
            • Check your internet connection<br>
            • Try a different language code<br>
            • Check browser console (F12) for details
        </div>`).show();
    }
}

function translateField(text, targetLang) {
    return new Promise((resolve, reject) => {
        console.log('Sending translation request:', {
            textLength: text.length,
            targetLang: targetLang,
            preview: text.substring(0, 100)
        });
        
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                translate_content: true,
                text: text,
                target_lang: targetLang
            },
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            success: function(data) {
                console.log('Translation response:', data);
                
                if (data && data.success && data.translated) {
                    resolve(data.translated);
                } else {
                    console.error('Translation response invalid:', data);
                    reject(data.error || 'Invalid translation response');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                // Try to parse error response
                let errorMsg = error || 'Network error';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.error) {
                        errorMsg = response.error;
                    }
                } catch (e) {
                    // Response wasn't JSON - probably a PHP error
                    if (xhr.responseText && xhr.responseText.length > 0) {
                        // Extract readable error from HTML
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = xhr.responseText;
                        const textContent = tempDiv.textContent || tempDiv.innerText || '';
                        errorMsg = 'Server error: ' + textContent.substring(0, 200);
                        console.error('Raw PHP error:', xhr.responseText.substring(0, 500));
                    }
                }
                
                reject(errorMsg);
            }
        });
    });
}
    </script>
</body>
</html> 