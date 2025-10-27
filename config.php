<?php
/**
 * ============================================
 * ENVIRONMENT CONFIGURATION MANAGER
 * Handles dev/staging/production environments
 * Safe for both CLI and Web modes
 * ============================================
 */

// Detect environment (supports both web + CLI)
function get_environment() {
    // ✅ Detect if running from CLI (like php migrate.php)
    if (php_sapi_name() === 'cli') {
        return 'development';
    }
    
    // For web requests, use HTTP_HOST safely
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Check for localhost (development)
    if (in_array($host, ['localhost', '127.0.0.1', 'localhost:8080', 'localhost:80'])) {
        return 'development';
    }
    
    // Check for staging domains
    if (strpos($host, 'staging.') === 0) {
        return 'staging';
    }
    
    // Check for InfinityFree domains (production)
    if (strpos($host, '.infinityfreeapp.com') !== false || 
        strpos($host, '.rf.gd') !== false ||
        strpos($host, '.42web.io') !== false) {
        return 'production';
    }
    
    // Everything else = production
    return 'production';
}

// Load environment-specific configuration
function load_config() {
    $env = get_environment();
    $config_file = __DIR__ . "/.env.{$env}";
    
    if (!file_exists($config_file)) {
        die("Configuration file not found: .env.{$env}");
    }
    
    // Parse .env file
    $lines = file($config_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) continue;
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Set as constant if not already defined
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Load the configuration
load_config();

// Database configuration helper
function get_db_config() {
    return [
        'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
        'name' => defined('DB_NAME') ? DB_NAME : 'anglicankenya',
        'user' => defined('DB_USER') ? DB_USER : 'root',
        'pass' => defined('DB_PASS') ? DB_PASS : '',
        'charset' => 'utf8mb4'
    ];
}

// Environment check helpers
function current_environment() {
    return get_environment();
}

function is_development() {
    return get_environment() === 'development';
}

function is_production() {
    return get_environment() === 'production';
}

// Error reporting (show all errors in development only)
if (is_development()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php-errors.log');
}

// ✅ NO CLOSING TAG - Prevents whitespace/BOM issues
