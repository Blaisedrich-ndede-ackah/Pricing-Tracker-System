<?php
// Start session and set up basic authentication simulation
session_start();

// Simulate logged in user (you may need to adjust this user_id)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Adjust this to match your actual user ID
    $_SESSION['username'] = 'admin';
}

// Database configuration
$host = 'localhost';
$dbname = 'pricing_tracker';
$username = 'root';
$password = ''; // Update if you have a password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_sale') {
        try {
            // Validate input
            $productId = (int)$_POST['product_id'];
            $quantitySold = (int)$_POST['quantity_sold'];
            $salePrice = (float)$_POST['sale_price'];
            $saleDate = $_POST['sale_date'];
            $notes = trim($_POST['notes']);
            $updateInventory = isset($_POST['update_inventory']);
            
            // Get product details
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
            $stmt->execute([$productId, $_SESSION['user_id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Product not found");
            }
            
            if ($quantitySold > $product['quantity']) {
                throw new Exception("Only {$product['quantity']} items available");
            }
            
            // Calculate profit
            $actualProfit = ($salePrice - $product['actual_price']) * $quantitySold;
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert sale
            $stmt = $pdo->prepare("
                INSERT INTO sales (user_id, product_id, quantity_sold, sale_price, actual_profit, sale_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $productId,
                $quantitySold,
                $salePrice,
                $actualProfit,
                $saleDate,
                $notes ?: null
            ]);
            
            $saleId = $pdo->lastInsertId();
            
            // Update inventory if requested
            if ($updateInventory) {
                $newQuantity = $product['quantity'] - $quantitySold;
                $newStatus = $newQuantity <= 0 ? 'sold' : $product['status'];
                
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET quantity = ?, status = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$newQuantity, $newStatus, $productId, $_SESSION['user_id']]);
            }
            
            $pdo->commit();
            
            $message = "Sale recorded successfully! Sale ID: $saleId, Profit: GHS " . number_format($actualProfit, 2);
            $messageType = 'success';
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollback();
            }
            $message = "Error: " . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get products for dropdown
