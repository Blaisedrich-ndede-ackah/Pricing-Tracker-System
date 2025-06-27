<?php
/**
 * Database Structure Checker
 * Verifies all tables have the correct structure
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Structure Checker</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
</style>";

try {
    require_once 'api/config.php';
    $pdo = getDBConnection();
    
    echo "<h3>Database Connection</h3>";
    echo "<p class='success'>✓ Connected to database: " . DB_NAME . "</p>";
    
    // Check all tables
    $tables = ['users', 'vendors', 'products', 'sales', 'markup_presets'];
    
    foreach ($tables as $tableName) {
        echo "<h3>Table: {$tableName}</h3>";
        
        try {
            $stmt = $pdo->query("DESCRIBE {$tableName}");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($columns)) {
                echo "<p class='error'>✗ Table {$tableName} does not exist or has no columns</p>";
                continue;
            }
            
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td><strong>{$column['Field']}</strong></td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
                echo "<td>{$column['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Count records
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$tableName}");
            $count = $stmt->fetch()['count'];
            echo "<p><strong>Records:</strong> {$count}</p>";
            
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error checking table {$tableName}: " . $e->getMessage() . "</p>";
        }
    }
    
    // Test critical queries
    echo "<h3>Query Tests</h3>";
    
    $testQueries = [
        'Users' => "SELECT id, username, role FROM users LIMIT 1",
        'Products' => "SELECT id, product_name, actual_price, markup_percentage, selling_price, profit FROM products LIMIT 1",
        'Vendors' => "SELECT id, vendor_name FROM vendors LIMIT 1"
    ];
    
    foreach ($testQueries as $testName => $query) {
        try {
            $stmt = $pdo->query($query);
            $result = $stmt->fetch();
            echo "<p class='success'>✓ {$testName} query successful</p>";
        } catch (Exception $e) {
            echo "<p class='error'>✗ {$testName} query failed: " . $e->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}
?>
