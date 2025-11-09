<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

$success = '';
$error = '';

// Fetch current footer
$current_footer = '';
$sql = "SELECT content FROM global_footer WHERE id=1 LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_footer = $row['content'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $footer_content = trim($_POST['footer_content'] ?? '');
    if ($footer_content !== 'null') {
        $escaped = $conn->real_escape_string($footer_content);
        $sql = "SELECT id FROM global_footer WHERE id=1 LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $sql = "UPDATE global_footer SET content='$escaped' WHERE id=1";
            $conn->query($sql);
        } else {
            $sql = "INSERT INTO global_footer (id, content) VALUES (1, '$escaped')";
            $conn->query($sql);
        }
        $success = 'Footer saved successfully.';
        $current_footer = $footer_content;
    } else {
        $error = 'Footer content cannot be empty.';
    }
}
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="footer-section">
            <h4>Footer - Global</h4>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <label class="form-label mb-2">Footer Content (HTML/JS)</label>
                <textarea class="form-control" name="footer_content" spellcheck="false"><?php echo htmlspecialchars($current_footer); ?></textarea>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
<?php
include 'includes/footer.php';
?>