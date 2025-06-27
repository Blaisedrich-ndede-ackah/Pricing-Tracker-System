<?php
/**
 * MySQL Database Setup Script
 * Run this file to initialize your MySQL database
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Pricing Tracker MySQL Database Setup</h2>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";

// Database configuration - UPDATE THESE VALUES
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '', // SET YOUR MYSQL PASSWORD HERE
    'database' => 'pricing_tracker'
];

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>⚠️ Important: Update Database Credentials</h3>";
echo "<p>Before running this script, please update the database credentials in this file:</p>";
echo "<ul>";
echo "<li><strong>Host:</strong> {$config['host']}</li>";
echo "<li><strong>Username:</strong> {$config['username']}</li>";
echo "<li><strong>Password:</strong> " . (empty($config['password']) ? '<span style=\"color: red;\">NOT SET - PLEASE UPDATE</span>' : 'Set') . "</li>";
echo "<li><strong>Database:</strong> {$config['database']}</li>";
echo "</ul>";
echo "</div>";

// Step 1: Check PHP MySQL extension
echo "<h3>Step 1: System Requirements Check</h3>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";

$required_extensions = ['pdo', 'pdo_mysql'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p style='color: green;'>✓ {$ext} extension is loaded</p>";
    } else {
        echo "<p style='color: red;'>✗ {$ext} extension is NOT loaded (REQUIRED)</p>";
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Missing Required Extensions</h3>";
    echo "<p>Please install the following PHP extensions:</p>";
    echo "<ul>";
    foreach ($missing_extensions as $ext) {
        echo "<li>{$ext}</li>";
    }
    echo "</ul>";
    echo "<p>Contact your hosting provider or system administrator to install these extensions.</p>";
    echo "</div>";
    exit;
}

// Step 2: Test MySQL connection
echo "<h3>Step 2: MySQL Connection Test</h3>";

try {
    // First, connect without specifying database to create it if needed
    $dsn = "mysql:host={$config['host']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "<p style='color: green;'>✓ MySQL server connection successful</p>";
    
    // Check if database exists, create if not
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['database']}'");
    if ($stmt->rowCount() == 0) {
        echo "<p>Database '{$config['database']}' doesn't exist. Creating...</p>";
        $pdo->exec("CREATE DATABASE `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>✓ Database created successfully</p>";
    } else {
        echo "<p style='color: green;'>✓ Database '{$config['database']}' exists</p>";
    }
    
    // Connect to the specific database
    $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ MySQL connection failed: " . $e->getMessage() . "</p>";
    
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ MySQL Connection Failed</h3>";
    echo "<h4>Common Solutions:</h4>";
    echo "<ul>";
    echo "<li><strong>Check MySQL Service:</strong> Ensure MySQL/MariaDB is running</li>";
    echo "<li><strong>Verify Credentials:</strong> Check username and password</li>";
    echo "<li><strong>Check Host:</strong> Use 'localhost' or '127.0.0.1' for local installations</li>";
    echo "<li><strong>Port Issues:</strong> Default MySQL port is 3306</li>";
    echo "<li><strong>Firewall:</strong> Ensure MySQL port is not blocked</li>";
    echo "<li><strong>User Permissions:</strong> Ensure MySQL user has CREATE DATABASE privileges</li>";
    echo "</ul>";
    echo "<h4>For XAMPP/WAMP Users:</h4>";
    echo "<ul>";
    echo "<li>Start MySQL service from control panel</li>";
    echo "<li>Default username is usually 'root' with empty password</li>";
    echo "</ul>";
    echo "</div>";
    exit;
}

// Step 3: Check and fix existing tables
echo "<h3>Step 3: Checking and Fixing Database Tables</h3>";

try {
    // Check if users table exists and has correct structure
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠ Users table exists, checking structure...</p>";
        
        // Check if role column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($stmt->rowCount() == 0) {
            echo "<p style='color: orange;'>⚠ Adding missing 'role' column to users table...</p>";
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin', 'viewer') DEFAULT 'admin' AFTER password_hash");
            echo "<p style='color: green;'>✓ Role column added successfully</p>";
        } else {
            echo "<p style='color: green;'>✓ Users table structure is correct</p>";
        }
    } else {
        // Create users table from scratch
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role ENUM('admin', 'viewer') DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color: green;'>✓ Users table created</p>";
    }
    
    // Vendors table
    $stmt = $pdo->query("SHOW TABLES LIKE 'vendors'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE vendors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                vendor_name VARCHAR(255) NOT NULL,
                contact_info TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color: green;'>✓ Vendors table created</p>";
    } else {
        echo "<p style='color: green;'>✓ Vendors table exists</p>";
    }
    
    // Products table
    $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                vendor_id INT NULL,
                product_name VARCHAR(255) NOT NULL,
                actual_price DECIMAL(10, 2) NOT NULL,
                markup_percentage DECIMAL(5, 2) NOT NULL,
                selling_price DECIMAL(10, 2) NOT NULL,
                profit DECIMAL(10, 2) NOT NULL,
                quantity INT DEFAULT 1,
                total_profit DECIMAL(10, 2) NOT NULL,
                product_url TEXT,
                product_image VARCHAR(500),
                notes TEXT,
                date_added DATE NOT NULL,
                status ENUM('active', 'sold', 'discontinued') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color: green;'>✓ Products table created</p>";
    } else {
        echo "<p style='color: green;'>✓ Products table exists</p>";
    }
    
    // Sales table
    $stmt = $pdo->query("SHOW TABLES LIKE 'sales'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                user_id INT NOT NULL,
                quantity_sold INT NOT NULL,
                sale_price DECIMAL(10, 2) NOT NULL,
                actual_profit DECIMAL(10, 2) NOT NULL,
                sale_date DATE NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color: green;'>✓ Sales table created</p>";
    } else {
        echo "<p style='color: green;'>✓ Sales table exists</p>";
    }
    
    // Markup presets table
    $stmt = $pdo->query("SHOW TABLES LIKE 'markup_presets'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            CREATE TABLE markup_presets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                preset_name VARCHAR(100) NOT NULL,
                markup_percentage DECIMAL(5, 2) NOT NULL,
                is_default BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p style='color: green;'>✓ Markup presets table created</p>";
    } else {
        echo "<p style='color: green;'>✓ Markup presets table exists</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Failed to create/fix tables: " . $e->getMessage() . "</p>";
    exit;
}

// Step 4: Create indexes for better performance
echo "<h3>Step 4: Creating Performance Indexes</h3>";

try {
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_user_products ON products(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_vendors ON vendors(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_product_sales ON sales(product_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_sales ON sales(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_product_status ON products(status)",
        "CREATE INDEX IF NOT EXISTS idx_date_added ON products(date_added)"
    ];
    
    foreach ($indexes as $indexQuery) {
        try {
            $pdo->exec($indexQuery);
        } catch (Exception $e) {
            // Index might already exist, continue
        }
    }
    
    echo "<p style='color: green;'>✓ Performance indexes created</p>";
    
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠ Some indexes might already exist: " . $e->getMessage() . "</p>";
}

// Step 5: Create default user and data
echo "<h3>Step 5: Creating Default Data</h3>";

try {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "<p style='color: orange;'>⚠ Admin user already exists</p>";
        $adminId = $existingUser['id'];
    } else {
        // Create admin user with password 'admin123'
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $passwordHash, 'admin']);
        $adminId = $pdo->lastInsertId();
        
        echo "<p style='color: green;'>✓ Default admin user created successfully</p>";
    }
    
    // Insert default markup presets
    $presets = [
        ['Low Margin', 10.00, false],
        ['Standard', 25.00, true],
        ['High Margin', 50.00, false],
        ['Premium', 100.00, false]
    ];
    
    foreach ($presets as $preset) {
        $stmt = $pdo->prepare("SELECT id FROM markup_presets WHERE user_id = ? AND preset_name = ?");
        $stmt->execute([$adminId, $preset[0]]);
        
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO markup_presets (user_id, preset_name, markup_percentage, is_default) VALUES (?, ?, ?, ?)");
            $stmt->execute([$adminId, $preset[0], $preset[1], $preset[2]]);
        }
    }
    
    echo "<p style='color: green;'>✓ Default markup presets created</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Failed to create default data: " . $e->getMessage() . "</p>";
    exit;
}

// Step 6: Update config.php file
echo "<h3>Step 6: Updating Configuration File</h3>";

$configContent = "<?php
/**
 * Configuration and Database Connection
 */

