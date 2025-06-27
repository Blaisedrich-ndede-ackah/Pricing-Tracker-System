<?php
/**
 * Products API Endpoints
 * Handles CRUD operations for products
 */

require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    debugLog("Products API called", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'session' => $_SESSION ?? 'No session',
        'user_id' => $_SESSION['user_id'] ?? 'not set'
    ]);
    
    // Require authentication for all product operations
    requireAuth();

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    debugLog("Products API input", [
        'method' => $method,
        'input' => $input
    ]);

    switch ($method) {
        case 'GET':
            handleGetProducts();
            break;
        case 'POST':
            handleCreateProduct($input);
            break;
        case 'PUT':
            handleUpdateProduct($input);
            break;
        case 'DELETE':
            handleDeleteProduct($input);
            break;
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    debugLog("Products API error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

/**
 * Get all products for the authenticated user
 */
function handleGetProducts() {
    try {
        $pdo = getDBConnection();
        
        debugLog("Getting products for user", ['user_id' => $_SESSION['user_id']]);
        
        // Get filter parameters
        $search = $_GET['search'] ?? '';
        $vendor = $_GET['vendor'] ?? '';
        $status = $_GET['status'] ?? '';
        $minPrice = $_GET['min_price'] ?? '';
        $maxPrice = $_GET['max_price'] ?? '';
        $minProfit = $_GET['min_profit'] ?? '';
        $maxProfit = $_GET['max_profit'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo = $_GET['date_to'] ?? '';
        
        // Base query with proper field handling
        $sql = "
            SELECT 
                p.id, 
                p.product_name, 
                p.actual_price, 
                COALESCE(p.markup_percentage, 0) as markup_percentage, 
                COALESCE(p.selling_price, p.actual_price) as selling_price, 
                COALESCE(p.profit, 0) as profit, 
                COALESCE(p.quantity, 1) as quantity, 
                COALESCE(p.total_profit, 0) as total_profit,
                p.product_url, 
                p.product_image, 
                p.notes, 
                COALESCE(p.date_added, CURDATE()) as date_added, 
                COALESCE(p.status, 'active') as status, 
                p.created_at, 
                p.updated_at,
                v.vendor_name, 
                p.vendor_id
            FROM products p
            LEFT JOIN vendors v ON p.vendor_id = v.id
            WHERE p.user_id = ?
        ";
        
        $params = [$_SESSION['user_id']];
        
        // Add filters
        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE ? OR COALESCE(p.notes, '') LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        if (!empty($vendor)) {
            $sql .= " AND p.vendor_id = ?";
            $params[] = $vendor;
        }
        
        if (!empty($status)) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if (!empty($minPrice) && is_numeric($minPrice)) {
            $sql .= " AND p.actual_price >= ?";
            $params[] = $minPrice;
        }
        
        if (!empty($maxPrice) && is_numeric($maxPrice)) {
            $sql .= " AND p.actual_price <= ?";
            $params[] = $maxPrice;
        }
        
        if (!empty($minProfit) && is_numeric($minProfit)) {
            $sql .= " AND p.total_profit >= ?";
            $params[] = $minProfit;
        }
        
        if (!empty($maxProfit) && is_numeric($maxProfit)) {
            $sql .= " AND p.total_profit <= ?";
            $params[] = $maxProfit;
        }
        
        if (!empty($dateFrom)) {
            $sql .= " AND p.date_added >= ?";
            $params[] = $dateFrom;
        }
        
        if (!empty($dateTo)) {
            $sql .= " AND p.date_added <= ?";
            $params[] = $dateTo;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        debugLog("Products query", ['sql' => $sql, 'params' => $params]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure all required fields exist with proper defaults
        foreach ($products as &$product) {
            $product['id'] = (int)$product['id'];
            $product['quantity'] = (int)($product['quantity'] ?? 1);
            $product['markup_percentage'] = (float)($product['markup_percentage'] ?? 0);
            $product['actual_price'] = (float)($product['actual_price'] ?? 0);
            $product['selling_price'] = (float)($product['selling_price'] ?? $product['actual_price']);
            $product['profit'] = (float)($product['profit'] ?? 0);
            $product['total_profit'] = (float)($product['total_profit'] ?? 0);
            $product['date_added'] = $product['date_added'] ?? date('Y-m-d');
            $product['status'] = $product['status'] ?? 'active';
            $product['product_image'] = $product['product_image'] ?? null;
            $product['notes'] = $product['notes'] ?? null;
            $product['vendor_name'] = $product['vendor_name'] ?? null;
            $product['vendor_id'] = $product['vendor_id'] ? (int)$product['vendor_id'] : null;
            $product['product_url'] = $product['product_url'] ?? null;
        }
        
        debugLog("Products fetched successfully", [
            'count' => count($products),
            'user_id' => $_SESSION['user_id']
        ]);
        
        sendResponse(['products' => $products]);
        
    } catch (PDOException $e) {
        debugLog("Get products PDO error", [
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Get products general error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Failed to fetch products: ' . $e->getMessage()], 500);
    }
}

/**
 * Create a new product
 */
function handleCreateProduct($data) {
    try {
        debugLog("Creating product", ['data' => $data]);
        
        if (!validateProductData($data)) {
            debugLog("Product validation failed", ['data' => $data]);
            sendResponse(['error' => 'Invalid product data. Please check all required fields.'], 400);
        }
        
        $pdo = getDBConnection();
        
        // Calculate values with proper type casting
        $quantity = max(1, (int)($data['quantity'] ?? 1));
        $markupPercentage = (float)($data['markup_percentage'] ?? 0);
        $actualPrice = (float)$data['actual_price'];
        $sellingPrice = $actualPrice * (1 + $markupPercentage / 100);
        $profit = $sellingPrice - $actualPrice;
        $totalProfit = $profit * $quantity;
        $dateAdded = !empty($data['date_added']) ? $data['date_added'] : date('Y-m-d');
        
        // Prepare vendor_id (can be null)
        $vendorId = !empty($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        
        debugLog("Calculated product values", [
            'quantity' => $quantity,
            'markup_percentage' => $markupPercentage,
            'actual_price' => $actualPrice,
            'selling_price' => $sellingPrice,
            'profit' => $profit,
            'total_profit' => $totalProfit,
            'vendor_id' => $vendorId
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO products (
                user_id, vendor_id, product_name, actual_price, markup_percentage, 
                selling_price, profit, quantity, total_profit, product_url, 
                product_image, notes, date_added, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $vendorId,
            trim($data['product_name']),
            $actualPrice,
            $markupPercentage,
            $sellingPrice,
            $profit,
            $quantity,
            $totalProfit,
            !empty($data['product_url']) ? trim($data['product_url']) : null,
            !empty($data['product_image']) ? trim($data['product_image']) : null,
            !empty($data['notes']) ? trim($data['notes']) : null,
            $dateAdded,
            !empty($data['status']) ? $data['status'] : 'active'
        ]);
        
        if ($result) {
            $productId = $pdo->lastInsertId();
            debugLog("Product created successfully", [
                'product_id' => $productId,
                'product_name' => $data['product_name']
            ]);
            
            sendResponse([
                'success' => true,
                'message' => 'Product created successfully',
                'product_id' => $productId
            ]);
        } else {
            debugLog("Product creation failed - execute returned false");
            sendResponse(['error' => 'Failed to create product'], 500);
        }
        
    } catch (PDOException $e) {
        debugLog("Create product PDO error", [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'data' => $data
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Create product general error", [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        sendResponse(['error' => 'Failed to create product: ' . $e->getMessage()], 500);
    }
}

/**
 * Update an existing product
 */
function handleUpdateProduct($data) {
    try {
        debugLog("Updating product", ['data' => $data]);
        
        if (!isset($data['id']) || !validateProductData($data)) {
            sendResponse(['error' => 'Invalid product data or missing ID'], 400);
        }
        
        $pdo = getDBConnection();
        
        // Calculate values
        $quantity = max(1, (int)($data['quantity'] ?? 1));
        $markupPercentage = (float)($data['markup_percentage'] ?? 0);
        $actualPrice = (float)$data['actual_price'];
        $sellingPrice = $actualPrice * (1 + $markupPercentage / 100);
        $profit = $sellingPrice - $actualPrice;
        $totalProfit = $profit * $quantity;
        
        $vendorId = !empty($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        
        $stmt = $pdo->prepare("
            UPDATE products 
            SET vendor_id = ?, product_name = ?, actual_price = ?, markup_percentage = ?, 
                selling_price = ?, profit = ?, quantity = ?, total_profit = ?, 
                product_url = ?, product_image = ?, notes = ?, date_added = ?, 
                status = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND user_id = ?
        ");
        
        $result = $stmt->execute([
            $vendorId,
            trim($data['product_name']),
            $actualPrice,
            $markupPercentage,
            $sellingPrice,
            $profit,
            $quantity,
            $totalProfit,
            !empty($data['product_url']) ? trim($data['product_url']) : null,
            !empty($data['product_image']) ? trim($data['product_image']) : null,
            !empty($data['notes']) ? trim($data['notes']) : null,
            !empty($data['date_added']) ? $data['date_added'] : date('Y-m-d'),
            !empty($data['status']) ? $data['status'] : 'active',
            (int)$data['id'],
            $_SESSION['user_id']
        ]);
        
        if ($stmt->rowCount() > 0) {
            debugLog("Product updated successfully", ['id' => $data['id']]);
            sendResponse(['success' => true, 'message' => 'Product updated successfully']);
        } else {
            debugLog("Product update failed - no rows affected", ['id' => $data['id']]);
            sendResponse(['error' => 'Product not found or no changes made'], 404);
        }
        
    } catch (PDOException $e) {
        debugLog("Update product PDO error", [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Update product general error", [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        sendResponse(['error' => 'Failed to update product: ' . $e->getMessage()], 500);
    }
}

/**
 * Delete a product
 */
function handleDeleteProduct($data) {
    try {
        if (!isset($data['id'])) {
            sendResponse(['error' => 'Product ID required'], 400);
        }
        
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([(int)$data['id'], $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            debugLog("Product deleted successfully", ['id' => $data['id']]);
            sendResponse(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            debugLog("Product delete failed - not found", ['id' => $data['id']]);
            sendResponse(['error' => 'Product not found or unauthorized'], 404);
        }
        
    } catch (PDOException $e) {
        debugLog("Delete product PDO error", [
            'error' => $e->getMessage(),
            'id' => $data['id'] ?? 'not set'
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Delete product general error", [
            'error' => $e->getMessage(),
            'id' => $data['id'] ?? 'not set'
        ]);
        sendResponse(['error' => 'Failed to delete product: ' . $e->getMessage()], 500);
    }
}

/**
 * Validate product data
 */
function validateProductData($data) {
    $errors = [];
    
    // Check required fields
    if (!isset($data['product_name']) || empty(trim($data['product_name']))) {
        $errors[] = 'Product name is required';
    }
    
    if (!isset($data['actual_price']) || !is_numeric($data['actual_price']) || $data['actual_price'] <= 0) {
        $errors[] = 'Valid actual price is required (must be greater than 0)';
    }
    
    if (!isset($data['markup_percentage']) || !is_numeric($data['markup_percentage']) || $data['markup_percentage'] < 0) {
        $errors[] = 'Valid markup percentage is required (must be 0 or greater)';
    }
    
    // Optional field validation
    if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 1)) {
        $errors[] = 'Quantity must be at least 1';
    }
    
    if (isset($data['vendor_id']) && !empty($data['vendor_id']) && !is_numeric($data['vendor_id'])) {
        $errors[] = 'Invalid vendor ID';
    }
    
    if (!empty($errors)) {
        debugLog("Product validation errors", ['errors' => $errors, 'data' => $data]);
        return false;
    }
    
    return true;
}
?>
