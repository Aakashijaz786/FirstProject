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

$page_name = 'Faqs';
$slug = 'faqs';
$success = '';
$error = '';
$page = null;
$english_page = null;

// Handle translate request - DO NOT SAVE, just prepare translated content
$translated_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['translate_content'])) {
    // First fetch current page
    $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
    }
    
    // Fetch English page if not already loaded
    if (!$english_page) {
        $sql = "SELECT * FROM language_pages WHERE language_id=41 AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $english_page = $res->fetch_assoc();
        }
    }
    
    if ($english_page) {
        $target_language = $lang['name'];
        error_log("Translating FAQs page from English to {$target_language}");
        
        // Translate page metadata
        $meta_title_result = translateText($english_page['meta_title'], $target_language);
        $meta_description_result = translateText($english_page['meta_description'], $target_language);
        $meta_header_result = translateText($english_page['meta_header'], $target_language);
        
        // Fetch English FAQs
        $english_faqs = [];
        $sql = "SELECT * FROM language_faqs WHERE language_id=41 ORDER BY id ASC";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $english_faqs[] = $row;
            }
        }
        
        // Translate FAQs
        $translated_faqs = [];
        foreach ($english_faqs as $faq) {
            $question_result = translateText($faq['question'], $target_language);
            $answer_result = translateText($faq['answer'], $target_language);
            
            $translated_faqs[] = [
                'question' => $question_result['success'] ? $question_result['response'] : $faq['question'],
                'answer' => $answer_result['success'] ? $answer_result['response'] : $faq['answer']
            ];
            
            if (!$question_result['success']) error_log("FAQ question translation failed: " . $question_result['error']);
            if (!$answer_result['success']) error_log("FAQ answer translation failed: " . $answer_result['error']);
        }
        
        $translated_data = [
            'meta_title' => $meta_title_result['success'] ? $meta_title_result['response'] : $english_page['meta_title'],
            'meta_description' => $meta_description_result['success'] ? $meta_description_result['response'] : $english_page['meta_description'],
            'meta_header' => $meta_header_result['success'] ? $meta_header_result['response'] : $english_page['meta_header'],
            'slug' => $page ? $page['slug'] : 'faqs',
            'faqs' => $translated_faqs
        ];
        
        $success = "Content and FAQs translated from English to {$target_language}! Please review and click Save to store the translation.";
        
        if (!$meta_title_result['success']) {
            error_log("Meta Title translation failed: " . $meta_title_result['error']);
            $error = "Some translations failed. Using English content where translation failed.";
        }
        if (!$meta_description_result['success']) error_log("Meta Description translation failed: " . $meta_description_result['error']);
        if (!$meta_header_result['success']) error_log("Meta Header translation failed: " . $meta_header_result['error']);
        
    } else {
        $error = 'No English content found to translate. Please ensure English (ID 41) FAQs page exists.';
    }
}

// Try to find the faqs page for this language
if (!$page) {
    $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
    }
}

