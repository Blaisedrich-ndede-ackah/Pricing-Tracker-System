<?php
/**
 * Enhanced Database Setup Script with Diagnostics
 * Run this file to initialize your database with detailed error reporting
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Pricing Tracker Database Setup</h2>";
echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";

// Step 1: Check PHP version and extensions
echo "<h3>Step 1: System Requirements Check</h3>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";

$required_extensions = ['pdo'];
$optional_extensions = ['pdo_mysql', 'pdo_sqlite'];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✓ {$ext} extension is loaded</p>";
    } else {
        echo "<p style='color: red;'>✗ {$ext} extension is NOT loaded (REQUIRED)</p>";
    }
}

foreach ($optional_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✓ {$ext} extension is loaded</p>";
    } else {
        echo "<p style='color: orange;'>⚠ {$ext} extension is NOT loaded</p>";
    }
}

// Step 2: Check configuration file
echo "<h3>Step 2: Configuration Check</h3>";

if (!file_exists('api/config.php')) {
    echo "<p style='color: red;'>✗ Configuration file 'api/config.php' not found!</p>";
    echo "<p>Please make sure all files are uploaded correctly.</p>";
    exit;
}

require_once 'api/config.php';

echo "<p><strong>Database Type:</strong> " . DB_TYPE . "</p>";

if (DB_TYPE === 'mysql') {
    echo "<p><strong>MySQL Host:</strong> " . DB_HOST . "</p>";
    echo "<p><strong>Database Name:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Username:</strong> " . DB_USER . "</p>";
    echo "<p><strong>Password:</strong> " . (empty(DB_PASS) ? 'Not set' : 'Set (hidden)') . "</p>";
} else {
    echo "<p><strong>SQLite Path:</strong> " . SQLITE_PATH . "</p>";
    
    // Check if SQLite directory exists and is writable
    $dbDir = dirname(SQLITE_PATH);
    if (!is_dir($dbDir)) {
        echo "<p style='color: orange;'>⚠ Database directory doesn't exist. Creating: {$dbDir}</p>";
        if (!mkdir($dbDir, 0755, true)) {
            echo "<p style='color: red;'>✗ Failed to create database directory. Check permissions.</p>";
            exit;
        }
        echo "<p style='color: green;'>✓ Database directory created successfully</p>";
    } else {
        echo "<p style='color: green;'>✓ Database directory exists</p>";
    }
    
    if (!is_writable($dbDir)) {
        echo "<p style='color: red;'>✗ Database directory is not writable. Please set permissions to 755 or 777.</p>";
        exit;
    } else {
        echo "<p style='color: green;'>✓ Database directory is writable</p>";
    }
}

// Step 3: Test database connection
echo "<h3>Step 3: Database Connection Test</h3>";

try {
    if (DB_TYPE === 'mysql') {
        // Test MySQL connection
        echo "<p>Testing MySQL connection...</p>";
        
        if (!extension_loaded('pdo_mysql')) {
            throw new Exception("PDO MySQL extension is not loaded");
        }
        
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        echo "<p style='color: green;'>✓ MySQL server connection successful</p>";
        
        // Check if database exists, create if not
        $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        if ($stmt->rowCount() == 0) {
            echo "<p>Database '" . DB_NAME . "' doesn't exist. Creating...</p>";
            $pdo->exec("CREATE DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<p style='color: green;'>✓ Database created successfully</p>";
        } else {
            echo "<p style='color: green;'>✓ Database '" . DB_NAME . "' exists</p>";
        }
        
        // Connect to the specific database
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
    } else {
        // Test SQLite connection
        echo "<p>Testing SQLite connection...</p>";
        
        if (!extension_loaded('pdo_sqlite')) {
            throw new Exception("PDO SQLite extension is not loaded");
        }
        
        $dsn = "sqlite:" . SQLITE_PATH;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Enable foreign key constraints for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        echo "<p style='color: green;'>✓ SQLite connection successful</p>";
        echo "<p><strong>Database file:</strong> " . SQLITE_PATH . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    
    // Provide specific troubleshooting advice
    if (DB_TYPE === 'mysql') {
        echo "<h4>MySQL Troubleshooting:</h4>";
        echo "<ul>";
        echo "<li>Check if MySQL server is running</li>";
        echo "<li>Verify host, username, and password in api/config.php</li>";
        echo "<li>Ensure the MySQL user has CREATE DATABASE privileges</li>";
        echo "<li>Check if port 3306 is accessible (if using remote MySQL)</li>";
        echo "</ul>";
    } else {
        echo "<h4>SQLite Troubleshooting:</h4>";
        echo "<ul>";
        echo "<li>Check if the database directory has write permissions (755 or 777)</li>";
        echo "<li>Ensure PHP has SQLite extension enabled</li>";
        echo "<li>Verify the path in SQLITE_PATH is correct</li>";
        echo "</ul>";
    }
    
    exit;
}

// Step 4: Create tables
echo "<h3>Step 4: Creating Database Tables</h3>";

try {
    if (DB_TYPE === 'mysql') {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            actual_price DECIMAL(10, 2) NOT NULL,
            markup_percentage DECIMAL(5, 2) NOT NULL,
            selling_price DECIMAL(10, 2) NOT NULL,
            profit DECIMAL(10, 2) NOT NULL,
            product_url TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE INDEX IF NOT EXISTS idx_user_products ON products(user_id);
        ";
    } else {
        $sql = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            actual_price REAL NOT NULL,
            markup_percentage REAL NOT NULL,
            selling_price REAL NOT NULL,
            profit REAL NOT NULL,
            product_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        CREATE INDEX IF NOT EXISTS idx_user_products ON products(user_id);
        ";
    }
    
    // Execute SQL statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "<p style='color: green;'>✓ Database tables created successfully</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Failed to create tables: " . $e->getMessage() . "</p>";
    exit;
}

// Step 5: Create default user
echo "<h3>Step 5: Creating Default User</h3>";

try {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    
    if ($stmt->fetch()) {
        echo "<p style='color: orange;'>⚠ Admin user already exists</p>";
    } else {
        // Create admin user with password 'admin123'
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute(['admin', $passwordHash]);
        
        echo "<p style='color: green;'>✓ Default admin user created successfully</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Failed to create default user: " . $e->getMessage() . "</p>";
    exit;
}

// Step 6: Final verification
echo "<h3>Step 6: Final Verification</h3>";

try {
    // Test the getDBConnection function
    $testPdo = getDBConnection();
    
    // Count users and products
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM products");
    $productCount = $stmt->fetch()['count'];
    
    echo "<p style='color: green;'>✓ Database connection function works correctly</p>";
    echo "<p><strong>Users in database:</strong> {$userCount}</p>";
    echo "<p><strong>Products in database:</strong> {$productCount}</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Final verification failed: " . $e->getMessage() . "</p>";
    exit;
}

// Success message
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🎉 Setup Completed Successfully!</h3>";
echo "<p>Your Pricing Tracker system is now ready to use.</p>";
echo "<p><strong>Default Login Credentials:</strong></p>";
echo "<ul>";
echo "<li><strong>Username:</strong> admin</li>";
echo "<li><strong>Password:</strong> admin123</li>";
echo "</ul>";
echo "<p><strong>Important:</strong> Please change the default password after your first login for security.</p>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.html' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Login Page</a>";
echo "</div>";

echo "</div>";
?>
