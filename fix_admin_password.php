<?php
/**
 * Fix Admin Password Script
 * Updates the admin user password to Admin@2025!
 */

require_once __DIR__ . '/includes/config.php';

echo "=== Fixing Admin Password ===\n\n";

// Check if admin user exists
$stmt = $conn->prepare('SELECT id, username FROM admin_users WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$username = 'admin';
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Admin user not found. Creating admin user...\n";
    
    // Create admin user
    $new_password = 'Admin@2025!';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare('INSERT INTO admin_users (username, password) VALUES (?, ?)');
    $stmt->bind_param('ss', $username, $hashed_password);
    
    if ($stmt->execute()) {
        echo "✓ Admin user created successfully!\n";
        echo "Username: admin\n";
        echo "Password: Admin@2025!\n";
    } else {
        echo "ERROR: Failed to create admin user: " . $stmt->error . "\n";
        exit(1);
    }
    $stmt->close();
} else {
    echo "Admin user found. Updating password...\n";
    
    // Update admin password
    $new_password = 'Admin@2025!';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare('UPDATE admin_users SET password = ? WHERE username = ?');
    $stmt->bind_param('ss', $hashed_password, $username);
    
    if ($stmt->execute()) {
        echo "✓ Admin password updated successfully!\n";
        echo "Username: admin\n";
        echo "Password: Admin@2025!\n";
    } else {
        echo "ERROR: Failed to update password: " . $stmt->error . "\n";
        exit(1);
    }
    $stmt->close();
}

echo "\n=== Done! ===\n";
echo "You can now login at: http://localhost:8000/admin/login.php\n";
echo "Username: admin\n";
echo "Password: Admin@2025!\n";

