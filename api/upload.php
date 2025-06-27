<?php
/**
 * Image Upload API - Handle product image uploads
 */

require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    debugLog("Upload API called", [
        'method' => $_SERVER['REQUEST_METHOD'],
        'files' => $_FILES ?? 'No files'
    ]);
    
    requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(['error' => 'Method not allowed'], 405);
    }

    handleImageUpload();
    
} catch (Exception $e) {
    debugLog("Upload API error", ['error' => $e->getMessage()]);
    sendResponse(['error' => $e->getMessage()], 500);
}

function handleImageUpload() {
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            sendResponse(['error' => 'No image uploaded or upload error'], 400);
        }
        
        $file = $_FILES['image'];
        $userId = $_SESSION['user_id'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            sendResponse(['error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.'], 400);
        }
        
        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            sendResponse(['error' => 'File too large. Maximum size is 5MB.'], 400);
        }
        
        // Create upload directory
        $uploadDir = __DIR__ . '/../uploads/user_' . $userId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $uploadDir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            sendResponse(['error' => 'Failed to save uploaded file'], 500);
        }
        
        // Create thumbnail
        $thumbnailPath = $uploadDir . '/thumb_' . $filename;
        createThumbnail($filepath, $thumbnailPath, 150, 150);
        
        // Generate URLs
        $baseUrl = 'uploads/user_' . $userId . '/';
        $imageUrl = $baseUrl . $filename;
        $thumbnailUrl = $baseUrl . 'thumb_' . $filename;
        
        debugLog("Image uploaded", [
            'filename' => $filename,
            'size' => $file['size'],
            'type' => $file['type']
        ]);
        
        sendResponse([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'filename' => $filename
        ]);
        
    } catch (Exception $e) {
        debugLog("Image upload error", ['error' => $e->getMessage()]);
        sendResponse(['error' => 'Image upload failed'], 500);
    }
}

function createThumbnail($source, $destination, $width, $height) {
    try {
        $imageInfo = getimagesize($source);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceType = $imageInfo[2];
        
        // Create source image
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($source);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($source);
                break;
            case IMAGETYPE_WEBP:
                $sourceImage = imagecreatefromwebp($source);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Calculate thumbnail dimensions
        $ratio = min($width / $sourceWidth, $height / $sourceHeight);
        $thumbWidth = intval($sourceWidth * $ratio);
        $thumbHeight = intval($sourceHeight * $ratio);
        
        // Create thumbnail
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Preserve transparency for PNG and GIF
        if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }
        
        // Resize image
        imagecopyresampled(
            $thumbnail, $sourceImage,
            0, 0, 0, 0,
            $thumbWidth, $thumbHeight,
            $sourceWidth, $sourceHeight
        );
        
        // Save thumbnail
        $result = false;
        switch ($sourceType) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($thumbnail, $destination, 85);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($thumbnail, $destination, 6);
                break;
            case IMAGETYPE_GIF:
                $result = imagegif($thumbnail, $destination);
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($thumbnail, $destination, 85);
                break;
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result;
        
    } catch (Exception $e) {
        debugLog("Thumbnail creation error", ['error' => $e->getMessage()]);
        return false;
    }
}
?>
