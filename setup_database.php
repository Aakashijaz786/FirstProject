<?php
/**
 * Database Setup Script
 * This script will create the database, user, and import the SQL file
 */

echo "=== Database Setup Script ===\n\n";

// Configuration
$root_user = 'root';
$root_pass = ''; // You'll be prompted or set this
$db_name = 'tiktokio.mobi';
$db_user = 'tiktokio.mobi';
$db_pass = 'TfjfPrtjC4Z4wmBm';
$sql_file = __DIR__ . '/tiktokio_mobi.sql';

// Try to get root password from command line or environment
if (isset($argv[1])) {
    $root_pass = $argv[1];
} elseif (isset($_ENV['MYSQL_ROOT_PASSWORD'])) {
    $root_pass = $_ENV['MYSQL_ROOT_PASSWORD'];
}

// If no password provided, try empty (common for local dev)
if ($root_pass === '') {
    echo "Attempting to connect with empty root password...\n";
}

// Connect as root
$conn = @new mysqli('localhost', $root_user, $root_pass);

if ($conn->connect_error) {
    echo "ERROR: Could not connect to MySQL as root.\n";
    echo "Error: " . $conn->connect_error . "\n\n";
    echo "Please provide MySQL root password:\n";
    echo "Usage: php setup_database.php [root_password]\n";
    echo "Or set MYSQL_ROOT_PASSWORD environment variable\n";
    exit(1);
}

echo "✓ Connected to MySQL as root\n";

// Create database
echo "Creating database '$db_name'...\n";
$sql = "CREATE DATABASE IF NOT EXISTS `" . $conn->real_escape_string($db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if ($conn->query($sql)) {
    echo "✓ Database created or already exists\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}

// Create user
echo "Creating user '$db_user'...\n";
$sql = "CREATE USER IF NOT EXISTS '" . $conn->real_escape_string($db_user) . "'@'localhost' IDENTIFIED BY '" . $conn->real_escape_string($db_pass) . "'";
if ($conn->query($sql)) {
    echo "✓ User created or already exists\n";
} else {
    // User might already exist, try to update password
    echo "User might already exist, updating password...\n";
    $sql = "ALTER USER '" . $conn->real_escape_string($db_user) . "'@'localhost' IDENTIFIED BY '" . $conn->real_escape_string($db_pass) . "'";
    if ($conn->query($sql)) {
        echo "✓ User password updated\n";
    } else {
        echo "WARNING: " . $conn->error . "\n";
    }
}

// Grant privileges
echo "Granting privileges...\n";
$sql = "GRANT ALL PRIVILEGES ON `" . $conn->real_escape_string($db_name) . "`.* TO '" . $conn->real_escape_string($db_user) . "'@'localhost'";
if ($conn->query($sql)) {
    echo "✓ Privileges granted\n";
} else {
    echo "ERROR: " . $conn->error . "\n";
    exit(1);
}

// Flush privileges
$conn->query("FLUSH PRIVILEGES");
echo "✓ Privileges flushed\n";

// Select database
$conn->select_db($db_name);

// Check if tables already exist
$result = $conn->query("SHOW TABLES");
$table_count = $result ? $result->num_rows : 0;

if ($table_count > 0) {
    echo "\n⚠ Database already has $table_count table(s).\n";
    echo "Do you want to import the SQL file anyway? (This might add duplicate data)\n";
    echo "Press Enter to skip, or type 'yes' to continue: ";
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    if (strtolower($line) !== 'yes') {
        echo "Skipping SQL import.\n";
        $conn->close();
        echo "\n✓ Database setup complete!\n";
        exit(0);
    }
}

// Import SQL file
if (!file_exists($sql_file)) {
    echo "ERROR: SQL file not found: $sql_file\n";
    exit(1);
}

echo "\nImporting SQL file...\n";
echo "This may take a few moments...\n";

// Read and execute SQL file
$sql_content = file_get_contents($sql_file);

// Remove comments and split by semicolons
$sql_content = preg_replace('/--.*$/m', '', $sql_content);
$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql_content)),
    function($stmt) {
        return !empty($stmt) && !preg_match('/^(SET|START|COMMIT|\/\*)/i', $stmt);
    }
);

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty(trim($statement))) {
        continue;
    }
    
    // Skip CREATE DATABASE statements
    if (preg_match('/^CREATE\s+DATABASE/i', $statement)) {
        continue;
    }
    
    if ($conn->query($statement)) {
        $success_count++;
    } else {
        // Some errors are expected (like table already exists)
        if (strpos($conn->error, 'already exists') === false && 
            strpos($conn->error, 'Duplicate entry') === false) {
            $error_count++;
            if ($error_count <= 5) { // Show first 5 errors
                echo "  Warning: " . substr($conn->error, 0, 100) . "\n";
            }
        }
    }
}

echo "✓ Imported $success_count statements";
if ($error_count > 0) {
    echo " ($error_count errors - some may be expected)";
}
echo "\n";

$conn->close();

echo "\n=== Setup Complete! ===\n";
echo "Database: $db_name\n";
echo "User: $db_user\n";
echo "Password: $db_pass\n";
echo "\nYou can now access the admin portal at: http://localhost:8000/admin/login.php\n";
echo "Username: admin\n";
echo "Password: Admin@2025!\n";

