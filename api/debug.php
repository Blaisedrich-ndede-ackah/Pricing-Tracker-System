<?php
/**
 * Debug and Error Handling Utilities
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs/';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * Log debug information
 */
function debugLog($message, $data = null) {
    $logFile = __DIR__ . '/../logs/debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= "\nData: " . print_r($data, true);
    }
    
    $logMessage .= "\n" . str_repeat('-', 50) . "\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Handle uncaught exceptions
 */
function handleException($exception) {
    debugLog("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

/**
 * Handle PHP errors
 */
function handleError($severity, $message, $file, $line) {
    debugLog("PHP Error: $message", [
        'severity' => $severity,
        'file' => $file,
        'line' => $line
    ]);
    
    if ($severity & error_reporting()) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
}

// Set error handlers
set_exception_handler('handleException');
set_error_handler('handleError');
?>
