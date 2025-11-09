<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}
require_once 'includes/header.php';
require_once '../includes/config.php';

// Handle form submission
$success = '';
$error = '';
$provider = '';
$api_url = '';

// Fetch all providers for the form
$providers = [];
$res = $conn->query("SELECT * FROM api_settings");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $providers[] = $row;
    }
}

// Set current values for the form (first provider as default if none selected)
if (count($providers) > 0) {
    $provider = $providers[0]['provider'];
    $api_url = $providers[0]['api_url'];
    foreach ($providers as $row) {
        if ($row['is_default']) {
            $provider = $row['provider'];
            $api_url = $row['api_url'];
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['provider'] ?? '';
    $api_url = trim($_POST['api_url'] ?? '');
    if ($provider && $api_url) {
        // Set all to not default first
        $conn->query("UPDATE api_settings SET is_default=0");
        // Upsert and set selected as default
        $sql = "INSERT INTO api_settings (provider, api_url, is_default) VALUES ('" . $conn->real_escape_string($provider) . "', '" . $conn->real_escape_string($api_url) . "', 1) ON DUPLICATE KEY UPDATE provider=VALUES(provider), api_url=VALUES(api_url), is_default=1";
        if ($conn->query($sql)) {
            $success = 'API settings saved.';
        } else {
            $error = 'Failed to save settings: ' . $conn->error;
        }
    } else {
        $error = 'Please select a provider and enter the API URL.';
    }
}

// Fetch all provider URLs from the database and pass them to JavaScript
$api_urls = [];
$res = $conn->query("SELECT * FROM api_settings");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $api_urls[$row['provider']] = $row['api_url'];
    }
}
?>
<div class="main-content" id="mainContent">
    <div class="page-section">
        <h4>API Settings</h4>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">API Providers</label>
                <?php foreach ($providers as $row): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="provider" id="provider_<?php echo htmlspecialchars($row['provider']); ?>" value="<?php echo htmlspecialchars($row['provider']); ?>" <?php if ($row['is_default']) echo 'checked'; ?> required>
                        <label class="form-check-label" for="provider_<?php echo htmlspecialchars($row['provider']); ?>">
                            <?php echo htmlspecialchars($row['provider']); ?>
                        </label>
                        <input type="hidden" name="api_url_<?php echo htmlspecialchars($row['provider']); ?>" value="<?php echo htmlspecialchars($row['api_url']); ?>">
                        <?php if ($row['is_default']): ?>
                            <span class="badge bg-success">Default</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">API URL</label>
                <input type="text" class="form-control" name="api_url" id="api_url_input" value="<?php echo htmlspecialchars($api_url); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var providerRadios = document.querySelectorAll('input[type="radio"][name="provider"]');
    var apiUrlInput = document.getElementById('api_url_input');
    providerRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            var selectedProvider = this.value;
            var hiddenInput = document.querySelector('input[name="api_url_' + selectedProvider + '"]');
            if (hiddenInput) {
                apiUrlInput.value = hiddenInput.value;
            }
        });
    });
    // On page load, set the correct value
    var checkedRadio = document.querySelector('input[type="radio"][name="provider"]:checked');
    if (checkedRadio) {
        var selectedProvider = checkedRadio.value;
        var hiddenInput = document.querySelector('input[name="api_url_' + selectedProvider + '"]');
        if (hiddenInput) {
            apiUrlInput.value = hiddenInput.value;
        }
    }
});
</script>
<?php require_once 'includes/footer.php'; ?> 