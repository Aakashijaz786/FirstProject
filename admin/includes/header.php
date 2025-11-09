<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/ckeditor/ckeditor/ckeditor.js"></script>
    
</head>
<body>
    
   <?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">Admin Panel</div>
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
    <nav class="nav flex-column mt-4">
        <a class="nav-link <?= ($current_page == 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a class="nav-link" href="../" target="_blank">
            <i class="fas fa-globe"></i> <span>Website</span>
        </a>
        <a class="nav-link <?= ($current_page == 'language.php') ? 'active' : '' ?>" href="language.php">
            <i class="fas fa-language"></i> <span>Language</span>
        </a>

        <div class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= in_array($current_page, ['logo_and_favicon.php', 'global_header.php', 'global_footer.php', 'general.php', 'changepassword.php']) ? 'active' : '' ?>" 
               href="#" id="settingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-cogs"></i> <span>Setting</span>
            </a>
            <ul class="dropdown-menu" aria-labelledby="settingDropdown">
                <li><a class="dropdown-item <?= ($current_page == 'general.php') ? 'active' : '' ?>" href="general.php">General Settings</a></li>
                <li><a class="dropdown-item <?= ($current_page == 'changepassword.php') ? 'active' : '' ?>" href="changepassword.php">Change Password</a></li>
                <li><a class="dropdown-item <?= ($current_page == 'logo_and_favicon.php') ? 'active' : '' ?>" href="logo_and_favicon.php">Logo & Favicon</a></li>
                <li><a class="dropdown-item <?= ($current_page == 'global_header.php') ? 'active' : '' ?>" href="global_header.php">Global Header</a></li>
                <li><a class="dropdown-item <?= ($current_page == 'global_footer.php') ? 'active' : '' ?>" href="global_footer.php">Global Footer</a></li>
            </ul>
        </div>
        <a class="nav-link <?= ($current_page == 'robot.php') ? 'active' : '' ?>" href="robot.php">
            <i class="fas fa-robot"></i> <span>Robots.txt</span>
        </a>
         <a class="nav-link <?= ($current_page == 'contact_messages.php') ? 'active' : '' ?>" href="contact_messages.php">
            <i class="fas fa-envelope"></i> <span>Contact Messages</span>
        </a>
        <!--<a class="nav-link <?= ($current_page == 'google_adsense.php') ? 'active' : '' ?>" href="google_adsense.php">-->
        <!--    <i class="fas fa-envelope"></i> <span>Google adsense</span>-->
        <!--</a>-->
        <a class="nav-link <?= ($current_page == 'api.php') ? 'active' : '' ?>" href="api.php">
            <i class="fas fa-robot"></i> <span>API Settings</span>
        </a>
        <a class="nav-link" href="logout.php">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </nav>
</div>