// Include debug utilities
require_once __DIR__ . '/debug.php';

// Configure session settings
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    debugLog(\"Session started\", [
        'session_id' => session_id(),
        'session_data' => \$_SESSION
    ]);
}

// Database configuration - MySQL
define('DB_TYPE', 'mysql');
define('DB_HOST', '{$config['host']}');
define('DB_NAME', '{$config['database']}');
define('DB_USER', '{$config['username']}');
define('DB_PASS', '{$config['password']}');

/**
 * Get database connection
 */
function getDBConnection() {
    static \$pdo = null;
    
    if (\$pdo === null) {
        try {
            \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\";
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            debugLog(\"Database connection established\", ['type' => DB_TYPE]);
            
        } catch (PDOException \$e) {
            debugLog(\"Database connection failed\", [
                'error' => \$e->getMessage(),
                'type' => DB_TYPE
            ]);
            throw new Exception('Database connection failed: ' . \$e->getMessage());
        }
    }
    
    return \$pdo;
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    \$authenticated = isset(\$_SESSION['user_id']) && !empty(\$_SESSION['user_id']);
    
    debugLog(\"Authentication check\", [
        'authenticated' => \$authenticated,
        'user_id' => \$_SESSION['user_id'] ?? 'not set',
        'session_id' => session_id()
    ]);
    
    return \$authenticated;
}

