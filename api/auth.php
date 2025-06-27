<?php
/**
 * Authentication API Endpoints
 * Handles user login and logout functionality
 */

require_once 'config.php';

// Set CORS headers for frontend requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'POST':
        if (isset($input['action'])) {
            switch ($input['action']) {
                case 'login':
                    handleLogin($input);
                    break;
                case 'logout':
                    handleLogout();
                    break;
                case 'register':
                    handleRegister($input);
                    break;
                default:
                    sendResponse(['error' => 'Invalid action'], 400);
            }
        } else {
            sendResponse(['error' => 'Action required'], 400);
        }
        break;
    
    case 'GET':
        // Check authentication status
        checkAuthStatus();
        break;
    
    default:
        sendResponse(['error' => 'Method not allowed'], 405);
}

/**
 * Check authentication status
 */
function checkAuthStatus() {
    debugLog("Checking auth status", [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'username' => $_SESSION['username'] ?? 'not set',
        'session_data' => $_SESSION
    ]);
    
    $authenticated = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    
    sendResponse([
        'authenticated' => $authenticated,
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'session_id' => session_id()
    ]);
}

/**
 * Handle user login
 */
function handleLogin($data) {
    debugLog("Login attempt", ['username' => $data['username'] ?? 'not provided']);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendResponse(['error' => 'Username and password required'], 400);
    }
    
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        $user = $stmt->fetch();
        
        debugLog("User lookup result", [
            'found' => $user ? 'yes' : 'no',
            'username' => $data['username']
        ]);
        
        if ($user && password_verify($data['password'], $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['login_time'] = time();
            
            debugLog("Login successful", [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'session_id' => session_id()
            ]);
            
            sendResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'], 
                    'username' => $user['username']
                ],
                'session_id' => session_id()
            ]);
        } else {
            debugLog("Login failed - invalid credentials", ['username' => $data['username']]);
            sendResponse(['error' => 'Invalid credentials'], 401);
        }
    } catch (PDOException $e) {
        debugLog("Login database error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Login failed'], 500);
    }
}

/**
 * Handle user registration
 */
function handleRegister($data) {
    debugLog("Registration attempt", ['username' => $data['username'] ?? 'not provided']);
    
    if (!isset($data['username']) || !isset($data['password'])) {
        sendResponse(['error' => 'Username and password required'], 400);
    }
    
    if (strlen($data['password']) < 6) {
        sendResponse(['error' => 'Password must be at least 6 characters'], 400);
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        
        if ($stmt->fetch()) {
            sendResponse(['error' => 'Username already exists'], 409);
        }
        
        // Create new user
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$data['username'], $passwordHash]);
        
        debugLog("Registration successful", ['username' => $data['username']]);
        sendResponse(['success' => true, 'message' => 'Registration successful']);
        
    } catch (PDOException $e) {
        debugLog("Registration database error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Registration failed'], 500);
    }
}

/**
 * Handle user logout
 */
function handleLogout() {
    debugLog("Logout attempt", [
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'session_id' => session_id()
    ]);
    
    // Clear session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    debugLog("Logout successful");
    sendResponse(['success' => true, 'message' => 'Logout successful']);
}
?>
