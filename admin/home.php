<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

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

$success = '';
$error = '';
$page = null;

// Handle translate request - DO NOT SAVE, just prepare translated content
$translated_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['translate_content'])) {
    // Fetch English page if not already loaded
    $english_page_trans = null;
    $sql = "SELECT * FROM languages_home WHERE language_id=41 LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $english_page_trans = $res->fetch_assoc();
        $english_page_trans['add_columns'] = $english_page_trans['add_columns'] ? json_decode($english_page_trans['add_columns'], true) : [];
        $english_page_trans['images'] = $english_page_trans['images'] ? json_decode($english_page_trans['images'], true) : [];
    }
    
    if ($english_page_trans) {
        $target_language = $lang['name'];
        error_log("Translating Home page from English to {$target_language}");
        
        // Translate main fields
        $meta_title_result = translateText($english_page_trans['meta_title'], $target_language);
        $meta_description_result = translateText($english_page_trans['meta_description'], $target_language);
        $header_result = translateText($english_page_trans['header'], $target_language);
        $title1_result = translateText($english_page_trans['title1'], $target_language);
        $description1_result = translateText($english_page_trans['description1'], $target_language);
        $title2_result = translateText($english_page_trans['title2'], $target_language);
        $description2_result = translateText($english_page_trans['description2'], $target_language);
        $title3_result = translateText($english_page_trans['title3'], $target_language);
        $description3_result = translateText($english_page_trans['description3'], $target_language);
        $heading2_result = translateText($english_page_trans['heading2'], $target_language);
        $heading2_description_result = translateText($english_page_trans['heading2_description'], $target_language);
        $how_to_download_heading_result = translateText($english_page_trans['how_to_download_heading'], $target_language);
        $description_bottom_result = translateText($english_page_trans['description_bottom'], $target_language);
        $title1_2_result = translateText($english_page_trans['title1_2'], $target_language);
        $pink_title1_2_result = translateText($english_page_trans['pink_title1_2'], $target_language);
        $description1_2_result = translateText($english_page_trans['description1_2'], $target_language);
        $title2_2_result = translateText($english_page_trans['title2_2'], $target_language);
        $description2_2_result = translateText($english_page_trans['description2_2'], $target_language);
        
        // Translate add_columns
        $translated_columns = [];
        foreach ($english_page_trans['add_columns'] as $col) {
            $heading_result = translateText($col['heading'], $target_language);
            $desc_result = translateText($col['description'], $target_language);
            
            $translated_columns[] = [
                'heading' => $heading_result['success'] ? $heading_result['response'] : $col['heading'],
                'description' => $desc_result['success'] ? $desc_result['response'] : $col['description']
            ];
            
            if (!$heading_result['success']) error_log("Column heading translation failed: " . $heading_result['error']);
            if (!$desc_result['success']) error_log("Column description translation failed: " . $desc_result['error']);
        }
        
        // Translate image descriptions
        $translated_images = [];
        foreach ($english_page_trans['images'] as $img) {
            $desc_result = translateText($img['description'], $target_language);
            
            $translated_images[] = [
                'image' => $img['image'],
                'description' => $desc_result['success'] ? $desc_result['response'] : $img['description']
            ];
            
            if (!$desc_result['success']) error_log("Image description translation failed: " . $desc_result['error']);
        }
        
        $translated_data = [
            'meta_title' => $meta_title_result['success'] ? $meta_title_result['response'] : $english_page_trans['meta_title'],
            'meta_description' => $meta_description_result['success'] ? $meta_description_result['response'] : $english_page_trans['meta_description'],
            'header' => $header_result['success'] ? $header_result['response'] : $english_page_trans['header'],
            'title1' => $title1_result['success'] ? $title1_result['response'] : $english_page_trans['title1'],
            'description1' => $description1_result['success'] ? $description1_result['response'] : $english_page_trans['description1'],
            'title2' => $title2_result['success'] ? $title2_result['response'] : $english_page_trans['title2'],
            'description2' => $description2_result['success'] ? $description2_result['response'] : $english_page_trans['description2'],
            'title3' => $title3_result['success'] ? $title3_result['response'] : $english_page_trans['title3'],
            'description3' => $description3_result['success'] ? $description3_result['response'] : $english_page_trans['description3'],
            'heading2' => $heading2_result['success'] ? $heading2_result['response'] : $english_page_trans['heading2'],
            'heading2_description' => $heading2_description_result['success'] ? $heading2_description_result['response'] : $english_page_trans['heading2_description'],
            'how_to_download_heading' => $how_to_download_heading_result['success'] ? $how_to_download_heading_result['response'] : $english_page_trans['how_to_download_heading'],
            'description_bottom' => $description_bottom_result['success'] ? $description_bottom_result['response'] : $english_page_trans['description_bottom'],
            'title1_2' => $title1_2_result['success'] ? $title1_2_result['response'] : $english_page_trans['title1_2'],
            'pink_title1_2' => $pink_title1_2_result['success'] ? $pink_title1_2_result['response'] : $english_page_trans['pink_title1_2'],
            'description1_2' => $description1_2_result['success'] ? $description1_2_result['response'] : $english_page_trans['description1_2'],
            'title2_2' => $title2_2_result['success'] ? $title2_2_result['response'] : $english_page_trans['title2_2'],
            'description2_2' => $description2_2_result['success'] ? $description2_2_result['response'] : $english_page_trans['description2_2'],
            'slug' => $page ? $page['slug'] : 'home',
            'direction' => $page ? $page['direction'] : 'LTR',
            'add_columns' => $translated_columns,
            'images' => $translated_images,
            'image1' => $english_page_trans['image1'],
            'image2' => $english_page_trans['image2']
        ];
        
        $success = "All content translated from English to {$target_language}! Please review and click Save to store the translation.";
        
        if (!$meta_title_result['success']) {
            error_log("Meta Title translation failed: " . $meta_title_result['error']);
            $error = "Some translations failed. Using English content where translation failed.";
        }
        
    } else {
        $error = 'No English content found to translate. Please ensure English (ID 41) Home page exists.';
    }
}

