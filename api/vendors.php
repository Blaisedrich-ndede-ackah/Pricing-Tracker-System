<?php
/**
 * Vendors API - CRUD operations for vendors
 */

require_once 'config.php';

// Set proper headers for CORS and JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

try {
    debugLog("Vendors API called", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'session' => $_SESSION ?? 'No session',
        'input' => file_get_contents('php://input')
    ]);
    
    // Check authentication
    requireAuth();

    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);

    // Log the parsed input
    debugLog("Vendors API input parsed", [
        'method' => $method,
        'input' => $input,
        'user_id' => $_SESSION['user_id']
    ]);

    switch ($method) {
        case 'GET':
            handleGetVendors();
            break;
        case 'POST':
            handleCreateVendor($input);
            break;
        case 'PUT':
            handleUpdateVendor($input);
            break;
        case 'DELETE':
            handleDeleteVendor($input);
            break;
        default:
            sendResponse(['error' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    debugLog("Vendors API error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
}

function handleGetVendors() {
    try {
        $pdo = getDBConnection();
        
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
        
        debugLog("Vendors fetched successfully", [
            'count' => count($vendors),
            'user_id' => $_SESSION['user_id']
        ]);
        
        sendResponse(['vendors' => $vendors]);
        
    } catch (PDOException $e) {
        debugLog("Get vendors database error", [
            'error' => $e->getMessage(),
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Failed to fetch vendors: ' . $e->getMessage()], 500);
    }
}

function handleCreateVendor($data) {
    try {
        debugLog("Creating vendor", [
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        
        // Validate input
        if (!$data || !isset($data['vendor_name']) || empty(trim($data['vendor_name']))) {
            debugLog("Vendor creation validation failed", ['data' => $data]);
            sendResponse(['error' => 'Vendor name is required'], 400);
            return;
        }
        
        $vendorName = trim($data['vendor_name']);
        $contactInfo = isset($data['contact_info']) ? trim($data['contact_info']) : null;
        
        // Empty contact info should be null
        if (empty($contactInfo)) {
            $contactInfo = null;
        }
        
        $pdo = getDBConnection();
        
        // Check if vendor name already exists for this user
        $checkStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? AND vendor_name = ?");
        $checkStmt->execute([$_SESSION['user_id'], $vendorName]);
        
        if ($checkStmt->fetch()) {
            debugLog("Vendor name already exists", [
                'vendor_name' => $vendorName,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'A vendor with this name already exists'], 400);
            return;
        }
        
        // Insert new vendor
        $stmt = $pdo->prepare("INSERT INTO vendors (user_id, vendor_name, contact_info) VALUES (?, ?, ?)");
        $result = $stmt->execute([
            $_SESSION['user_id'],
            $vendorName,
            $contactInfo
        ]);
        
        if ($result) {
            $vendorId = $pdo->lastInsertId();
            
            debugLog("Vendor created successfully", [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'user_id' => $_SESSION['user_id']
            ]);
            
            sendResponse([
                'success' => true,
                'message' => 'Vendor created successfully',
                'vendor_id' => $vendorId,
                'vendor' => [
                    'id' => $vendorId,
                    'vendor_name' => $vendorName,
                    'contact_info' => $contactInfo,
                    'user_id' => $_SESSION['user_id'],
                    'product_count' => 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            debugLog("Vendor creation failed - no result", [
                'vendor_name' => $vendorName,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'Failed to create vendor'], 500);
        }
        
    } catch (PDOException $e) {
        debugLog("Create vendor database error", [
            'error' => $e->getMessage(),
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Create vendor general error", [
            'error' => $e->getMessage(),
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

function handleUpdateVendor($data) {
    try {
        debugLog("Updating vendor", [
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        
        // Validate input
        if (!$data || !isset($data['id']) || !isset($data['vendor_name']) || empty(trim($data['vendor_name']))) {
            debugLog("Vendor update validation failed", ['data' => $data]);
            sendResponse(['error' => 'Vendor ID and name are required'], 400);
            return;
        }
        
        $vendorId = (int)$data['id'];
        $vendorName = trim($data['vendor_name']);
        $contactInfo = isset($data['contact_info']) ? trim($data['contact_info']) : null;
        
        // Empty contact info should be null
        if (empty($contactInfo)) {
            $contactInfo = null;
        }
        
        $pdo = getDBConnection();
        
        // Check if vendor exists and belongs to user
        $checkStmt = $pdo->prepare("SELECT id FROM vendors WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$vendorId, $_SESSION['user_id']]);
        
        if (!$checkStmt->fetch()) {
            debugLog("Vendor not found or unauthorized", [
                'vendor_id' => $vendorId,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'Vendor not found or unauthorized'], 404);
            return;
        }
        
        // Check if new name conflicts with existing vendor (excluding current vendor)
        $conflictStmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? AND vendor_name = ? AND id != ?");
        $conflictStmt->execute([$_SESSION['user_id'], $vendorName, $vendorId]);
        
        if ($conflictStmt->fetch()) {
            debugLog("Vendor name conflict on update", [
                'vendor_name' => $vendorName,
                'vendor_id' => $vendorId,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'A vendor with this name already exists'], 400);
            return;
        }
        
        // Update vendor
        $stmt = $pdo->prepare("
            UPDATE vendors 
            SET vendor_name = ?, contact_info = ?
            WHERE id = ? AND user_id = ?
        ");
        $result = $stmt->execute([
            $vendorName,
            $contactInfo,
            $vendorId,
            $_SESSION['user_id']
        ]);
        
        if ($result && $stmt->rowCount() > 0) {
            debugLog("Vendor updated successfully", [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendorName,
                'user_id' => $_SESSION['user_id']
            ]);
            
            sendResponse([
                'success' => true,
                'message' => 'Vendor updated successfully'
            ]);
        } else {
            debugLog("Vendor update failed - no rows affected", [
                'vendor_id' => $vendorId,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'Failed to update vendor'], 500);
        }
        
    } catch (PDOException $e) {
        debugLog("Update vendor database error", [
            'error' => $e->getMessage(),
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Update vendor general error", [
            'error' => $e->getMessage(),
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}

function handleDeleteVendor($data) {
    try {
        debugLog("Deleting vendor", [
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        
        // Validate input
        if (!$data || !isset($data['id'])) {
            debugLog("Vendor delete validation failed", ['data' => $data]);
            sendResponse(['error' => 'Vendor ID required'], 400);
            return;
        }
        
        $vendorId = (int)$data['id'];
        $pdo = getDBConnection();
        
        // Check if vendor exists and belongs to user
        $checkStmt = $pdo->prepare("SELECT vendor_name FROM vendors WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$vendorId, $_SESSION['user_id']]);
        $vendor = $checkStmt->fetch();
        
        if (!$vendor) {
            debugLog("Vendor not found or unauthorized for deletion", [
                'vendor_id' => $vendorId,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'Vendor not found or unauthorized'], 404);
            return;
        }
        
        // Check if vendor has products
        $productStmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
        $productStmt->execute([$vendorId]);
        $productCount = $productStmt->fetch()['count'];
        
        if ($productCount > 0) {
            debugLog("Cannot delete vendor with products", [
                'vendor_id' => $vendorId,
                'product_count' => $productCount,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => "Cannot delete vendor '{$vendor['vendor_name']}' because it has {$productCount} associated products. Please remove or reassign the products first."], 400);
            return;
        }
        
        // Delete vendor
        $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ? AND user_id = ?");
        $result = $stmt->execute([$vendorId, $_SESSION['user_id']]);
        
        if ($result && $stmt->rowCount() > 0) {
            debugLog("Vendor deleted successfully", [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor['vendor_name'],
                'user_id' => $_SESSION['user_id']
            ]);
            
            sendResponse([
                'success' => true,
                'message' => "Vendor '{$vendor['vendor_name']}' deleted successfully"
            ]);
        } else {
            debugLog("Vendor deletion failed - no rows affected", [
                'vendor_id' => $vendorId,
                'user_id' => $_SESSION['user_id']
            ]);
            sendResponse(['error' => 'Failed to delete vendor'], 500);
        }
        
    } catch (PDOException $e) {
        debugLog("Delete vendor database error", [
            'error' => $e->getMessage(),
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    } catch (Exception $e) {
        debugLog("Delete vendor general error", [
            'error' => $e->getMessage(),
            'data' => $data,
            'user_id' => $_SESSION['user_id']
        ]);
        sendResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
    }
}
?>
