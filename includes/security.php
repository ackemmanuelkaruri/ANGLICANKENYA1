<?php
/**
 * ============================================
 * MULTI-TENANT SECURITY & AUTHENTICATION (SECURED)
 * Enhanced with SQL Injection Protection + CSRF Token Support
 * ============================================
 */

// Prevent direct access
if (!defined('DB_INCLUDED')) {
    // 1. Clean any existing output buffer just in case
    if (ob_get_level()) { ob_end_clean(); }
    
    // 2. Set the HTTP header for JSON and a 403 Forbidden status code
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    
    // 3. Output the error as valid JSON
    echo json_encode([
        'success' => false, 
        'message' => 'Security check failed: Direct access not permitted to core file.'
    ]);
    
    // 4. Stop execution immediately
    exit; // Use exit instead of die for cleaner execution flow
}

/**
 * ============================================
 * SECURE SESSION CONFIGURATION
 * ============================================
 */
function configure_secure_session() {
    $session_timeout = 1800; // 30 minutes
    
    ini_set('session.cookie_lifetime', $session_timeout);
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_maxlifetime', $session_timeout);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.use_only_cookies', 1);
    
    // Check if we're behind ngrok or localhost
    $is_ngrok = strpos($_SERVER['HTTP_HOST'] ?? '', 'ngrok') !== false;
    $is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1']);
    
    // For ngrok/testing: use Lax and disable secure flag
    // For production HTTPS: use Strict and enable secure flag
    if ($is_ngrok || $is_localhost) {
        session_set_cookie_params([
            'lifetime' => $session_timeout,
            'path' => '/',
            'domain' => '',
            'secure' => false,  // Allow HTTP for ngrok/localhost
            'httponly' => true,
            'samesite' => 'Lax'  // Allow cross-site for ngrok
        ]);
    } else {
        $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
        session_set_cookie_params([
            'lifetime' => $session_timeout,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
}

function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        configure_secure_session();
        session_start();
        
        if (!isset($_SESSION['_session_initialized'])) {
            session_regenerate_id(true);
            $_SESSION['_session_initialized'] = true;
            $_SESSION['_created'] = time();
            $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['_ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        
        // ✅ CSRF Token Generation
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    
    validate_session_security();
}

function validate_session_security() {
    $session_timeout = 1800;
    
    if (!isset($_SESSION['_session_initialized'])) {
        return;
    }
    
    // User-Agent validation
    if (isset($_SESSION['_user_agent'])) {
        if ($_SESSION['_user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            destroy_session('User-Agent mismatch - potential session hijacking');
            exit('Session security validation failed.');
        }
    }
    
    // IP validation
    /*
    if (isset($_SESSION['_ip_address'])) {
        if ($_SESSION['_ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            error_log("WARNING: IP change detected for session " . session_id() . 
                     " from " . $_SESSION['_ip_address'] . " to " . $_SERVER['REMOTE_ADDR']);
        }
    }
    */
    
    // Session expiration
    if (isset($_SESSION['_created'])) {
        $elapsed = time() - $_SESSION['_created'];
        
        if ($elapsed > $session_timeout) {
            destroy_session('Session expired (timeout)');
            header('Location: login.php?expired=1');
            exit;
        }
        
        if (isset($_SESSION['_last_activity'])) {
            $inactivity = time() - $_SESSION['_last_activity'];
            
            if ($inactivity > 900) {
                destroy_session('Session expired (inactivity)');
                header('Location: login.php?inactive=1');
                exit;
            }
        }
        
        $_SESSION['_last_activity'] = time();
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function regenerate_session_id() {
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
    $_SESSION['_last_activity'] = time();
}

function destroy_session($reason = 'User logout') {
    if (is_logged_in()) {
        error_log("Session destroyed - User: " . $_SESSION['user_id'] . 
                 " | Reason: $reason | Session ID: " . session_id());
    }
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    session_destroy();
}

function get_session_remaining_time() {
    if (!isset($_SESSION['_created'])) {
        return 0;
    }
    
    $session_timeout = 1800;
    $elapsed = time() - $_SESSION['_created'];
    $remaining = $session_timeout - $elapsed;
    
    return max(0, $remaining);
}

function get_session_status() {
    $remaining = get_session_remaining_time();
    $total = 1800;
    $percent = ($remaining / $total) * 100;
    
    return [
        'remaining_seconds' => $remaining,
        'remaining_minutes' => floor($remaining / 60),
        'percent' => $percent,
        'is_expiring_soon' => $percent < 10,
        'is_expired' => $remaining <= 0
    ];
}

/**
 * ============================================
 * CSRF TOKEN FUNCTIONS
 * ============================================
 */

/**
 * Get or generate CSRF token
 */
function get_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token from HTTP header
 */
function verify_csrf_from_header() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token)) {
        return false;
    }
    return validate_csrf_token($token);
}

/**
 * Generate CSRF token field for forms
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST data
 */
function verify_csrf_token() {
    if (!isset($_POST['csrf_token'])) {
        return false;
    }
    return validate_csrf_token($_POST['csrf_token']);
}

/**
 * ============================================
 * ROLE & PERMISSION CHECKS
 * ============================================
 */
function is_super_admin() {
    return isset($_SESSION['role_level']) && $_SESSION['role_level'] === 'super_admin';
}

function is_national_admin() {
    return isset($_SESSION['role_level']) && $_SESSION['role_level'] === 'national_admin';
}

function is_diocese_admin() {
    return isset($_SESSION['role_level']) && $_SESSION['role_level'] === 'diocese_admin';
}

function is_church_admin() {
    return is_diocese_admin() || (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 3);
}

function is_archdeaconry_admin() {
    return isset($_SESSION['role_level']) && $_SESSION['role_level'] === 'archdeaconry_admin';
}

function is_deanery_admin() {
    return isset($_SESSION['role_level']) && $_SESSION['role_level'] === 'deanery_admin';
}

function is_parish_admin() {
    return isset($_SESSION['role_level']) && $_SESSION['role_level'] === 'parish_admin';
}
/**
 * ✅ SECURED: Check user permission with prepared statement
 */
function has_permission($permission_name) {
    global $pdo;

    if (!is_logged_in()) return false;
    if (is_super_admin()) return true;

    // ✅ Validate input
    if (!is_string($permission_name) || empty($permission_name)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.permission_name = ?
        ");
        $stmt->execute([$_SESSION['role_id'] ?? 0, $permission_name]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Permission check failed: " . $e->getMessage());
        return false;
    }
}

function require_permission($permission_name) {
    if (!has_permission($permission_name)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access Denied: You do not have permission to perform this action.');
    }
}

/**
 * ============================================
 * ORGANIZATION HELPERS (SECURED)
 * ============================================
 */
function get_user_org_id() {
    $org_id = $_SESSION['org_id'] ?? null;
    return $org_id ? (int)$org_id : null;
}

/**
 * ✅ SECURED: Validate org_id as integer
 */
function can_access_org($org_id) {
    // ✅ Validate input
    $org_id = filter_var($org_id, FILTER_VALIDATE_INT);
    if ($org_id === false || $org_id <= 0) {
        return false;
    }
    
    if (is_super_admin() || is_national_admin()) {
        return true;
    }
    
    return get_user_org_id() == $org_id;
}

/**
 * ✅ SECURED: Returns parameterized WHERE clause
 */
function get_org_filter() {
    if (is_super_admin() || is_national_admin()) {
        return ["", []]; // No filter, no params
    }
    
    $org_id = get_user_org_id();
    if (!$org_id) {
        return [" AND org_id IS NULL", []];
    }
    
    // ✅ Return parameterized WHERE clause
    return [" AND org_id = ?", [$org_id]];
}

/**
 * ✅ SECURED: Input sanitization with proper encoding (PHP 8.1+ compatible)
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    // PHP 8.1+ compatible: use htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING
    return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * ✅ SECURED: Activity logging with prepared statements
 * Enhanced with Impersonation Tracking
 */
if (!function_exists('log_activity')) {
    function log_activity($action_type, $table_name = null, $record_id = null, $additional_data = []) {
        global $pdo;
        
        try {
            // Get current user ID (or null if not logged in)
            $user_id = $_SESSION['user_id'] ?? null;
            
            // Check if currently impersonating
            $is_impersonated = isset($_SESSION['impersonating']) && isset($_SESSION['original_user_id']);
            $original_user_id = $is_impersonated ? $_SESSION['original_user_id'] : null;
            
            // Get IP address
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // Get user agent
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // If impersonating, add to additional data
            if ($is_impersonated) {
                $additional_data['impersonated_by'] = $original_user_id;
                $additional_data['impersonating_as'] = $user_id;
            }
            
            // Convert additional data to JSON
            $additional_data_json = !empty($additional_data) ? json_encode($additional_data) : null;
            
            // Insert into activity log
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (
                    user_id, 
                    action_type, 
                    table_name, 
                    record_id, 
                    ip_address, 
                    user_agent, 
                    additional_data,
                    is_impersonated,
                    original_user_id,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $action_type,
                $table_name,
                $record_id,
                $ip_address,
                $user_agent,
                $additional_data_json,
                $is_impersonated ? 1 : 0,
                $original_user_id
            ]);
            
            // Special handling for specific actions
            switch ($action_type) {
                case 'ROLE_CHANGED':
                    if (isset($additional_data['target_user_id'], $additional_data['old_role'], $additional_data['new_role'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO role_change_history (
                                user_id, 
                                old_role, 
                                new_role, 
                                changed_by, 
                                changed_at
                            ) VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $additional_data['target_user_id'],
                            $additional_data['old_role'],
                            $additional_data['new_role'],
                            $user_id
                        ]);
                    }
                    break;
                    
                case 'STATUS_CHANGED':
                    if (isset($additional_data['target_user_id'], $additional_data['old_status'], $additional_data['new_status'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO status_change_history (
                                user_id, 
                                old_status, 
                                new_status, 
                                changed_by, 
                                changed_at
                            ) VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $additional_data['target_user_id'],
                            $additional_data['old_status'],
                            $additional_data['new_status'],
                            $user_id
                        ]);
                    }
                    break;
                    
                case 'IMPERSONATION_STARTED':
                    if (isset($additional_data['target_user_id'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO impersonation_sessions (
                                original_user_id, 
                                target_user_id, 
                                started_at, 
                                ip_address
                            ) VALUES (?, ?, NOW(), ?)
                        ");
                        $stmt->execute([
                            $original_user_id ?? $user_id,
                            $additional_data['target_user_id'],
                            $ip_address
                        ]);
                    }
                    break;
                    
                case 'IMPERSONATION_ENDED':
                    if (isset($additional_data['original_user_id'], $additional_data['impersonated_user_id'])) {
                        $duration = $additional_data['duration_seconds'] ?? null;
                        $stmt = $pdo->prepare("
                            UPDATE impersonation_sessions 
                            SET ended_at = NOW(), 
                                duration_seconds = ?
                            WHERE original_user_id = ? 
                            AND target_user_id = ? 
                            AND ended_at IS NULL
                            ORDER BY started_at DESC 
                            LIMIT 1
                        ");
                        $stmt->execute([
                            $duration,
                            $additional_data['original_user_id'],
                            $additional_data['impersonated_user_id']
                        ]);
                    }
                    break;
                    
                case 'UNAUTHORIZED_ACCESS_ATTEMPT':
                    // Log to separate table for security monitoring
                    $stmt = $pdo->prepare("
                        INSERT INTO failed_permission_attempts (
                            user_id, 
                            resource_type, 
                            resource_id, 
                            action, 
                            ip_address, 
                            user_agent, 
                            attempted_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $additional_data['resource'] ?? null,
                        $additional_data['resource_id'] ?? null,
                        $additional_data['action'] ?? null,
                        $ip_address,
                        $user_agent
                    ]);
                    break;
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Activity logging error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * ✅ SECURED: User details with prepared statement
 */
function get_user_details($user_id = null) {
    global $pdo;

    $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
    
    // ✅ Validate user_id
    $user_id = filter_var($user_id, FILTER_VALIDATE_INT);
    if ($user_id === false || $user_id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.*, 
                o.org_name, 
                o.org_code,
                r.role_name, 
                r.role_level
            FROM users u
            LEFT JOIN organizations o ON u.org_id = o.id
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get user details: " . $e->getMessage());
        return null;
    }
}

/**
 * ✅ SECURED: Organization info
 */
function get_organization($org_id = null) {
    global $pdo;
    
    $org_id = $org_id ?? get_user_org_id();
    
    // ✅ Validate org_id
    $org_id = filter_var($org_id, FILTER_VALIDATE_INT);
    if ($org_id === false || $org_id <= 0) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM organizations WHERE id = ?");
        $stmt->execute([$org_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to get organization: " . $e->getMessage());
        return null;
    }
}

/**
 * ✅ SECURED: Accessible organizations
 */
function get_accessible_organizations() {
    global $pdo;

    try {
        if (is_super_admin() || is_national_admin()) {
            $stmt = $pdo->query("SELECT * FROM organizations ORDER BY org_name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $org_id = get_user_org_id();
            if (!$org_id) return [];
            
            $stmt = $pdo->prepare("SELECT * FROM organizations WHERE id = ?");
            $stmt->execute([$org_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Failed to get organizations: " . $e->getMessage());
        return [];
    }
}

/**
 * ✅ SECURED: Validate organization access
 */
function validate_org_access($org_id) {
    if (!can_access_org($org_id)) {
        log_activity('UNAUTHORIZED_ACCESS_ATTEMPT', null, null, ['attempted_org' => $org_id]);
        header('HTTP/1.1 403 Forbidden');
        die('Access Denied: You cannot access this organization.');
    }
}

/**
 * ✅ IMPROVED: Better encryption (use PHP 7.2+ sodium if available)
 */
function encrypt_data($data, $key) {
    // Use sodium if available (PHP 7.2+)
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($data, $nonce, $key);
        return base64_encode($nonce . $encrypted);
    }
    
    // Fallback to OpenSSL
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
    $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, 0, $iv, $tag);
    return base64_encode($encrypted . '::' . $iv . '::' . $tag);
}

function decrypt_data($data, $key) {
    $decoded = base64_decode($data);
    
    // Try sodium first
    if (function_exists('sodium_crypto_secretbox_open')) {
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
    }
    
    // Fallback to OpenSSL
    $parts = explode('::', $decoded);
    if (count($parts) !== 3) return false;
    
    list($encrypted_data, $iv, $tag) = $parts;
    return openssl_decrypt($encrypted_data, 'aes-256-gcm', $key, 0, $iv, $tag);
}

/**
 * ✅ SECURED: Rate limiting with prepared statements
 */
function check_rate_limit($identifier, $max_attempts = 5, $time_window = 900) {
    global $pdo;

    // ✅ Validate inputs (PHP 8.1+ compatible)
    $identifier = htmlspecialchars(trim($identifier), ENT_QUOTES, 'UTF-8');
    $max_attempts = (int)$max_attempts;
    $time_window = (int)$time_window;

    try {
        // Clean old attempts
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$time_window]);

        // Check attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE identifier = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $time_window]);
        $attempts = $stmt->fetchColumn();

        if ($attempts >= $max_attempts) return false;

        // Log attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, NOW())");
        $stmt->execute([$identifier]);

        return true;
    } catch (Exception $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return true; // Fail open to prevent lockout
    }
}

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function get_role_badge() {
    if (is_super_admin()) {
        return '<span class="badge badge-danger">Super Admin</span>';
    } elseif (is_national_admin()) {
        return '<span class="badge badge-warning">National Admin</span>';
    } elseif (is_church_admin()) {
        return '<span class="badge badge-primary">Church Admin</span>';
    } else {
        return '<span class="badge badge-secondary">Member</span>';
    }
}

/**
 * ✅ SECURED: Subscription check
 */
function is_subscription_active($org_id = null) {
    global $pdo;

    $org_id = $org_id ?? get_user_org_id();
    if (is_super_admin()) return true;
    
    // ✅ Validate org_id
    $org_id = filter_var($org_id, FILTER_VALIDATE_INT);
    if ($org_id === false || $org_id <= 0) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT subscription_status, subscription_end_date 
            FROM organizations 
            WHERE id = ?
        ");
        $stmt->execute([$org_id]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$org) return false;
        if ($org['subscription_status'] !== 'active') return false;

        if ($org['subscription_end_date'] && strtotime($org['subscription_end_date']) < time()) {
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log("Subscription check failed: " . $e->getMessage());
        return false;
    }
}

function get_role_display_name($role_level) {
    if (is_numeric($role_level)) {
        $roles = [
            1 => 'Super Admin',
            2 => 'National Admin',
            3 => 'Diocese Admin',
            4 => 'Archdeaconry Admin',
            5 => 'Deanery Admin',
            6 => 'Parish Admin',
            7 => 'Member'
        ];
        return $roles[$role_level] ?? 'Unknown Role';
    }
    
    $roles = [
        'super_admin' => 'Super Admin',
        'national_admin' => 'National Admin',
        'diocese_admin' => 'Diocese Admin',
        'archdeaconry_admin' => 'Archdeaconry Admin',
        'deanery_admin' => 'Deanery Admin',
        'parish_admin' => 'Parish Admin',
        'member' => 'Member'
    ];
    
    return $roles[strtolower($role_level)] ?? 'Unknown Role';
}

/**
 * Check if current user can manage a target user
 */
function can_manage_user($currentUserRole, $currentUserId, $targetUserRole, $targetUserId) {
    // Users can't modify themselves
    if ($currentUserId === $targetUserId) {
        return false;
    }
    
    // Super admins can manage everyone
    if ($currentUserRole === 'super_admin') {
        return true;
    }
    
    // Define role hierarchy
    $roleHierarchy = [
        'super_admin' => 100,
        'national_admin' => 90,
        'diocese_admin' => 80,
        'archdeaconry_admin' => 70,
        'deanery_admin' => 60,
        'parish_admin' => 50,
        'member' => 10
    ];
    
    // Check if current user has higher role than target user
    if (isset($roleHierarchy[$currentUserRole]) && isset($roleHierarchy[$targetUserRole])) {
        return $roleHierarchy[$currentUserRole] > $roleHierarchy[$targetUserRole];
    }
    
    return false;
}

/**
 * Start impersonating another user
 */
function start_impersonation($target_user_id) {
    global $pdo;
    
    // Only super admins can impersonate
    if (!isset($_SESSION['role_level']) || $_SESSION['role_level'] !== 'super_admin') {
        return false;
    }
    
    // Validate target user ID
    $target_user_id = filter_var($target_user_id, FILTER_VALIDATE_INT);
    if ($target_user_id === false || $target_user_id <= 0) {
        return false;
    }
    
    // Get target user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target_user) {
        return false;
    }
    
    // Store original user
    $_SESSION['original_user_id'] = $_SESSION['user_id'];
    $_SESSION['original_role_level'] = $_SESSION['role_level'];
    
    // Switch to target user
    $_SESSION['user_id'] = $target_user['id'];
    $_SESSION['role_level'] = $target_user['role_level'];
    $_SESSION['impersonating'] = true;
    
    // Log impersonation
    log_activity('IMPERSONATE_START', 'users', $target_user_id, [
        'impersonator_id' => $_SESSION['original_user_id'],
        'impersonator_role' => $_SESSION['original_role_level']
    ]);
    
    return true;
}

/**
 * Stop impersonating
 */
function stop_impersonation() {
    if (!isset($_SESSION['original_user_id'])) {
        return false;
    }
    
    // Log end of impersonation
    log_activity('IMPERSONATE_END', 'users', $_SESSION['user_id'], [
        'impersonator_id' => $_SESSION['original_user_id'],
        'impersonator_role' => $_SESSION['original_role_level']
    ]);
    
    // Restore original user
    $_SESSION['user_id'] = $_SESSION['original_user_id'];
    $_SESSION['role_level'] = $_SESSION['original_role_level'];
    unset($_SESSION['original_user_id']);
    unset($_SESSION['original_role_level']);
    unset($_SESSION['impersonating']);
    
    return true;
}

/**
 * Get recent activity for a user
 * 
 * @param int $user_id User ID
 * @param int $limit Number of records to return
 * @return array
 */
function get_user_activity($user_id, $limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                log_id,
                action_type,
                table_name,
                record_id,
                ip_address,
                is_impersonated,
                original_user_id,
                additional_data,
                created_at
            FROM activity_log
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching user activity: " . $e->getMessage());
        return [];
    }
}

/**
 * Get system-wide recent activity (admin only)
 * 
 * @param int $limit Number of records
 * @param array $filters Optional filters
 * @return array
 */
function get_system_activity($limit = 100, $filters = []) {
    global $pdo;
    
    try {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['action_type'])) {
            $where_conditions[] = "action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['is_impersonated'])) {
            $where_conditions[] = "is_impersonated = 1";
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $sql = "
            SELECT 
                a.log_id,
                a.user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                a.action_type,
                a.table_name,
                a.record_id,
                a.ip_address,
                a.is_impersonated,
                a.original_user_id,
                a.additional_data,
                a.created_at
            FROM activity_log a
            LEFT JOIN users u ON a.user_id = u.id
            $where_clause
            ORDER BY a.created_at DESC
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching system activity: " . $e->getMessage());
        return [];
    }
}

/**
 * Clean up old activity logs (should be run via cron)
 * 
 * @param int $days_to_keep Number of days to keep logs
 * @return int Number of deleted records
 */
function cleanup_old_logs($days_to_keep = 90) {
    global $pdo;
    
    try {
        // Don't delete critical security events
        $stmt = $pdo->prepare("
            DELETE FROM activity_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND action_type NOT IN (
                'LOGIN_FAILED', 
                'UNAUTHORIZED_ACCESS_ATTEMPT', 
                'ROLE_CHANGED', 
                'STATUS_CHANGED',
                'IMPERSONATION_STARTED',
                'IMPERSONATION_ENDED'
            )
        ");
        $stmt->execute([$days_to_keep]);
        
        return $stmt->rowCount();
        
    } catch (PDOException $e) {
        error_log("Error cleaning up logs: " . $e->getMessage());
        return 0;
    }
}