// Handle delete redirect FIRST (before any other processing)
if (isset($_GET['delete_redirect']) && is_numeric($_GET['delete_redirect'])) {
    $del_id = intval($_GET['delete_redirect']);
    if ($conn->query("DELETE FROM languages_home_redirects WHERE id=$del_id AND language_id=$lang_id")) {
        $success = 'Redirect deleted successfully.';
    } else {
        $error = 'Failed to delete redirect.';
    }
    // Redirect to clear the URL parameter
    header("Location: home.php?id=$lang_id&deleted=1");
    exit;
}

// Handle delete active slug (only if no other redirects)
if (isset($_GET['delete_active_slug']) && $_GET['delete_active_slug'] == '1' && isset($lang_id)) {
    // Get the last added redirect (highest id)
    $last_redirect_res = $conn->query("SELECT id, old_slug FROM languages_home_redirects WHERE language_id=$lang_id ORDER BY id DESC LIMIT 1");
    if ($last_redirect_res && ($row = $last_redirect_res->fetch_assoc())) {
        $last_redirect_id = (int)$row['id'];
        $last_old_slug = $conn->real_escape_string($row['old_slug']);
        // Set the slug to the last redirect's old_slug
        $conn->query("UPDATE languages_home SET slug='$last_old_slug' WHERE language_id=$lang_id");
        // Remove that redirect
        $conn->query("DELETE FROM languages_home_redirects WHERE id=$last_redirect_id");
        header("Location: home.php?id=$lang_id&deleted_active=1");
        exit;
    } else {
        // No redirects, just clear the slug
        $conn->query("UPDATE languages_home SET slug='' WHERE language_id=$lang_id");
        header("Location: home.php?id=$lang_id&deleted_active=1");
        exit;
    }
}

// Show success message if redirect was deleted
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $success = 'Redirect deleted successfully.';
}
// Show success message if active slug was deleted
if (isset($_GET['deleted_active']) && $_GET['deleted_active'] == '1') {
    $success = 'Active slug deleted successfully.';
}
// Show error message if error param is set
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Function to process CKEditor content
function processEditorContent($content) {
    // Decode HTML entities
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    
    // Clean up extra whitespace and line breaks
    $content = preg_replace('/\s+/', ' ', $content);
    
    // Ensure proper paragraph structure
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);
    
    // Fix bullet points and lists
    $content = preg_replace('/<ul>\s*<li>/', '<ul><li>', $content);
    $content = preg_replace('/<\/li>\s*<\/ul>/', '</li></ul>', $content);
    
    // Clean up empty paragraphs
    $content = preg_replace('/<p>\s*<\/p>/', '', $content);
    
    // Trim whitespace
    $content = trim($content);
    
    return $content;
}

// Initialize previous_slug variable
$previous_slug = null;

