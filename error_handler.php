<?php
/**
 * Helpify Error Handler
 * Centralized error handling and logging
 */

/**
 * Custom error handler
 */
function helpifyErrorHandler($errno, $errstr, $errfile, $errline) {
    // Don't handle errors if error reporting is turned off
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_types = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    
    // Log the error
    $log_message = sprintf(
        "[%s] %s: %s in %s on line %d",
        date('Y-m-d H:i:s'),
        $error_type,
        $errstr,
        $errfile,
        $errline
    );
    
    error_log($log_message);
    
    // For fatal errors, show user-friendly page
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        showErrorPage('System Error', 'An unexpected error occurred. Please try again later.');
        exit();
    }
    
    return true;
}

/**
 * Custom exception handler
 */
function helpifyExceptionHandler($exception) {
    $log_message = sprintf(
        "[%s] Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
        date('Y-m-d H:i:s'),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    
    error_log($log_message);
    
    // Show user-friendly error page
    showErrorPage('Application Error', 'Something went wrong. Our team has been notified.');
}

/**
 * Shutdown handler for fatal errors
 */
function helpifyShutdownHandler() {
    $error = error_get_last();
    
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $log_message = sprintf(
            "[%s] Fatal Error: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        
        error_log($log_message);
        
        // Clear any previous output
        if (ob_get_level()) {
            ob_clean();
        }
        
        showErrorPage('System Error', 'A fatal error occurred. Please try again later.');
    }
}

/**
 * Show user-friendly error page
 */
function showErrorPage($title, $message, $code = 500) {
    http_response_code($code);
    
    // If we're making an AJAX request, return JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => $code
        ]);
        return;
    }
    
    // Get appropriate icon based on error code
    $icon_name = 'alert-circle';
    $icon_color = '#ef4444';
    
    switch ($code) {
        case 404:
            $icon_name = 'search';
            $icon_color = '#6b7280';
            break;
        case 403:
            $icon_name = 'lock';
            $icon_color = '#f59e0b';
            break;
        case 500:
        default:
            $icon_name = 'alert-triangle';
            $icon_color = '#ef4444';
            break;
    }
    
    // Otherwise show HTML error page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | Helpify</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #1a1a1a;
            }
            
            .error-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 24px;
                padding: 48px;
                max-width: 500px;
                width: 90%;
                text-align: center;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            }
            
            .error-icon {
                margin-bottom: 24px;
                display: flex;
                justify-content: center;
            }
            
            .error-title {
                font-size: 32px;
                font-weight: 700;
                color: #1a1a1a;
                margin-bottom: 16px;
            }
            
            .error-message {
                font-size: 16px;
                color: #666;
                line-height: 1.6;
                margin-bottom: 32px;
            }
            
            .error-actions {
                display: flex;
                gap: 16px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .btn {
                padding: 12px 24px;
                border-radius: 12px;
                font-weight: 600;
                text-decoration: none;
                transition: all 0.2s;
                cursor: pointer;
                border: none;
                font-size: 14px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
            }
            
            .btn-secondary {
                background: #f3f4f6;
                color: #4b5563;
                border: 2px solid #d1d5db;
            }
            
            .btn-secondary:hover {
                background: #e5e7eb;
            }
            
            @media (max-width: 600px) {
                .error-container {
                    padding: 32px 24px;
                }
                
                .error-actions {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="<?php echo $icon_color; ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php
                    // SVG paths for different error types
                    $svg_paths = [
                        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
                        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><circle cx="12" cy="16" r="1"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
                        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                        'alert-circle' => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
                    ];
                    echo $svg_paths[$icon_name] ?? $svg_paths['alert-circle'];
                    ?>
                </svg>
            </div>
            
            <h1 class="error-title"><?php echo htmlspecialchars($title); ?></h1>
            <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
            
            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Go Back
                </a>
                <a href="/" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9,22 9,12 15,12 15,22"/>
                    </svg>
                    Go Home
                </a>
            </div>
        </div>
        
        <script>
            // Auto-refresh after 30 seconds for server errors
            <?php if ($code >= 500): ?>
            setTimeout(function() {
                if (confirm('Would you like to try reloading the page?')) {
                    window.location.reload();
                }
            }, 30000);
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
}

/**
 * Log database errors
 */
function logDatabaseError($error, $query = null, $params = null) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error,
        'query' => $query,
        'params' => $params,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    error_log('[HELPIFY DB ERROR] ' . json_encode($log_data));
}

/**
 * Handle database exceptions
 */
function handleDatabaseException($e, $user_message = 'Database error occurred.') {
    logDatabaseError($e->getMessage());
    
    // In development, show detailed error
    if (defined('DEBUG') && DEBUG) {
        throw $e;
    }
    
    // In production, show generic message
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        jsonResponse(['success' => false, 'message' => $user_message], 500);
    } else {
        showErrorPage('Database Error', $user_message, 500);
        exit();
    }
}

/**
 * Validate and sanitize input with error handling
 */
function validateInput($data, $rules) {
    $errors = [];
    $sanitized = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        $sanitized[$field] = $value;
        
        // Required check
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[$field] = $rule['required_message'] ?? ucfirst($field) . ' is required.';
            continue;
        }
        
        // Skip other validations if value is empty and not required
        if (empty($value)) {
            continue;
        }
        
        // Type validation
        if (isset($rule['type'])) {
            switch ($rule['type']) {
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field] = $rule['type_message'] ?? 'Invalid email format.';
                    } else {
                        $sanitized[$field] = sanitize($value);
                    }
                    break;
                    
                case 'int':
                    if (!filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field] = $rule['type_message'] ?? 'Must be a valid number.';
                    } else {
                        $sanitized[$field] = intval($value);
                    }
                    break;
                    
                case 'float':
                    if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                        $errors[$field] = $rule['type_message'] ?? 'Must be a valid decimal number.';
                    } else {
                        $sanitized[$field] = floatval($value);
                    }
                    break;
                    
                case 'string':
                default:
                    $sanitized[$field] = sanitize($value);
                    break;
            }
        }
        
        // Length validation
        if (isset($rule['min_length']) && strlen($sanitized[$field]) < $rule['min_length']) {
            $errors[$field] = $rule['min_message'] ?? ucfirst($field) . ' must be at least ' . $rule['min_length'] . ' characters.';
        }
        
        if (isset($rule['max_length']) && strlen($sanitized[$field]) > $rule['max_length']) {
            $errors[$field] = $rule['max_message'] ?? ucfirst($field) . ' must be no more than ' . $rule['max_length'] . ' characters.';
        }
        
        // Range validation
        if (isset($rule['min']) && $sanitized[$field] < $rule['min']) {
            $errors[$field] = $rule['min_message'] ?? ucfirst($field) . ' must be at least ' . $rule['min'] . '.';
        }
        
        if (isset($rule['max']) && $sanitized[$field] > $rule['max']) {
            $errors[$field] = $rule['max_message'] ?? ucfirst($field) . ' must be no more than ' . $rule['max'] . '.';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $sanitized
    ];
}

// Set up error handlers
set_error_handler('helpifyErrorHandler');
set_exception_handler('helpifyExceptionHandler');
register_shutdown_function('helpifyShutdownHandler');

// Set up error reporting based on environment
if (defined('DEBUG') && DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(