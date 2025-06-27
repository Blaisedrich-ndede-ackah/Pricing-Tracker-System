<?php
/**
 * Import/Export API - CSV import and export functionality
 */

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    debugLog("Import/Export API called", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'action' => $_GET['action'] ?? 'none'
    ]);
    
    requireAuth();

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'export':
            handleExport();
            break;
        case 'template':
            handleDownloadTemplate();
            break;
        case 'import':
            handleImport();
            break;
        default:
            sendResponse(['error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    debugLog("Import/Export API error", ['error' => $e->getMessage()]);
    sendResponse(['error' => $e->getMessage()], 500);
}

function handleExport() {
    try {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            SELECT 
                p.product_name,
                v.vendor_name,
                p.actual_price,
                p.markup_percentage,
                p.selling_price,
                p.quantity,
                p.total_profit,
                p.product_url,
                p.notes,
                p.status,
                p.date_added
            FROM products p
            LEFT JOIN vendors v ON p.vendor_id = v.id
            WHERE p.user_id = ?
            ORDER BY p.date_added DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $products = $stmt->fetchAll();
        
        // Generate CSV
        $filename = 'products_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Product Name',
            'Vendor Name',
            'Actual Price',
            'Markup Percentage',
            'Selling Price',
            'Quantity',
            'Total Profit',
            'Product URL',
            'Notes',
            'Status',
            'Date Added'
        ]);
        
        // CSV data
        foreach ($products as $product) {
            fputcsv($output, [
                $product['product_name'],
                $product['vendor_name'] ?? '',
                $product['actual_price'],
                $product['markup_percentage'],
                $product['selling_price'],
                $product['quantity'],
                $product['total_profit'],
                $product['product_url'] ?? '',
                $product['notes'] ?? '',
                $product['status'],
                $product['date_added']
            ]);
        }
        
        fclose($output);
        debugLog("Export completed", ['count' => count($products)]);
        exit;
        
    } catch (PDOException $e) {
        debugLog("Export error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Export failed'], 500);
    }
}

function handleDownloadTemplate() {
    try {
        $filename = 'import_template.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Product Name',
            'Vendor Name',
            'Actual Price',
            'Markup Percentage',
            'Quantity',
            'Product URL',
            'Notes',
            'Status',
            'Date Added'
        ]);
        
        // Sample data
        fputcsv($output, [
            'Sample Product',
            'Sample Vendor',
            '100.00',
            '25',
            '1',
            'https://example.com',
            'Sample notes',
            'active',
            date('Y-m-d')
        ]);
        
        fclose($output);
        debugLog("Template downloaded");
        exit;
        
    } catch (Exception $e) {
        debugLog("Template download error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Template download failed'], 500);
    }
}

function handleImport() {
    try {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            sendResponse(['error' => 'No file uploaded or upload error'], 400);
        }
        
        $file = $_FILES['csv_file'];
        $handle = fopen($file['tmp_name'], 'r');
        
        if (!$handle) {
            sendResponse(['error' => 'Could not read uploaded file'], 400);
        }
        
        $pdo = getDBConnection();
        $imported = 0;
        $errors = [];
        $lineNumber = 0;
        
        // Skip header row
        fgetcsv($handle);
        $lineNumber++;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $lineNumber++;
            
            try {
                if (count($data) < 4) {
                    $errors[] = "Line $lineNumber: Insufficient data";
                    continue;
                }
                
                $productName = trim($data[0]);
                $vendorName = trim($data[1] ?? '');
                $actualPrice = floatval($data[2] ?? 0);
                $markupPercentage = floatval($data[3] ?? 0);
                $quantity = intval($data[4] ?? 1);
                $productUrl = trim($data[5] ?? '');
                $notes = trim($data[6] ?? '');
                $status = trim($data[7] ?? 'active');
                $dateAdded = trim($data[8] ?? date('Y-m-d'));
                
                if (empty($productName) || $actualPrice <= 0) {
                    $errors[] = "Line $lineNumber: Invalid product name or price";
                    continue;
                }
                
                // Find or create vendor
                $vendorId = null;
                if (!empty($vendorName)) {
                    $stmt = $pdo->prepare("SELECT id FROM vendors WHERE vendor_name = ? AND user_id = ?");
                    $stmt->execute([$vendorName, $_SESSION['user_id']]);
                    $vendor = $stmt->fetch();
                    
                    if (!$vendor) {
                        $stmt = $pdo->prepare("INSERT INTO vendors (user_id, vendor_name) VALUES (?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $vendorName]);
                        $vendorId = $pdo->lastInsertId();
                    } else {
                        $vendorId = $vendor['id'];
                    }
                }
                
                // Calculate values
                $sellingPrice = $actualPrice * (1 + $markupPercentage / 100);
                $profit = $sellingPrice - $actualPrice;
                $totalProfit = $profit * $quantity;
                
                // Insert product
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        user_id, vendor_id, product_name, actual_price, markup_percentage,
                        selling_price, profit, quantity, total_profit, product_url,
                        notes, status, date_added
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $vendorId,
                    $productName,
                    $actualPrice,
                    $markupPercentage,
                    $sellingPrice,
                    $profit,
                    $quantity,
                    $totalProfit,
                    $productUrl ?: null,
                    $notes ?: null,
                    $status,
                    $dateAdded
                ]);
                
                $imported++;
                
            } catch (Exception $e) {
                $errors[] = "Line $lineNumber: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        debugLog("Import completed", [
            'imported' => $imported,
            'errors' => count($errors)
        ]);
        
        sendResponse([
            'success' => true,
            'message' => "Import completed",
            'imported_count' => $imported,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        debugLog("Import error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Import failed: ' . $e->getMessage()], 500);
    }
}
?>