// Handle add/edit (but not translate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['translate_content'])) {
    $page_name = trim(strip_tags($_POST['page_name'] ?? ''));
    $slug = trim(strip_tags($_POST['slug'] ?? ''));
    $direction = trim(strip_tags($_POST['direction'] ?? 'LTR'));
    $meta_title = trim(strip_tags($_POST['meta_title'] ?? ''));
    $meta_description = trim(strip_tags($_POST['meta_description'] ?? ''));
    $header = trim(strip_tags($_POST['header'] ?? ''));
    $title1 = trim(strip_tags($_POST['title1'] ?? ''));
    $description1 = processEditorContent($_POST['description1'] ?? '');
    $title2 = trim(strip_tags($_POST['title2'] ?? ''));
    $description2 = processEditorContent($_POST['description2'] ?? '');
    $title3 = trim(strip_tags($_POST['title3'] ?? ''));
    $description3 = processEditorContent($_POST['description3'] ?? '');
    $heading2 = trim(strip_tags($_POST['heading2'] ?? ''));
    $heading2_description = processEditorContent($_POST['heading2_description'] ?? '');
    $how_to_download_heading = trim(strip_tags($_POST['how_to_download_heading'] ?? ''));
    $description_bottom = processEditorContent($_POST['description_bottom'] ?? '');
    $title1_2 = trim(strip_tags($_POST['title1_2'] ?? ''));
    $pink_title1_2 = trim(strip_tags($_POST['pink_title1_2'] ?? ''));
    $description1_2 = processEditorContent($_POST['description1_2'] ?? '');
    $title2_2 = trim(strip_tags($_POST['title2_2'] ?? ''));
    $description2_2 = processEditorContent($_POST['description2_2'] ?? '');
    // FAQs (enable + items)
    $faqs_enabled = isset($_POST['faqs_enabled']) ? 1 : 0;
    $faq_questions = $_POST['faq_question'] ?? [];
    $faq_answers = $_POST['faq_answer'] ?? [];
    $faqs = [];
    $faq_count = max(count($faq_questions), count($faq_answers));
    for ($i = 0; $i < $faq_count; $i++) {
        $q = isset($faq_questions[$i]) ? trim(strip_tags($faq_questions[$i])) : '';
        $a = isset($faq_answers[$i]) ? processEditorContent($faq_answers[$i]) : '';
        if ($q !== '' || $a !== '') {
            $faqs[] = ['q' => $q, 'a' => $a];
        }
    }
    $faqs_json = json_encode($faqs);
    // Handle dynamic columns
    $add_column_headings = $_POST['add_column_heading'] ?? [];
    $add_column_descriptions = $_POST['add_column_description'] ?? [];
    $add_columns = [];
    for ($i = 0; $i < count($add_column_headings); $i++) {
        $add_columns[] = [
            'heading' => strip_tags($add_column_headings[$i]),
            'description' => processEditorContent($add_column_descriptions[$i])
        ];
    }
    $add_columns_json = json_encode($add_columns);
    
    // Handle multiple images
    function upload_img($field, $old = null, $index = null) {
        if ($index !== null) {
            // Handle array-based file uploads
            if (!empty($_FILES[$field]['name'][$index])) {
                $original_filename = $_FILES[$field]['name'][$index];
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Check if file with same name exists, if so add timestamp
                $filename = $original_filename;
                $counter = 1;
                while (file_exists($upload_dir . $filename)) {
                    $path_info = pathinfo($original_filename);
                    $filename = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
                    $counter++;
                }
                
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES[$field]['tmp_name'][$index], $target)) {
                    return 'uploads/' . $filename;
                }
            }
        } else {
            // Handle single file uploads
            if (!empty($_FILES[$field]['name'])) {
                $original_filename = $_FILES[$field]['name'];
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Check if file with same name exists, if so add timestamp
                $filename = $original_filename;
                $counter = 1;
                while (file_exists($upload_dir . $filename)) {
                    $path_info = pathinfo($original_filename);
                    $filename = $path_info['filename'] . '_' . $counter . '.' . $path_info['extension'];
                    $counter++;
                }
                
                $target = $upload_dir . $filename;
                if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
                    return 'uploads/' . $filename;
                }
            }
        }
        return $old;
    }
    
    // Process multiple images
    $images_data = [];
    $current_images = $_POST['current_images'] ?? [];
    $image_descriptions = $_POST['image_descriptions'] ?? [];
    
    // Handle existing images
    for ($i = 0; $i < count($current_images); $i++) {
        $current_image = $current_images[$i];
        $description = isset($image_descriptions[$i]) ? processEditorContent($image_descriptions[$i]) : '';
        
        // Check if new file was uploaded for this image
        $new_image = null;
        if (isset($_FILES['images']['name'][$i]) && !empty($_FILES['images']['name'][$i])) {
            $new_image = upload_img('images', $current_image, $i);
        }
        
        $images_data[] = [
            'image' => $new_image ?: $current_image,
            'description' => $description
        ];
    }
    
    // Handle new images (beyond existing ones)
    $new_images_count = isset($_FILES['images']['name']) ? count($_FILES['images']['name']) : 0;
    for ($i = count($current_images); $i < $new_images_count; $i++) {
        if (!empty($_FILES['images']['name'][$i])) {
            $new_image = upload_img('images', null, $i);
            $description = isset($image_descriptions[$i]) ? processEditorContent($image_descriptions[$i]) : '';
            
            if ($new_image) {
                $images_data[] = [
                    'image' => $new_image,
                    'description' => $description
                ];
            }
        }
    }
    
    $images_json = json_encode($images_data);
    
    // Handle old single image fields separately (don't mix with multiple images)
    $image = upload_img('image', $_POST['current_image'] ?? null);
    $image_description = processEditorContent($_POST['image_description'] ?? '');
    $image1 = upload_img('image1', $_POST['current_image1'] ?? null);
    $image2 = upload_img('image2', $_POST['current_image2'] ?? null);
    // Fetch previous slug BEFORE updating
    $previous_slug = null;
    $sql = "SELECT slug FROM languages_home WHERE language_id=$lang_id LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $previous_slug = $row['slug'];
    }
    // Check if page exists
    $sql = "SELECT id FROM languages_home WHERE language_id=$lang_id LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        // Update
        $sql = sprintf(
            "UPDATE languages_home SET page_name='%s', slug='%s', direction='%s', meta_title='%s', meta_description='%s', header='%s', title1='%s', description1='%s', title2='%s', description2='%s', title3='%s', description3='%s', heading2='%s', heading2_description='%s', how_to_download_heading='%s', description_bottom='%s', image='%s', image_description='%s', title1_2='%s', pink_title1_2='%s', description1_2='%s', image1='%s', title2_2='%s', description2_2='%s', image2='%s', add_columns='%s', images='%s', faqs_enabled=%d, faqs='%s' WHERE language_id=%d",
            $conn->real_escape_string($page_name),
            $conn->real_escape_string($slug),
            $conn->real_escape_string($direction),
            $conn->real_escape_string($meta_title),
            $conn->real_escape_string($meta_description),
            $conn->real_escape_string($header),
            $conn->real_escape_string($title1),
            $conn->real_escape_string($description1),
            $conn->real_escape_string($title2),
            $conn->real_escape_string($description2),
            $conn->real_escape_string($title3),
            $conn->real_escape_string($description3),
            $conn->real_escape_string($heading2),
            $conn->real_escape_string($heading2_description),
            $conn->real_escape_string($how_to_download_heading),
            $conn->real_escape_string($description_bottom),
            $conn->real_escape_string($image),
            $conn->real_escape_string($image_description),
            $conn->real_escape_string($title1_2),
            $conn->real_escape_string($pink_title1_2),
            $conn->real_escape_string($description1_2),
            $conn->real_escape_string($image1),
            $conn->real_escape_string($title2_2),
            $conn->real_escape_string($description2_2),
            $conn->real_escape_string($image2),
            $conn->real_escape_string($add_columns_json),
            $conn->real_escape_string($images_json),
            $faqs_enabled,
            $conn->real_escape_string($faqs_json),
            $lang_id
        );
        if ($conn->query($sql)) {
            $success = 'Home page updated.';
        } else {
            $error = 'Failed to update page.';
        }
    } else {
        // Insert
        $sql = sprintf(
            "INSERT INTO languages_home (language_id, page_name, slug, direction, meta_title, meta_description, header, title1, description1, title2, description2, title3, description3, heading2, heading2_description, how_to_download_heading, description_bottom, image, image_description, title1_2, pink_title1_2, description1_2, image1, title2_2, description2_2, image2, add_columns, images, faqs_enabled, faqs) VALUES (%d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, '%s')",
            $lang_id,
            $conn->real_escape_string($page_name),
            $conn->real_escape_string($slug),
            $conn->real_escape_string($direction),
            $conn->real_escape_string($meta_title),
            $conn->real_escape_string($meta_description),
            $conn->real_escape_string($header),
            $conn->real_escape_string($title1),
            $conn->real_escape_string($description1),
            $conn->real_escape_string($title2),
            $conn->real_escape_string($description2),
            $conn->real_escape_string($title3),
            $conn->real_escape_string($description3),
            $conn->real_escape_string($heading2),
            $conn->real_escape_string($heading2_description),
            $conn->real_escape_string($how_to_download_heading),
            $conn->real_escape_string($description_bottom),
            $conn->real_escape_string($image),
            $conn->real_escape_string($image_description),
            $conn->real_escape_string($title1_2),
            $conn->real_escape_string($pink_title1_2),
            $conn->real_escape_string($description1_2),
            $conn->real_escape_string($image1),
            $conn->real_escape_string($title2_2),
            $conn->real_escape_string($description2_2),
            $conn->real_escape_string($image2),
            $conn->real_escape_string($add_columns_json),
            $conn->real_escape_string($images_json),
            $faqs_enabled,
            $conn->real_escape_string($faqs_json)
        );
        if ($conn->query($sql)) {
            $success = 'Home page added.';
        } else {
            $error = 'Failed to add page.';
        }
    }
}

