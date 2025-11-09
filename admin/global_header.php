<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

$success = '';
$error = '';

// Fetch current header
$current_header = '';
$sql = "SELECT content FROM global_header WHERE id=1 LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_header = $row['content'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $header_content = trim($_POST['header_content'] ?? '');
    if ($header_content !== 'null') {
        $escaped = $conn->real_escape_string($header_content);
        $sql = "SELECT id FROM global_header WHERE id=1 LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $sql = "UPDATE global_header SET content='$escaped' WHERE id=1";
            $conn->query($sql);
        } else {
            $sql = "INSERT INTO global_header (id, content) VALUES (1, '$escaped')";
            $conn->query($sql);
        }
        $success = 'Header saved successfully.';
        $current_header = $header_content;
    } else {
        $error = 'Header content cannot be empty.';
    }
}
?>
<?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="header-section">
            <h4>Header - Global</h4>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <label class="form-label mb-2">Header Content (HTML/JS)</label>
                <textarea class="form-control" name="header_content" spellcheck="false"><?php echo htmlspecialchars($current_header); ?></textarea>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
<?php
include 'includes/footer.php';
?>