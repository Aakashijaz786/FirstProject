<?php

session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {

    header('Location: login.php');

    exit;

}



require_once '../includes/config.php';



$message = '';

$message_type = '';



// Handle form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $site_name = trim($_POST['site_name'] ?? '');

    $site_url = trim($_POST['site_url'] ?? '');

    $site_email = trim($_POST['site_email'] ?? '');

    $site_phone = trim($_POST['site_phone'] ?? '');

    $site_status = isset($_POST['site_status']) ? 1 : 0; // Enable/Disable status

    $download_app_enabled = isset($_POST['download_app_enabled']) ? 1 : 0; // Download App section enable/disable
    $mp3_page_enabled = isset($_POST['mp3_page_enabled']) ? 1 : 0; // MP3 page visibility

    

    // Validate required fields

    if (empty($site_name) || empty($site_url) || empty($site_email)) {

        $message = 'Site name, URL, and email are required fields.';

        $message_type = 'danger';

    } else {

        // Check if settings table exists, if not create it

        $check_table = $conn->query("SHOW TABLES LIKE 'site_settings'");

        if ($check_table->num_rows == 0) {

            $create_table = "CREATE TABLE site_settings (

                id INT AUTO_INCREMENT PRIMARY KEY,

                site_name VARCHAR(255) NOT NULL,

                site_url VARCHAR(255) NOT NULL,

                site_email VARCHAR(255) NOT NULL,

                site_phone VARCHAR(50),

                site_status TINYINT(1) DEFAULT 1,

                download_app_enabled TINYINT(1) DEFAULT 1,

                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

            )";

            $conn->query($create_table);

        } else {

            // Check if site_status column exists, if not add it

            $check_column = $conn->query("SHOW COLUMNS FROM site_settings LIKE 'site_status'");

            if ($check_column->num_rows == 0) {

                $conn->query("ALTER TABLE site_settings ADD COLUMN site_status TINYINT(1) DEFAULT 1");

            }

            

            // Check if download_app_enabled column exists, if not add it

            $check_download_app = $conn->query("SHOW COLUMNS FROM site_settings LIKE 'download_app_enabled'");

            if ($check_download_app->num_rows == 0) {

                $conn->query("ALTER TABLE site_settings ADD COLUMN download_app_enabled TINYINT(1) DEFAULT 1");

            }

            // Check if mp3_page_enabled column exists, if not add it
            $check_mp3_page = $conn->query("SHOW COLUMNS FROM site_settings LIKE 'mp3_page_enabled'");
            if ($check_mp3_page->num_rows == 0) {
                $conn->query("ALTER TABLE site_settings ADD COLUMN mp3_page_enabled TINYINT(1) DEFAULT 1");
            }

        }

        

        // Check if settings already exist

        $check_settings = $conn->query("SELECT * FROM site_settings LIMIT 1");

        

        if ($check_settings->num_rows > 0) {

            // Update existing settings

            $settings = $check_settings->fetch_assoc();

            

            $sql = "UPDATE site_settings SET 

                    site_name = ?, 

                    site_url = ?, 

                    site_email = ?, 

                    site_phone = ?,

                    site_status = ?,

                    download_app_enabled = ?,

                    mp3_page_enabled = ?

                    WHERE id = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param('ssssiiii', $site_name, $site_url, $site_email, $site_phone, $site_status, $download_app_enabled, $mp3_page_enabled, $settings['id']);

            

            if ($stmt->execute()) {

                $message = 'Site settings updated successfully!';

                $message_type = 'success';

            } else {

                $message = 'Error updating site settings: ' . $conn->error;

                $message_type = 'danger';

            }

            $stmt->close();

        } else {

            // Insert new settings

            $sql = "INSERT INTO site_settings (site_name, site_url, site_email, site_phone, site_status, download_app_enabled, mp3_page_enabled) 

                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param('ssssiii', $site_name, $site_url, $site_email, $site_phone, $site_status, $download_app_enabled, $mp3_page_enabled);

            

            if ($stmt->execute()) {

                $message = 'Site settings saved successfully!';

                $message_type = 'success';

            } else {

                $message = 'Error saving site settings: ' . $conn->error;

                $message_type = 'danger';

            }

            $stmt->close();

        }

    }

}



// Fetch current settings

$current_settings = null;

$check_settings = $conn->query("SELECT * FROM site_settings LIMIT 1");

if ($check_settings && $check_settings->num_rows > 0) {

    $current_settings = $check_settings->fetch_assoc();

}

?>



<?php include 'includes/header.php'; ?>



