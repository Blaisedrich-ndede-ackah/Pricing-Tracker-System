<?php
/**
 * Sales API - Handle sales recording and tracking
 */

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    debugLog("Sales API called", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'session' => $_SESSION ?? 'No session',
        'user_id' => $_SESSION['user_id'] ?? 'not set',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ]);
    
    requireAuth();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Handle both JSON and form data
    $input = null;
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if ($method === 'POST') {
        if (strpos($contentType, 'application/json') !== false) {
            // JSON data
            $rawInput = file_get_contents('php://input');
            $input = json_decode($rawInput, true);
            debugLog("JSON input received", ['raw' => $rawInput, 'parsed' => $input]);
        } else {
            // Form data
            $input = $_POST;
            debugLog("Form data received", ['post' => $_POST]);
        }
    } else {
        // For PUT/DELETE, always expect JSON
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        debugLog("Raw input for $method", ['raw' => $rawInput, 'parsed' => $input]);
    }

    debugLog("Sales API processed input", [
        'method' => $method,
        'input' => $input,
        'action' => $action
    ]);

    switch ($method) {
        case 'GET':
            if ($action === 'stats') {
                handleGetSalesStats();
            } else {
                handleGetSales();
            }
            break;
        case 'POST':
            handleCreateSale($input);
            break;
        case 'PUT':
            handleUpdateSale($input);
            break;
        case 'DELETE':
            handleDeleteSale($input);
            break;
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    debugLog("Sales API error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

function handleCreateSale($data) {
    try {
        debugLog("Creating sale - raw data", ['data' => $data]);
        
        // Check if data is null or empty
        if (!$data || !is_array($data)) {
            debugLog("Sale data is null or not array", ['data' => $data]);
            sendResponse(['error' => 'No sale data received'], 400);
        }
        
        // Handle checkbox values from forms
        if (isset($data['update_inventory'])) {
            $data['update_inventory'] = ($data['update_inventory'] === 'on' || $data['update_inventory'] === '1' || $data['update_inventory'] === true);
        }
        
        // Detailed validation with specific error messages
        $validationErrors = [];
        
        // Check product_id
        if (!isset($data['product_id']) || empty($data['product_id'])) {
            $validationErrors[] = 'Product ID is missing';
        } elseif (!is_numeric($data['product_id']) || $data['product_id'] <= 0) {
            $validationErrors[] = 'Product ID must be a positive number';
        }
        
        // Check quantity_sold
        if (!isset($data['quantity_sold']) || $data['quantity_sold'] === '' || $data['quantity_sold'] === null) {
            $validationErrors[] = 'Quantity sold is missing';
        } elseif (!is_numeric($data['quantity_sold']) || $data['quantity_sold'] <= 0) {
            $validationErrors[] = 'Quantity sold must be greater than 0';
        }
        
        // Check sale_price
        if (!isset($data['sale_price']) || $data['sale_price'] === '' || $data['sale_price'] === null) {
            $validationErrors[] = 'Sale price is missing';
        } elseif (!is_numeric($data['sale_price']) || $data['sale_price'] <= 0) {
            $validationErrors[] = 'Sale price must be greater than 0';
        }
        
        // Check sale_date (optional, but if provided must be valid)
        if (isset($data['sale_date']) && !empty($data['sale_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $data['sale_date']);
            if (!$date || $date->format('Y-m-d') !== $data['sale_date']) {
                $validationErrors[] = 'Sale date must be in YYYY-MM-DD format';
            }
        }
        
        if (!empty($validationErrors)) {
            debugLog("Sale validation failed", [
                'errors' => $validationErrors,
                'data' => $data,
                'field_checks' => [
                    'product_id_isset' => isset($data['product_id']),
                    'product_id_value' => $data['product_id'] ?? 'NOT SET',
                    'quantity_sold_isset' => isset($data['quantity_sold']),
                    'quantity_sold_value' => $data['quantity_sold'] ?? 'NOT SET',
                    'sale_price_isset' => isset($data['sale_price']),
                    'sale_price_value' => $data['sale_price'] ?? 'NOT SET'
                ]
            ]);
            sendResponse([
                'error' => 'Validation failed: ' . implode(', ', $validationErrors),
                'validation_errors' => $validationErrors,
                'received_data' => $data
            ], 400);
        }
        
        $pdo = getDBConnection();
        
        // Get product details and verify ownership
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['product_id'], $_SESSION['user_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            debugLog("Product not found", [
                'product_id' => $data['product_id'],
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'Product not found or you do not have permission to sell this product'], 404);
        }
        
        // Validate quantity available
        $quantitySold = (int)$data['quantity_sold'];
        $salePrice = (float)$data['sale_price'];
        
        if ($quantitySold > $product['quantity']) {
            debugLog("Insufficient quantity", [
                'requested' => $quantitySold,
                'available' => $product['quantity']
            ]);
            sendResponse(['error' => "Insufficient quantity. Only {$product['quantity']} items available"], 400);
        }
        
        // Calculate actual profit
        $actualProfit = ($salePrice - $product['actual_price']) * $quantitySold;
        $saleDate = !empty($data['sale_date']) ? $data['sale_date'] : date('Y-m-d');
        
        debugLog("Sale calculations", [
            'quantity_sold' => $quantitySold,
            'sale_price' => $salePrice,
            'product_cost' => $product['actual_price'],
            'actual_profit' => $actualProfit,
            'sale_date' => $saleDate
        ]);
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert sale record
            $stmt = $pdo->prepare("
                INSERT INTO sales (user_id, product_id, quantity_sold, sale_price, actual_profit, sale_date, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([
                $_SESSION['user_id'],
                $data['product_id'],
                $quantitySold,
                $salePrice,
                $actualProfit,
                $saleDate,
                !empty($data['notes']) ? trim($data['notes']) : null
            ]);
            
            if (!$result) {
                throw new Exception("Failed to insert sale record");
            }
            
            $saleId = $pdo->lastInsertId();
            
            // Update product quantity if requested
            if (!empty($data['update_inventory']) && $data['update_inventory']) {
                $newQuantity = $product['quantity'] - $quantitySold;
                $newStatus = $newQuantity <= 0 ? 'sold' : $product['status'];
                
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET quantity = ?, status = ?, updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $updateResult = $stmt->execute([
                    $newQuantity, 
                    $newStatus, 
                    $data['product_id'], 
                    $_SESSION['user_id']
                ]);
                
                if (!$updateResult) {
                    throw new Exception("Failed to update product inventory");
                }
                
                debugLog("Product inventory updated", [
                    'product_id' => $data['product_id'],
                    'old_quantity' => $product['quantity'],
                    'new_quantity' => $newQuantity,
                    'new_status' => $newStatus
                ]);
            }
            
            $pdo->commit();
            
            debugLog("Sale recorded successfully", [
                'sale_id' => $saleId,
                'product_id' => $data['product_id'],
                'quantity_sold' => $quantitySold,
                'actual_profit' => $actualProfit
            ]);
            
            // Return success response - handle both form and AJAX requests
            $response = [
                'success' => true,
                'message' => 'Sale recorded successfully',
                'sale_id' => $saleId,
                'actual_profit' => $actualProfit,
                'inventory_updated' => !empty($data['update_inventory'])
            ];
            
            // If this was a form submission, redirect back with success message
            if (!empty($_POST) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                // Form submission - redirect with success message
                header('Location: ../debug-sales.php?success=' . urlencode($response['message']));
                exit;
            } else {
                // AJAX request - return JSON
                sendResponse($response);
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            debugLog("Sale transaction failed", ['error' => $e->getMessage()]);
            throw $e;
        }
        
    } catch (PDOException $e) {
        debugLog("Create sale PDO error", [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'data' => $data
        ]);
        
        $errorMsg = 'Database error: ' . $e->getMessage();
        
        // Handle form vs AJAX response
        if (!empty($_POST) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Location: ../debug-sales.php?error=' . urlencode($errorMsg));
            exit;
        } else {
            sendResponse(['error' => $errorMsg], 500);
        }
    } catch (Exception $e) {
        debugLog("Create sale general error", [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        
        $errorMsg = 'Failed to record sale: ' . $e->getMessage();
        
        // Handle form vs AJAX response
        if (!empty($_POST) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Location: ../debug-sales.php?error=' . urlencode($errorMsg));
            exit;
        } else {
            sendResponse(['error' => $errorMsg], 500);
        }
    }
}

function handleGetSales() {
    try {
        $pdo = getDBConnection();
        
        debugLog("Getting sales for user", ['user_id' => $_SESSION['user_id']]);
        
        $stmt = $pdo->prepare("
            SELECT 
                s.id, s.quantity_sold, s.sale_price, s.actual_profit, 
                s.sale_date, s.notes, s.created_at,
                p.product_name, p.actual_price, p.selling_price,
                COALESCE(v.vendor_name, 'No Vendor') as vendor_name
            FROM sales s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN vendors v ON p.vendor_id = v.id
            WHERE s.user_id = ?
            ORDER BY s.sale_date DESC, s.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure proper data types
        foreach ($sales as &$sale) {
            $sale['id'] = (int)$sale['id'];
            $sale['quantity_sold'] = (int)$sale['quantity_sold'];
            $sale['sale_price'] = (float)$sale['sale_price'];
            $sale['actual_profit'] = (float)$sale['actual_profit'];
            $sale['actual_price'] = (float)$sale['actual_price'];
            $sale['selling_price'] = (float)$sale['selling_price'];
        }
        
        debugLog("Sales fetched successfully", [
            'count' => count($sales),
            'user_id' => $_SESSION['user_id']
        ]);
        
        sendResponse(['sales' => $sales]);
        
    } catch (PDOException $e) {
        debugLog("Get sales PDO error", [
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Get sales general error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Failed to fetch sales: ' . $e->getMessage()], 500);
    }
}

function handleGetSalesStats() {
    try {
        $pdo = getDBConnection();
        
        debugLog("Getting sales stats for user", ['user_id' => $_SESSION['user_id']]);
        
        // Overall statistics
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(COUNT(*), 0) as total_sales,
                COALESCE(SUM(quantity_sold), 0) as total_items_sold,
                COALESCE(SUM(actual_profit), 0) as total_profit,
                COALESCE(AVG(actual_profit), 0) as avg_profit_per_sale,
                MIN(sale_date) as first_sale_date,
                MAX(sale_date) as last_sale_date
            FROM sales 
            WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $overallStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Monthly statistics for current year
        $currentYear = date('Y');
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(sale_date) as month,
                COUNT(*) as sales_count,
                SUM(actual_profit) as monthly_profit
            FROM sales 
            WHERE user_id = ? AND YEAR(sale_date) = ?
            GROUP BY MONTH(sale_date)
            ORDER BY MONTH(sale_date)
        ");
        $stmt->execute([$_SESSION['user_id'], $currentYear]);
        $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Top selling products
        $stmt = $pdo->prepare("
            SELECT 
                p.product_name,
                SUM(s.quantity_sold) as total_sold,
                SUM(s.actual_profit) as total_profit
            FROM sales s
            JOIN products p ON s.product_id = p.id
            WHERE s.user_id = ?
            GROUP BY p.id, p.product_name
            ORDER BY total_sold DESC
            LIMIT 10
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        debugLog("Sales stats generated successfully");
        
        sendResponse([
            'overall_stats' => $overallStats,
            'monthly_stats' => $monthlyStats,
            'top_products' => $topProducts
        ]);
        
    } catch (PDOException $e) {
        debugLog("Get sales stats PDO error", [
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Get sales stats general error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Failed to fetch sales statistics: ' . $e->getMessage()], 500);
    }
}

function handleUpdateSale($data) {
    try {
        debugLog("Updating sale", ['data' => $data]);
        
        if (!isset($data['id'])) {
            sendResponse(['error' => 'Sale ID is required for updates'], 400);
        }
        
        // Validate required fields for update
        $validationErrors = [];
        
        if (!isset($data['quantity_sold']) || !is_numeric($data['quantity_sold']) || $data['quantity_sold'] <= 0) {
            $validationErrors[] = 'Quantity sold must be greater than 0';
        }
        
        if (!isset($data['sale_price']) || !is_numeric($data['sale_price']) || $data['sale_price'] <= 0) {
            $validationErrors[] = 'Sale price must be greater than 0';
        }
        
        if (!empty($validationErrors)) {
            sendResponse([
                'error' => 'Validation failed: ' . implode(', ', $validationErrors),
                'validation_errors' => $validationErrors
            ], 400);
        }
        
        $pdo = getDBConnection();
        
        // Get product details for profit calculation
        $stmt = $pdo->prepare("
            SELECT p.* FROM products p 
            JOIN sales s ON p.id = s.product_id 
            WHERE s.id = ? AND s.user_id = ?
        ");
        $stmt->execute([$data['id'], $_SESSION['user_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            sendResponse(['error' => 'Sale not found or unauthorized'], 404);
        }
        
        // Calculate actual profit
        $quantitySold = (int)$data['quantity_sold'];
        $salePrice = (float)$data['sale_price'];
        $actualProfit = ($salePrice - $product['actual_price']) * $quantitySold;
        
        $stmt = $pdo->prepare("
            UPDATE sales 
            SET quantity_sold = ?, sale_price = ?, actual_profit = ?, sale_date = ?, notes = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $result = $stmt->execute([
            $quantitySold,
            $salePrice,
            $actualProfit,
            !empty($data['sale_date']) ? $data['sale_date'] : date('Y-m-d'),
            !empty($data['notes']) ? trim($data['notes']) : null,
            (int)$data['id'],
            $_SESSION['user_id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            debugLog("Sale updated successfully", ['id' => $data['id']]);
            sendResponse([
                'success' => true, 
                'message' => 'Sale updated successfully',
                'actual_profit' => $actualProfit
            ]);
        } else {
            debugLog("Sale update failed - no rows affected", ['id' => $data['id']]);
            sendResponse(['error' => 'Sale not found or no changes made'], 404);
        }
        
    } catch (PDOException $e) {
        debugLog("Update sale PDO error", [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Update sale general error", [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        sendResponse(['error' => 'Failed to update sale: ' . $e->getMessage()], 500);
    }
}

function handleDeleteSale($data) {
    try {
        if (!isset($data['id'])) {
            sendResponse(['error' => 'Sale ID required'], 400);
        }
        
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("DELETE FROM sales WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([(int)$data['id'], $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            debugLog("Sale deleted successfully", ['id' => $data['id']]);
            sendResponse(['success' => true, 'message' => 'Sale deleted successfully']);
        } else {
            debugLog("Sale delete failed - not found", ['id' => $data['id']]);
            sendResponse(['error' => 'Sale not found or unauthorized'], 404);
        }
        
    } catch (PDOException $e) {
        debugLog("Delete sale PDO error", [
            'error' => $e->getMessage(),
            'id' => $data['id'] ?? 'not set'
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Delete sale general error", [
            'error' => $e->getMessage(),
            'id' => $data['id'] ?? 'not set'
        ]);
        sendResponse(['error' => 'Failed to delete sale: ' . $e->getMessage()], 500);
    }
}
?>
