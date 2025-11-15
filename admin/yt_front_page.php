<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';
require_once '../includes/yt_frontend.php';

$lang_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_key = isset($_GET['page']) ? strtolower($_GET['page']) : 'home';

$pages = yt_frontend_registry();
if (!$lang_id) {
    die('Language not found.');
}
if (!isset($pages[$page_key])) {
    die('Invalid page key.');
}

$lang_res = $conn->query("SELECT * FROM languages WHERE id={$lang_id} LIMIT 1");
if (!$lang_res || $lang_res->num_rows === 0) {
    die('Language not found.');
}
$language = $lang_res->fetch_assoc();

$fields = yt_frontend_fields($page_key);
$defaults = yt_frontend_defaults($page_key);
$base_language_id = yt_frontend_default_language_id($conn);
$base_strings = yt_frontend_resolve_strings($conn, $base_language_id ?: $lang_id, $page_key);
$current_strings = yt_frontend_fetch_row($conn, $lang_id, $page_key) ?? [];
if (!is_array($current_strings)) {
    $current_strings = [];
}

// Normalize image paths and remove defaults so they can fall back
foreach ($current_strings as $key => $value) {
    if (isset($fields[$key]) && ($fields[$key]['type'] ?? '') === 'image') {
        $normalized = yt_frontend_normalize_image_path((string)$value);
        $current_strings[$key] = $normalized;
        if ($normalized === '' || $normalized === ($defaults[$key] ?? '')) {
            unset($current_strings[$key]);
        }
    }
}

$display_strings = yt_frontend_apply_image_defaults(array_merge($defaults, $current_strings), $page_key);

$status_message = '';
$status_type = 'success';

