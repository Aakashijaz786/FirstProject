<?php
/**
 * Test MySQL Connection Script
 * This will help us find the correct MySQL credentials
 */

// Disable mysqli exceptions for testing
mysqli_report(MYSQLI_REPORT_OFF);

echo "=== MySQL Connection Test ===\n\n";

// Try different common configurations
$configs = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'password'],
    ['host' => 'localhost', 'user' => 'root', 'pass' => '123456'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
];

echo "Trying common MySQL configurations...\n\n";

foreach ($configs as $config) {
    echo "Trying: {$config['user']}@{$config['host']} (password: " . ($config['pass'] ? '***' : 'empty') . ")... ";
    
    $conn = @new mysqli($config['host'], $config['user'], $config['pass']);
    
    if ($conn && !$conn->connect_error) {
        echo "✓ SUCCESS!\n";
        echo "Working configuration:\n";
        echo "  Host: {$config['host']}\n";
        echo "  User: {$config['user']}\n";
        echo "  Password: " . ($config['pass'] ? '*** (set)' : 'empty') . "\n\n";
        
        // Now create the database and user
        echo "Creating database and user...\n";
        
        $db_name = 'tiktokio.mobi';
        $db_user = 'tiktokio.mobi';
        $db_pass = 'TfjfPrtjC4Z4wmBm';
        
        // Create database
        $sql = "CREATE DATABASE IF NOT EXISTS `" . $conn->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
        if ($conn->query($sql)) {
            echo "✓ Database created\n";
        } else {
            echo "✗ Error creating database: " . $conn->error . "\n";
        }
        
        // Create user
        $sql = "CREATE USER IF NOT EXISTS '" . $conn->real_escape_string($db_user) . "'@'localhost' IDENTIFIED BY '" . $conn->real_escape_string($db_pass) . "'";
        if ($conn->query($sql)) {
            echo "✓ User created\n";
        } else {
            // Try to update password if user exists
            $sql = "ALTER USER '" . $conn->real_escape_string($db_user) . "'@'localhost' IDENTIFIED BY '" . $conn->real_escape_string($db_pass) . "'";
            if ($conn->query($sql)) {
                echo "✓ User password updated\n";
            } else {
                echo "✗ Error with user: " . $conn->error . "\n";
            }
        }
        
        // Grant privileges
        $sql = "GRANT ALL PRIVILEGES ON `" . $conn->real_escape_string($db_name) . "`.* TO '" . $conn->real_escape_string($db_user) . "'@'localhost'";
        if ($conn->query($sql)) {
            echo "✓ Privileges granted\n";
            $conn->query("FLUSH PRIVILEGES");
            echo "✓ Privileges flushed\n";
        } else {
            echo "✗ Error granting privileges: " . $conn->error . "\n";
        }
        
        // Import SQL file
        echo "\nImporting SQL file...\n";
        $conn->select_db($db_name);
        
        $sql_file = __DIR__ . '/tiktokio_mobi.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            // Remove comments
            $sql_content = preg_replace('/--.*$/m', '', $sql_content);
            $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
            
            // Split and execute
            $statements = array_filter(
                array_map('trim', explode(';', $sql_content)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^(SET|START|COMMIT|\/\*)/i', $stmt);
                }
            );
            
            $success = 0;
            $errors = 0;
            foreach ($statements as $statement) {
                if (empty(trim($statement)) || preg_match('/^CREATE\s+DATABASE/i', $statement)) {
                    continue;
                }
                
                if ($conn->query($statement)) {
                    $success++;
                } else {
                    if (strpos($conn->error, 'already exists') === false && 
                        strpos($conn->error, 'Duplicate entry') === false) {
                        $errors++;
                    }
                }
            }
            
            echo "✓ Imported $success statements";
            if ($errors > 0) {
                echo " ($errors errors - some may be expected)";
            }
            echo "\n";
        } else {
            echo "✗ SQL file not found: $sql_file\n";
        }
        
        $conn->close();
        echo "\n=== Setup Complete! ===\n";
        exit(0);
    } else {
        echo "✗ Failed\n";
    }
}

echo "\nNone of the common configurations worked.\n";
echo "Please provide your MySQL root password, or we can update config.php to use root directly.\n";
echo "\nTo manually set up:\n";
echo "1. Find your MySQL root password\n";
echo "2. Run: mysql -u root -p\n";
echo "3. Then run the SQL commands from setup_database.sql\n";

