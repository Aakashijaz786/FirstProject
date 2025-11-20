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
$language_direction = $language['direction'] ?? 'LTR';
$language_slug = '';
$language_home_exists = false;
$legacy_home_res = $conn->query("SELECT slug FROM languages_home WHERE language_id={$lang_id} LIMIT 1");
if ($legacy_home_res && $legacy_home_res->num_rows > 0) {
    $legacy_home_row = $legacy_home_res->fetch_assoc();
    $language_slug = $legacy_home_row['slug'] ?? '';
    $language_home_exists = true;
}

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
    
    if ($action === 'save_faqs') {
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
            $answer_raw = $faq_answers[$i] ?? '';
            // Don't trim answer - preserve whitespace and HTML from CKEditor
            $answer = $answer_raw;
            $faq_id = isset($faq_ids[$i]) ? intval($faq_ids[$i]) : 0;
            
            // Save FAQ if question is not empty (answer can be empty initially)
            if ($question) {
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
        
        // Save FAQ heading if provided
        if (array_key_exists('faqTitle', $_POST)) {
            $faq_title_raw = $_POST['faqTitle'] ?? '';
            $faq_title_value = !empty($fields['faqTitle']['allow_html'])
                ? trim($faq_title_raw)
                : trim(strip_tags($faq_title_raw));
            $faq_payload = $current_strings;
            if (!is_array($faq_payload)) {
                $faq_payload = [];
            }
            $faq_payload['faqTitle'] = $faq_title_value;
            yt_frontend_save_strings($conn, $lang_id, $page_key, $faq_payload, $_SESSION['admin_username'] ?? 'admin');
            $current_strings['faqTitle'] = $faq_title_value;
            $display_strings['faqTitle'] = $faq_title_value;
        }
    } elseif ($action === 'delete_faq' && isset($_POST['faq_id'])) {
        $faq_id = intval($_POST['faq_id']);
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        if ($faq_id > 0) {
            $delete_query = "DELETE FROM language_faqs WHERE id=$faq_id AND language_id=$lang_id";
            if ($conn->query($delete_query)) {
                $status_message = 'FAQ deleted successfully.';
                $status_type = 'success';
                // Return JSON response for AJAX requests
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'FAQ deleted successfully.']);
                    exit;
                }
            } else {
                $error_msg = 'Failed to delete FAQ: ' . $conn->error;
                $status_message = $error_msg;
                $status_type = 'danger';
                // Return JSON response for AJAX requests
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => $error_msg]);
                    exit;
                }
            }
        } else {
            $error_msg = 'Invalid FAQ ID.';
            $status_message = $error_msg;
            $status_type = 'danger';
            // Return JSON response for AJAX requests
            if ($is_ajax) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit;
            }
        }
    }
}