// Handle FAQ operations (add/edit/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['faq_action'])) {
    $action = $_POST['faq_action'];
    
    if ($action === 'save_faqs' && $page_key === 'home') {
        $faq_ids = $_POST['faq_id'] ?? [];
        $faq_questions = $_POST['faq_question'] ?? [];
        $faq_answers = $_POST['faq_answer'] ?? [];
        
        // Get existing FAQs
        $existing_faqs = [];
        $res = $conn->query("SELECT * FROM language_faqs WHERE language_id={$lang_id} ORDER BY id ASC");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $existing_faqs[$row['id']] = $row;
            }
        }
        
        $handled_ids = [];
        for ($i = 0; $i < count($faq_questions); $i++) {
            $question = trim($faq_questions[$i] ?? '');
            $answer = trim($faq_answers[$i] ?? '');
            $faq_id = isset($faq_ids[$i]) ? intval($faq_ids[$i]) : 0;
            
            if ($question && $answer) {
                $q_esc = $conn->real_escape_string($question);
                $a_esc = $conn->real_escape_string($answer);
                
                if ($faq_id && isset($existing_faqs[$faq_id])) {
                    // Update existing
                    $conn->query("UPDATE language_faqs SET question='$q_esc', answer='$a_esc' WHERE id=$faq_id AND language_id=$lang_id");
                    $handled_ids[] = $faq_id;
                } else {
                    // Insert new
                    $conn->query("INSERT INTO language_faqs (language_id, question, answer) VALUES ($lang_id, '$q_esc', '$a_esc')");
                    $handled_ids[] = $conn->insert_id;
                }
            }
        }
        
        // Delete removed FAQs
        foreach ($existing_faqs as $id => $row) {
            if (!in_array($id, $handled_ids)) {
                $conn->query("DELETE FROM language_faqs WHERE id=$id");
            }
        }
        
        $status_message = 'FAQs saved successfully.';
    } elseif ($action === 'delete_faq' && isset($_POST['faq_id'])) {
        $faq_id = intval($_POST['faq_id']);
        $conn->query("DELETE FROM language_faqs WHERE id=$faq_id AND language_id=$lang_id");
        $status_message = 'FAQ deleted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['faq_action'])) {
    $payload = [];
    
    // Handle image uploads
    $upload_dir = __DIR__ . '/../updated_frontend/client_frontend/images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    foreach ($fields as $key => $meta) {
        $field_type = $meta['type'] ?? 'text';
        
        if ($field_type === 'image') {
            // Handle image upload
            $upload_key = $key . '_upload';
            if (isset($_FILES[$upload_key]) && $_FILES[$upload_key]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$upload_key];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    // Use original filename pattern but keep it organized
                    $filename = $key . '_' . time() . '.' . $ext;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        // Delete old uploaded image if it exists and is not a default
                        $old_image = $current_strings[$key] ?? '';
                        if (!empty($old_image) && $old_image !== ($defaults[$key] ?? '') && strpos($old_image, 'images/') === 0) {
                            $old_path = __DIR__ . '/../updated_frontend/client_frontend/' . $old_image;
                            if (file_exists($old_path) && strpos(realpath($old_path), realpath($upload_dir)) === 0) {
                                @unlink($old_path);
                            }
                        }
                        $payload[$key] = yt_frontend_normalize_image_path('images/' . $filename);
                    } else {
                        // Keep existing or default
                        $payload[$key] = $current_strings[$key] ?? ($defaults[$key] ?? '');
                    }
                } else {
                    // Keep existing or default
                    $payload[$key] = $current_strings[$key] ?? ($defaults[$key] ?? '');
                }
            } else {
                // Use provided URL/path or keep default
                $raw = $_POST[$key] ?? '';
                $value = trim($raw);
                if (!empty($value) && $value !== ($defaults[$key] ?? '')) {
                    $payload[$key] = yt_frontend_normalize_image_path($value);
                } else {
                    // Blank or default string means fall back to default image
                    $payload[$key] = '';
                }
            }
        } else {
            // Handle text/textarea fields
            $raw = $_POST[$key] ?? '';
            if (!empty($meta['allow_html'])) {
                $value = trim($raw);
            } else {
                $value = trim(strip_tags($raw));
            }
            $payload[$key] = $value;
        }
    }

    // Remove image fields that are empty or same as default (let them fall back to defaults)
    foreach ($payload as $key => $value) {
        if (isset($fields[$key]) && $fields[$key]['type'] === 'image') {
            $default_value = $defaults[$key] ?? '';
            if (empty($value) || $value === $default_value) {
                // Don't save empty or default values - let them fall back to defaults
                unset($payload[$key]);
            }
        }
    }
    
    yt_frontend_save_strings($conn, $lang_id, $page_key, $payload, $_SESSION['admin_username'] ?? 'admin');
    $current_strings = yt_frontend_fetch_row($conn, $lang_id, $page_key) ?? [];
    if (!is_array($current_strings)) {
        $current_strings = [];
    }
    foreach ($current_strings as $key => $value) {
        if (isset($fields[$key]) && ($fields[$key]['type'] ?? '') === 'image') {
            $normalized = yt_frontend_normalize_image_path((string)$value);
            $current_strings[$key] = $normalized;
            if ($normalized === '' || $normalized === ($defaults[$key] ?? '')) {
                unset($current_strings[$key]);
            }
        }
    }
    $display_strings = yt_frontend_apply_image_defaults(array_merge($defaults, $current_strings), $page_key);
    if (empty($status_message)) {
        $status_message = 'Content saved successfully.';
    }
}

// Fetch FAQs for this language (only for home page)
$faqs = [];
if ($page_key === 'home') {
    $res = $conn->query("SELECT * FROM language_faqs WHERE language_id={$lang_id} ORDER BY id ASC");
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $faqs[] = $row;
        }
    }
    // If no FAQs exist, add one empty FAQ for editing
    if (empty($faqs)) {
        $faqs[] = ['id' => 0, 'question' => '', 'answer' => ''];
    }
}

include 'includes/header.php';

function yt_grouped_fields(array $fields): array
{
    $grouped = [];
    foreach ($fields as $key => $meta) {
        $group = $meta['group'] ?? 'Content';
        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][$key] = $meta;
    }
    return $grouped;
}

