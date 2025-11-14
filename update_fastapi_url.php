<?php
require_once __DIR__ . '/includes/config.php';

// Update FastAPI URL to port 8001 (since PHP server uses 8000)
$new_url = 'http://127.0.0.1:8001';
$conn->query("UPDATE site_settings SET fastapi_base_url='{$new_url}' WHERE id=1");

echo "Updated FastAPI base URL to: {$new_url}\n";
echo "You can now start the FastAPI backend on port 8001\n";

