<?php
require_once 'api/config.php';

try {
    $pdo = getDBConnection();
    
    // Create test user
    $username = 'admin';
    $password = 'admin123';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo "Test user 'admin' already exists.<br>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$username, $passwordHash]);
        echo "Test user created successfully!<br>";
    }
    
    echo "<strong>Login credentials:</strong><br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br><br>";
    echo "<a href='index.html'>Go to Login Page</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