$grouped_fields = yt_grouped_fields($fields);
$page_label = $pages[$page_key]['label'];
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="language_pages.php?id=<?php echo $lang_id; ?>" class="btn btn-outline-secondary">
                &laquo; Back to Languages
            </a>
            <div>
                <span class="fw-bold"><?php echo htmlspecialchars($language['name']); ?></span>
                <span class="text-muted">/ <?php echo htmlspecialchars($page_label); ?></span>
            </div>
        </div>

        <?php if ($status_message): ?>
            <div class="alert alert-<?php echo $status_type; ?>">
                <?php echo htmlspecialchars($status_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Edit <?php echo htmlspecialchars($page_label); ?> Content</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="copyEnglishBtn">
                    Use English Defaults
                </button>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <?php foreach ($grouped_fields as $group => $group_fields): ?>
                        <div class="mb-4">
                            <h5 class="mb-3 border-bottom pb-1"><?php echo htmlspecialchars($group); ?></h5>
                            <?php foreach ($group_fields as $key => $meta):
                                $current = $display_strings[$key] ?? '';
                                $english_value = $base_strings[$key] ?? ($defaults[$key] ?? '');
                                $field_type = $meta['type'] ?? 'text';
                                $is_textarea = $field_type === 'textarea';
                                $is_image = $field_type === 'image';
                                $custom_value = $current_strings[$key] ?? '';
                                $is_custom_image = $is_image && $custom_value !== '';
                                ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <?php echo htmlspecialchars($meta['label'] ?? $key); ?>
                                        <small class="text-muted d-block">English: <?php echo htmlspecialchars($english_value); ?></small>
                                    </label>
                                    <?php if ($is_image): ?>
                                        <div class="d-flex gap-3 align-items-start">
                                            <div class="flex-grow-1">
                                                <label class="form-label fw-bold">Upload New Image</label>
                                                <input
                                                    type="file"
                                                    class="form-control"
                                                    name="<?php echo htmlspecialchars($key); ?>_upload"
                                                    accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                                                    id="<?php echo htmlspecialchars($key); ?>_upload"
                                                >
                                                <small class="text-muted d-block mt-1">Choose a file to replace the current image (max 5MB)</small>
                                                <label class="form-label fw-bold mt-3">Or Enter Image URL/Path</label>
                                                <input
                                                    type="text"
                                                    class="form-control"
                                                    name="<?php echo htmlspecialchars($key); ?>"
                                                    value="<?php echo htmlspecialchars($custom_value ?: $english_value); ?>"
                                                    placeholder="e.g., images/logo.webp"
                                                    data-default-value="<?php echo htmlspecialchars($english_value); ?>"
                                                >
                                                <small class="text-muted d-block mt-1">Leave empty to use default image</small>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <label class="form-label d-block">Current Image Preview</label>
                                                <?php 
                                                $preview_image = !empty($custom_value) ? $custom_value : $current;
                                                if (!empty($preview_image)): 
                                                ?>
                                                    <img src="/<?php echo htmlspecialchars($preview_image); ?>" 
                                                         alt="Preview" 
                                                         id="preview_<?php echo htmlspecialchars($key); ?>"
                                                         style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; display: block;"
                                                         onerror="this.style.display='none'">
                                                    <small class="text-muted d-block mt-1 text-center">
                                                        <?php echo $is_custom_image ? 'Custom Image' : 'Default Image'; ?>
                                                    </small>
                                                <?php else: ?>
                                                    <div style="width: 150px; height: 150px; border: 1px solid #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; background: #f5f5f5;">
                                                        <small class="text-muted">No image</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <script>
                                        (function() {
                                            var uploadInput = document.getElementById('<?php echo htmlspecialchars($key); ?>_upload');
                                            var previewImg = document.getElementById('preview_<?php echo htmlspecialchars($key); ?>');
                                            if (uploadInput && previewImg) {
                                                uploadInput.addEventListener('change', function(e) {
                                                    var file = e.target.files[0];
                                                    if (file) {
                                                        var reader = new FileReader();
                                                        reader.onload = function(e) {
                                                            previewImg.src = e.target.result;
                                                            previewImg.style.display = 'block';
                                                        };
                                                        reader.readAsDataURL(file);
                                                    }
                                                });
                                            }
                                        })();
                                        </script>
                                    <?php elseif ($is_textarea): ?>
                                        <textarea
                                            class="form-control"
                                            name="<?php echo htmlspecialchars($key); ?>"
                                            rows="3"
                                            data-default-value="<?php echo htmlspecialchars($english_value); ?>"
                                        ><?php echo htmlspecialchars($current); ?></textarea>
                                    <?php else: ?>
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="<?php echo htmlspecialchars($key); ?>"
                                            value="<?php echo htmlspecialchars($current); ?>"
                                            data-default-value="<?php echo htmlspecialchars($english_value); ?>"
                                        >
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($page_key === 'home'): ?>
        <!-- FAQ Management Section -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Manage FAQs</span>
                <button type="button" class="btn btn-sm btn-success" id="addFaqBtn">
                    <i class="fa-solid fa-plus"></i> Add FAQ
                </button>
            </div>
            <div class="card-body">
                <form method="post" id="faqForm">
                    <input type="hidden" name="faq_action" value="save_faqs">
                    <div id="faqList">
                        <?php foreach ($faqs as $index => $faq): ?>
                            <div class="faq-item mb-3 p-3 border rounded" data-faq-id="<?php echo $faq['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong>FAQ #<?php echo $index + 1; ?></strong>
                                    <button type="button" class="btn btn-sm btn-danger delete-faq-btn" data-faq-id="<?php echo $faq['id']; ?>">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </div>
                                <input type="hidden" name="faq_id[]" value="<?php echo $faq['id']; ?>">
                                <div class="mb-2">
                                    <label class="form-label">Question</label>
                                    <input type="text" class="form-control" name="faq_question[]" 
                                           value="<?php echo htmlspecialchars($faq['question']); ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Answer</label>
                                    <textarea class="form-control" name="faq_answer[]" rows="3" required><?php echo htmlspecialchars($faq['answer']); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary">Save FAQs</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var copyBtn = document.getElementById('copyEnglishBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            document.querySelectorAll('[data-default-value]').forEach(function (field) {
                var value = field.getAttribute('data-default-value');
                if (value !== null) {
                    if (field.tagName === 'TEXTAREA') {
                        field.value = value;
                    } else {
                        field.value = value;
                    }
                }
            });
        });
    }

    // FAQ Management
    var addFaqBtn = document.getElementById('addFaqBtn');
    var faqList = document.getElementById('faqList');
    var faqCounter = <?php echo count($faqs); ?>;

    if (addFaqBtn && faqList) {
        addFaqBtn.addEventListener('click', function() {
            faqCounter++;
            var newFaq = document.createElement('div');
            newFaq.className = 'faq-item mb-3 p-3 border rounded';
            newFaq.innerHTML = 
                '<div class="d-flex justify-content-between align-items-start mb-2">' +
                    '<strong>FAQ #' + faqCounter + '</strong>' +
                    '<button type="button" class="btn btn-sm btn-danger delete-faq-btn" data-faq-id="0">' +
                        '<i class="fa-solid fa-trash"></i> Delete' +
                    '</button>' +
                '</div>' +
                '<input type="hidden" name="faq_id[]" value="0">' +
                '<div class="mb-2">' +
                    '<label class="form-label">Question</label>' +
                    '<input type="text" class="form-control" name="faq_question[]" required>' +
                '</div>' +
                '<div class="mb-2">' +
                    '<label class="form-label">Answer</label>' +
                    '<textarea class="form-control" name="faq_answer[]" rows="3" required></textarea>' +
                '</div>';
            faqList.appendChild(newFaq);
        });

        // Delete FAQ handler
        faqList.addEventListener('click', function(e) {
            if (e.target.closest('.delete-faq-btn')) {
                var btn = e.target.closest('.delete-faq-btn');
                var faqId = btn.getAttribute('data-faq-id');
                var faqItem = btn.closest('.faq-item');
                
                if (faqId && faqId !== '0') {
                    // Delete from database via AJAX
                    if (confirm('Are you sure you want to delete this FAQ?')) {
                        var formData = new FormData();
                        formData.append('faq_action', 'delete_faq');
                        formData.append('faq_id', faqId);
                        
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        }).then(function() {
                            location.reload();
                        });
                    }
                } else {
                    // Just remove from DOM if it's a new FAQ
                    faqItem.remove();
                    // Renumber FAQs
                    var items = faqList.querySelectorAll('.faq-item');
                    items.forEach(function(item, index) {
                        item.querySelector('strong').textContent = 'FAQ #' + (index + 1);
                    });
                }
            }
        });
    }
})();
</script>

<?php
include 'includes/footer.php';