// Use translated data if available, otherwise fetch from database
if ($translated_data) {
    $page = $translated_data;
    // add_columns and images are already arrays in translated_data
} else {
    // Fetch home page for this language
    $page = null;
    $sql = "SELECT * FROM languages_home WHERE language_id=$lang_id LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
        // Decode add_columns JSON only if it's not already an array
        $page['add_columns'] = is_array($page['add_columns']) ? $page['add_columns'] : ($page['add_columns'] ? json_decode($page['add_columns'], true) : []);
        // Decode images JSON only if it's not already an array
        $page['images'] = is_array($page['images']) ? $page['images'] : ($page['images'] ? json_decode($page['images'], true) : []);
    }
}

// If no page exists for this language, fetch English (ID 41) content as default
$english_page = null;
if (!$page && $lang_id != 41) {
    $sql = "SELECT * FROM languages_home WHERE language_id=41 LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $english_page = $res->fetch_assoc();
        $english_page['add_columns'] = $english_page['add_columns'] ? json_decode($english_page['add_columns'], true) : [];
        $english_page['images'] = $english_page['images'] ? json_decode($english_page['images'], true) : [];
    }
}

// Redirects logic
if (!function_exists('add_home_redirect')) {
    function add_home_redirect($conn, $lang_id, $old_slug, $new_slug) {
        // Only add if old_slug is not empty and not equal to new_slug
        if ($old_slug && $old_slug !== $new_slug) {
            $conn->query("INSERT INTO languages_home_redirects (language_id, old_slug, new_slug) VALUES ($lang_id, '" . $conn->real_escape_string($old_slug) . "', '" . $conn->real_escape_string($new_slug) . "')");
        }
    }
}
// Create table if not exists (for first run)
$conn->query("CREATE TABLE IF NOT EXISTS languages_home_redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_id INT(11) NOT NULL,
    old_slug VARCHAR(255) NOT NULL,
    new_slug VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_languages_home_redirects_language
        FOREIGN KEY (language_id) REFERENCES languages(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
// Track slug changes and store in redirects table if changed
// After successful update/insert, add redirect if slug changed and both are not empty
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $success
    && $previous_slug
    && $previous_slug !== $slug
) {
    // Only add if this redirect does not already exist
    $check = $conn->query("SELECT id FROM languages_home_redirects WHERE language_id=$lang_id AND old_slug='" . $conn->real_escape_string($previous_slug) . "' AND new_slug='" . $conn->real_escape_string($slug) . "'");
    if (!$check || $check->num_rows === 0) {
        $conn->query("INSERT INTO languages_home_redirects (language_id, old_slug, new_slug) VALUES ($lang_id, '" . $conn->real_escape_string($previous_slug) . "', '" . $conn->real_escape_string($slug) . "')");
        if ($conn->error) {
            echo '<div class="alert alert-danger">MySQL Error: ' . $conn->error . '</div>';
        }
    }
}
// Fetch current slug
$current_slug = $page['slug'] ?? '';

// Fetch all redirects for this language (only active ones from database)
$redirects = [];
$res = $conn->query("SELECT id, old_slug, new_slug FROM languages_home_redirects WHERE language_id=$lang_id ORDER BY id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        // Only show old_slug as redirect (since new_slug is the current active one)
        if ($row['old_slug'] && $row['old_slug'] !== $current_slug) {
            $redirects[$row['old_slug']] = [
                'id' => $row['id'],
                'slug' => $row['old_slug'],
                'status' => 'Redirect'
            ];
        }
    }
}

