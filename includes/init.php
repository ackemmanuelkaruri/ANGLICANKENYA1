<?php
/**
 * ============================================
 * SYSTEM INITIALIZER (init.php)
 * Central entry point for configuration, DB, and security
 * ============================================
 */

// -----------------------------------------------------
// 1. Define app root for consistent includes
// -----------------------------------------------------
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__)); // One level above /includes/
}

// -----------------------------------------------------
// 2. Load configuration and environment setup
//    (Since config.php is in the ROOT folder)
// -----------------------------------------------------
require_once APP_ROOT . '/config.php';

// -----------------------------------------------------
// 3. Define DB_INCLUDED before loading security.php
//    (Prevents "Direct access not permitted" errors)
// -----------------------------------------------------
define('DB_INCLUDED', true);

// -----------------------------------------------------
// 4. Load Database and Security modules
//    (db.php is also in the ROOT folder)
// -----------------------------------------------------
require_once APP_ROOT . '/db.php';
require_once APP_ROOT . '/includes/security.php';

// -----------------------------------------------------
// 5. Start secure session if not started
// -----------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -----------------------------------------------------
// 6. Optional: Auto-check login session
// -----------------------------------------------------
/*
if (!isset($_SESSION['user_id'])) {
    // Redirect to login if not logged in
  //  header("Location: " . (is_development() ? "/anglicankenya/login.php" : "/login.php"));
    //exit();
}
*/

// -----------------------------------------------------
// 7. Environment-specific logging (optional)
// -----------------------------------------------------
if (is_development()) {
    error_log("✅ init.php loaded successfully for environment: " . current_environment());
}