// If no page exists, fetch English (ID 41) content as default
if (!$page && $lang_id != 41 && !$english_page) {
    $sql = "SELECT * FROM language_pages WHERE language_id=41 AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $english_page = $res->fetch_assoc();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['translate_content'])) {
    $page_name = trim($_POST['page_name'] ?? 'Faqs');
    $slug = trim($_POST['slug'] ?? 'faqs');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_header = trim($_POST['meta_header'] ?? '');
    $questions = $_POST['question'] ?? [];
    $answers = $_POST['answer'] ?? [];
    $faq_ids = $_POST['faq_id'] ?? [];

    if ($page) {
        // Update by id
        $page_id = $page['id'];
        $sql = "UPDATE language_pages SET page_name='" . $conn->real_escape_string($page_name) .
            "', slug='" . $conn->real_escape_string($slug) .
            "', meta_title='" . $conn->real_escape_string($meta_title) .
            "', meta_description='" . $conn->real_escape_string($meta_description) .
            "', meta_header='" . $conn->real_escape_string($meta_header) .
            "' WHERE id=$page_id";
        $conn->query($sql);
    } else {
        // Insert only if not exists
        $sql = "INSERT INTO language_pages (language_id, page_name, slug, meta_title, meta_description, meta_header) VALUES ("
            . "$lang_id, '" . $conn->real_escape_string($page_name) . "', '" . $conn->real_escape_string($slug) . "', '" . $conn->real_escape_string($meta_title) . "', '"
            . $conn->real_escape_string($meta_description) . "', '"
            . $conn->real_escape_string($meta_header) . "')";
        $conn->query($sql);
        // Always fetch the latest page after insert
        $sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
        $res = $conn->query($sql);
        if ($res && $res->num_rows > 0) {
            $page = $res->fetch_assoc();
        }
    }

    // Handle FAQs: update existing, insert new, delete removed
    // Fetch current faqs
    $existing_faqs = [];
    $sql = "SELECT * FROM language_faqs WHERE language_id=$lang_id ORDER BY id ASC";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $existing_faqs[$row['id']] = $row;
        }
    }
    $handled_ids = [];
    for ($i = 0; $i < count($questions); $i++) {
        $q = trim($questions[$i]);
        $a = trim($answers[$i]);
        $faq_id = isset($faq_ids[$i]) ? intval($faq_ids[$i]) : 0;
        if ($q && $a) {
            $q_esc = $conn->real_escape_string($q);
            $a_esc = $conn->real_escape_string($a);
            if ($faq_id && isset($existing_faqs[$faq_id])) {
                // Update existing
                $sql = "UPDATE language_faqs SET question='$q_esc', answer='$a_esc' WHERE id=$faq_id AND language_id=$lang_id";
                $conn->query($sql);
                $handled_ids[] = $faq_id;
            } else {
                // Insert new
                $sql = "INSERT INTO language_faqs (language_id, question, answer) VALUES ($lang_id, '$q_esc', '$a_esc')";
                $conn->query($sql);
                $handled_ids[] = $conn->insert_id;
            }
        }
    }
    // Delete removed faqs
    foreach ($existing_faqs as $id => $row) {
        if (!in_array($id, $handled_ids)) {
            $conn->query("DELETE FROM language_faqs WHERE id=$id");
        }
    }

    if (count($handled_ids) > 0) {
        $success = 'FAQs saved successfully.';
    } else {
        $error = 'Please add at least one FAQ.';
    }
}

// Always fetch the latest page and faqs after POST or GET
$page = null;
$sql = "SELECT * FROM language_pages WHERE language_id=$lang_id AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $page = $res->fetch_assoc();
    $slug = $page['slug']; // update $slug to latest
}

// Fetch English page if no page exists
if (!$page && $lang_id != 41) {
    $sql = "SELECT * FROM language_pages WHERE language_id=41 AND (LOWER(page_name) LIKE '%faq%' OR LOWER(slug) LIKE '%faq%') LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        $english_page = $res->fetch_assoc();
    }
}

// Use translated FAQs if available, otherwise fetch from database
if ($translated_data && isset($translated_data['faqs'])) {
    $faqs = $translated_data['faqs'];
} else {
    $faqs = [];
    $sql = "SELECT * FROM language_faqs WHERE language_id=$lang_id ORDER BY id ASC";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $faqs[] = $row;
        }
    }
}

// If no FAQs exist, fetch English FAQs as default
$english_faqs = [];
if (empty($faqs) && $lang_id != 41) {
    $sql = "SELECT * FROM language_faqs WHERE language_id=41 ORDER BY id ASC";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $english_faqs[] = $row;
        }
    }
}