// Build a unique list: current slug (active), all old/new slugs (redirect)
$all_slugs = [];
if ($current_slug) {
    $all_slugs[] = [
        'slug' => $current_slug,
        'status' => 'Active',
        'id' => 0
    ];
}
foreach ($redirects as $slug => $info) {
    $all_slugs[] = $info;
}
?>
<?php include 'includes/header.php'; ?>
<style>
.note-editable {
    direction: ltr !important;
    text-align: left !important;
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
        <h4>Home Page (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
        <!--<div class="url-preview">URL: <b><?php echo htmlspecialchars($page['slug'] ?? 'home'); ?></b></div>-->
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Language Direction</label>
                <select class="form-select" name="direction" required>
                    <option value="LTR">LTR</option>
                    <option value="RTL">RTL</option>
                </select>
            </div>
            <div class="mb-3" style="display:none;">
                <label class="form-label">Page Name</label>
                <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name'] ?? 'Home'); ?>" >
            </div>
            <div class="mb-3">
                <label class="form-label">Slug <?php if ($translated_data): ?><small class="text-success">(Not translated)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($page['slug'] ?? ($english_page['slug'] ?? 'home')); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Meta Title <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_title" value="<?php echo htmlspecialchars($page['meta_title'] ?? ($english_page['meta_title'] ?? '')); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Meta Description <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_description" rows="2"><?php echo htmlspecialchars($page['meta_description'] ?? ($english_page['meta_description'] ?? '')); ?></textarea>
            </div>
            <h3>Banner Title</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Header <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="header" value="<?php echo htmlspecialchars($page['header'] ?? ($english_page['header'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['header'] ?? ''); ?>">
            </div>
            <h3>Section 1</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Title 1 <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="title1" value="<?php echo htmlspecialchars($page['title1'] ?? ($english_page['title1'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['title1'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description 1 <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control" id="editor1" name="description1" dir="ltr" placeholder="<?php echo htmlspecialchars($english_page['description1'] ?? ''); ?>"><?php echo htmlspecialchars($page['description1'] ?? ($english_page['description1'] ?? '')); ?></textarea>
            </div>
           
            <div class="mb-3" style="display:none">
                <label class="form-label">Title 3</label>
                <input type="text" class="form-control" name="title3" value="<?php echo htmlspecialchars($page['title3'] ?? ''); ?>">
            </div>
            <div class="mb-3" style="display:none">
                <label class="form-label">Description 3</label>
                <textarea class="form-control summernote" name="description3" dir="ltr"><?php echo htmlspecialchars($page['description3'] ?? ''); ?></textarea>
            </div>
            <h3>Section 2</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Heading 2 <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="heading2" value="<?php echo htmlspecialchars($page['heading2'] ?? ($english_page['heading2'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['heading2'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description 2 (for Heading 2) <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control" id="editor2" name="heading2_description" dir="ltr" placeholder="<?php echo htmlspecialchars($english_page['heading2_description'] ?? ''); ?>"><?php echo htmlspecialchars($page['heading2_description'] ?? ($english_page['heading2_description'] ?? '')); ?></textarea>
            </div>
            <h3>Section 3</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">How to download heading <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="how_to_download_heading" value="<?php echo htmlspecialchars($page['how_to_download_heading'] ?? ($english_page['how_to_download_heading'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['how_to_download_heading'] ?? ''); ?>">
            </div>
            <!-- Add Column Section (Dynamic) -->
            <div class="mb-3">
                <label class="form-label">Add Columns</label>
                <div id="add-columns-wrapper">
                    <?php if (!empty($page['add_columns'])): ?>
                        <?php foreach ($page['add_columns'] as $col): ?>
                            <div class="add-column-item mb-2">
                                <input type="text" class="form-control mb-1" name="add_column_heading[]" placeholder="Column Heading" value="<?php echo htmlspecialchars($col['heading']); ?>">
                                <textarea class="form-control" name="add_column_description[]" placeholder="Column Description" dir="ltr"><?php echo htmlspecialchars($col['description']); ?></textarea>
                                <button type="button" class="btn btn-danger btn-sm remove-column-btn mt-1">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (!empty($english_page['add_columns'])): ?>
                        <?php foreach ($english_page['add_columns'] as $col): ?>
                            <div class="add-column-item mb-2">
                                <input type="text" class="form-control mb-1" name="add_column_heading[]" placeholder="<?php echo htmlspecialchars($col['heading']); ?>" value="">
                                <small class="text-muted">English: <?php echo htmlspecialchars($col['heading']); ?></small>
                                <textarea class="form-control" name="add_column_description[]" placeholder="<?php echo htmlspecialchars($col['description']); ?>" dir="ltr"></textarea>
                                <small class="text-muted">English: <?php echo htmlspecialchars(substr($col['description'], 0, 100)); ?>...</small>
                                <button type="button" class="btn btn-danger btn-sm remove-column-btn mt-1">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="add-column-item mb-2">
                            <input type="text" class="form-control mb-1" name="add_column_heading[]" placeholder="Column Heading">
                            <textarea class="form-control" name="add_column_description[]" placeholder="Column Description" dir="ltr"></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-column-btn">+ Add Column</button>
            </div>
            <!-- Description Section -->
            <h3>Section 4</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Description Bottom <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control" id="editor3" name="description_bottom" dir="ltr" placeholder="<?php echo htmlspecialchars($english_page['description_bottom'] ?? ''); ?>"><?php echo htmlspecialchars($page['description_bottom'] ?? ($english_page['description_bottom'] ?? '')); ?></textarea>
            </div>
            <!-- Image and Description Section -->
            <h3>Section 5</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Images</label>
                <div id="images-wrapper">
                    <?php 
                    $images_data = [];
                    if (!empty($page['images'])) {
                        // Check if it's already an array (from translated data) or needs to be decoded
                        $images_data = is_array($page['images']) ? $page['images'] : json_decode($page['images'], true);
                    } elseif (!empty($page['image'])) {
                        // Convert old single image to new format
                        $images_data = [[
                            'image' => $page['image'],
                            'description' => $page['image_description'] ?? ''
                        ]];
                    }
                    
                    // If no images exist for this language, use English images as default
                    $english_images_data = [];
                    if (empty($images_data) && !empty($english_page['images'])) {
                        $english_images_data = $english_page['images'];
                    }
                    
                    if (!empty($images_data)): ?>
                        <?php foreach ($images_data as $index => $img_data): ?>
                            <div class="image-item mb-3 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Image <?php echo $index + 1; ?></h6>
                                    <button type="button" class="btn btn-danger btn-sm remove-image-btn">Remove</button>
                                </div>
                                <?php if (!empty($img_data['image'])): ?>
                                    <input type="hidden" name="current_images[]" value="<?php echo htmlspecialchars($img_data['image']); ?>">
                                    <img src="<?php echo htmlspecialchars($img_data['image']); ?>" alt="Current Image" style="max-width:100px; margin-bottom:10px;">
                                <?php endif; ?>
                                <input type="file" class="form-control mb-2" name="images[]" accept="image/*">
                                <textarea class="form-control" name="image_descriptions[]" placeholder="Image Description" dir="ltr"><?php echo htmlspecialchars($img_data['description'] ?? ''); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (!empty($english_images_data)): ?>
                        <?php foreach ($english_images_data as $index => $img_data): ?>
                            <div class="image-item mb-3 p-3 border rounded">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6>Image <?php echo $index + 1; ?> <small class="text-muted">(English default)</small></h6>
                                    <button type="button" class="btn btn-danger btn-sm remove-image-btn">Remove</button>
                                </div>
                                <?php if (!empty($img_data['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($img_data['image']); ?>" alt="English Image" style="max-width:100px; margin-bottom:10px; opacity:0.6;">
                                    <br><small class="text-muted">English image (upload new or keep this)</small>
                                <?php endif; ?>
                                <input type="file" class="form-control mb-2" name="images[]" accept="image/*">
                                <textarea class="form-control" name="image_descriptions[]" placeholder="<?php echo htmlspecialchars($img_data['description'] ?? 'Image Description'); ?>" dir="ltr"></textarea>
                                <small class="text-muted">English description: <?php echo htmlspecialchars(substr($img_data['description'] ?? '', 0, 100)); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="image-item mb-3 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6>Image 1</h6>
                                <button type="button" class="btn btn-danger btn-sm remove-image-btn">Remove</button>
                            </div>
                            <input type="file" class="form-control mb-2" name="images[]" accept="image/*">
                            <textarea class="form-control " name="image_descriptions[]" placeholder="Image Description" dir="ltr"></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" id="add-image-btn">+ Add Image</button>
            </div>
            <!-- Title/Description/Image 1 -->
            <h3>Section 6</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Pink Title 1 (Section 2) <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="pink_title1_2" value="<?php echo htmlspecialchars($page['pink_title1_2'] ?? ($english_page['pink_title1_2'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['pink_title1_2'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Title 1 (Section 2) <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="title1_2" value="<?php echo htmlspecialchars($page['title1_2'] ?? ($english_page['title1_2'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['title1_2'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description 1 (Section 2) <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control" id="editor4" name="description1_2" dir="ltr" placeholder="<?php echo htmlspecialchars($english_page['description1_2'] ?? ''); ?>"><?php echo htmlspecialchars($page['description1_2'] ?? ($english_page['description1_2'] ?? '')); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Image 1 (Section 2) <?php if (!$page && $english_page && !empty($english_page['image1'])): ?><small class="text-muted">(English image available)</small><?php endif; ?></label>
                <?php if (!empty($page['image1'])): ?>
                    <input type="hidden" name="current_image1" value="<?php echo htmlspecialchars($page['image1']); ?>">
                    <img src="<?php echo htmlspecialchars($page['image1']); ?>" alt="Current Image 1" style="max-width: 100px; margin-top: 10px;">
                <?php elseif (!empty($english_page['image1'])): ?>
                    <input type="hidden" name="current_image1" value="<?php echo htmlspecialchars($english_page['image1']); ?>">
                    <img src="<?php echo htmlspecialchars($english_page['image1']); ?>" alt="English Image 1" style="max-width: 100px; margin-top: 10px; opacity:0.6;">
                    <br><small class="text-muted">English image (will be used if no new image uploaded)</small>
                <?php endif; ?>
                <input type="file" class="form-control" name="image1" accept="image/*">
            </div>
            <!-- Title/Description/Image 2 -->
            <h3>Section 7</h3>
            <hr>
            <div class="mb-3">
                <label class="form-label">Title 2 (Section 2) <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="title2_2" value="<?php echo htmlspecialchars($page['title2_2'] ?? ($english_page['title2_2'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['title2_2'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description 2 (Section 2) <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control" id="editor5" name="description2_2" dir="ltr" placeholder="<?php echo htmlspecialchars($english_page['description2_2'] ?? ''); ?>"><?php echo htmlspecialchars($page['description2_2'] ?? ($english_page['description2_2'] ?? '')); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Image 2 (Section 2) <?php if (!$page && $english_page && !empty($english_page['image2'])): ?><small class="text-muted">(English image available)</small><?php endif; ?></label>
                <?php if (!empty($page['image2'])): ?>
                    <input type="hidden" name="current_image2" value="<?php echo htmlspecialchars($page['image2']); ?>">
                    <img src="<?php echo htmlspecialchars($page['image2']); ?>" alt="Current Image 2" style="max-width: 100px; margin-top: 10px;">
                <?php elseif (!empty($english_page['image2'])): ?>
                    <input type="hidden" name="current_image2" value="<?php echo htmlspecialchars($english_page['image2']); ?>">
                    <img src="<?php echo htmlspecialchars($english_page['image2']); ?>" alt="English Image 2" style="max-width: 100px; margin-top: 10px; opacity:0.6;">
                    <br><small class="text-muted">English image (will be used if no new image uploaded)</small>
                <?php endif; ?>
                <input type="file" class="form-control" name="image2" accept="image/*">
            </div>
             <h3>Section 8</h3>
            <hr>
             <div class="mb-3">
                <label class="form-label">Title <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <input type="text" class="form-control" name="title2" value="<?php echo htmlspecialchars($page['title2'] ?? ($english_page['title2'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars($english_page['title2'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description <?php if (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                <textarea class="form-control" id="editor6" name="description2" dir="ltr" placeholder="<?php echo htmlspecialchars($english_page['description2'] ?? ''); ?>"><?php echo htmlspecialchars($page['description2'] ?? ($english_page['description2'] ?? '')); ?></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <?php if ($lang_id != 41): ?>
                    <button type="submit" name="translate_content" class="btn btn-success" onclick="return confirm('Translate all content from English to <?php echo htmlspecialchars($lang['name']); ?>? This will use ChatGPT API and may take a moment.')">
                        <i class="fa fa-language"></i> Translate from English
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <!-- FAQs Section -->
    <div class="page-section mt-5">
        <h4>FAQs (Home Page)</h4>
        <hr>
        <form method="post">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="faqs_enabled" name="faqs_enabled" <?php echo !empty($page['faqs_enabled']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="faqs_enabled">Enable FAQs on homepage</label>
            </div>
            <div id="faq-wrapper">
                <?php 
                $faqs_list = [];
                if (!empty($page['faqs'])) {
                    $faqs_list = is_array($page['faqs']) ? $page['faqs'] : json_decode($page['faqs'], true);
                } elseif (!empty($english_page['faqs'])) {
                    $faqs_list = json_decode($english_page['faqs'], true);
                }
                if (!empty($faqs_list)): ?>
                    <?php foreach ($faqs_list as $idx => $faq): ?>
                        <div class="faq-item border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>FAQ <?php echo $idx + 1; ?></strong>
                                <button type="button" class="btn btn-sm btn-danger remove-faq">Remove</button>
                            </div>
                            <input type="text" class="form-control mb-2" name="faq_question[]" placeholder="Question" value="<?php echo htmlspecialchars($faq['q'] ?? ''); ?>">
                            <textarea class="form-control" name="faq_answer[]" placeholder="Answer" dir="ltr"><?php echo htmlspecialchars($faq['a'] ?? ''); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="faq-item border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>FAQ 1</strong>
                            <button type="button" class="btn btn-sm btn-danger remove-faq">Remove</button>
                        </div>
                        <input type="text" class="form-control mb-2" name="faq_question[]" placeholder="Question">
                        <textarea class="form-control" name="faq_answer[]" placeholder="Answer" dir="ltr"></textarea>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-faq" class="btn btn-secondary btn-sm mt-2">+ Add FAQ</button>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save FAQs</button>
            </div>
        </form>
    </div>
    <div class="mt-5">
    <div class="page-section">
        <div class="card-header">
            <h5 class="mb-0">Redirects</h5>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Sno.</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sno = 1; foreach ($all_slugs as $info): ?>
                    <tr>
                        <td><?php echo $sno++; ?></td>
                        <td><b><?php echo htmlspecialchars($info['slug']); ?></b></td>
                        <td>
                            <?php if ($info['status'] === 'Active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Redirect</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($info['status'] !== 'Active'): ?>
                                <a href="?id=<?php echo $lang_id; ?>&delete_redirect=<?php echo $info['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this redirect?');">Delete</a>
                            <?php elseif ($info['status'] === 'Active'): ?>
                                <a href="?id=<?php echo $lang_id; ?>&delete_active_slug=1" class="btn btn-sm btn-danger" onclick="return confirm('Delete the active slug? If redirects exist, the last redirect will become active.');">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<!-- Redirects Table in Card -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    // CKEditor configuration with image upload
    function initializeCKEditor(elementId, height = 300) {
        if (elementId.startsWith('#')) {
            elementId = elementId.substring(1);
        }
        
        if (CKEDITOR.instances[elementId]) {
            CKEDITOR.instances[elementId].destroy();
        }
        
        CKEDITOR.replace(elementId, {
            height: height,
            filebrowserUploadUrl: 'upload-clean.php',
            filebrowserUploadMethod: 'form'
        });
    }

    // Initialize all main editors
    $(document).ready(function() {
        initializeCKEditor('editor1', 300);
        initializeCKEditor('editor2', 300);
        initializeCKEditor('editor3', 300);
        initializeCKEditor('editor4', 300);
        initializeCKEditor('editor5', 300);
        initializeCKEditor('editor6', 300);
        
        // Initialize existing CKEditor instances with summernote class
        $('.summernote').each(function() {
            var editorId = $(this).attr('id');
            if (editorId && !CKEDITOR.instances[editorId]) {
                CKEDITOR.replace(editorId, {
                    height: 180,
                    filebrowserUploadUrl: 'upload-clean.php',
                    filebrowserUploadMethod: 'form'
                });
            }
        });
    });
</script>
<script>
    $(document).ready(function() {
        function stripAttributes(html) {
            // Remove all attributes from all tags
            return html.replace(/<(\w+)(\s+[^>]*)?>/g, '<$1>');
        }
     
        // Add Column dynamic logic
        var columnCounter = 0;
        $('#add-column-btn').on('click', function() {
            columnCounter++;
            var textareaId = 'add_column_desc_' + columnCounter;
            var newItem = $('<div class="add-column-item mb-2">' +
                '<input type="text" class="form-control mb-1" name="add_column_heading[]" placeholder="Column Heading">' +
                '<textarea class="form-control" name="add_column_description[]" placeholder="Column Description" dir="ltr"></textarea>' +
                '<button type="button" class="btn btn-danger btn-sm remove-column-btn mt-1">Remove</button>' +
                '</div>');
            $('#add-columns-wrapper').append(newItem);
            CKEDITOR.replace(textareaId, {
                height: 180,
                filebrowserUploadUrl: 'upload-clean.php',
                filebrowserUploadMethod: 'form'
            });
        });
        // Remove column
        $('#add-columns-wrapper').on('click', '.remove-column-btn', function() {
            $(this).closest('.add-column-item').remove();
        });
        
        // Add Image dynamic logic
        var imageDescCounter = 0;
        $('#add-image-btn').on('click', function() {
            var imageCount = $('.image-item').length + 1;
            imageDescCounter++;
            var textareaId = 'image_desc_' + imageDescCounter;
            var newItem = $('<div class="image-item mb-3 p-3 border rounded">' +
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<h6>Image ' + imageCount + '</h6>' +
                '<button type="button" class="btn btn-danger btn-sm remove-image-btn">Remove</button>' +
                '</div>' +
                '<input type="file" class="form-control mb-2" name="images[]" accept="image/*">' +
                '<textarea class="form-control" name="image_descriptions[]" placeholder="Image Description" dir="ltr"></textarea>' +
                '</div>');
            $('#images-wrapper').append(newItem);
            // Note: We're not initializing CKEditor for image descriptions as they are typically short text
        });
        
        // Remove image
        $('#images-wrapper').on('click', '.remove-image-btn', function() {
            $(this).closest('.image-item').remove();
            // Renumber remaining images
            $('.image-item').each(function(index) {
                $(this).find('h6').text('Image ' + (index + 1));
            });
        });

        // FAQs dynamic logic
        $('#add-faq').on('click', function() {
            var count = $('.faq-item').length + 1;
            var html = '<div class="faq-item border rounded p-3 mb-2">' +
                '<div class="d-flex justify-content-between align-items-center mb-2">' +
                '<strong>FAQ ' + count + '</strong>' +
                '<button type="button" class="btn btn-sm btn-danger remove-faq">Remove</button>' +
                '</div>' +
                '<input type="text" class="form-control mb-2" name="faq_question[]" placeholder="Question">' +
                '<textarea class="form-control" name="faq_answer[]" placeholder="Answer" dir="ltr"></textarea>' +
                '</div>';
            $('#faq-wrapper').append(html);
        });
        $('#faq-wrapper').on('click', '.remove-faq', function() {
            $(this).closest('.faq-item').remove();
            // Renumber
            $('.faq-item').each(function(index) {
                $(this).find('strong').text('FAQ ' + (index + 1));
            });
        });
    });
</script>
<?php include 'includes/footer.php'; ?> 