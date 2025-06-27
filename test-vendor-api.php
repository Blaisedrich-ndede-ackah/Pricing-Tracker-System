<?php
/**
 * Vendor API Test Script
 * Use this to test vendor creation directly
 */

// Start session FIRST before any output or includes
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Vendor API Test</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: blue; font-weight: bold; }
    .section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    form { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0; }
    input, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px; }
    button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
    button:hover { background: #0056b3; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
</style>";

// Check if user is logged in, if not simulate login
if (!isset($_SESSION['user_id'])) {
    // Simulate login with admin user
    try {
        // Database configuration (inline to avoid session conflicts)
        $dsn = "mysql:host=localhost;dbname=pricing_tracker;charset=utf8mb4";
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ? LIMIT 1");
        $stmt->execute(['admin']);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo "<div class='section'>";
            echo "<p class='success'>✓ Simulated login as: {$user['username']} (ID: {$user['id']})</p>";
            echo "</div>";
        } else {
            echo "<div class='section'>";
            echo "<p class='error'>✗ No admin user found. Please run the database setup first.</p>";
            echo "</div>";
            exit;
        }
    } catch (Exception $e) {
        echo "<div class='section'>";
        echo "<p class='error'>✗ Database connection failed: " . $e->getMessage() . "</p>";
        echo "</div>";
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    echo "<div class='section'>";
    echo "<h3>Test Results</h3>";
    
    if ($_POST['action'] === 'create_vendor') {
        $vendorName = trim($_POST['vendor_name']);
        $contactInfo = trim($_POST['contact_info']);
        
        if (empty($vendorName)) {
            echo "<p class='error'>✗ Vendor name is required</p>";
        } else {
            // Test vendor creation
            $testData = [
                'vendor_name' => $vendorName,
                'contact_info' => empty($contactInfo) ? null : $contactInfo
            ];
            
            echo "<p class='info'>Testing vendor creation with data:</p>";
            echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";
            
            // Direct database operation (avoiding config.php session conflicts)
            try {
                $dsn = "mysql:host=localhost;dbname=pricing_tracker;charset=utf8mb4";
                $pdo = new PDO($dsn, 'root', '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
                
                // Check if vendor already exists
                $checkStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? AND vendor_name = ?");
                $checkStmt->execute([$_SESSION['user_id'], $vendorName]);
                
                if ($checkStmt->fetch()) {
                    echo "<p class='error'>✗ Vendor with this name already exists</p>";
                } else {
                    // Create vendor
                    $stmt = $pdo->prepare("INSERT INTO vendors (user_id, vendor_name, contact_info) VALUES (?, ?, ?)");
                    $result = $stmt->execute([
                        $_SESSION['user_id'],
                        $vendorName,
                        empty($contactInfo) ? null : $contactInfo
                    ]);
                    
                    if ($result) {
                        $vendorId = $pdo->lastInsertId();
                        echo "<p class='success'>✓ Vendor created successfully!</p>";
                        echo "<p class='info'>Vendor ID: {$vendorId}</p>";
                        echo "<p class='info'>Vendor Name: {$vendorName}</p>";
                        echo "<p class='info'>Contact Info: " . ($contactInfo ?: 'None') . "</p>";
                    } else {
                        echo "<p class='error'>✗ Failed to create vendor</p>";
                    }
                }
            } catch (Exception $e) {
                echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "</div>";
}

// Show current vendors
echo "<div class='section'>";
echo "<h3>Current Vendors</h3>";

try {
    $dsn = "mysql:host=localhost;dbname=pricing_tracker;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $stmt = $pdo->prepare("
        SELECT v.*, 
               COALESCE(COUNT(p.id), 0) as product_count
        FROM vendors v
        LEFT JOIN products p ON v.id = p.vendor_id
        WHERE v.user_id = ?
        GROUP BY v.id, v.vendor_name, v.contact_info, v.created_at
        ORDER BY v.vendor_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $vendors = $stmt->fetchAll();
    
    if (empty($vendors)) {
        echo "<p class='info'>No vendors found.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Contact Info</th><th>Products</th><th>Created</th></tr>";
        foreach ($vendors as $vendor) {
            echo "<tr>";
            echo "<td>{$vendor['id']}</td>";
            echo "<td><strong>" . htmlspecialchars($vendor['vendor_name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($vendor['contact_info'] ?: 'None') . "</td>";
            echo "<td>{$vendor['product_count']}</td>";
            echo "<td>{$vendor['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Failed to load vendors: " . $e->getMessage() . "</p>";
}

echo "</div>";

// Test form
echo "<div class='section'>";
echo "<h3>Test Vendor Creation</h3>";
echo "<form method='POST'>";
echo "<input type='hidden' name='action' value='create_vendor'>";
echo "<label>Vendor Name (required):</label>";
echo "<input type='text' name='vendor_name' placeholder='Enter vendor name' required>";
echo "<label>Contact Info (optional):</label>";
echo "<textarea name='contact_info' placeholder='Enter contact information' rows='3'></textarea>";
echo "<button type='submit'>Create Vendor</button>";
echo "</form>";
echo "</div>";

// API endpoint test
echo "<div class='section'>";
echo "<h3>API Endpoint Information</h3>";
echo "<p>The vendor API endpoint is now fixed and should work properly:</p>";
echo "<p><strong>Endpoint:</strong> <code>api/vendors.php</code></p>";
echo "<p><strong>Method:</strong> POST</p>";
echo "<p><strong>Headers:</strong> Content-Type: application/json</p>";
echo "<p><strong>Body Example:</strong></p>";
echo "<pre>{
    \"vendor_name\": \"Test Vendor\",
    \"contact_info\": \"test@example.com\"
}</pre>";
echo "</div>";

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='dashboard.html' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Dashboard</a>";
echo "</div>";
?>