if (empty($faqs)) {
    $faqs[] = ['question' => '', 'answer' => ''];
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
            <h4>FAQs (<?php echo htmlspecialchars($lang['name']); ?>)</h4>
            <div class="url-preview" style="display:none;">URL: <b>/<?php echo htmlspecialchars($lang_code); ?>/<?php echo htmlspecialchars($page['slug'] ?? 'faqs'); ?></b></div>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" id="faqsForm">
                <div class="mb-3"  style="display:none;">
                    <label class="form-label">Page Name</label>
                    <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name'] ?? 'Faqs'); ?>">
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Slug <?php if ($translated_data): ?><small class="text-success">(Not translated)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($translated_data ? $translated_data['slug'] : ($page['slug'] ?? ($english_page['slug'] ?? 'faqs'))); ?>">
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Meta Title <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <input type="text" class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_title" value="<?php echo htmlspecialchars($translated_data ? $translated_data['meta_title'] : ($page['meta_title'] ?? ($english_page['meta_title'] ?? ''))); ?>">
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Meta Description <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_description" rows="2"><?php echo htmlspecialchars($translated_data ? $translated_data['meta_description'] : ($page['meta_description'] ?? ($english_page['meta_description'] ?? ''))); ?></textarea>
                </div>
                <div class="mb-3" style="display:none;">
                    <label class="form-label">Header (Meta Tags) <?php if ($translated_data): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif (!$page && $english_page): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                    <textarea class="form-control <?php echo $translated_data ? 'border-success' : ''; ?>" name="meta_header" rows="2"><?php echo htmlspecialchars($translated_data ? $translated_data['meta_header'] : ($page['meta_header'] ?? ($english_page['meta_header'] ?? ''))); ?></textarea>
                </div>
                <div id="faqsContainer">
                    <?php 
                    $display_faqs = $faqs;
                    $is_translated = $translated_data && isset($translated_data['faqs']);
                    
                    if (empty($faqs) || (count($faqs) == 1 && empty($faqs[0]['question']))) {
                        if (!empty($english_faqs)) {
                            $display_faqs = $english_faqs;
                        }
                    }
                    
                    foreach ($display_faqs as $i => $faq): 
                        $is_english_default = (!$page && !empty($english_faqs) && !$is_translated);
                    ?>
                    <div class="faq-row row align-items-end">
                        <input type="hidden" name="faq_id[]" value="<?php echo isset($faq['id']) ? (int)$faq['id'] : 0; ?>">
                        <div class="col-md-12 mb-2">
                            <label class="form-label">Question <?php if ($is_translated): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif ($is_english_default): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                            <input type="text" class="form-control <?php echo $is_translated ? 'border-success' : ''; ?>" name="question[]" value="<?php echo htmlspecialchars($faq['question']); ?>" required>
                        </div>
                        <div class="col-md-12 mb-2">
                            <label class="form-label">Answer <?php if ($is_translated): ?><small class="text-success">(Translated - Review & Save)</small><?php elseif ($is_english_default): ?><small class="text-muted">(English default shown)</small><?php endif; ?></label>
                            <textarea class="form-control <?php echo $is_translated ? 'border-success' : ''; ?>" name="answer[]" rows="2" required><?php echo htmlspecialchars($faq['answer']); ?></textarea>
                        </div>
                        <div class="col-md-12 mb-2 text-end">
                            <?php if ($i > 0): ?>
                            <span class="remove-faq-btn" title="Remove FAQ" onclick="removeFaqRow(this)"><i class="fa fa-trash"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-secondary mb-3" onclick="addFaqRow()"><i class="fa fa-plus"></i> Add More</button>
                <br>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <?php if ($lang_id != 41): ?>
                        <button type="submit" name="translate_content" class="btn btn-success" onclick="return confirm('Translate all content and FAQs from English to <?php echo htmlspecialchars($lang['name']); ?>? This will use ChatGPT API.')">
                            <i class="fa fa-language"></i> Translate from English
                        </button>
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

        function addFaqRow() {
            const html = `<div class="faq-row row align-items-end">
                <div class="col-md-12 mb-2">
                    <label class="form-label">Question</label>
                    <input type="text" class="form-control" name="question[]" required>
                </div>
                <div class="col-md-12 mb-2">
                    <label class="form-label">Answer</label>
                    <textarea class="form-control" name="answer[]" rows="2" required></textarea>
                </div>
                <div class="col-md-12 mb-2 text-end">
                    <span class="remove-faq-btn" title="Remove FAQ" onclick="removeFaqRow(this)"><i class="fa fa-trash"></i></span>
                </div>
            </div>`;
            $('#faqsContainer').append(html);
        }
        function removeFaqRow(el) {
            $(el).closest('.faq-row').remove();
        }
    </script>
</body>
</html> 