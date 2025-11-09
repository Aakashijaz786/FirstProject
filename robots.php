<?php
// Set content type to plain text
header('Content-Type: text/plain');

try {
    require_once 'includes/config.php';
    
    // Fetch robots.txt content from database
    $robots_content = '';
    $sql = "SELECT content FROM robots_txt WHERE id=1 LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $robots_content = $row['content'];
    }
} catch (Exception $e) {
    // If database connection fails, use default content
    $robots_content = '';
}

// If no content found, provide default robots.txt
if (empty($robots_content)) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    $robots_content = "User-agent: *
Allow: /

# Disallow admin area
Disallow: /admin/

# Sitemap
Sitemap: {$protocol}://{$host}/sitemap.xml";
}

// Output the robots.txt content
echo $robots_content;
?>