<div class="main-content" id="mainContent">

    <div class="container-fluid">

        <div class="row">

            <div class="col-12">

                <div class="card">

                    <div class="card-header">

                        <h4 class="card-title">General Settings</h4>

                    </div>

                    <div class="card-body">

                       



                        <form method="POST" action="">

                            <div class="row">

                            <?php if ($message): ?>

                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">

                                <?php echo $message; ?>

                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                            </div>

                        <?php endif; ?>

                                    <h5 class="mb-3">Site Information</h5>

                                    

                                    <div class="mb-3">

                                        <label for="site_name" class="form-label">Site Name *</label>

                                        <input type="text" class="form-control" id="site_name" name="site_name" 

                                               value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>" required>

                                    </div>
                                    <div class="mb-3">

                                        <label for="site_email" class="form-label">Tag Line *</label>

                                        <input type="text" class="form-control" id="site_email" name="site_email" 

                                               value="<?php echo htmlspecialchars($current_settings['site_email'] ?? ''); ?>" required>

                                    </div>
                                    

                                    <div class="mb-3">

                                        <label for="site_url" class="form-label">Site URL *</label>

                                        <input type="url" class="form-control" id="site_url" name="site_url" 

                                               value="<?php echo htmlspecialchars($current_settings['site_url'] ?? ''); ?>" required>

                                    </div>

                                        <div class="mb-3">

                                        <label for="site_phone" class="form-label">Site Email</label>

                                        <input type="email" class="form-control" id="site_phone" name="site_phone" 

                                               value="<?php echo htmlspecialchars($current_settings['site_phone'] ?? ''); ?>">

                                    </div>


                                    <div class="mb-3">

                                        <label class="form-label">Server Time</label>

                                        <div class="form-control-plaintext bg-light p-2 rounded">

                                            <strong id="server-time">

                                                <?php 

                                                date_default_timezone_set('Asia/Karachi');

                                                echo date('d M Y H:i:s A');

                                                ?>

                                            </strong>

                                        </div>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <div class="form-check form-switch">

                                            <input class="form-check-input" type="checkbox" id="site_status" name="site_status" 

                                                   <?php echo (isset($current_settings['site_status']) && $current_settings['site_status'] == 1) ? 'checked' : ''; ?>>

                                            <label class="form-check-label" for="site_status">

                                                <strong>Site Status</strong>

                                                <small class="text-muted d-block">

                                                    Enable or disable the website functionality

                                                </small>

                                            </label>

                                        </div>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <div class="form-check form-switch">

                                            <input class="form-check-input" type="checkbox" id="mp3_page_enabled" name="mp3_page_enabled" 

                                                   <?php echo (isset($current_settings['mp3_page_enabled']) && $current_settings['mp3_page_enabled'] == 1) ? 'checked' : ''; ?>>

                                            <label class="form-check-label" for="mp3_page_enabled">

                                                <strong>MP3 Page Visibility</strong>

                                                <small class="text-muted d-block">

                                                    Show or hide MP3 page button in admin panel

                                                </small>

                                            </label>

                                        </div>

                                    </div>

                                    

                                    <div class="mb-3">

                                        <div class="form-check form-switch">

                                            <input class="form-check-input" type="checkbox" id="download_app_enabled" name="download_app_enabled" 

                                                   <?php echo (isset($current_settings['download_app_enabled']) && $current_settings['download_app_enabled'] == 1) ? 'checked' : ''; ?>>

                                            <label class="form-check-label" for="download_app_enabled">

                                                <strong>Download the App Section</strong>

                                                <small class="text-muted d-block">

                                                    Enable or disable the "Download the App" section on the website

                                                </small>

                                            </label>

                                        </div>

                                    </div>


                            </div>

                            

                            <div class="row mt-4">

                                <div class="col-12">

                                    <button type="submit" class="btn btn-primary">

                                        <i class="fas fa-save"></i> Save Settings

                                    </button>

                                    <a href="dashboard.php" class="btn btn-secondary">

                                        <i class="fas fa-arrow-left"></i> Back to Dashboard

                                    </a>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>



<?php include 'includes/footer.php'; ?>



<script>

// Update server time every second

function updateServerTime() {

    const now = new Date();

    const options = {

        timeZone: 'Asia/Karachi',

        year: 'numeric',

        month: 'short',

        day: 'numeric',

        hour: '2-digit',

        minute: '2-digit',

        second: '2-digit',

        hour12: true

    };

    

    const formattedTime = now.toLocaleString('en-US', options);

    const serverTimeElement = document.getElementById('server-time');

    if (serverTimeElement) {

        serverTimeElement.textContent = formattedTime;

    }

}



// Update time immediately and then every second

updateServerTime();

setInterval(updateServerTime, 1000);



// Add visual feedback for site status toggle

document.addEventListener('DOMContentLoaded', function() {

    const statusToggle = document.getElementById('site_status');

    const statusLabel = statusToggle.nextElementSibling.querySelector('strong');

    

    function updateStatusDisplay() {

        if (statusToggle.checked) {

            statusLabel.innerHTML = '<strong class="text-success">Site Status: ENABLED</strong>';

            statusLabel.parentElement.classList.add('text-success');

            statusLabel.parentElement.classList.remove('text-danger');

        } else {

            statusLabel.innerHTML = '<strong class="text-danger">Site Status: DISABLED</strong>';

            statusLabel.parentElement.classList.add('text-danger');

            statusLabel.parentElement.classList.remove('text-success');

        }

    }

    

    statusToggle.addEventListener('change', updateStatusDisplay);

    updateStatusDisplay(); // Initial call

    

    // Add visual feedback for download app section toggle

    const downloadAppToggle = document.getElementById('download_app_enabled');

    const downloadAppLabel = downloadAppToggle.nextElementSibling.querySelector('strong');

    

    function updateDownloadAppDisplay() {

        if (downloadAppToggle.checked) {

            downloadAppLabel.innerHTML = '<strong class="text-success">Download the App Section: ENABLED</strong>';

            downloadAppLabel.parentElement.classList.add('text-success');

            downloadAppLabel.parentElement.classList.remove('text-danger');

        } else {

            downloadAppLabel.innerHTML = '<strong class="text-danger">Download the App Section: DISABLED</strong>';

            downloadAppLabel.parentElement.classList.add('text-danger');

            downloadAppLabel.parentElement.classList.remove('text-success');

        }

    }

    

    downloadAppToggle.addEventListener('change', updateDownloadAppDisplay);

    updateDownloadAppDisplay(); // Initial call

});

</script> 