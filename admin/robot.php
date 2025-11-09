<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once '../includes/config.php';

// Ensure robots_txt table exists
$conn->query("CREATE TABLE IF NOT EXISTS robots_txt (
    id INT PRIMARY KEY DEFAULT 1,
    content TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$success = '';
$error = '';

// Fetch current robots.txt
$current_robots = '';
$sql = "SELECT content FROM robots_txt WHERE id=1 LIMIT 1";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_robots = $row['content'];
}

// If no content exists, provide default robots.txt
if (empty($current_robots)) {
    $current_robots = "User-agent: *
Allow: /

# Disallow admin area
Disallow: /admin/

# Sitemap
Sitemap: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/sitemap.xml";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $robots_content = trim($_POST['robots_content'] ?? '');
    if ($robots_content !== 'null') {
        $escaped = $conn->real_escape_string($robots_content);
        $sql = "SELECT id FROM robots_txt WHERE id=1 LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            $sql = "UPDATE robots_txt SET content='$escaped' WHERE id=1";
            $conn->query($sql);
        } else {
            $sql = "INSERT INTO robots_txt (id, content) VALUES (1, '$escaped')";
            $conn->query($sql);
        }
        $success = 'robots.txt saved successfully.';
        $current_robots = $robots_content;
    } else {
        $error = 'robots.txt content cannot be empty.';
    }
}
?>
  <?php
include 'includes/header.php';
?>
    <div class="main-content" id="mainContent">
        <div class="robots-section">
            <h4>robots.txt</h4>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <label class="form-label mb-2">robots.txt Content</label>
                <textarea class="form-control" name="robots_content" spellcheck="false"><?php echo htmlspecialchars($current_robots); ?></textarea>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
    </div>
  <?php
include 'includes/footer.php';
?>