<?php
/**
 * Test page to check API functionality
 */

require_once 'api/config.php';

// Create a test session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test_user';

echo "<h1>API Test Page</h1>";

try {
    $pdo = getDBConnection();
    echo "<p>✅ Database connection successful</p>";
    
    // Test if tables exist
    if (DB_TYPE === 'mysql') {
        $stmt = $pdo->query("SHOW TABLES");
    } else {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    }
    
    $tables = $stmt->fetchAll();
    echo "<p>✅ Found " . count($tables) . " tables:</p>";
    echo "<ul>";
    foreach ($tables as $table) {
        $tableName = DB_TYPE === 'mysql' ? array_values($table)[0] : $table['name'];
        echo "<li>$tableName</li>";
    }
    echo "</ul>";
    
    // Test vendors table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM vendors");
        $result = $stmt->fetch();
        echo "<p>✅ Vendors table accessible - " . $result['count'] . " records</p>";
    } catch (Exception $e) {
        echo "<p>❌ Vendors table error: " . $e->getMessage() . "</p>";
    }
    
    // Test products table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $result = $stmt->fetch();
        echo "<p>✅ Products table accessible - " . $result['count'] . " records</p>";
    } catch (Exception $e) {
        echo "<p>❌ Products table error: " . $e->getMessage() . "</p>";
    }
    
    // Test sales table
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sales");
        $result = $stmt->fetch();
        echo "<p>✅ Sales table accessible - " . $result['count'] . " records</p>";
    } catch (Exception $e) {
        echo "<p>❌ Sales table error: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>API Endpoints Test</h2>";
echo "<p><a href='api/vendors.php' target='_blank'>Test Vendors API</a></p>";
echo "<p><a href='api/products.php' target='_blank'>Test Products API</a></p>";
echo "<p><a href='api/sales.php' target='_blank'>Test Sales API</a></p>";
echo "<p><a href='api/backup.php?action=list' target='_blank'>Test Backup API</a></p>";

echo "<h2>Debug Logs</h2>";
$debugLog = __DIR__ . '/logs/debug.log';
if (file_exists($debugLog)) {
    echo "<pre>" . htmlspecialchars(file_get_contents($debugLog)) . "</pre>";
} else {
    echo "<p>No debug log found</p>";
}
?>
