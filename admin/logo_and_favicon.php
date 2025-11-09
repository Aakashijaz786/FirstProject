<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

$success = '';
$error = '';
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$fields = [
    'logo_light' => 'Logo Light',
    'logo_dark' => 'Logo Dark',
    'favicon_16' => 'Favicon 16x16',
    'favicon_32' => 'Favicon 32x32',
    'favicon_192' => 'Favicon 192x192',
    'favicon_512' => 'Favicon 512x512',
];

// Fetch current values from DB
$current = [];
$sql = "SELECT * FROM logo_and_favicon WHERE id=1 LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $current = $result->fetch_assoc();
}

$uploaded_files = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $field => $label) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
            $filename = $field . '_' . time() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
                $uploaded_files[$field] = 'uploads/' . $filename;
            } else {
                $error = 'Failed to upload ' . $label;
            }
        } else if (!empty($current[$field])) {
            // No new upload, keep existing
            $uploaded_files[$field] = $current[$field];
        } else {
            $uploaded_files[$field] = null;
        }
    }
    if (!$error) {
        // Prepare SQL for insert/update
        $sql = "SELECT id FROM logo_and_favicon WHERE id=1 LIMIT 1";
        $result = $conn->query($sql);
        $columns = [];
        foreach ($fields as $field => $label) {
            $columns[$field] = isset($uploaded_files[$field]) ? $conn->real_escape_string($uploaded_files[$field]) : null;
        }
        if ($result && $result->num_rows > 0) {
            // Update
            $set = [];
            foreach ($fields as $field => $label) {
                $set[] = "$field=" . ($columns[$field] ? "'{$columns[$field]}'" : "NULL");
            }
            $sql = "UPDATE logo_and_favicon SET " . implode(", ", $set) . ", updated_at=NOW() WHERE id=1";
            $conn->query($sql);
        } else {
            // Insert
            $cols = [];
            $vals = [];
            foreach ($fields as $field => $label) {
                $cols[] = $field;
                $vals[] = $columns[$field] ? "'{$columns[$field]}'" : "NULL";
            }
            $sql = "INSERT INTO logo_and_favicon (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
            $conn->query($sql);
        }
        $success = 'Files uploaded and saved to database.';
        // Refresh current values
        $sql = "SELECT * FROM logo_and_favicon WHERE id=1 LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $current = $result->fetch_assoc();
        }
    }
}
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="logo-favicon-section">
            <h4>Logo & Favicon</h4>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0" style="display:none;">
                        <label class="form-label">Logo Light (SVG) 160 x 40 px</label>
                        <input type="file" class="form-control" name="logo_light" accept=".svg">
                        <?php if (!empty($current['logo_light'])): ?>
                            <div class="file-name">Current: <?php echo htmlspecialchars(basename($current['logo_light'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo Light (SVG) 160 x 40 px</label>
                        <input type="file" class="form-control" name="logo_dark" accept=".svg">
                        <?php if (!empty($current['logo_dark'])): ?>
                            <div class="file-name">Current: <?php echo htmlspecialchars(basename($current['logo_dark'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label">Favicon 16x16 (PNG)</label>
                        <input type="file" class="form-control" name="favicon_16" accept=".png">
                        <?php if (!empty($current['favicon_16'])): ?>
                            <div class="file-name">Current: <?php echo htmlspecialchars(basename($current['favicon_16'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Favicon 32x32 (PNG)</label>
                        <input type="file" class="form-control" name="favicon_32" accept=".png">
                        <?php if (!empty($current['favicon_32'])): ?>
                            <div class="file-name">Current: <?php echo htmlspecialchars(basename($current['favicon_32'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label class="form-label">Favicon 192x192 (PNG)</label>
                        <input type="file" class="form-control" name="favicon_192" accept=".png">
                        <?php if (!empty($current['favicon_192'])): ?>
                            <div class="file-name">Current: <?php echo htmlspecialchars(basename($current['favicon_192'])); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Favicon 512x512 (PNG)</label>
                        <input type="file" class="form-control" name="favicon_512" accept=".png">
                        <?php if (!empty($current['favicon_512'])): ?>
                            <div class="file-name">Current: <?php echo htmlspecialchars(basename($current['favicon_512'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
  <?php
include 'includes/footer.php';
?>