<?php
// =============================
//  UNIFIED HEADER TEMPLATE
// =============================
// This header handles both public pages (login/register) and authenticated dashboard pages

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
   define('BASE_URL', '/');
}

// Determine if user is logged in
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['username']);
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'member';
$username = $_SESSION['username'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$full_name = trim($first_name . ' ' . $last_name) ?: $username;

// Page configuration
$page_title = $page_title ?? 'Church Management System';
$layout_type = $layout_type ?? 'dashboard'; // 'dashboard' or 'public'
$breadcrumb = $breadcrumb ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

// Function to check if a link is active
function is_active($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return ($page === $current) ? 'active' : '';
}

// Function to check if current section is active (for sidebar)
function is_section_active($section) {
    $current_path = $_SERVER['PHP_SELF'];
    return (strpos($current_path, '/' . $section . '/') !== false) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Anglican Church Management System</title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/main.css">
    
    <?php if ($layout_type === 'dashboard'): ?>
        <!-- Dashboard-specific CSS -->
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css">
    <?php else: ?>
        <!-- Public pages CSS -->
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/public.css">
    <?php endif; ?>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/bootstrap.min.js"></script>
</head>
<body data-theme="light">

<?php if ($layout_type === 'dashboard' && $is_logged_in): ?>
    <!-- ========================================
         DASHBOARD LAYOUT (Authenticated Users)
         ======================================== -->
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ACK Emmanuel Karuri</h3>
                <small style="opacity: 0.8; display: block; font-size: 0.75rem;">Church Management</small>
            </div>

            <div class="sidebar-menu">
                <ul>
                    <li class="<?php echo is_section_active('dashboard'); ?>">
                        <a href="<?php echo BASE_URL; ?>dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('members'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/members/">
                            <i class="fas fa-users"></i> Members
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('events'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/events/">
                            <i class="fas fa-calendar-alt"></i> Events
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('visitors'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/visitors/">
                            <i class="fas fa-user-plus"></i> Visitors
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('facilities'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/facilities/">
                            <i class="fas fa-building"></i> Facilities
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('sermons'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/sermons/">
                            <i class="fas fa-book"></i> Sermons
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('reports'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/reports/">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li class="<?php echo is_section_active('settings'); ?>">
                        <a href="<?php echo BASE_URL; ?>modules/settings/">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="user-role"><?php echo ucfirst(htmlspecialchars($user_role)); ?></div>
                </div>
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="breadcrumb">
                    <a href="<?php echo BASE_URL; ?>dashboard.php">Home</a>
                    <?php if (!empty($breadcrumb)): ?>
                        / <span><?php echo htmlspecialchars($breadcrumb); ?></span>
                    <?php endif; ?>
                </div>

                <div class="nav-actions">
                    <div class="notification-icon">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=3498db&color=fff&size=40" alt="User Avatar">
                    </div>
                </div>
            </div>

            <!-- Page Content -->
            <div class="page-content">
                <!-- Alert Container -->
                <div id="alert-container"></div>

<?php else: ?>
    <!-- ========================================
         PUBLIC LAYOUT (Guest Users)
         ======================================== -->
    <nav class="navbar navbar-expand-lg" style="background-color: #007bff;">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo BASE_URL; ?>index.php" style="color: white;">
                ACK EMMANUEL KARURI
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation" 
                    style="background-color: rgba(255,255,255,0.1);">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo is_active('index.php'); ?>" 
                           href="<?php echo BASE_URL; ?>index.php" style="color: white;">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>

                    <?php if ($is_logged_in): ?>
                        <!-- Logged-in user links -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_active('dashboard.php'); ?>" 
                               href="<?php echo BASE_URL; ?>dashboard.php" style="color: white;">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link" style="color: white;">
                                <i class="fas fa-user"></i> Welcome, <?php echo htmlspecialchars($username); ?>!
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>logout.php" style="color: white;">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Guest user links -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_active('login.php'); ?>" 
                               href="<?php echo BASE_URL; ?>login.php" style="color: white;">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_active('register.php'); ?>" 
                               href="<?php echo BASE_URL; ?>register.php" style="color: white;">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo is_active('reset_password.php'); ?>" 
                               href="<?php echo BASE_URL; ?>reset_password.php" style="color: white;">
                                <i class="fas fa-key"></i> Reset Password
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Public Page Container -->
    <div class="container mt-4">
        <!-- Alert Container -->
        <div id="alert-container"></div>

<?php endif; ?>

<!-- Content continues in individual pages -->
