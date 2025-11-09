<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$message = '';
$message_type = '';

// Handle manual sitemap generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_sitemap'])) {
    // The sitemap is already dynamic, but we can add a timestamp update
    $message = 'Sitemap is automatically updated when pages are added/modified.';
    $message_type = 'info';
}

// Get sitemap statistics
$stats = [
    'total_languages' => 0,
    'total_mp3_pages' => 0,
    'total_stories_pages' => 0,
    'total_how_pages' => 0,
    'total_custom_pages' => 0,
    'enabled_mp3' => 0,
    'enabled_stories' => 0,
    'enabled_how' => 0
];

// Count languages
$res = $conn->query("SELECT COUNT(*) as count FROM languages");
if ($res && $res->num_rows > 0) {
    $stats['total_languages'] = $res->fetch_assoc()['count'];
}

// Count enabled pages by type
$res = $conn->query("SELECT 
    SUM(CASE WHEN mp3_enabled = 1 THEN 1 ELSE 0 END) as enabled_mp3,
    SUM(CASE WHEN stories_enabled = 1 THEN 1 ELSE 0 END) as enabled_stories,
    SUM(CASE WHEN how_enabled = 1 THEN 1 ELSE 0 END) as enabled_how
    FROM languages");
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $stats['enabled_mp3'] = $row['enabled_mp3'];
    $stats['enabled_stories'] = $row['enabled_stories'];
    $stats['enabled_how'] = $row['enabled_how'];
}

// Count total pages
$res = $conn->query("SELECT COUNT(*) as count FROM mp3_page_slugs WHERE status = 'active'");
if ($res && $res->num_rows > 0) {
    $stats['total_mp3_pages'] = $res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COUNT(*) as count FROM stories_page_slugs WHERE status = 'active'");
if ($res && $res->num_rows > 0) {
    $stats['total_stories_pages'] = $res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COUNT(*) as count FROM how_page_slugs WHERE status = 'active'");
if ($res && $res->num_rows > 0) {
    $stats['total_how_pages'] = $res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COUNT(*) as count FROM custom_page_slugs WHERE status = 'active'");
if ($res && $res->num_rows > 0) {
    $stats['total_custom_pages'] = $res->fetch_assoc()['count'];
}

include 'includes/header.php';
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Dynamic Sitemap Generator</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Sitemap Information</h5>
                                <p>The sitemap is now <strong>dynamic</strong> and automatically updates based on your database content.</p>
                                <p><strong>Sitemap URL:</strong> <a href="/sitemap.xml" target="_blank">https://app.yt1s.ing/sitemap.xml</a></p>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['total_languages']; ?></h3>
                                        <p>Languages</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['enabled_mp3']; ?></h3>
                                        <p>MP3 Pages Enabled</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['enabled_stories']; ?></h3>
                                        <p>Stories Pages Enabled</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h3><?php echo $stats['enabled_how']; ?></h3>
                                        <p>How Pages Enabled</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5>Page Statistics</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Page Type</th>
                                                <th>Total Active Slugs</th>
                                                <th>Languages Enabled</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>MP3 Pages</strong></td>
                                                <td><?php echo $stats['total_mp3_pages']; ?></td>
                                                <td><?php echo $stats['enabled_mp3']; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Stories Pages</strong></td>
                                                <td><?php echo $stats['total_stories_pages']; ?></td>
                                                <td><?php echo $stats['enabled_stories']; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>How Pages</strong></td>
                                                <td><?php echo $stats['total_how_pages']; ?></td>
                                                <td><?php echo $stats['enabled_how']; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Custom Pages</strong></td>
                                                <td><?php echo $stats['total_custom_pages']; ?></td>
                                                <td>All</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <h5>How It Works</h5>
                                <div class="alert alert-info">
                                    <h6><strong>Automatic Updates:</strong></h6>
                                    <ul>
                                        <li>✅ <strong>Enabled pages</strong> are automatically included in sitemap</li>
                                        <li>❌ <strong>Disabled pages</strong> are automatically excluded from sitemap</li>
                                        <li>🔄 <strong>New pages</strong> are automatically added when created</li>
                                        <li>🗑️ <strong>Deleted pages</strong> are automatically removed from sitemap</li>
                                        <li>🌐 <strong>Multi-language</strong> support with proper language prefixes</li>
                                    </ul>
                                </div>

                                <h5>SEO Benefits</h5>
                                <div class="alert alert-success">
                                    <ul>
                                        <li>✅ Search engines get updated sitemap automatically</li>
                                        <li>✅ Only accessible pages are indexed</li>
                                        <li>✅ Proper priority and lastmod tags</li>
                                        <li>✅ XML format compliant with Google standards</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <a href="/sitemap.xml" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt"></i> View Sitemap
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
