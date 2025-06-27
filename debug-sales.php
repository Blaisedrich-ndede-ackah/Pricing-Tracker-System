<?php
session_start();

// Simulate logged in user
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
}

// Show success/error messages from form submission
$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    $message = $_GET['success'];
    $messageType = 'success';
} elseif (isset($_GET['error'])) {
    $message = $_GET['error'];
    $messageType = 'error';
}

echo "<h1>Sales API Debug</h1>";

// Show message if any
if ($message) {
    $color = $messageType === 'success' ? 'green' : 'red';
    echo "<div style='padding: 10px; margin: 10px 0; border: 1px solid $color; background: " . ($messageType === 'success' ? '#d4edda' : '#f8d7da') . "; color: $color; border-radius: 4px;'>";
    echo "<strong>" . ucfirst($messageType) . ":</strong> " . htmlspecialchars($message);
    echo "</div>";
}

// Test database connection and show available products
try {
    $host = 'localhost';
    $dbname = 'pricing_tracker';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<h2>Available Products</h2>";
    
    $stmt = $pdo->prepare("SELECT id, product_name, actual_price, selling_price, quantity, status FROM products WHERE user_id = ? ORDER BY product_name");
    $stmt->execute([$_SESSION['user_id']]);
    $products = $stmt->fetchAll();
    
    if ($products) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th><th>Product Name</th><th>Cost</th><th>Selling Price</th><th>Quantity</th><th>Status</th>";
        echo "</tr>";
        
        foreach ($products as $product) {
            $rowColor = $product['quantity'] > 0 ? '#ffffff' : '#ffeeee';
            echo "<tr style='background: $rowColor;'>";
            echo "<td>{$product['id']}</td>";
            echo "<td>" . htmlspecialchars($product['product_name']) . "</td>";
            echo "<td>GHS " . number_format($product['actual_price'], 2) . "</td>";
            echo "<td>GHS " . number_format($product['selling_price'], 2) . "</td>";
            echo "<td>{$product['quantity']}</td>";
            echo "<td>" . ucfirst($product['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No products found. Please add some products first.</p>";
        echo "<p><a href='dashboard.html'>Go to Dashboard to Add Products</a></p>";
    }
    
    // Show recent sales
    echo "<h2>Recent Sales</h2>";
    
    $stmt = $pdo->prepare("
        SELECT s.*, p.product_name 
        FROM sales s 
        JOIN products p ON s.product_id = p.id 
        WHERE s.user_id = ? 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $sales = $stmt->fetchAll();
    
    if ($sales) {
        echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse; margin-bottom: 20px;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>Date</th><th>Product</th><th>Quantity</th><th>Sale Price</th><th>Profit</th>";
        echo "</tr>";
        
        foreach ($sales as $sale) {
            echo "<tr>";
            echo "<td>{$sale['sale_date']}</td>";
            echo "<td>" . htmlspecialchars($sale['product_name']) . "</td>";
            echo "<td>{$sale['quantity_sold']}</td>";
            echo "<td>GHS " . number_format($sale['sale_price'], 2) . "</td>";
            echo "<td>GHS " . number_format($sale['actual_profit'], 2) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No sales recorded yet.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Record New Sale</h3>";
?>

<form method="POST" action="api/sales.php" style="max-width: 400px; border: 1px solid #ddd; padding: 20px; border-radius: 8px;">
    <div style="margin-bottom: 15px;">
        <label for="product_id"><strong>Product:</strong></label><br>
        <select name="product_id" id="product_id" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <option value="">Select a product...</option>
            <?php
            if (isset($products)) {
                foreach ($products as $product) {
                    if ($product['quantity'] > 0) {
                        echo "<option value='{$product['id']}' data-cost='{$product['actual_price']}' data-price='{$product['selling_price']}' data-qty='{$product['quantity']}'>";
                        echo htmlspecialchars($product['product_name']) . " (Qty: {$product['quantity']}, Suggested: GHS " . number_format($product['selling_price'], 2) . ")";
                        echo "</option>";
                    }
                }
            }
            ?>
        </select>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="quantity_sold"><strong>Quantity Sold:</strong></label><br>
        <input type="number" name="quantity_sold" id="quantity_sold" value="1" min="1" required 
               style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <small id="qty-info" style="color: #666;"></small>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="sale_price"><strong>Sale Price (per item):</strong></label><br>
        <input type="number" name="sale_price" id="sale_price" step="0.01" min="0.01" required 
               style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <small id="price-info" style="color: #666;"></small>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="sale_date"><strong>Sale Date:</strong></label><br>
        <input type="date" name="sale_date" id="sale_date" value="<?php echo date('Y-m-d'); ?>" required 
               style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label for="notes"><strong>Notes (optional):</strong></label><br>
        <textarea name="notes" id="notes" rows="3" 
                  style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" 
                  placeholder="Add any notes about this sale..."></textarea>
    </div>
    
    <div style="margin-bottom: 15px;">
        <label>
            <input type="checkbox" name="update_inventory" checked> 
            <strong>Update Inventory</strong> (reduce product quantity)
        </label>
    </div>
    
    <div id="profit-preview" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; display: none;">
        <strong>Profit Preview:</strong> <span id="profit-amount">GHS 0.00</span>
    </div>
    
    <button type="submit" style="background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; width: 100%;">
        Record Sale
    </button>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity_sold');
    const priceInput = document.getElementById('sale_price');
    const qtyInfo = document.getElementById('qty-info');
    const priceInfo = document.getElementById('price-info');
    const profitPreview = document.getElementById('profit-preview');
    const profitAmount = document.getElementById('profit-amount');
    
    function updateInfo() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (selectedOption.value) {
            const cost = parseFloat(selectedOption.dataset.cost);
            const suggestedPrice = parseFloat(selectedOption.dataset.price);
            const maxQty = parseInt(selectedOption.dataset.qty);
            
            // Update quantity info
            qtyInfo.textContent = `Maximum available: ${maxQty}`;
            quantityInput.max = maxQty;
            
            // Set suggested price
            if (!priceInput.value) {
                priceInput.value = suggestedPrice.toFixed(2);
            }
            
            // Update price info
            priceInfo.textContent = `Product cost: GHS ${cost.toFixed(2)}, Suggested: GHS ${suggestedPrice.toFixed(2)}`;
            
            updateProfitPreview();
        } else {
            qtyInfo.textContent = '';
            priceInfo.textContent = '';
            profitPreview.style.display = 'none';
        }
    }
    
    function updateProfitPreview() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (selectedOption.value && quantityInput.value && priceInput.value) {
            const cost = parseFloat(selectedOption.dataset.cost);
            const quantity = parseInt(quantityInput.value);
            const salePrice = parseFloat(priceInput.value);
            
            const profit = (salePrice - cost) * quantity;
            profitAmount.textContent = `GHS ${profit.toFixed(2)}`;
            profitAmount.style.color = profit >= 0 ? 'green' : 'red';
            profitPreview.style.display = 'block';
        } else {
            profitPreview.style.display = 'none';
        }
    }
    
    productSelect.addEventListener('change', updateInfo);
    quantityInput.addEventListener('input', updateProfitPreview);
    priceInput.addEventListener('input', updateProfitPreview);
});

// Test with JavaScript/AJAX
function testSalesAPI() {
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    const data = {
        product_id: formData.get('product_id'),
        quantity_sold: formData.get('quantity_sold'),
        sale_price: formData.get('sale_price'),
        sale_date: formData.get('sale_date'),
        notes: formData.get('notes'),
        update_inventory: formData.get('update_inventory') ? true : false
    };
    
    fetch('api/sales.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        console.log('Sales API Result:', result);
        if (result.success) {
            alert('Success: ' + result.message + '\nProfit: GHS ' + result.actual_profit.toFixed(2));
            location.reload();
        } else {
            alert('Error: ' + (result.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Sales API Error:', error);
        alert('Network Error: ' + error.message);
    });
}
</script>

<button onclick="testSalesAPI()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; margin-top: 10px;">
    Test with AJAX
</button>

<p><a href="dashboard.html">← Back to Dashboard</a></p>
