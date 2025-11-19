<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

$success = '';
$error = '';

// Handle add new language
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_language'])) {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $image_path = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = 'lang_' . time() . '.' . $ext;
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $image_path = 'uploads/' . $filename;
        }
    }
    $add_download_label = $_POST['add_download_label'] ?? '';
    $add_how_to_save = $_POST['add_how_to_save'] ?? '';
    $add_tiktok_downloaders = $_POST['add_tiktok_downloaders'] ?? '';
    $add_stories = $_POST['add_stories'] ?? '';
    $add_terms_conditions = $_POST['add_terms_conditions'] ?? '';
    $add_privacy_policy = $_POST['add_privacy_policy'] ?? '';
    $add_contact = $_POST['add_contact'] ?? '';
    
    if ($name && $code) {
        $name_esc = $conn->real_escape_string($name);
        $code_esc = $conn->real_escape_string($code);
        $img_esc = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : 'NULL';
        $sql = "INSERT INTO languages (image, name, code, download_label, how_to_save, tiktok_downloaders, stories, terms_conditions, privacy_policy, contact) VALUES ($img_esc, '$name_esc', '$code_esc', '" . $conn->real_escape_string($add_download_label) . "', '" . $conn->real_escape_string($add_how_to_save) . "', '" . $conn->real_escape_string($add_tiktok_downloaders) . "', '" . $conn->real_escape_string($add_stories) . "', '" . $conn->real_escape_string($add_terms_conditions) . "', '" . $conn->real_escape_string($add_privacy_policy) . "', '" . $conn->real_escape_string($add_contact) . "')";
        if ($conn->query($sql)) {
            $success = 'Language added successfully!';
        } else {
            $error = 'Failed to add language.';
        }
    } else {
        $error = 'Please fill all fields.';
    }
}

// Handle edit language
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_language'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['edit_name'] ?? '');
    $code = trim($_POST['edit_code'] ?? '');
    $image_path = $_POST['current_image'] ?? null;
    if (!empty($_FILES['edit_image']['name'])) {
        $ext = pathinfo($_FILES['edit_image']['name'], PATHINFO_EXTENSION);
        $filename = 'lang_' . time() . '.' . $ext;
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $target)) {
            $image_path = 'uploads/' . $filename;
        }
    }
    $edit_download_label = $_POST['edit_download_label'] ?? '';
    $edit_how_to_save = $_POST['edit_how_to_save'] ?? '';
    $edit_tiktok_downloaders = $_POST['edit_tiktok_downloaders'] ?? '';
    $edit_stories = $_POST['edit_stories'] ?? '';
    $edit_terms_conditions = $_POST['edit_terms_conditions'] ?? '';
    $edit_privacy_policy = $_POST['edit_privacy_policy'] ?? '';
    $edit_contact = $_POST['edit_contact'] ?? '';
    
    if ($id && $name && $code) {
        $name_esc = $conn->real_escape_string($name);
        $code_esc = $conn->real_escape_string($code);
        $img_esc = $image_path ? "'" . $conn->real_escape_string($image_path) . "'" : 'NULL';
        $sql = "UPDATE languages SET image=$img_esc, name='$name_esc', code='$code_esc',
  download_label='" . $conn->real_escape_string($edit_download_label) . "',
  how_to_save='" . $conn->real_escape_string($edit_how_to_save) . "',
  tiktok_downloaders='" . $conn->real_escape_string($edit_tiktok_downloaders) . "',
  stories='" . $conn->real_escape_string($edit_stories) . "',
  terms_conditions='" . $conn->real_escape_string($edit_terms_conditions) . "',
  privacy_policy='" . $conn->real_escape_string($edit_privacy_policy) . "',
  contact='" . $conn->real_escape_string($edit_contact) . "'
  WHERE id=$id";
        if ($conn->query($sql)) {
            $success = 'Language updated successfully!';
        } else {
            $error = 'Failed to update language.';
        }
    } else {
        $error = 'Please fill all fields.';
    }
}