// Handle FAQ saving when main form is submitted (if FAQ data is present)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['faq_action']) && isset($_POST['faq_question']) && is_array($_POST['faq_question'])) {
    // Save FAQs along with main content
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
        $answer_raw = $faq_answers[$i] ?? '';
        // Don't trim answer - preserve whitespace and HTML from CKEditor
        $answer = $answer_raw;
        $faq_id = isset($faq_ids[$i]) ? intval($faq_ids[$i]) : 0;
        
        // Save FAQ if question is not empty (answer can be empty initially)
        if ($question) {
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
    
    // Set success message for FAQ save
    if (!isset($status_message)) {
        $status_message = 'Content and FAQs saved successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['faq_action'])) {
    if ($page_key === 'home') {
        if (isset($_POST['language_direction'])) {
            $new_direction = strtoupper($_POST['language_direction']) === 'RTL' ? 'RTL' : 'LTR';
            if ($new_direction !== $language_direction) {
                $conn->query("UPDATE languages SET direction='" . $conn->real_escape_string($new_direction) . "' WHERE id={$lang_id}");
                $language_direction = $new_direction;
            }
        }
        if (isset($_POST['home_slug'])) {
            $new_slug = strtolower(trim($_POST['home_slug']));
            $new_slug = preg_replace('/[^a-z0-9\-]/', '-', $new_slug);
            if ($language_home_exists) {
                $conn->query("UPDATE languages_home SET slug='" . $conn->real_escape_string($new_slug) . "' WHERE language_id={$lang_id}");
            }
            $language_slug = $new_slug;
        }
    }

    $payload = $current_strings;
    
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
            $has_upload = isset($_FILES[$upload_key]) && $_FILES[$upload_key]['error'] === UPLOAD_ERR_OK;
            $has_path_value = array_key_exists($key, $_POST);
            if (!$has_upload && !$has_path_value) {
                continue;
            }
            if ($has_upload) {
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
            // For text/textarea fields, check if they exist in POST
            // Note: CKEditor fields might send empty strings, so we check array_key_exists
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            // Handle text/textarea fields
            $raw = $_POST[$key] ?? '';
            if (!empty($meta['allow_html'])) {
                $value = trim($raw);
            } else {
                $value = trim(strip_tags($raw));
            }
            // Always save the value if the field exists in POST (even if empty)
            // This allows users to clear fields and save empty values
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
    
    // Ensure faqTitle is always saved if it was submitted (even if empty or same as default)
    // This allows users to explicitly set and save the FAQ heading
    if (array_key_exists('faqTitle', $_POST)) {
        $faq_title_value = trim($_POST['faqTitle'] ?? '');
        $payload['faqTitle'] = $faq_title_value;
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

// Fetch FAQs for this language (for all pages)
$faqs = [];
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

include 'includes/header.php';
?>
<style>
.page-section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}
.page-section h4 {
    margin-bottom: 20px;
    font-weight: 600;
    color: #333;
}
.page-section h3 {
    margin-top: 30px;
    margin-bottom: 10px;
    font-weight: 600;
    color: #333;
    font-size: 1.25rem;
}
.page-section hr {
    margin-top: 10px;
    margin-bottom: 20px;
    border-color: #dee2e6;
}
.page-section .form-label {
    font-weight: 500;
    margin-bottom: 8px;
    color: #495057;
}
.page-section .border.rounded {
    border: 1px solid #dee2e6 !important;
    border-radius: 6px !important;
}
.page-section .image-item,
.page-section .add-column-item,
.page-section .faq-item {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 15px;
}
.page-section .form-control {
    border: 1px solid #ced4da;
    border-radius: 4px;
}
.page-section .form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
.page-section .btn {
    border-radius: 4px;
    font-weight: 500;
}
.promo-image-minimal {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.promo-image-minimal-preview {
    min-height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
}
</style>
<?php

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

function yt_admin_preview_url(string $path): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $trimmed)) {
        return $trimmed;
    }
    $normalized = ltrim($trimmed, '/');
    if (strpos($normalized, 'uploads/') === 0) {
        return '/' . $normalized;
    }
    if (strpos($normalized, 'updated_frontend/') === 0) {
        return '/' . $normalized;
    }
    return '/updated_frontend/client_frontend/' . $normalized;
}

$grouped_fields = yt_grouped_fields($fields);
$page_label = $pages[$page_key]['label'];

$renderField = function (string $key, array $options = []) use ($fields, $display_strings, $base_strings, $current_strings, $defaults) {
    if (!isset($fields[$key])) {
        return '';
    }
    $meta = $fields[$key];
    $label = $options['label'] ?? ($meta['label'] ?? $key);
    $input_id = $options['id'] ?? ('field_' . $key);
    $field_type = $options['type'] ?? ($meta['type'] ?? 'text');
    $is_textarea = $field_type === 'textarea';
    $is_image = $field_type === 'image';
    $current = $display_strings[$key] ?? '';
    $english_value = $base_strings[$key] ?? ($defaults[$key] ?? '');
    $custom_value = $current_strings[$key] ?? '';
    $show_image_path = $options['show_image_path'] ?? false;
    $default_show_english = !$is_image;
    $show_english = $options['show_english'] ?? $default_show_english;
    $minimal_image = !empty($options['minimal_image']);
    ob_start();
    ?>
    <div class="mb-3">
        <label class="form-label" for="<?php echo htmlspecialchars($input_id); ?>">
            <?php echo htmlspecialchars($label); ?>
            <?php 
            // Skip showing "English:" for fields that already have custom display (like faqTitle, stepsTitle)
            $skip_english_display = ($key === 'faqTitle' || $key === 'stepsTitle') && empty($label);
            if ($show_english && $english_value !== '' && !$skip_english_display): 
            ?>
                <small class="text-muted d-block">English: <?php echo htmlspecialchars(strip_tags($english_value)); ?></small>
            <?php endif; ?>
        </label>
        <?php if ($is_image): ?>
            <?php if ($minimal_image): ?>
                <div class="promo-image-minimal">
                    <div class="promo-image-minimal-preview border rounded text-center p-3">
                        <?php
                        $preview_image = !empty($custom_value) ? $custom_value : $current;
                        $preview_src = yt_admin_preview_url($preview_image);
                        ?>
                        <?php if (!empty($preview_src)): ?>
                            <img src="<?php echo htmlspecialchars($preview_src); ?>" alt="Preview" id="preview_<?php echo htmlspecialchars($key); ?>" style="max-width: 100%; height: auto; object-fit: contain;">
                        <?php else: ?>
                            <div class="text-muted small" id="preview_<?php echo htmlspecialchars($key); ?>_placeholder">No image selected</div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2">
                        <input
                            type="file"
                            class="form-control form-control-sm"
                            name="<?php echo htmlspecialchars($key); ?>_upload"
                            accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                            id="<?php echo htmlspecialchars($key); ?>_upload"
                        >
                        <?php if (!$show_image_path): ?>
                            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($custom_value ?: $english_value); ?>">
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                (function() {
                    var uploadInput = document.getElementById('<?php echo htmlspecialchars($key); ?>_upload');
                    var previewImg = document.getElementById('preview_<?php echo htmlspecialchars($key); ?>');
                    var placeholder = document.getElementById('preview_<?php echo htmlspecialchars($key); ?>_placeholder');
                    if (uploadInput) {
                        uploadInput.addEventListener('change', function(e) {
                            var file = e.target.files[0];
                            if (file) {
                                var reader = new FileReader();
                                reader.onload = function(evt) {
                                    if (placeholder) {
                                        placeholder.style.display = 'none';
                                    }
                                    if (!previewImg) {
                                        previewImg = document.createElement('img');
                                        previewImg.id = 'preview_<?php echo htmlspecialchars($key); ?>';
                                        previewImg.style.maxWidth = '100%';
                                        previewImg.style.height = 'auto';
                                        previewImg.style.objectFit = 'contain';
                                        uploadInput.closest('.promo-image-minimal').querySelector('.promo-image-minimal-preview').appendChild(previewImg);
                                    }
                                    previewImg.src = evt.target.result;
                                    previewImg.style.display = 'block';
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }
                })();
                </script>
            <?php else: ?>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label fw-bold">Upload New Image</label>
                    <input
                        type="file"
                        class="form-control"
                        name="<?php echo htmlspecialchars($key); ?>_upload"
                        accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                        id="<?php echo htmlspecialchars($key); ?>_upload"
                    >
                    <small class="text-muted d-block mt-1">Leave empty to keep the current image.</small>
                    <?php if ($show_image_path): ?>
                        <label class="form-label fw-bold mt-3">Image Path / URL</label>
                        <input
                            type="text"
                            class="form-control"
                            name="<?php echo htmlspecialchars($key); ?>"
                            value="<?php echo htmlspecialchars($custom_value ?: $english_value); ?>"
                            placeholder="e.g., images/feature-icon.webp"
                            data-default-value="<?php echo htmlspecialchars($english_value); ?>"
                        >
                    <?php else: ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($custom_value ?: $english_value); ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-5 text-center">
                    <div class="border rounded p-2">
                        <?php $preview_image = !empty($custom_value) ? $custom_value : $current; ?>
                        <?php $preview_src = yt_admin_preview_url($preview_image); ?>
                        <?php if (!empty($preview_src)): ?>
                            <img src="<?php echo htmlspecialchars($preview_src); ?>" alt="Preview" id="preview_<?php echo htmlspecialchars($key); ?>" style="max-width: 160px; max-height: 160px; width: 100%; object-fit: contain;">
                            <small class="text-muted d-block mt-2"><?php echo !empty($custom_value) ? 'Custom Image' : 'Default Image'; ?></small>
                        <?php else: ?>
                            <span class="text-muted small">No image available</span>
                        <?php endif; ?>
                    </div>
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
                            reader.onload = function(evt) {
                                previewImg.src = evt.target.result;
                                previewImg.style.display = 'block';
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }
            })();
            </script>
            <?php endif; ?>
        <?php elseif ($is_textarea): ?>
            <?php
            $classes = 'form-control';
            if (!empty($options['ckeditor'])) {
                $classes .= ' js-ckeditor';
            }
            $rows = $options['rows'] ?? 4;
            ?>
            <textarea
                class="<?php echo $classes; ?>"
                name="<?php echo htmlspecialchars($key); ?>"
                id="<?php echo htmlspecialchars($input_id); ?>"
                rows="<?php echo (int)$rows; ?>"
                data-default-value="<?php echo htmlspecialchars($english_value); ?>"
                <?php if (!empty($options['editor_height'])): ?>
                    data-editor-height="<?php echo (int)$options['editor_height']; ?>"
                <?php endif; ?>
            ><?php echo htmlspecialchars($current); ?></textarea>
        <?php else: ?>
            <input
                type="text"
                class="form-control"
                name="<?php echo htmlspecialchars($key); ?>"
                id="<?php echo htmlspecialchars($input_id); ?>"
                value="<?php echo htmlspecialchars($current); ?>"
                data-default-value="<?php echo htmlspecialchars($english_value); ?>"
                <?php if (!empty($options['placeholder'])): ?>
                    placeholder="<?php echo htmlspecialchars($options['placeholder']); ?>"
                <?php endif; ?>
            >
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
};
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

        <?php if (in_array($page_key, ['home', 'mp3', 'mp4'])): ?>
        <div class="page-section">
            <h4><?php echo htmlspecialchars($page_label); ?> (<?php echo htmlspecialchars($language['name']); ?>)</h4>
            <?php if ($status_message): ?>
                <div class="alert alert-<?php echo $status_type; ?>"><?php echo htmlspecialchars($status_message); ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="pageContentForm">
                <?php if ($page_key === 'home'): ?>
                <div class="mb-3">
                    <label class="form-label">Language Direction</label>
                    <select class="form-select" name="language_direction" style="max-width: 200px;">
                        <option value="LTR" <?php if ($language_direction === 'LTR') echo 'selected'; ?>>LTR</option>
                        <option value="RTL" <?php if ($language_direction === 'RTL') echo 'selected'; ?>>RTL</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" class="form-control" name="home_slug" value="<?php echo htmlspecialchars($language_slug); ?>" placeholder="home" style="max-width: 300px;">
                </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label">Meta Title</label>
                    <?php echo $renderField('meta_title', ['label' => '']); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Meta Description</label>
                    <?php echo $renderField('meta_description', ['label' => '', 'ckeditor' => true, 'editor_height' => 120]); ?>
                </div>
                <h3>Banner Title</h3>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Header</label>
                    <?php echo $renderField('heroTitle', ['label' => '']); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Banner Description</label>
                    <?php echo $renderField('heroSubtitle', ['label' => '', 'ckeditor' => true, 'editor_height' => 140, 'show_english' => false]); ?>
                </div>
                <h3>Section 1</h3>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Title 1</label>
                    <?php echo $renderField('sectionTitle', ['label' => '', 'ckeditor' => true, 'editor_height' => 140, 'show_english' => false]); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description 1</label>
                    <?php echo $renderField('description1', ['label' => '', 'ckeditor' => true, 'editor_height' => 260, 'show_english' => false]); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description 2 (Optional)</label>
                    <?php echo $renderField('description2', ['label' => '', 'ckeditor' => true, 'editor_height' => 260, 'show_english' => false]); ?>
                </div>
                <h3>Section 2</h3>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Images</label>
                    <div id="features-wrapper">
                        <?php
                        $featureConfig = [
                            1 => ['icon' => 'feature1Icon', 'title' => 'feature1Title', 'desc' => 'feature1Desc'],
                            2 => ['icon' => 'feature2Icon', 'title' => 'feature2Title', 'desc' => 'feature2Desc'],
                            3 => ['icon' => 'feature3Icon', 'title' => 'feature3Title', 'desc' => 'feature3Desc'],
                            4 => ['icon' => 'feature4Icon', 'title' => 'feature4Title', 'desc' => 'feature4Desc'],
                            5 => ['icon' => 'feature5Icon', 'title' => 'feature5Title', 'desc' => 'feature5Desc'],
                            6 => ['icon' => 'feature6Icon', 'title' => 'feature6Title', 'desc' => 'feature6Desc'],
                        ];
                        foreach ($featureConfig as $index => $config):
                        ?>
                            <div class="image-item mb-3 p-3 border rounded" style="background: #fff;">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 style="margin: 0; font-weight: 600;">Image <?php echo $index; ?></h6>
                                    <button type="button" class="btn btn-danger btn-sm remove-feature-btn" style="padding: 4px 12px; font-size: 12px; display: none;" disabled>Remove</button>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" style="font-weight: 500;">Image</label>
                                    <?php echo $renderField($config['icon'], ['label' => '', 'show_image_path' => false, 'minimal_image' => true]); ?>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" style="font-weight: 500;">Heading</label>
                                    <?php echo $renderField($config['title'], ['label' => '', 'ckeditor' => true, 'editor_height' => 100, 'show_english' => false]); ?>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label" style="font-weight: 500;">Description</label>
                                    <?php echo $renderField($config['desc'], ['label' => '', 'ckeditor' => true, 'editor_height' => 150, 'show_english' => false]); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if (in_array($page_key, ['home', 'mp3', 'mp4'])): ?>
                <h3>Section 3 - MP3 Promo</h3>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Images</label>
                    <div class="row g-3">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="col-md-4">
                                <div class="p-3 border rounded h-100">
                                    <h6 class="fw-semibold mb-2">Image <?php echo $i; ?></h6>
                                    <?php echo $renderField('mp3PromoImage' . $i, ['label' => '', 'show_image_path' => false, 'show_english' => false, 'minimal_image' => true]); ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Heading</label>
                    <div class="p-3 border rounded">
                        <?php echo $renderField('mp3PromoTitle', ['label' => '', 'ckeditor' => true, 'editor_height' => 140, 'show_english' => false]); ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description</label>
                    <div class="p-3 border rounded">
                        <?php echo $renderField('mp3PromoDesc', ['label' => '', 'ckeditor' => true, 'editor_height' => 160, 'show_english' => false]); ?>
                    </div>
                </div>
                <?php endif; ?>
                <h3>Section 4</h3>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Heading</label>
                    <?php echo $renderField('stepsTitle', ['label' => '', 'ckeditor' => true, 'editor_height' => 120]); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Add Columns</label>
                    <div id="steps-wrapper">
                        <?php
                        $stepConfigs = [
                            1 => ['key' => 'step1', 'imageKey' => 'step1Image', 'default' => $defaults['step1'] ?? 'Paste the link or keyword into the box.'],
                            2 => ['key' => 'step2', 'imageKey' => 'step2Image', 'default' => $defaults['step2'] ?? 'Pick MP3 or MP4 and click Convert.'],
                            3 => ['key' => 'step3', 'imageKey' => 'step3Image', 'default' => $defaults['step3'] ?? 'Download the file once it is ready.'],
                        ];
                        foreach ($stepConfigs as $stepNum => $config):
                            $step_value = $display_strings[$config['key']] ?? '';
                        ?>
                        <div class="add-column-item mb-2 p-3 border rounded" style="background: #fff;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Step <?php echo $stepNum; ?></strong>
                                <button type="button" class="btn btn-danger btn-sm remove-step-btn" style="padding: 4px 12px; font-size: 12px; display: none;">Remove</button>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" style="font-weight: 500;">Text</label>
                                <?php echo $renderField($config['key'], ['label' => '', 'ckeditor' => true, 'editor_height' => 120, 'show_english' => false]); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-step-btn" style="padding: 6px 16px;">+ Add Column</button>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 24px;">Save Page Content</button>
                </div>
            </form>

            <form method="post" id="faqForm">
                <input type="hidden" name="faq_action" value="save_faqs">
                <h3>FAQs (<?php echo htmlspecialchars($page_label); ?>)</h3>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Heading</label>
                    <?php echo $renderField('faqTitle', ['label' => '', 'ckeditor' => true, 'editor_height' => 120]); ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">FAQs</label>
                    <div id="faqList">
                        <?php foreach ($faqs as $index => $faq): ?>
                            <div class="faq-item mb-3 p-3 border rounded" data-faq-id="<?php echo $faq['id']; ?>" style="background: #fff;">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong>FAQ <?php echo $index + 1; ?></strong>
                                    <button type="button" class="btn btn-danger btn-sm delete-faq-btn" data-faq-id="<?php echo $faq['id']; ?>" style="padding: 4px 12px; font-size: 12px;">
                                        <i class="fa-solid fa-trash"></i> Remove
                                    </button>
                                </div>
                                <input type="hidden" name="faq_id[]" value="<?php echo $faq['id']; ?>">
                                <div class="mb-2">
                                    <label class="form-label">Question</label>
                                    <input type="text" class="form-control" name="faq_question[]" value="<?php echo htmlspecialchars($faq['question']); ?>" required>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Answer</label>
                                    <textarea class="form-control js-ckeditor" name="faq_answer[]" rows="3" data-editor-height="150" dir="ltr" required><?php echo htmlspecialchars($faq['answer']); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-faq-btn" style="padding: 6px 16px;">+ Add FAQ</button>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary" style="padding: 8px 24px;">Save FAQs</button>
                </div>
            </form>
        </div>
        <?php else: ?>
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
                            <?php foreach ($group_fields as $key => $meta): ?>
                                <?php echo $renderField($key); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (in_array($page_key, ['home', 'mp3', 'mp4'])): ?>
<script>
(function() {
    function initEditors() {
        document.querySelectorAll('textarea.js-ckeditor').forEach(function (field) {
            if (!field.id) {
                field.id = 'editor_' + Math.random().toString(36).slice(2);
            }
            if (window.CKEDITOR && !CKEDITOR.instances[field.id]) {
                // CKEditor automatically reads the value from the textarea
                var editorConfig = {
                    height: parseInt(field.dataset.editorHeight || 200, 10)
                };
                CKEDITOR.replace(field.id, editorConfig);
            }
        });
    }

    function initEditorForField(field) {
        if (!field.id) {
            field.id = 'editor_' + Math.random().toString(36).slice(2);
        }
        if (window.CKEDITOR && !CKEDITOR.instances[field.id]) {
            CKEDITOR.replace(field.id, {
                height: parseInt(field.dataset.editorHeight || 200, 10)
            });
        }
    }

    if (typeof CKEDITOR === 'undefined') {
        var script = document.createElement('script');
        script.src = 'assets/ckeditor/ckeditor/ckeditor.js';
        script.onload = function() {
            initEditors();
        };
        document.body.appendChild(script);
    } else {
        initEditors();
    }

    // Add Step Column
    var addStepBtn = document.getElementById('add-step-btn');
    var stepsWrapper = document.getElementById('steps-wrapper');
    var stepCounter = 3;

    if (addStepBtn && stepsWrapper) {
        addStepBtn.addEventListener('click', function() {
            stepCounter++;
            var newStep = document.createElement('div');
            newStep.className = 'add-column-item mb-2 p-3 border rounded';
            newStep.style.background = '#fff';
            newStep.innerHTML = 
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                    '<strong>Step ' + stepCounter + '</strong>' +
                    '<button type="button" class="btn btn-danger btn-sm remove-step-btn" style="padding: 4px 12px; font-size: 12px;">Remove</button>' +
                '</div>' +
                '<textarea class="form-control js-ckeditor" name="step' + stepCounter + '" rows="3" placeholder="Step Description" dir="ltr" data-editor-height="120"></textarea>';
            stepsWrapper.appendChild(newStep);
            var textarea = newStep.querySelector('textarea.js-ckeditor');
            if (textarea && window.CKEDITOR) {
                setTimeout(function() {
                    initEditorForField(textarea);
                }, 100);
            }
        });
    }

    // Remove Step Column
    if (stepsWrapper) {
        stepsWrapper.addEventListener('click', function(e) {
            if (e.target.closest('.remove-step-btn')) {
                var btn = e.target.closest('.remove-step-btn');
                var stepItem = btn.closest('.add-column-item');
                var textarea = stepItem.querySelector('textarea');
                if (textarea && textarea.id && window.CKEDITOR && CKEDITOR.instances[textarea.id]) {
                    CKEDITOR.instances[textarea.id].destroy();
                }
                stepItem.remove();
            }
        });
    }

    // Add FAQ
    var addFaqBtn = document.getElementById('add-faq-btn');
    var faqList = document.getElementById('faqList');
    var faqCounter = <?php echo count($faqs); ?>;

    if (addFaqBtn && faqList) {
        function renumberFaqItems() {
            var items = faqList.querySelectorAll('.faq-item');
            items.forEach(function(item, index) {
                var strong = item.querySelector('strong');
                if (strong) {
                    strong.textContent = 'FAQ ' + (index + 1);
                }
            });
            faqCounter = items.length;
        }

        function destroyFaqEditors(container) {
            if (!window.CKEDITOR) {
                return;
            }
            var textareas = container.querySelectorAll('textarea');
            textareas.forEach(function(textarea) {
                if (textarea.id && CKEDITOR.instances[textarea.id]) {
                    CKEDITOR.instances[textarea.id].destroy();
                }
            });
        }

        function removeFaqItem(faqItem) {
            if (!faqItem) return;
            destroyFaqEditors(faqItem);
            faqItem.remove();
            if (faqList.querySelectorAll('.faq-item').length === 0 && addFaqBtn) {
                addFaqBtn.click();
            }
            renumberFaqItems();
        }

        function showFaqNotice(type, message) {
            var existing = document.querySelector('.faq-alert');
            if (existing) {
                existing.remove();
            }
            var alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-' + type + ' faq-alert';
            alertDiv.textContent = message;
            faqList.parentNode.insertBefore(alertDiv, faqList);
            setTimeout(function() {
                alertDiv.remove();
            }, 3000);
        }

        addFaqBtn.addEventListener('click', function() {
            faqCounter++;
            var newFaq = document.createElement('div');
            newFaq.className = 'faq-item mb-3 p-3 border rounded';
            newFaq.style.background = '#fff';
            newFaq.innerHTML = 
                '<div class="d-flex justify-content-between align-items-start mb-2">' +
                    '<strong>FAQ ' + faqCounter + '</strong>' +
                    '<button type="button" class="btn btn-danger btn-sm delete-faq-btn" data-faq-id="0" style="padding: 4px 12px; font-size: 12px;">' +
                        '<i class="fa-solid fa-trash"></i> Remove' +
                    '</button>' +
                '</div>' +
                '<input type="hidden" name="faq_id[]" value="0">' +
                '<div class="mb-2">' +
                    '<label class="form-label">Question</label>' +
                    '<input type="text" class="form-control" name="faq_question[]" required>' +
                '</div>' +
                '<div class="mb-2">' +
                    '<label class="form-label">Answer</label>' +
                    '<textarea class="form-control js-ckeditor" name="faq_answer[]" rows="3" data-editor-height="150" dir="ltr" required></textarea>' +
                '</div>';
            faqList.appendChild(newFaq);
            var textarea = newFaq.querySelector('textarea.js-ckeditor');
            if (textarea && window.CKEDITOR) {
                setTimeout(function() {
                    initEditorForField(textarea);
                }, 100);
            }
        });

        // Delete FAQ handler - use event delegation
        document.addEventListener('click', function(e) {
            if (e.target.closest('.delete-faq-btn')) {
                e.preventDefault();
                e.stopPropagation();
                var btn = e.target.closest('.delete-faq-btn');
                var faqId = btn.getAttribute('data-faq-id');
                var faqItem = btn.closest('.faq-item');
                
                if (!faqItem) return;
                
                if (faqId && faqId !== '0') {
                    // Delete from database via AJAX
                    if (confirm('Are you sure you want to delete this FAQ? This will remove it from both admin portal and frontend website.')) {
                        var formData = new FormData();
                        formData.append('faq_action', 'delete_faq');
                        formData.append('faq_id', faqId);
                        
                        // Show loading state
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
                        
                        var deleteUrl = window.location.pathname + window.location.search;
                        fetch(deleteUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(function(response) {
                            // Check if response is JSON
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return response.json().then(function(data) {
                                    if (data.success) {
                                        removeFaqItem(faqItem);
                                        btn.disabled = false;
                                        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove';
                                        showFaqNotice('success', 'FAQ removed successfully.');
                                    } else {
                                        alert('Failed to delete FAQ: ' + (data.message || 'Unknown error'));
                                        btn.disabled = false;
                                        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove';
                                    }
                                });
                            } else {
                                // If response is not JSON, check if it was successful
                                if (response.ok) {
                                    removeFaqItem(faqItem);
                                    btn.disabled = false;
                                    btn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove';
                                    showFaqNotice('success', 'FAQ removed successfully.');
                                } else {
                                    // Try to get text response
                                    return response.text().then(function(text) {
                                        console.error('Delete FAQ response:', text);
                                        alert('Failed to delete FAQ. Please try again.');
                                        btn.disabled = false;
                                        btn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove';
                                    });
                                }
                            }
                        }).catch(function(error) {
                            console.error('Error deleting FAQ:', error);
                            alert('Failed to delete FAQ. Please check your connection and try again.');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-trash"></i> Remove';
                        });
                    }
                } else {
                    // Just remove from DOM if it's a new FAQ (not saved yet)
                    removeFaqItem(faqItem);
                }
            }
        });
    }

    // Handle form submission - update CKEditor content before saving
    var postForms = document.querySelectorAll('form[method="post"]');
    if (postForms.length) {
        postForms.forEach(function(form) {
            form.addEventListener('submit', function() {
                if (window.CKEDITOR) {
                    for (var instance in CKEDITOR.instances) {
                        if (Object.prototype.hasOwnProperty.call(CKEDITOR.instances, instance)) {
                            CKEDITOR.instances[instance].updateElement();
                        }
                    }
                }
            });
        });
    }
})();
</script>
<?php endif; ?>

<?php
include 'includes/footer.php';
