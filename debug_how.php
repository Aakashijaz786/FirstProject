<?php
require_once 'includes/config.php';

echo "<h2>Debug: Checking language_pages table for 'how' content</h2>";

// Check all languages
$res = $conn->query("SELECT * FROM languages ORDER BY id");
if ($res && $res->num_rows > 0) {
    echo "<h3>Available Languages:</h3>";
    while ($lang = $res->fetch_assoc()) {
        echo "ID: {$lang['id']}, Code: {$lang['code']}, Name: {$lang['name']}<br>";
        
        // Check for how pages in this language
        $lang_id = $lang['id'];
        $page_res = $conn->query("SELECT * FROM language_pages WHERE language_id=$lang_id AND (slug LIKE '%how%' OR page_name LIKE '%how%')");
        if ($page_res && $page_res->num_rows > 0) {
            echo "  ✅ Found how pages:<br>";
            while ($page = $page_res->fetch_assoc()) {
                echo "    - ID: {$page['id']}, Slug: {$page['slug']}, Page Name: {$page['page_name']}, Header: {$page['header']}<br>";
            }
        } else {
            echo "  ❌ No how pages found for this language<br>";
        }
        echo "<br>";
    }
} else {
    echo "No languages found in database<br>";
}

// Check language_how_titles table
echo "<h3>Checking language_how_titles table:</h3>";
$res = $conn->query("SELECT * FROM language_how_titles ORDER BY language_id, id");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "Language ID: {$row['language_id']}, Title: {$row['title']}, Description: {$row['description']}<br>";
    }
} else {
    echo "No entries found in language_how_titles table<br>";
}

echo "<h3>Test Query Results:</h3>";
// Test the exact query from how-download.php
$test_lang_id = 1; // Assuming English is ID 1
$res = $conn->query("SELECT * FROM language_pages WHERE language_id=$test_lang_id AND slug='how' LIMIT 1");
if ($res && $res->num_rows > 0) {
    $page = $res->fetch_assoc();
    echo "✅ Exact match found: " . json_encode($page) . "<br>";
} else {
    echo "❌ No exact match found for language_id=$test_lang_id and slug='how'<br>";
    
    // Try partial match
    $res = $conn->query("SELECT * FROM language_pages WHERE language_id=$test_lang_id AND (slug LIKE '%how%' OR page_name LIKE '%how%') LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $page = $res->fetch_assoc();
        echo "✅ Partial match found: " . json_encode($page) . "<br>";
    } else {
        echo "❌ No partial match found either<br>";
    }
}
?> 