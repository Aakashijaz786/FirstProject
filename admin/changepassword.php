<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';

$message = '';
$message_type = '';

// Get current admin user
$current_admin = null;
$admin_query = $conn->query("SELECT * FROM admin_users LIMIT 1");
if ($admin_query && $admin_query->num_rows > 0) {
    $current_admin = $admin_query->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_old_password = $_POST['admin_old_password'] ?? '';
    $admin_new_password = $_POST['admin_new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate required fields
    if (empty($admin_old_password) || empty($admin_new_password) || empty($confirm_password)) {
        $message = 'All password fields are required.';
        $message_type = 'danger';
    } elseif ($admin_new_password !== $confirm_password) {
        $message = 'New password and confirm password do not match.';
        $message_type = 'danger';
    } elseif (strlen($admin_new_password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $message_type = 'danger';
    } else {
        // Check if admin user exists
        if ($current_admin) {
            // Verify old password
            if (password_verify($admin_old_password, $current_admin['password'])) {
                // Update password
                $sql = "UPDATE admin_users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $hashed_password = password_hash($admin_new_password, PASSWORD_DEFAULT);
                $stmt->bind_param('si', $hashed_password, $current_admin['id']);
                
                if ($stmt->execute()) {
                    $message = 'Password updated successfully!';
                    $message_type = 'success';
                    // Refresh current admin data
                    $admin_query = $conn->query("SELECT * FROM admin_users LIMIT 1");
                    if ($admin_query && $admin_query->num_rows > 0) {
                        $current_admin = $admin_query->fetch_assoc();
                    }
                } else {
                    $message = 'Error updating password: ' . $conn->error;
                    $message_type = 'danger';
                }
                $stmt->close();
            } else {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            }
        } else {
            $message = 'No admin user found. Please contact the system administrator.';
            $message_type = 'danger';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Change Admin Password</h4>
                    </div>
                    <div class="card-body">
                     

                        <form method="POST" action="" id="passwordForm">
                            <div class="row">
                                <div class="col-12">
                                    <h5 class="mb-3">Password Change</h5>
                                    <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php endif; ?>

                            <?php if ($current_admin): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-user"></i>
                                    <strong>Current Admin:</strong> <?php echo htmlspecialchars($current_admin['username']); ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Warning:</strong> No admin user found in the system.
                                </div>
                            <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="admin_old_password" class="form-label">Current Password *</label>
                                        <input type="password" class="form-control" id="admin_old_password" name="admin_old_password" required>
                                        <div class="form-text">Enter your current password to verify your identity</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="admin_new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="admin_new_password" name="admin_new_password" required>
                                        <div class="form-text">Enter your new password (minimum 6 characters)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <div class="form-text">Re-enter your new password to confirm</div>
                                    </div>
                                </div>
                                
                             
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key"></i> Change Password
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('passwordForm');
    const newPassword = document.getElementById('admin_new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const oldPassword = document.getElementById('admin_old_password');
    
    // Real-time password confirmation check
    function checkPasswordMatch() {
        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);
    
    // Password strength indicator
    function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        return strength;
    }
    
    newPassword.addEventListener('input', function() {
        const strength = checkPasswordStrength(this.value);
        const strengthBar = document.getElementById('passwordStrength');
        if (!strengthBar) {
            const bar = document.createElement('div');
            bar.id = 'passwordStrength';
            bar.className = 'progress mt-2';
            bar.innerHTML = '<div class="progress-bar" role="progressbar" style="width: 0%"></div>';
            this.parentNode.appendChild(bar);
        }
        
        const progressBar = document.getElementById('passwordStrength').querySelector('.progress-bar');
        const percentage = (strength / 5) * 100;
        progressBar.style.width = percentage + '%';
        
        if (strength < 2) {
            progressBar.className = 'progress-bar bg-danger';
        } else if (strength < 4) {
            progressBar.className = 'progress-bar bg-warning';
        } else {
            progressBar.className = 'progress-bar bg-success';
        }
    });
    
    form.addEventListener('submit', function(e) {
        // Additional validation
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            alert('New password and confirm password do not match!');
            return;
        }
        
        if (newPassword.value.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            return;
        }
        
        if (newPassword.value === oldPassword.value) {
            e.preventDefault();
            alert('New password must be different from current password!');
            return;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?> 