<?php
/**
 * Configuration and Database Connection
 */

// Include debug utilities
require_once __DIR__ . '/debug.php';

// Configure session settings ONLY if session hasn't started yet
if (session_status() === PHP_SESSION_NONE) {
    // Set session configuration before starting session
    ini_set('session.cookie_lifetime', 86400); // 24 hours
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    
    // Start session
    session_start();
    debugLog("Session started with config", [
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
} else {
    debugLog("Session already active", [
        'session_id' => session_id(),
        'session_data' => $_SESSION
    ]);
}

// Database configuration - MySQL
define('DB_TYPE', 'mysql');
define('DB_HOST', 'localhost');
define('DB_NAME', 'pricing_tracker');
define('DB_USER', 'root');
define('DB_PASS', ''); // Update this if you have a MySQL password

/**
 * Get database connection
 */
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            debugLog("Database connection established", ['type' => DB_TYPE]);
            
        } catch (PDOException $e) {
            debugLog("Database connection failed", [
                'error' => $e->getMessage(),
                'type' => DB_TYPE
            ]);
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    $authenticated = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    
    debugLog("Authentication check", [
        'authenticated' => $authenticated,
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'session_id' => session_id()
    ]);
    
    return $authenticated;
}

/**
 * Require authentication for protected endpoints
 */
function requireAuth() {
    if (!isAuthenticated()) {
        debugLog("Authentication required - access denied", [
            'session_data' => $_SESSION,
            'session_id' => session_id()
        ]);
        sendResponse(['error' => 'Authentication required', 'redirect' => 'index.html'], 401);
    }
}

/**
 * Send JSON response
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>