// Handle delete language
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_language'])) {
    $id = intval($_POST['delete_id']);
    
    // Delete all associated pages and data for this language
    // Delete from languages_home
    $conn->query("DELETE FROM languages_home WHERE language_id=$id");
    
    // Delete from languages_home_redirects
    $conn->query("DELETE FROM languages_home_redirects WHERE language_id=$id");
    
    // Delete from language_pages (Terms, Privacy, Copyright, FAQs, etc.)
    $conn->query("DELETE FROM language_pages WHERE language_id=$id");
    
    // Delete from language_faqs
    $conn->query("DELETE FROM language_faqs WHERE language_id=$id");
    
    // Delete from mp3_pages
    $conn->query("DELETE FROM mp3_pages WHERE language_id=$id");
    
    // Delete from language_mp3_titles
    $conn->query("DELETE FROM language_mp3_titles WHERE language_id=$id");
    
    // Delete from mp3_page_slugs (need to get mp3_page_id first)
    $mp3_pages = $conn->query("SELECT id FROM mp3_pages WHERE language_id=$id");
    if ($mp3_pages && $mp3_pages->num_rows > 0) {
        while ($mp3 = $mp3_pages->fetch_assoc()) {
            $conn->query("DELETE FROM mp3_page_slugs WHERE mp3_page_id=" . $mp3['id']);
        }
    }
    
    // Delete from stories_pages
    $conn->query("DELETE FROM stories_pages WHERE language_id=$id");
    
    // Delete from language_stories_titles
    $conn->query("DELETE FROM language_stories_titles WHERE language_id=$id");
    
    // Delete from stories_page_slugs (need to get stories_page_id first)
    $stories_pages = $conn->query("SELECT id FROM stories_pages WHERE language_id=$id");
    if ($stories_pages && $stories_pages->num_rows > 0) {
        while ($story = $stories_pages->fetch_assoc()) {
            $conn->query("DELETE FROM stories_page_slugs WHERE stories_page_id=" . $story['id']);
        }
    }
    
    // Finally, delete the language itself
    $sql = "DELETE FROM languages WHERE id=$id";
    if ($conn->query($sql)) {
        $success = 'Language and all associated pages deleted successfully!';
    } else {
        $error = 'Failed to delete language.';
    }
}

// Handle set default language
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_default_language'])) {
    $id = intval($_POST['default_id']);
    // Unset all as default
    $conn->query("UPDATE languages SET is_default=0");
    // Set selected as default
    $conn->query("UPDATE languages SET is_default=1 WHERE id=$id");
    $success = 'Default language updated!';
}

