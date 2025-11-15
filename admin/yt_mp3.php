<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

// Check if MP3 page is enabled
require_once '../includes/config.php';
require_once '../includes/api_client.php';
$settings = get_site_settings_cached($conn);
$mp3_enabled = isset($settings['mp3_page_enabled']) ? (int)$settings['mp3_page_enabled'] : 1;

if ($mp3_enabled != 1) {
    header('Location: language_pages.php?id=' . (int)$_GET['id'] . '&error=mp3_disabled');
    exit;
}

$_GET['page'] = 'mp3';
require __DIR__ . '/yt_front_page.php';
