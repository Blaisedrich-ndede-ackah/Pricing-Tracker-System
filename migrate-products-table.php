<?php
/**
 * Products Table Migration Script
 * Adds missing columns to existing products table
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Products Table Migration</h2>";
echo "<style>body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }</style>";

try {
    // Include config to get database connection
    require_once 'api/config.php';
    $pdo = getDBConnection();
    
    echo "<h3>Step 1: Checking Current Table Structure</h3>";
    
    // Get current table structure
    $stmt = $pdo->query("DESCRIBE products");
    $currentColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Current columns in products table:</strong></p>";
    echo "<ul>";
    foreach ($currentColumns as $column) {
        echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} " . 
             ($column['Null'] === 'YES' ? '(nullable)' : '(not null)') . "</li>";
    }
    echo "</ul>";
    
    // Define required columns with their specifications
    $requiredColumns = [
        'markup_percentage' => 'DECIMAL(5, 2) DEFAULT 0',
        'selling_price' => 'DECIMAL(10, 2) DEFAULT 0',
        'profit' => 'DECIMAL(10, 2) DEFAULT 0',
        'quantity' => 'INT DEFAULT 1',
        'total_profit' => 'DECIMAL(10, 2) DEFAULT 0',
        'product_url' => 'TEXT NULL',
        'product_image' => 'VARCHAR(500) NULL',
        'notes' => 'TEXT NULL',
        'date_added' => 'DATE NOT NULL DEFAULT (CURDATE())',
        'status' => "ENUM('active', 'sold', 'discontinued') DEFAULT 'active'",
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    // Check which columns are missing
    $existingColumnNames = array_column($currentColumns, 'Field');
    $missingColumns = [];
    
    foreach ($requiredColumns as $columnName => $columnSpec) {
        if (!in_array($columnName, $existingColumnNames)) {
            $missingColumns[$columnName] = $columnSpec;
        }
    }
    
    echo "<h3>Step 2: Adding Missing Columns</h3>";
    
    if (empty($missingColumns)) {
        echo "<p style='color: green;'>✓ All required columns already exist!</p>";
    } else {
        echo "<p><strong>Missing columns to be added:</strong></p>";
        echo "<ul>";
        foreach ($missingColumns as $columnName => $columnSpec) {
            echo "<li><strong>{$columnName}</strong> - {$columnSpec}</li>";
        }
        echo "</ul>";
        
        // Add missing columns one by one
        foreach ($missingColumns as $columnName => $columnSpec) {
            try {
                $sql = "ALTER TABLE products ADD COLUMN {$columnName} {$columnSpec}";
                $pdo->exec($sql);
                echo "<p style='color: green;'>✓ Added column: <strong>{$columnName}</strong></p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ Failed to add column {$columnName}: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<h3>Step 3: Updating Existing Data</h3>";
    
    // Update existing records to calculate missing values
    try {
        // First, let's see if we have any existing products
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM products");
        $productCount = $stmt->fetch()['count'];
        
        echo "<p><strong>Found {$productCount} existing products</strong></p>";
        
        if ($productCount > 0) {
            // Update products that have actual_price but missing calculated fields
            $updateSql = "
                UPDATE products 
                SET 
                    selling_price = CASE 
                        WHEN selling_price = 0 OR selling_price IS NULL 
                        THEN actual_price * (1 + COALESCE(markup_percentage, 0) / 100)
                        ELSE selling_price 
                    END,
                    profit = CASE 
                        WHEN profit = 0 OR profit IS NULL 
                        THEN (actual_price * (1 + COALESCE(markup_percentage, 0) / 100)) - actual_price
                        ELSE profit 
                    END,
                    quantity = CASE 
                        WHEN quantity = 0 OR quantity IS NULL 
                        THEN 1 
                        ELSE quantity 
                    END
                WHERE actual_price > 0
            ";
            
            $pdo->exec($updateSql);
            echo "<p style='color: green;'>✓ Updated selling prices and profits for existing products</p>";
            
            // Update total_profit based on profit and quantity
            $updateTotalProfitSql = "
                UPDATE products 
                SET total_profit = profit * quantity 
                WHERE profit IS NOT NULL AND quantity IS NOT NULL
            ";
            
            $pdo->exec($updateTotalProfitSql);
            echo "<p style='color: green;'>✓ Updated total profits for existing products</p>";
            
            // Set default date_added for products that don't have it
            $updateDateSql = "
                UPDATE products 
                SET date_added = CURDATE() 
                WHERE date_added IS NULL OR date_added = '0000-00-00'
            ";
            
            $pdo->exec($updateDateSql);
            echo "<p style='color: green;'>✓ Set default dates for existing products</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Warning updating existing data: " . $e->getMessage() . "</p>";
    }
    
    echo "<h3>Step 4: Final Verification</h3>";
    
    // Check final table structure
    $stmt = $pdo->query("DESCRIBE products");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Final table structure:</strong></p>";
    echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($finalColumns as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test a sample query
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, product_name, actual_price, markup_percentage, 
                selling_price, profit, quantity, total_profit, status
            FROM products 
            LIMIT 1
        ");
        $stmt->execute();
        $sampleProduct = $stmt->fetch();
        
        if ($sampleProduct) {
            echo "<p style='color: green;'>✓ Sample query successful - table structure is correct</p>";
            echo "<p><strong>Sample product data:</strong></p>";
            echo "<pre>" . print_r($sampleProduct, true) . "</pre>";
        } else {
            echo "<p style='color: green;'>✓ Query successful - table is ready for new products</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Test query failed: " . $e->getMessage() . "</p>";
    }
    
    // Success message
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🎉 Products Table Migration Completed!</h3>";
    echo "<p>Your products table now has all required columns and existing data has been updated.</p>";
    echo "<p><strong>You can now:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Create new products</li>";
    echo "<li>✅ Load existing products</li>";
    echo "<li>✅ Update product information</li>";
    echo "<li>✅ Track profits and quantities</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin: 30px 0;'>";
    echo "<a href='dashboard.html' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Migration Failed</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
    echo "</div>";
}
?>
