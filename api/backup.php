<?php
/**
 * Backup/Restore API - Data backup and restore functionality
 */

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    debugLog("Backup API called", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $_GET['action'] ?? 'none'
    ]);
    
    requireAuth();

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'backup':
            handleCreateBackup();
            break;
        case 'list':
            handleListBackups();
            break;
        case 'delete':
            handleDeleteBackup();
            break;
        case 'restore':
            handleRestore();
            break;
        default:
            sendResponse(['error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    debugLog("Backup API error", ['error' => $e->getMessage()]);
    sendResponse(['error' => $e->getMessage()], 500);
}

function handleCreateBackup() {
    try {
        $pdo = getDBConnection();
        $userId = $_SESSION['user_id'];
        
        // Create backup directory
        $backupDir = __DIR__ . '/../backups/user_' . $userId;
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        // Get all user data
        $backup = [
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'data' => []
        ];
        
        // Get products
        $stmt = $pdo->prepare("SELECT * FROM products WHERE user_id = ?");
        $stmt->execute([$userId]);
        $backup['data']['products'] = $stmt->fetchAll();
        
        // Get vendors
        $stmt = $pdo->prepare("SELECT * FROM vendors WHERE user_id = ?");
        $stmt->execute([$userId]);
        $backup['data']['vendors'] = $stmt->fetchAll();
        
        // Get sales (if table exists)
        try {
            $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ?");
            $stmt->execute([$userId]);
            $backup['data']['sales'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $backup['data']['sales'] = [];
        }
        
        // Create backup file
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $backupDir . '/' . $filename;
        
        file_put_contents($filepath, json_encode($backup, JSON_PRETTY_PRINT));
        
        debugLog("Backup created", [
            'filename' => $filename,
            'products' => count($backup['data']['products']),
            'vendors' => count($backup['data']['vendors']),
            'sales' => count($backup['data']['sales'])
        ]);
        
        sendResponse([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'records' => [
                'products' => count($backup['data']['products']),
                'vendors' => count($backup['data']['vendors']),
                'sales' => count($backup['data']['sales'])
            ]
        ]);
        
    } catch (Exception $e) {
        debugLog("Create backup error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Backup creation failed'], 500);
    }
}

function handleListBackups() {
    try {
        $userId = $_SESSION['user_id'];
        $backupDir = __DIR__ . '/../backups/user_' . $userId;
        
        if (!is_dir($backupDir)) {
            sendResponse(['backups' => []]);
            return;
        }
        
        $backups = [];
        $files = glob($backupDir . '/backup_*.json');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $created = date('Y-m-d H:i:s', filemtime($file));
            
            // Try to get backup info
            $info = null;
            try {
                $content = json_decode(file_get_contents($file), true);
                if ($content && isset($content['data'])) {
                    $info = [
                        'products' => count($content['data']['products'] ?? []),
                        'vendors' => count($content['data']['vendors'] ?? []),
                        'sales' => count($content['data']['sales'] ?? [])
                    ];
                }
            } catch (Exception $e) {
                // Ignore errors reading backup info
            }
            
            $backups[] = [
                'filename' => $filename,
                'size' => $size,
                'created' => $created,
                'info' => $info
            ];
        }
        
        // Sort by creation date (newest first)
        usort($backups, function($a, $b) {
            return strcmp($b['created'], $a['created']);
        });
        
        debugLog("Backups listed", ['count' => count($backups)]);
        sendResponse(['backups' => $backups]);
        
    } catch (Exception $e) {
        debugLog("List backups error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Failed to list backups'], 500);
    }
}

function handleDeleteBackup() {
    try {
        $filename = $_POST['filename'] ?? '';
        if (empty($filename)) {
            sendResponse(['error' => 'Filename required'], 400);
        }
        
        $userId = $_SESSION['user_id'];
        $filepath = __DIR__ . '/../backups/user_' . $userId . '/' . $filename;
        
        if (!file_exists($filepath)) {
            sendResponse(['error' => 'Backup file not found'], 404);
        }
        
        if (unlink($filepath)) {
            debugLog("Backup deleted", ['filename' => $filename]);
            sendResponse([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } else {
            sendResponse(['error' => 'Failed to delete backup'], 500);
        }
        
    } catch (Exception $e) {
        debugLog("Delete backup error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Failed to delete backup'], 500);
    }
}

function handleRestore() {
    try {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            sendResponse(['error' => 'No backup file uploaded'], 400);
        }
        
        $file = $_FILES['backup_file'];
        $content = file_get_contents($file['tmp_name']);
        $backup = json_decode($content, true);
        
        if (!$backup || !isset($backup['data'])) {
            sendResponse(['error' => 'Invalid backup file format'], 400);
        }
        
        $pdo = getDBConnection();
        $userId = $_SESSION['user_id'];
        $clearExisting = ($_POST['clear_existing'] ?? 'false') === 'true';
        
        $restored = [
            'products' => 0,
            'vendors' => 0,
            'sales' => 0,
            'markup_presets' => 0
        ];
        
        $pdo->beginTransaction();
        
        try {
            // Clear existing data if requested
            if ($clearExisting) {
                $pdo->prepare("DELETE FROM sales WHERE user_id = ?")->execute([$userId]);
                $pdo->prepare("DELETE FROM products WHERE user_id = ?")->execute([$userId]);
                $pdo->prepare("DELETE FROM vendors WHERE user_id = ?")->execute([$userId]);
            }
            
            // Restore vendors first
            if (isset($backup['data']['vendors'])) {
                foreach ($backup['data']['vendors'] as $vendor) {
                    $stmt = $pdo->prepare("
                        INSERT INTO vendors (user_id, vendor_name, contact_info, created_at)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $vendor['vendor_name'],
                        $vendor['contact_info'] ?? null,
                        $vendor['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                    $restored['vendors']++;
                }
            }
            
            // Restore products
            if (isset($backup['data']['products'])) {
                foreach ($backup['data']['products'] as $product) {
                    // Find vendor ID if vendor name exists
                    $vendorId = null;
                    if (!empty($product['vendor_id'])) {
                        $stmt = $pdo->prepare("SELECT id FROM vendors WHERE user_id = ? LIMIT 1");
                        $stmt->execute([$userId]);
                        $vendor = $stmt->fetch();
                        if ($vendor) {
                            $vendorId = $vendor['id'];
                        }
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO products (
                            user_id, vendor_id, product_name, actual_price, markup_percentage,
                            selling_price, profit, quantity, total_profit, product_url,
                            product_image, notes, status, date_added, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        $vendorId,
                        $product['product_name'],
                        $product['actual_price'],
                        $product['markup_percentage'] ?? 0,
                        $product['selling_price'] ?? $product['actual_price'],
                        $product['profit'] ?? 0,
                        $product['quantity'] ?? 1,
                        $product['total_profit'] ?? 0,
                        $product['product_url'] ?? null,
                        $product['product_image'] ?? null,
                        $product['notes'] ?? null,
                        $product['status'] ?? 'active',
                        $product['date_added'] ?? date('Y-m-d'),
                        $product['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                    $restored['products']++;
                }
            }
            
            // Restore sales
            if (isset($backup['data']['sales'])) {
                foreach ($backup['data']['sales'] as $sale) {
                    // Find product ID
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE user_id = ? LIMIT 1");
                    $stmt->execute([$userId]);
                    $product = $stmt->fetch();
                    
                    if ($product) {
                        $stmt = $pdo->prepare("
                            INSERT INTO sales (
                                user_id, product_id, quantity_sold, sale_price,
                                actual_profit, sale_date, notes, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $userId,
                            $product['id'],
                            $sale['quantity_sold'],
                            $sale['sale_price'],
                            $sale['actual_profit'],
                            $sale['sale_date'],
                            $sale['notes'] ?? null,
                            $sale['created_at'] ?? date('Y-m-d H:i:s')
                        ]);
                        $restored['sales']++;
                    }
                }
            }
            
            $pdo->commit();
            
            debugLog("Restore completed", $restored);
            
            sendResponse([
                'success' => true,
                'message' => 'Data restored successfully',
                'restored' => $restored
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        debugLog("Restore error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Restore failed: ' . $e->getMessage()], 500);
    }
}
?>
