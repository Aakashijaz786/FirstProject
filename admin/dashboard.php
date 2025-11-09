<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

// Get real statistics from database
$total_downloads = 0;
$today_downloads = 0;
$total_contacts = 0;
$today_contacts = 0;

// Get total contacts
$sql = "SELECT COUNT(*) as total FROM contact_messages";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_contacts = $row['total'];
}

// Get today's contacts
$sql = "SELECT COUNT(*) as today FROM contact_messages WHERE DATE(created_at) = CURDATE()";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $today_contacts = $row['today'];
}

// Get total downloads (you can modify this based on your download tracking table)
$sql = "SELECT COUNT(*) as total FROM downloads";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_downloads = $row['total'];
}

// Get today's downloads
$sql = "SELECT COUNT(*) as today FROM downloads WHERE DATE(download_time) = CURDATE()";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $today_downloads = $row['today'];
}

// Get total countries
$total_countries = 0;
$sql = "SELECT COUNT(DISTINCT country) as countries FROM downloads WHERE country != 'Unknown' AND country != 'Local/Private IP'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_countries = $row['countries'];
}

// Get recent downloads count (last 100)
$recent_downloads_count = 0;
$sql = "SELECT COUNT(*) as recent FROM downloads ORDER BY download_time DESC LIMIT 100";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $recent_downloads_count = $row['recent'];
}
?>
<?php
include 'includes/header.php';
?>

    <div class="main-content" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <span class="card-icon"><i class="fas fa-download"></i></span>
                            <div>
                                <div class="card-title">Total Downloads</div>
                                <div class="card-value"><?php echo number_format($total_downloads); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!--<div class="col-md-3">-->
                <!--    <div class="card">-->
                <!--        <div class="card-body">-->
                <!--            <span class="card-icon"><i class="fas fa-download"></i></span>-->
                <!--            <div>-->
                <!--                <div class="card-title">Today's Downloads</div>-->
                <!--                <div class="card-value"><?php echo number_format($today_downloads); ?></div>-->
                <!--            </div>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--</div>-->
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <span class="card-icon"><i class="fas fa-globe"></i></span>
                            <div>
                                <div class="card-title">Countries</div>
                                <div class="card-value"><?php echo number_format($total_countries); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <span class="card-icon"><i class="fas fa-envelope"></i></span>
                            <div>
                                <div class="card-title">Total Contacts</div>
                                <div class="card-value"><?php echo number_format($total_contacts); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <span class="card-icon"><i class="fas fa-envelope"></i></span>
                            <div>
                                <div class="card-title">Today's Contacts</div>
                                <div class="card-value"><?php echo number_format($today_contacts); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!--<div class="col-md-6">-->
                <!--    <div class="card">-->
                <!--        <div class="card-body">-->
                <!--            <span class="card-icon"><i class="fas fa-chart-line"></i></span>-->
                <!--            <div>-->
                <!--                <div class="card-title">Recent Downloads</div>-->
                <!--                <div class="card-value"><?php echo number_format($recent_downloads_count); ?></div>-->
                <!--            </div>-->
                <!--        </div>-->
                <!--    </div>-->
                <!--</div>-->
            </div>
            <!-- More dashboard content can go here -->
        </div>
    </div>
  <?php
include 'includes/footer.php';
?>