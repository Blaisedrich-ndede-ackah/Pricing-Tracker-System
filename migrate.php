<?php
/**
 * Database Migration Script
 * Updates existing databases to support new features
 */

require_once 'api/config.php';

echo "<h2>Pricing Tracker Database Migration</h2>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";

try {
    $pdo = getDBConnection();
    
    echo "<h3>Checking and updating database schema...</h3>";
    
    // Check if we need to add new columns to products table
    $needsUpdate = false;
    
    try {
        $stmt = $pdo->query("SELECT quantity, total_profit, product_image, notes, date_added, status FROM products LIMIT 1");
    } catch (PDOException $e) {
        $needsUpdate = true;
        echo "<p style='color: orange;'>⚠ Products table needs updates</p>";
    }
    
    if ($needsUpdate) {
        echo "<p>Adding new columns to products table...</p>";
        
        $alterQueries = [];
        
        if (DB_TYPE === 'mysql') {
            $alterQueries = [
                "ALTER TABLE products ADD COLUMN quantity INT DEFAULT 1 AFTER profit",
                "ALTER TABLE products ADD COLUMN total_profit DECIMAL(10, 2) DEFAULT 0 AFTER quantity",
                "ALTER TABLE products ADD COLUMN product_image VARCHAR(500) AFTER product_url",
                "ALTER TABLE products ADD COLUMN notes TEXT AFTER product_image",
                "ALTER TABLE products ADD COLUMN date_added DATE DEFAULT (CURDATE()) AFTER notes",
                "ALTER TABLE products ADD COLUMN status ENUM('active', 'sold', 'discontinued') DEFAULT 'active' AFTER date_added"
            ];
        } else {
            $alterQueries = [
                "ALTER TABLE products ADD COLUMN quantity INTEGER DEFAULT 1",
                "ALTER TABLE products ADD COLUMN total_profit REAL DEFAULT 0",
                "ALTER TABLE products ADD COLUMN product_image TEXT",
                "ALTER TABLE products ADD COLUMN notes TEXT",
                "ALTER TABLE products ADD COLUMN date_added DATE DEFAULT (date('now'))",
                "ALTER TABLE products ADD COLUMN status TEXT DEFAULT 'active' CHECK(status IN ('active', 'sold', 'discontinued'))"
            ];
        }
        
        foreach ($alterQueries as $query) {
            try {
                $pdo->exec($query);
                echo "<p style='color: green;'>✓ " . substr($query, 0, 50) . "...</p>";
            } catch (PDOException $e) {
                // Column might already exist
                echo "<p style='color: orange;'>⚠ " . substr($query, 0, 50) . "... (might already exist)</p>";
            }
        }
        
        // Update existing products with calculated total_profit
        echo "<p>Updating existing products with total profit calculations...</p>";
        $stmt = $pdo->prepare("UPDATE products SET total_profit = profit * quantity WHERE total_profit = 0 OR total_profit IS NULL");
        $stmt->execute();
        echo "<p style='color: green;'>✓ Updated " . $stmt->rowCount() . " products</p>";
    }
    
    // Check if vendors table exists
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM vendors");
        echo "<p style='color: green;'>✓ Vendors table exists</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Creating vendors table...</p>";
        
        if (DB_TYPE === 'mysql') {
            $pdo->exec("
                CREATE TABLE vendors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    vendor_name VARCHAR(255) NOT NULL,
                    contact_info TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE vendors (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    vendor_name TEXT NOT NULL,
                    contact_info TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        }
        echo "<p style='color: green;'>✓ Vendors table created</p>";
    }
    
    // Check if sales table exists
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM sales");
        echo "<p style='color: green;'>✓ Sales table exists</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Creating sales table...</p>";
        
        if (DB_TYPE === 'mysql') {
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
                )
            ");
        } else {
            $pdo->exec("
                CREATE TABLE sales (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    quantity_sold INTEGER NOT NULL,
                    sale_price REAL NOT NULL,
                    actual_profit REAL NOT NULL,
                    sale_date DATE NOT NULL,
                    notes TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
        }
        echo "<p style='color: green;'>✓ Sales table created</p>";
    }
    
    // Add vendor_id column to products if it doesn't exist
    try {
        $stmt = $pdo->query("SELECT vendor_id FROM products LIMIT 1");
        echo "<p style='color: green;'>✓ Products table has vendor_id column</p>";
    } catch (PDOException $e) {
        echo "<p style='color: orange;'>⚠ Adding vendor_id column to products table...</p>";
        
        if (DB_TYPE === 'mysql') {
            $pdo->exec("ALTER TABLE products ADD COLUMN vendor_id INT NULL AFTER user_id");
            $pdo->exec("ALTER TABLE products ADD FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL");
        } else {
            $pdo->exec("ALTER TABLE products ADD COLUMN vendor_id INTEGER NULL");
        }
        echo "<p style='color: green;'>✓ vendor_id column added</p>";
    }
    
    // Create indexes for better performance
    echo "<p>Creating performance indexes...</p>";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_user_vendors ON vendors(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_product_sales ON sales(product_id)",
        "CREATE INDEX IF NOT EXISTS idx_user_sales ON sales(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_product_status ON products(status)",
        "CREATE INDEX IF NOT EXISTS idx_date_added ON products(date_added)"
    ];
    
    foreach ($indexes as $indexQuery) {
        try {
            $pdo->exec($indexQuery);
            echo "<p style='color: green;'>✓ Index created</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ Index might already exist</p>";
        }
    }
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 Migration Completed Successfully!</h3>";
    echo "<p>Your database has been updated with all the new features:</p>";
    echo "<ul>";
    echo "<li>✅ Enhanced product tracking with quantity and status</li>";
    echo "<li>✅ Vendor management system</li>";
    echo "<li>✅ Sales tracking and reporting</li>";
    echo "<li>✅ Image upload support</li>";
    echo "<li>✅ Backup and restore functionality</li>";
    echo "</ul>";
    echo "<p><strong>You can now use all the enhanced features!</strong></p>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='dashboard.html' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Enhanced Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Migration Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}
?>