/**
 * Require authentication for protected endpoints
 */
function requireAuth() {
    if (!isAuthenticated()) {
        debugLog(\"Authentication required - access denied\", [
            'session_data' => \$_SESSION,
            'session_id' => session_id()
        ]);
        sendResponse(['error' => 'Authentication required', 'redirect' => 'index.html'], 401);
    }
}

/**
 * Send JSON response
 */
function sendResponse(\$data, \$statusCode = 200) {
    http_response_code(\$statusCode);
    header('Content-Type: application/json');
    echo json_encode(\$data);
    exit;
}
?>";

if (file_put_contents('api/config.php', $configContent)) {
    echo "<p style='color: green;'>✓ Configuration file updated with MySQL settings</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to update configuration file</p>";
}

// Step 7: Final verification
echo "<h3>Step 7: Final Verification</h3>";

try {
    // Test the connection with the updated config
    require_once 'api/config.php';
    $testPdo = getDBConnection();
    
    // Count records
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM products");
    $productCount = $stmt->fetch()['count'];
    
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM vendors");
    $vendorCount = $stmt->fetch()['count'];
    
    $stmt = $testPdo->query("SELECT COUNT(*) as count FROM markup_presets");
    $presetCount = $stmt->fetch()['count'];
    
    echo "<p style='color: green;'>✓ Database connection function works correctly</p>";
    echo "<p><strong>Users in database:</strong> {$userCount}</p>";
    echo "<p><strong>Products in database:</strong> {$productCount}</p>";
    echo "<p><strong>Vendors in database:</strong> {$vendorCount}</p>";
    echo "<p><strong>Markup presets in database:</strong> {$presetCount}</p>";
    
    // Test user table structure
    $stmt = $testPdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    $hasRole = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'role') {
            $hasRole = true;
            break;
        }
    }
    
    if ($hasRole) {
        echo "<p style='color: green;'>✓ Users table has correct structure with role column</p>";
    } else {
        echo "<p style='color: red;'>✗ Users table missing role column</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Final verification failed: " . $e->getMessage() . "</p>";
    exit;
}

// Success message
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🎉 MySQL Setup Completed Successfully!</h3>";
echo "<p>Your Pricing Tracker system is now configured to use MySQL database.</p>";
echo "<p><strong>Database Details:</strong></p>";
echo "<ul>";
echo "<li><strong>Host:</strong> {$config['host']}</li>";
echo "<li><strong>Database:</strong> {$config['database']}</li>";
echo "<li><strong>Username:</strong> {$config['username']}</li>";
echo "</ul>";
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
?>
