<?php
/**
 * Database Diagnostic Script
 * Run this to identify specific database connection issues
 */

echo "<h2>Pricing Tracker - Database Diagnostics</h2>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";

// Check if config file exists
if (!file_exists('api/config.php')) {
    echo "<p style='color: red;'>❌ Config file not found at: api/config.php</p>";
    echo "<p>Please ensure all files are uploaded correctly.</p>";
    exit;
}

// Include config and test
try {
    require_once 'api/config.php';
    echo "<p style='color: green;'>✅ Config file loaded successfully</p>";
    
    // Show current configuration
    echo "<h3>Current Configuration:</h3>";
    echo "<p><strong>Database Type:</strong> " . (defined('DB_TYPE') ? DB_TYPE : 'Not defined') . "</p>";
    
    if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
        echo "<p><strong>MySQL Host:</strong> " . (defined('DB_HOST') ? DB_HOST : 'Not defined') . "</p>";
        echo "<p><strong>Database Name:</strong> " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "</p>";
        echo "<p><strong>Username:</strong> " . (defined('DB_USER') ? DB_USER : 'Not defined') . "</p>";
        echo "<p><strong>Password:</strong> " . (defined('DB_PASS') ? (empty(DB_PASS) ? 'Empty' : 'Set') : 'Not defined') . "</p>";
    } else {
        echo "<p><strong>SQLite Path:</strong> " . (defined('SQLITE_PATH') ? SQLITE_PATH : 'Not defined') . "</p>";
        
        if (defined('SQLITE_PATH')) {
            $dbDir = dirname(SQLITE_PATH);
            echo "<p><strong>Directory:</strong> {$dbDir}</p>";
            echo "<p><strong>Directory exists:</strong> " . (is_dir($dbDir) ? 'Yes' : 'No') . "</p>";
            echo "<p><strong>Directory writable:</strong> " . (is_writable($dbDir) ? 'Yes' : 'No') . "</p>";
        }
    }
    
    // Test connection
    echo "<h3>Connection Test:</h3>";
    $testResult = testDatabaseConnection();
    
    if ($testResult['success']) {
        echo "<p style='color: green;'>✅ Database connection successful!</p>";
        echo "<p>Database type: " . $testResult['type'] . "</p>";
        echo "<p>Test query: " . ($testResult['test_query'] ? 'Passed' : 'Failed') . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Database connection failed!</p>";
        echo "<p><strong>Error:</strong> " . $testResult['error'] . "</p>";
        echo "<p><strong>Database type:</strong> " . $testResult['type'] . "</p>";
        
        // Provide specific solutions
        if ($testResult['type'] === 'sqlite') {
            echo "<h4>SQLite Troubleshooting Steps:</h4>";
            echo "<ol>";
            echo "<li>Make sure the 'database' folder has write permissions (chmod 755 or 777)</li>";
            echo "<li>Check if PHP SQLite extension is enabled</li>";
            echo "<li>Verify the file path is correct</li>";
            echo "</ol>";
            
            // Try to create the directory
            if (defined('SQLITE_PATH')) {
                $dbDir = dirname(SQLITE_PATH);
                if (!is_dir($dbDir)) {
                    echo "<p>Attempting to create database directory...</p>";
                    if (mkdir($dbDir, 0755, true)) {
                        echo "<p style='color: green;'>✅ Directory created successfully</p>";
                    } else {
                        echo "<p style='color: red;'>❌ Failed to create directory</p>";
                    }
                }
            }
            
        } else {
            echo "<h4>MySQL Troubleshooting Steps:</h4>";
            echo "<ol>";
            echo "<li>Check if MySQL server is running</li>";
            echo "<li>Verify host, username, and password</li>";
            echo "<li>Ensure the database exists</li>";
            echo "<li>Check user permissions</li>";
            echo "</ol>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading configuration: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='setup.php'>← Back to Setup</a> | <a href='index.html'>Go to Login →</a></p>";
?>