// Fetch all languages
$languages = [];
$sql = "SELECT * FROM languages ORDER BY id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $languages[] = $row;
    }
}
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Languages</h4>
            <button class="btn btn-add-language" data-bs-toggle="modal" data-bs-target="#addLanguageModal">Add New Language</button>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 10%">Image</th>
                        <th style="width: 30%">Name</th>
                        <th style="width: 20%">Code</th>
                        <th style="width: 20%">Pages</th>
                        <th style="width: 10%">Default</th>
                        <th style="width: 10%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (
                        $languages as $lang): ?>
                    <tr>
                        <td>
                            <?php if (!empty($lang['image'])): ?>
                                <img src="<?php echo htmlspecialchars($lang['image']); ?>" alt="Lang" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($lang['name']); ?></td>
                        <td><?php echo htmlspecialchars($lang['code']); ?></td>
                        <td>
                            <a href="language_pages.php?id=<?php echo $lang['id']; ?>" class="icon-action pages" title="Pages">
                                <i class="fa-solid fa-up-right-from-square"></i>
                            </a>
                        </td>
                        <td>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to set this language as default?');">
                                <input type="hidden" name="default_id" value="<?php echo $lang['id']; ?>">
                                <input type="radio" name="set_default_language" value="1" onchange="this.form.submit();" <?php echo ($lang['is_default'] == 1) ? 'checked' : ''; ?> <?php echo ($lang['is_default'] == 1) ? 'disabled' : ''; ?>>
                                <span><?php echo ($lang['is_default'] == 1) ? 'Default' : 'Set Default'; ?></span>
                            </form>
                        </td>
                        <td>
                            <a href="#" class="edit-btn"
                               data-id="<?php echo $lang['id']; ?>"
                               data-name="<?php echo htmlspecialchars($lang['name']); ?>"
                               data-code="<?php echo htmlspecialchars($lang['code']); ?>"
                               data-image="<?php echo htmlspecialchars($lang['image']); ?>"
                               data-download_label="<?php echo htmlspecialchars($lang['download_label'] ?? ''); ?>"
                               data-how_to_save="<?php echo htmlspecialchars($lang['how_to_save'] ?? ''); ?>"
                               data-tiktok_downloaders="<?php echo htmlspecialchars($lang['tiktok_downloaders'] ?? ''); ?>"
                               data-stories="<?php echo htmlspecialchars($lang['stories'] ?? ''); ?>"
                               data-terms_conditions="<?php echo htmlspecialchars($lang['terms_conditions'] ?? ''); ?>"
                               data-privacy_policy="<?php echo htmlspecialchars($lang['privacy_policy'] ?? ''); ?>"
                               data-contact="<?php echo htmlspecialchars($lang['contact'] ?? ''); ?>"
                               data-bs-toggle="modal" data-bs-target="#editLanguageModal">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this language?');">
                                <input type="hidden" name="delete_id" value="<?php echo $lang['id']; ?>">
                                <button type="submit" name="delete_language" class="icon-action delete btn btn-link p-0" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Language Modal -->
    <div class="modal fade" id="addLanguageModal" tabindex="-1" aria-labelledby="addLanguageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="post" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title" id="addLanguageModalLabel">Add New Language</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Language Image</label>
                    <input type="file" class="form-control" name="image" accept="image/*">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Language Name</label>
                    <input type="text" class="form-control" name="name" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Language Code</label>
                    <input type="text" class="form-control" name="code" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Download Label</label>
                    <input type="text" class="form-control" name="add_download_label" id="add_download_label">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">YouTube to MP3</label>
                    <input type="text" class="form-control" name="add_how_to_save" id="add_how_to_save" value="YouTube Viewer">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">YouTube Downloader</label>
                    <input type="text" class="form-control" name="add_tiktok_downloaders" id="add_tiktok_downloaders">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">YouTube to MP4</label>
                    <input type="text" class="form-control" name="add_stories" id="add_stories">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Terms & Conditions</label>
                    <input type="text" class="form-control" name="add_terms_conditions" id="add_terms_conditions">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Privacy Policy</label>
                    <input type="text" class="form-control" name="add_privacy_policy" id="add_privacy_policy">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Contact</label>
                    <input type="text" class="form-control" name="add_contact" id="add_contact">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" name="add_language">Add Language</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Language Modal -->
    <div class="modal fade" id="editLanguageModal" tabindex="-1" aria-labelledby="editLanguageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="current_image" id="current_image">
            <div class="modal-header">
              <h5 class="modal-title" id="editLanguageModalLabel">Edit Language</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Language Image</label>
                    <input type="file" class="form-control" name="edit_image" accept="image/*">
                    <div id="edit_image_preview" class="mt-2"></div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Language Name</label>
                    <input type="text" class="form-control" name="edit_name" id="edit_name" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Language Code</label>
                    <input type="text" class="form-control" name="edit_code" id="edit_code" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Download Label</label>
                    <input type="text" class="form-control" name="edit_download_label" id="edit_download_label">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">YouTube to MP3</label>
                    <input type="text" class="form-control" name="edit_how_to_save" id="edit_how_to_save">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">YouTube Downloader</label>
                    <input type="text" class="form-control" name="edit_tiktok_downloaders" id="edit_tiktok_downloaders">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">YouTube to MP4</label>
                    <input type="text" class="form-control" name="edit_stories" id="edit_stories">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Terms & Conditions</label>
                    <input type="text" class="form-control" name="edit_terms_conditions" id="edit_terms_conditions">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Privacy Policy</label>
                    <input type="text" class="form-control" name="edit_privacy_policy" id="edit_privacy_policy">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Contact</label>
                    <input type="text" class="form-control" name="edit_contact" id="edit_contact">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary" name="edit_language">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('mainContent');
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        });

        // Edit modal logic
        document.querySelectorAll('.edit-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Set all modal fields
                document.getElementById('edit_id').value = this.getAttribute('data-id') || '';
                document.getElementById('edit_name').value = this.getAttribute('data-name') || '';
                document.getElementById('edit_code').value = this.getAttribute('data-code') || '';
                document.getElementById('current_image').value = this.getAttribute('data-image') || '';
                document.getElementById('edit_download_label').value = this.getAttribute('data-download_label') || '';
                const rawHowToSave = this.getAttribute('data-how_to_save') || '';
                document.getElementById('edit_how_to_save').value = rawHowToSave === 'TikTok Viewer' || rawHowToSave === '' ? 'YouTube Viewer' : rawHowToSave;
                document.getElementById('edit_tiktok_downloaders').value = this.getAttribute('data-tiktok_downloaders') || '';
                document.getElementById('edit_stories').value = this.getAttribute('data-stories') || '';
                document.getElementById('edit_terms_conditions').value = this.getAttribute('data-terms_conditions') || '';
                document.getElementById('edit_privacy_policy').value = this.getAttribute('data-privacy_policy') || '';
                document.getElementById('edit_contact').value = this.getAttribute('data-contact') || '';

                // Image preview
                let img = this.getAttribute('data-image');
                let preview = document.getElementById('edit_image_preview');
                if (img) {
                    preview.innerHTML = '<img src="' + img + '" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">';
                } else {
                    preview.innerHTML = '';
                }
            });
        });

        // Clear modal fields on close
        $('#editLanguageModal').on('hidden.bs.modal', function () {
            $('#edit_id').val('');
            $('#edit_name').val('');
            $('#edit_code').val('');
            $('#current_image').val('');
            $('#edit_download_label').val('');
            $('#edit_how_to_save').val('YouTube Viewer');
            $('#edit_tiktok_downloaders').val('');
            $('#edit_stories').val('');
            $('#edit_terms_conditions').val('');
            $('#edit_privacy_policy').val('');
            $('#edit_contact').val('');
            $('#edit_image_preview').html('');
        });
    </script>
</body>
</html>