$stmt = $pdo->prepare("
    SELECT p.*, v.vendor_name 
    FROM products p 
    LEFT JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.user_id = ? AND p.quantity > 0
    ORDER BY p.product_name
");
$stmt->execute([$_SESSION['user_id']]);
$products = $stmt->fetchAll();

// Get recent sales
$stmt = $pdo->prepare("
    SELECT s.*, p.product_name, v.vendor_name
    FROM sales s
    JOIN products p ON s.product_id = p.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recentSales = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales API Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #005a87;
        }
        .message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
        }
        .profit {
            font-weight: bold;
            color: #28a745;
        }
        .product-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <h1>Sales Recording Test</h1>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="container">
        <h2>Record New Sale</h2>
        
        <?php if (empty($products)): ?>
            <p><strong>No products available for sale.</strong> Please add some products first.</p>
            <a href="dashboard.html">Go to Dashboard</a>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="create_sale">
                
                <div class="form-group">
                    <label for="product_id">Product:</label>
                    <select name="product_id" id="product_id" required onchange="updateProductInfo()">
                        <option value="">Select a product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    data-price="<?php echo $product['actual_price']; ?>"
                                    data-selling="<?php echo $product['selling_price']; ?>"
                                    data-quantity="<?php echo $product['quantity']; ?>">
                                <?php echo htmlspecialchars($product['product_name']); ?>
                                (<?php echo $product['vendor_name'] ?: 'No Vendor'; ?>) - 
                                GHS <?php echo number_format($product['actual_price'], 2); ?> - 
                                Qty: <?php echo $product['quantity']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="product-info" class="product-info">
                    <p><strong>Product Details:</strong></p>
                    <p>Cost Price: GHS <span id="cost-price">0.00</span></p>
                    <p>Suggested Sale Price: GHS <span id="suggested-price">0.00</span></p>
                    <p>Available Quantity: <span id="available-qty">0</span></p>
                </div>
                
                <div class="form-group">
                    <label for="quantity_sold">Quantity Sold:</label>
                    <input type="number" name="quantity_sold" id="quantity_sold" min="1" required onchange="calculateProfit()">
                </div>
                
                <div class="form-group">
                    <label for="sale_price">Sale Price (per unit):</label>
                    <input type="number" name="sale_price" id="sale_price" step="0.01" min="0.01" required onchange="calculateProfit()">
                </div>
                
                <div class="form-group">
                    <label for="sale_date">Sale Date:</label>
                    <input type="date" name="sale_date" id="sale_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (optional):</label>
                    <textarea name="notes" id="notes" rows="3" placeholder="Any additional notes about this sale"></textarea>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="update_inventory" checked>
                        Update product inventory (reduce quantity)
                    </label>
                </div>
                
                <div id="profit-preview" style="background: #e8f5e8; padding: 10px; border-radius: 4px; margin: 10px 0; display: none;">
                    <strong>Profit Preview: GHS <span id="profit-amount">0.00</span></strong>
                </div>
                
                <button type="submit">Record Sale</button>
            </form>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($recentSales)): ?>
    <div class="container">
        <h2>Recent Sales</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Product</th>
                    <th>Vendor</th>
                    <th>Qty</th>
                    <th>Sale Price</th>
                    <th>Profit</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSales as $sale): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($sale['sale_date'])); ?></td>
                    <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($sale['vendor_name'] ?: 'No Vendor'); ?></td>
                    <td><?php echo $sale['quantity_sold']; ?></td>
                    <td>GHS <?php echo number_format($sale['sale_price'], 2); ?></td>
                    <td class="profit">GHS <?php echo number_format($sale['actual_profit'], 2); ?></td>
                    <td><?php echo htmlspecialchars($sale['notes'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <h3>API Information</h3>
        <p><strong>Endpoint:</strong> api/sales.php</p>
        <p><strong>Session User ID:</strong> <?php echo $_SESSION['user_id']; ?></p>
        <p><strong>Available Products:</strong> <?php echo count($products); ?></p>
        <p><strong>Recent Sales:</strong> <?php echo count($recentSales); ?></p>
        
        <p><a href="dashboard.html">← Back to Dashboard</a></p>
    </div>
    
    <script>
        function updateProductInfo() {
            const select = document.getElementById('product_id');
            const option = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('product-info');
            
            if (option.value) {
                document.getElementById('cost-price').textContent = parseFloat(option.dataset.price).toFixed(2);
                document.getElementById('suggested-price').textContent = parseFloat(option.dataset.selling).toFixed(2);
                document.getElementById('available-qty').textContent = option.dataset.quantity;
                document.getElementById('sale_price').value = parseFloat(option.dataset.selling).toFixed(2);
                document.getElementById('quantity_sold').max = option.dataset.quantity;
                infoDiv.style.display = 'block';
                calculateProfit();
            } else {
                infoDiv.style.display = 'none';
                document.getElementById('profit-preview').style.display = 'none';
            }
        }
        
        function calculateProfit() {
            const select = document.getElementById('product_id');
            const option = select.options[select.selectedIndex];
            const quantity = parseInt(document.getElementById('quantity_sold').value) || 0;
            const salePrice = parseFloat(document.getElementById('sale_price').value) || 0;
            
            if (option.value && quantity > 0 && salePrice > 0) {
                const costPrice = parseFloat(option.dataset.price);
                const profit = (salePrice - costPrice) * quantity;
                
                document.getElementById('profit-amount').textContent = profit.toFixed(2);
                document.getElementById('profit-preview').style.display = 'block';
                
                // Update profit color based on value
                const profitElement = document.getElementById('profit-amount');
                if (profit > 0) {
                    profitElement.style.color = '#28a745';
                } else if (profit < 0) {
                    profitElement.style.color = '#dc3545';
                } else {
                    profitElement.style.color = '#6c757d';
                }
            } else {
                document.getElementById('profit-preview').style.display = 'none';
            }
        }
    </script>
</body>
</html>
