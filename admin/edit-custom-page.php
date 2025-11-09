<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

// Handle translation request (AJAX) - MUST BE BEFORE ANY OTHER PROCESSING
if (isset($_POST['translate_content'])) {
    header('Content-Type: application/json');
    
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    
    try {
        $text = $_POST['text'] ?? '';
        $target_lang = $_POST['target_lang'] ?? 'en';
        
        error_log("Translation request - Target: $target_lang, Text length: " . strlen($text));
        
        if (empty($text) || empty(trim($text))) {
            echo json_encode(['success' => false, 'error' => 'Empty text provided']);
            restore_error_handler();
            exit;
        }
        
        $isHtml = strip_tags($text) !== $text;
        $textToTranslate = $isHtml ? strip_tags($text) : $text;
        $textToTranslate = trim($textToTranslate);
        
        if (empty($textToTranslate)) {
            echo json_encode(['success' => false, 'error' => 'Text is empty after stripping HTML']);
            restore_error_handler();
            exit;
        }
        
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

$page_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$page = null;
$success = '';
$error = '';
$static_pages = ['Home', 'Mp3-file', 'Stories-file', 'How-file', 'Copyright', 'Terms', 'Contact', 'Privacy', 'Faqs'];

// Ensure custom_page_slugs table exists
$conn->query("CREATE TABLE IF NOT EXISTS custom_page_slugs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    custom_page_id INT NOT NULL,
    slug VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (custom_page_id) REFERENCES custom_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($page_id) {
    $res = $conn->query("SELECT * FROM custom_pages WHERE id=$page_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
        $lang_id = $page['language_id']; // Get the language ID from the custom page
        
        // Get language info for translation
        $lang = null;
        $lang_code = '';
        if ($lang_id) {
            $res_lang = $conn->query("SELECT * FROM languages WHERE id=$lang_id LIMIT 1");
            if ($res_lang && $res_lang->num_rows > 0) {
                $lang = $res_lang->fetch_assoc();
                $lang_code = $lang['code'];
            }
        }
    } else {
        die('Page not found.');
    }
} else {
    die('Page not found.');
}

// Handle delete slug (AJAX or GET)
if (isset($_GET['delete_slug_id'])) {
    $delete_id = intval($_GET['delete_slug_id']);
    // Find the slug to delete
    $res = $conn->query("SELECT * FROM custom_page_slugs WHERE id=$delete_id AND custom_page_id=$page_id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $slug_row = $res->fetch_assoc();
        if ($slug_row['status'] === 'active') {
            // If deleting active, find the last added inactive slug
            $res2 = $conn->query("SELECT * FROM custom_page_slugs WHERE custom_page_id=$page_id AND status='inactive' ORDER BY id DESC LIMIT 1");
            if ($res2 && $res2->num_rows > 0) {
                $last = $res2->fetch_assoc();
                // Set this as active
                $conn->query("UPDATE custom_page_slugs SET status='active' WHERE id=" . $last['id']);
                // Update main table slug
                $conn->query("UPDATE custom_pages SET slug='" . $conn->real_escape_string($last['slug']) . "' WHERE id=$page_id");
            } else {
                // No other slugs, just clear slug in main table
                $conn->query("UPDATE custom_pages SET slug='' WHERE id=$page_id");
            }
        }
        // Delete the slug row
        $conn->query("DELETE FROM custom_page_slugs WHERE id=$delete_id");
        header("Location: edit-custom-page.php?id=$page_id");
        exit;
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $page_name = trim($_POST['page_name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $meta_header = trim($_POST['meta_header'] ?? '');
    $header = trim($_POST['header'] ?? '');
    $heading = trim($_POST['heading'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $previous_slug = $page['slug'];
    $sql = "UPDATE custom_pages SET
        page_name='".$conn->real_escape_string($page_name)."',
        slug='".$conn->real_escape_string($slug)."',
        meta_title='".$conn->real_escape_string($meta_title)."',
        meta_description='".$conn->real_escape_string($meta_description)."',
        meta_header='".$conn->real_escape_string($meta_header)."',
        header='".$conn->real_escape_string($header)."',
        heading='".$conn->real_escape_string($heading)."',
        description='".$conn->real_escape_string($description)."'
        WHERE id=$page_id";
    if ($conn->query($sql)) {
        // If slug changed, update slug history
        if ($slug && $slug !== $previous_slug) {
            // Set all previous slugs to inactive
            $conn->query("UPDATE custom_page_slugs SET status='inactive' WHERE custom_page_id=$page_id");
            // Check if this slug already exists
            $res = $conn->query("SELECT * FROM custom_page_slugs WHERE custom_page_id=$page_id AND slug='".$conn->real_escape_string($slug)."'");
            if ($res && $res->num_rows > 0) {
                $conn->query("UPDATE custom_page_slugs SET status='active' WHERE custom_page_id=$page_id AND slug='".$conn->real_escape_string($slug)."'");
            } else {
                $conn->query("INSERT INTO custom_page_slugs (custom_page_id, slug, status) VALUES ($page_id, '".$conn->real_escape_string($slug)."', 'active')");
            }
        }
        $success = 'Page updated successfully.';
        // Refresh page data
        $res = $conn->query("SELECT * FROM custom_pages WHERE id=$page_id LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $page = $res->fetch_assoc();
        }
    } else {
        $error = 'Failed to update page: ' . $conn->error;
    }
}

// Fetch all slugs for this page
$slugs = [];
$res = $conn->query("SELECT * FROM custom_page_slugs WHERE custom_page_id=$page_id ORDER BY id ASC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $slugs[] = $row;
    }
}

include 'includes/header.php';
?>
<div class="main-content" id="mainContent">
<?php
            // Get the ID from the URL
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            ?>
            
            <!-- Back Button -->
            <a href="language_pages.php?id=<?php echo $lang_id; ?>" class="btn btn-secondary">
                ← Back
            </a>
    <div class="container-fluid">
        <h4 class="fw-bold mb-4">Edit Custom Page<?php if ($lang): ?> (<?php echo htmlspecialchars($lang['name']); ?>)<?php endif; ?></h4>
        <div id="translation-alert" style="display:none;"></div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Page Name</label>
                <input type="text" class="form-control" name="page_name" value="<?php echo htmlspecialchars($page['page_name']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Slug</label>
                <input type="text" class="form-control" name="slug" value="<?php echo htmlspecialchars($page['slug']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Meta Title</label>
                <input type="text" class="form-control" name="meta_title" value="<?php echo htmlspecialchars($page['meta_title']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Meta Description</label>
                <textarea class="form-control" name="meta_description" rows="2"><?php echo htmlspecialchars($page['meta_description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Header (Meta Tags)</label>
                <textarea class="form-control" name="meta_header" rows="2"><?php echo htmlspecialchars($page['meta_header']); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Header</label>
                <input type="text" class="form-control" name="header" value="<?php echo htmlspecialchars($page['header']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Heading</label>
                <input type="text" class="form-control" name="heading" value="<?php echo htmlspecialchars($page['heading']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="editor" name="description"><?php echo htmlspecialchars($page['description']); ?></textarea>
            </div>
            <?php if ($lang && $lang_code !== 'en'): ?>
            <button type="button" class="btn btn-info" id="translateBtn" onclick="translateContent()">
                <i class="fa fa-language"></i> Translate
            </button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
        <hr>
        <h5>Slug History</h5>
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
                <?php $sno = 1; foreach ($slugs as $r): ?>
                <tr>
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
                        <a href="?id=<?php echo $page_id; ?>&delete_slug_id=<?php echo $r['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this slug? If it is active, the last added will become active.');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

        // Translation functionality
        async function translateContent() {
            const langCode = '<?php echo $lang_code ?? ''; ?>';
            const langName = '<?php echo htmlspecialchars($lang['name'] ?? ''); ?>';
            
            if (!langCode || langCode === 'en') {
                alert('Translation is only available for non-English languages.');
                return;
            }
            
            if (!confirm(`Translate all content to ${langName} (${langCode})?\n\nNote: Please review the translations before saving.`)) {
                return;
            }
            
            const btn = $('#translateBtn');
            const originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Translating...');
            
            $('#translation-alert').html(`<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Translating content to ${langName}... Please wait.</div>`).show();
            
            let translatedCount = 0;
            let failedCount = 0;
            
            const fields = [
                {name: 'page_name', selector: 'input[name="page_name"]'},
                {name: 'meta_title', selector: 'input[name="meta_title"]'},
                {name: 'meta_description', selector: 'textarea[name="meta_description"]'},
                {name: 'header', selector: 'input[name="header"]'},
                {name: 'heading', selector: 'input[name="heading"]'},
                {name: 'description', selector: '#editor', isCKEditor: true}
            ];
            
            try {
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
                        await new Promise(resolve => setTimeout(resolve, 500));
                    } else {
                        console.log(`Skipping ${field.name}: no content`);
                    }
                }
                
                btn.prop('disabled', false).html(originalText);
                
                if (translatedCount > 0) {
                    let message = `<div class="alert alert-success">
                        <strong><i class="fa fa-check-circle"></i> Translation Completed!</strong><br>
                        ✓ ${translatedCount} field(s) translated successfully<br>
                        ${failedCount > 0 ? '✗ ' + failedCount + ' field(s) failed<br>' : ''}
                        <strong>Please review the translations below and click "Save" when ready.</strong>
                    </div>`;
                    $('#translation-alert').html(message).show();
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
                    $('html, body').animate({ scrollTop: 0 }, 'fast');
                }
                
            } catch (error) {
                console.error('Translation error:', error);
                $('#translation-alert').html('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Translation failed:</strong> ' + error + '<br>Please try again or translate manually.</div>').show();
                btn.prop('disabled', false).html(originalText);
                $('html, body').animate({ scrollTop: 0 }, 'fast');
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
                    timeout: 30000,
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
                        
                        let errorMsg = error || 'Network error';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.error) {
                                errorMsg = response.error;
                            }
                        } catch (e) {
                            if (xhr.responseText && xhr.responseText.length > 0) {
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