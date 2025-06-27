<?php
/**
 * Fix Users Table and Test Vendor Creation
 * This script will fix user issues and test vendor creation
 */

// Start session first
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Fix Users Table and Test Vendor Creation</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; font-weight: bold; }
    .section { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
    form { background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0; }
    input, textarea { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 3px; }
    button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
    button:hover { background: #0056b3; }
</style>";

try {
    // Database connection
    $dsn = "mysql:host=localhost;dbname=pricing_tracker;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    echo "<div class='section'>";
    echo "<h3>Step 1: Database Connection</h3>";
    echo "<p class='success'>✓ Connected to database successfully</p>";
    echo "</div>";

    // Check current users
    echo "<div class='section'>";
    echo "<h3>Step 2: Current Users Analysis</h3>";
    
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "<p class='info'>Found " . count($users) . " users in database:</p>";
    
    if (empty($users)) {
        echo "<p class='warning'>⚠ No users found in database!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";

    // Check session user
    echo "<div class='section'>";
    echo "<h3>Step 3: Session Analysis</h3>";
    
    if (isset($_SESSION['user_id'])) {
        $sessionUserId = $_SESSION['user_id'];
        echo "<p class='info'>Session user_id: {$sessionUserId}</p>";
        
        // Check if session user exists in database
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$sessionUserId]);
        $sessionUser = $stmt->fetch();
        
        if ($sessionUser) {
            echo "<p class='success'>✓ Session user exists in database: {$sessionUser['username']}</p>";
        } else {
            echo "<p class='error'>✗ Session user ID {$sessionUserId} does not exist in database!</p>";
            echo "<p class='warning'>This is causing the foreign key constraint error.</p>";
        }
    } else {
        echo "<p class='warning'>⚠ No user_id in session</p>";
    }
    echo "</div>";

    // Fix users if needed
    echo "<div class='section'>";
    echo "<h3>Step 4: User Repair</h3>";
    
    if (empty($users)) {
        echo "<p class='info'>Creating default admin user...</p>";
        
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        $stmt->execute(['admin', $passwordHash, 'admin']);
        $newUserId = $pdo->lastInsertId();
        
        echo "<p class='success'>✓ Created admin user with ID: {$newUserId}</p>";
        
        // Update session
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = 'admin';
        
        echo "<p class='success'>✓ Updated session with new user ID</p>";
        
    } elseif (isset($_SESSION['user_id']) && !$sessionUser) {
        echo "<p class='info'>Fixing session user reference...</p>";
        
        // Use the first available user
        $firstUser = $users[0];
        $_SESSION['user_id'] = $firstUser['id'];
        $_SESSION['username'] = $firstUser['username'];
        
        echo "<p class='success'>✓ Updated session to use existing user: {$firstUser['username']} (ID: {$firstUser['id']})</p>";
        
    } elseif (!isset($_SESSION['user_id'])) {
        echo "<p class='info'>Setting up session with existing user...</p>";
        
        // Use the first admin user or any user
        $adminUser = null;
        foreach ($users as $user) {
            if ($user['role'] === 'admin') {
                $adminUser = $user;
                break;
            }
        }
        
        if (!$adminUser) {
            $adminUser = $users[0]; // Use first user if no admin
        }
        
        $_SESSION['user_id'] = $adminUser['id'];
        $_SESSION['username'] = $adminUser['username'];
        
        echo "<p class='success'>✓ Set session user: {$adminUser['username']} (ID: {$adminUser['id']})</p>";
    } else {
        echo "<p class='success'>✓ User setup is correct</p>";
    }
    echo "</div>";

    // Test vendor creation
    echo "<div class='section'>";
    echo "<h3>Step 5: Test Vendor Creation</h3>";
    
    if (isset($_SESSION['user_id'])) {
        $testUserId = $_SESSION['user_id'];
        
        // Verify user exists
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$testUserId]);
        $testUser = $stmt->fetch();
        
        if ($testUser) {
            echo "<p class='info'>Testing vendor creation with user: {$testUser['username']} (ID: {$testUserId})</p>";
            
            // Test vendor creation
            $testVendorName = "Test Vendor " . date('H:i:s');
            $testContactInfo = "test@example.com";
            
            try {
                $stmt = $pdo->prepare("INSERT INTO vendors (user_id, vendor_name, contact_info) VALUES (?, ?, ?)");
                $result = $stmt->execute([$testUserId, $testVendorName, $testContactInfo]);
                
                if ($result) {
                    $vendorId = $pdo->lastInsertId();
                    echo "<p class='success'>✓ Test vendor created successfully!</p>";
                    echo "<p class='info'>Vendor ID: {$vendorId}</p>";
                    echo "<p class='info'>Vendor Name: {$testVendorName}</p>";
                    echo "<p class='info'>User ID: {$testUserId}</p>";
                } else {
                    echo "<p class='error'>✗ Failed to create test vendor</p>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>✗ Vendor creation error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ Test user not found in database</p>";
        }
    } else {
        echo "<p class='error'>✗ No user in session for testing</p>";
    }
    echo "</div>";

    // Show current vendors
    echo "<div class='section'>";
    echo "<h3>Step 6: Current Vendors</h3>";
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT v.*, u.username
            FROM vendors v
            JOIN users u ON v.user_id = u.id
            WHERE v.user_id = ?
            ORDER BY v.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $vendors = $stmt->fetchAll();
        
        if (empty($vendors)) {
            echo "<p class='info'>No vendors found for current user.</p>";
        } else {
            echo "<p class='success'>Found " . count($vendors) . " vendors:</p>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Contact</th><th>User</th><th>Created</th></tr>";
            foreach ($vendors as $vendor) {
                echo "<tr>";
                echo "<td>{$vendor['id']}</td>";
                echo "<td><strong>" . htmlspecialchars($vendor['vendor_name']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($vendor['contact_info'] ?: 'None') . "</td>";
                echo "<td>{$vendor['username']}</td>";
                echo "<td>{$vendor['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    echo "</div>";

    // Manual vendor creation form
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_vendor'])) {
        echo "<div class='section'>";
        echo "<h3>Manual Vendor Creation Result</h3>";
        
        $vendorName = trim($_POST['vendor_name']);
        $contactInfo = trim($_POST['contact_info']);
        
        if (empty($vendorName)) {
            echo "<p class='error'>✗ Vendor name is required</p>";
        } elseif (!isset($_SESSION['user_id'])) {
            echo "<p class='error'>✗ No user in session</p>";
        } else {
            try {
                // Check if vendor already exists
                $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? AND vendor_name = ?");
                $stmt->execute([$_SESSION['user_id'], $vendorName]);
                
                if ($stmt->fetch()) {
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
                        echo "<p class='success'>✓ Vendor '{$vendorName}' created successfully!</p>";
                        echo "<p class='info'>Vendor ID: {$vendorId}</p>";
                        echo "<p class='info'>User ID: {$_SESSION['user_id']}</p>";
                    } else {
                        echo "<p class='error'>✗ Failed to create vendor</p>";
                    }
                }
            } catch (Exception $e) {
                echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
            }
        }
        echo "</div>";
    }

    // Vendor creation form
    echo "<div class='section'>";
    echo "<h3>Create New Vendor</h3>";
    echo "<form method='POST'>";
    echo "<label>Vendor Name (required):</label>";
    echo "<input type='text' name='vendor_name' placeholder='Enter vendor name' required>";
    echo "<label>Contact Info (optional):</label>";
    echo "<textarea name='contact_info' placeholder='Enter contact information' rows='3'></textarea>";
    echo "<button type='submit' name='create_vendor'>Create Vendor</button>";
    echo "</form>";
    echo "</div>";

    // Final status
    echo "<div class='section'>";
    echo "<h3>Final Status</h3>";
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch();
        
        if ($currentUser) {
            echo "<p class='success'>✓ System is ready for vendor creation</p>";
            echo "<p class='info'>Current user: {$currentUser['username']} (ID: {$_SESSION['user_id']})</p>";
            echo "<p class='info'>You can now create vendors through the dashboard or API</p>";
        } else {
            echo "<p class='error'>✗ Session user still not found in database</p>";
        }
    } else {
        echo "<p class='error'>✗ No user in session</p>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h3>❌ Error</h3>";
    echo "<p class='error'>Database error: " . $e->getMessage() . "</p>";
    echo "<p class='info'>File: " . $e->getFile() . "</p>";
    echo "<p class='info'>Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='dashboard.html' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px;'>Go to Dashboard</a>";
echo "<a href='test-vendor-api.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px;'>Test Vendor API</a>";
echo "</div>";
?>
