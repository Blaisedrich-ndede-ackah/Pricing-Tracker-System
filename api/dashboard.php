<?php
/**
 * Dashboard API Endpoints
 * Provides dashboard statistics and summary data
 */

require_once 'config.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

try {
    debugLog("Dashboard API called", [
        'session' => $_SESSION ?? 'No session',
        'user_id' => $_SESSION['user_id'] ?? 'not set'
    ]);
    
    // Require authentication
    requireAuth();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendResponse(['error' => 'Method not allowed'], 405);
    }
    
    $pdo = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Get product statistics
    $productStats = getProductStats($pdo, $userId);
    
    // Get sales statistics
    $salesStats = getSalesStats($pdo, $userId);
    
    // Get vendor statistics
    $vendorStats = getVendorStats($pdo, $userId);
    
    // Get recent activity
    $recentActivity = getRecentActivity($pdo, $userId);
    
    debugLog("Dashboard data compiled successfully", [
        'user_id' => $userId,
        'product_count' => $productStats['total_products'] ?? 0
    ]);
    
    sendResponse([
        'product_stats' => $productStats,
        'sales_stats' => $salesStats,
        'vendor_stats' => $vendorStats,
        'recent_activity' => $recentActivity
    ]);
    
} catch (Exception $e) {
    debugLog("Dashboard API error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

/**
 * Get product statistics
 */
function getProductStats($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
                COUNT(CASE WHEN status = 'sold' THEN 1 END) as sold_products,
                COUNT(CASE WHEN status = 'discontinued' THEN 1 END) as discontinued_products,
                COALESCE(SUM(actual_price * quantity), 0) as total_investment,
                COALESCE(SUM(total_profit), 0) as total_potential_profit,
                COALESCE(AVG(markup_percentage), 0) as avg_markup_percentage
            FROM products 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Convert to proper types
        $stats['total_products'] = (int)$stats['total_products'];
        $stats['active_products'] = (int)$stats['active_products'];
        $stats['sold_products'] = (int)$stats['sold_products'];
        $stats['discontinued_products'] = (int)$stats['discontinued_products'];
        $stats['total_investment'] = (float)$stats['total_investment'];
        $stats['total_potential_profit'] = (float)$stats['total_potential_profit'];
        $stats['avg_markup_percentage'] = (float)$stats['avg_markup_percentage'];
        
        return $stats;
        
    } catch (PDOException $e) {
        debugLog("Product stats error", ['error' => $e->getMessage()]);
        return [
            'total_products' => 0,
            'active_products' => 0,
            'sold_products' => 0,
            'discontinued_products' => 0,
            'total_investment' => 0,
            'total_potential_profit' => 0,
            'avg_markup_percentage' => 0
        ];
    }
}

/**
 * Get sales statistics
 */
function getSalesStats($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_sales,
                COALESCE(SUM(quantity_sold), 0) as total_items_sold,
                COALESCE(SUM(actual_profit), 0) as total_profit,
                COALESCE(AVG(actual_profit), 0) as avg_profit_per_sale,
                COALESCE(SUM(sale_price * quantity_sold), 0) as total_revenue
            FROM sales 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            $stats = [
                'total_sales' => 0,
                'total_items_sold' => 0,
                'total_profit' => 0,
                'avg_profit_per_sale' => 0,
                'total_revenue' => 0
            ];
        }
        
        // Convert to proper types
        $stats['total_sales'] = (int)$stats['total_sales'];
        $stats['total_items_sold'] = (int)$stats['total_items_sold'];
        $stats['total_profit'] = (float)$stats['total_profit'];
        $stats['avg_profit_per_sale'] = (float)$stats['avg_profit_per_sale'];
        $stats['total_revenue'] = (float)$stats['total_revenue'];
        
        return $stats;
        
    } catch (PDOException $e) {
        debugLog("Sales stats error", ['error' => $e->getMessage()]);
        return [
            'total_sales' => 0,
            'total_items_sold' => 0,
            'total_profit' => 0,
            'avg_profit_per_sale' => 0,
            'total_revenue' => 0
        ];
    }
}

/**
 * Get vendor statistics
 */
function getVendorStats($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_vendors
            FROM vendors 
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_vendors' => (int)($result['total_vendors'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        debugLog("Vendor stats error", ['error' => $e->getMessage()]);
        return ['total_vendors' => 0];
    }
}

/**
 * Get recent activity
 */
function getRecentActivity($pdo, $userId) {
    try {
        // Get recent products
        $stmt = $pdo->prepare("
            SELECT 'product' as type, product_name as name, created_at as date
            FROM products 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        
        $stmt->execute([$userId]);
        $recentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent sales
        $stmt = $pdo->prepare("
            SELECT 'sale' as type, p.product_name as name, s.created_at as date
            FROM sales s
            JOIN products p ON s.product_id = p.id
            WHERE s.user_id = ? 
            ORDER BY s.created_at DESC 
            LIMIT 5
        ");
        
        $stmt->execute([$userId]);
        $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Combine and sort by date
        $activity = array_merge($recentProducts, $recentSales);
        usort($activity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return array_slice($activity, 0, 10);
        
    } catch (PDOException $e) {
        debugLog("Recent activity error", ['error' => $e->getMessage()]);
        return [];
    }
}
?>
