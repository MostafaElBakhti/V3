<?php
/**
 * Helpify Common Functions
 * Utility functions used throughout the application
 */

/**
 * Format time ago (e.g., "2 hours ago", "3 days ago")
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return 'Just now';
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return $minutes . ' minute' . ($minutes != 1 ? 's' : '') . ' ago';
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return $hours . ' hour' . ($hours != 1 ? 's' : '') . ' ago';
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return $days . ' day' . ($days != 1 ? 's' : '') . ' ago';
    } elseif ($time < 31536000) {
        $months = floor($time / 2592000);
        return $months . ' month' . ($months != 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($time / 31536000);
        return $years . ' year' . ($years != 1 ? 's' : '') . ' ago';
    }
}

/**
 * Generate star rating HTML with SVG icons
 */
function generateStars($rating, $total_ratings = null, $size = 'medium') {
    require_once 'icons.php';
    
    $sizes = [
        'small' => 12,
        'medium' => 16,
        'large' => 20
    ];
    
    $icon_size = $sizes[$size] ?? $sizes['medium'];
    $html = '<div class="rating-stars" style="display: flex; align-items: center; gap: 4px;">';
    
    // Stars
    $html .= '<div style="display: flex; gap: 2px;">';
    for ($i = 1; $i <= 5; $i++) {
        $color = $i <= round($rating) ? '#fbbf24' : '#e5e7eb';
        $html .= getIcon('star', $icon_size, '', $color);
    }
    $html .= '</div>';
    
    // Rating text
    if ($total_ratings !== null) {
        $html .= '<span style="font-size: 12px; color: #666; margin-left: 8px;">';
        $html .= '(' . number_format($rating, 1) . ' • ' . $total_ratings . ' review' . ($total_ratings != 1 ? 's' : '') . ')';
        $html .= '</span>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $include_cents = false) {
    if ($include_cents || ($amount - floor($amount)) > 0) {
        return '

/**
 * Format currency amount
 */
function formatCurrency($amount, $include_cents = false) {
    if ($include_cents || ($amount - floor($amount)) > 0) {
        return '$' . number_format($amount, 2);
    } else {
        return '$' . number_format($amount, 0);
    }
}

/**
 * Generate user avatar
 */
function generateAvatar($name, $size = 40, $background_color = null) {
    $initials = strtoupper(substr($name, 0, 1));
    
    if (!$background_color) {
        // Generate color based on name
        $colors = ['#667eea', '#764ba2', '#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6'];
        $background_color = $colors[ord($initials) % count($colors)];
    }
    
    return '<div style="width: ' . $size . 'px; height: ' . $size . 'px; background: ' . $background_color . '; border-radius: ' . ($size * 0.25) . 'px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: ' . ($size * 0.4) . 'px;">' . $initials . '</div>';
}

/**
 * Get task status badge HTML
 */
function getTaskStatusBadge($status) {
    $badges = [
        'open' => ['Open', '#10b981', '#dcfce7', '🟢'],
        'pending' => ['Pending', '#f59e0b', '#fed7aa', '🟡'],
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe', '🔵'],
        'completed' => ['Completed', '#6b7280', '#f3f4f6', '✅'],
        'cancelled' => ['Cancelled', '#ef4444', '#fee2e2', '❌']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6', '⚪'];
    return '<span style="background: ' . $badge[2] . '; color: ' . $badge[1] . '; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">' . $badge[3] . ' ' . $badge[0] . '</span>';
}

/**
 * Validate and sanitize file upload
 */
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded.';
                break;
            default:
                $errors[] = 'File upload failed.';
        }
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File is too large. Maximum size is ' . formatBytes($max_size) . '.';
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
    }
    
    // Check if it's actually an image (for image uploads)
    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            $errors[] = 'File is not a valid image.';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'extension' => $file_extension,
        'size' => $file['size']
    ];
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Validate CSRF token
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Check if user has permission for task
 */
function canAccessTask($task_id, $user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM tasks 
            WHERE id = ? AND (client_id = ? OR helper_id = ?)
            UNION
            SELECT 1 FROM applications a
            JOIN tasks t ON a.task_id = t.id
            WHERE a.task_id = ? AND a.helper_id = ?
        ");
        $stmt->execute([$task_id, $user_id, $user_id, $task_id, $user_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log activity for debugging
 */
function logActivity($message, $data = null, $level = 'INFO') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'data' => $data
    ];
    
    error_log('[HELPIFY] ' . json_encode($log_entry));
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $user_id, $limit = 10, $window = 3600, $pdo = null) {
    if (!$pdo) {
        return true; // Skip if no database connection
    }
    
    try {
        // Clean old entries
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $stmt->execute([date('Y-m-d H:i:s', time() - $window)]);
        
        // Check current count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action = ? AND user_id = ? AND created_at > ?");
        $stmt->execute([$action, $user_id, date('Y-m-d H:i:s', time() - $window)]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) {
            return false;
        }
        
        // Record this action
        $stmt = $pdo->prepare("INSERT INTO rate_limits (action, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$action, $user_id]);
        
        return true;
    } catch (PDOException $e) {
        return true; // Allow if database error
    }
}

/**
 * Generate notification HTML
 */
function generateNotification($type, $message, $dismissible = true) {
    $types = [
        'success' => ['#dcfce7', '#166534', '#10b981'],
        'error' => ['#fee2e2', '#dc2626', '#ef4444'],
        'warning' => ['#fef3c7', '#92400e', '#f59e0b'],
        'info' => ['#dbeafe', '#1e40af', '#3b82f6']
    ];
    
    $colors = $types[$type] ?? $types['info'];
    
    $html = '<div class="notification notification-' . $type . '" style="background: ' . $colors[0] . '; color: ' . $colors[1] . '; border: 1px solid ' . $colors[2] . '40; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">';
    $html .= '<span>' . htmlspecialchars($message) . '</span>';
    
    if ($dismissible) {
        $html .= '<button onclick="this.parentElement.remove()" style="background: none; border: none; color: ' . $colors[1] . '; cursor: pointer; font-size: 18px; padding: 0; margin-left: 12px;">&times;</button>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Check if date is today
 */
function isToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

/**
 * Check if date is this week
 */
function isThisWeek($date) {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $check_date = date('Y-m-d', strtotime($date));
    
    return $check_date >= $week_start && $check_date <= $week_end;
}

/**
 * Generate meta tags for SEO
 */
function generateMetaTags($title, $description, $image = null, $url = null) {
    $site_name = 'Helpify';
    $default_image = '/assets/images/logo.png';
    
    $html = '<title>' . htmlspecialchars($title . ' | ' . $site_name) . '</title>' . "\n";
    $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    
    // Open Graph tags
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta property="og:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . $site_name . '">' . "\n";
    
    if ($url) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    }
    
    // Twitter Card tags
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta name="twitter:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    
    return $html;
}
?> . number_format($amount, 2);
    } else {
        return '

/**
 * Format currency amount
 */
function formatCurrency($amount, $include_cents = false) {
    if ($include_cents || ($amount - floor($amount)) > 0) {
        return '$' . number_format($amount, 2);
    } else {
        return '$' . number_format($amount, 0);
    }
}

/**
 * Generate user avatar
 */
function generateAvatar($name, $size = 40, $background_color = null) {
    $initials = strtoupper(substr($name, 0, 1));
    
    if (!$background_color) {
        // Generate color based on name
        $colors = ['#667eea', '#764ba2', '#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6'];
        $background_color = $colors[ord($initials) % count($colors)];
    }
    
    return '<div style="width: ' . $size . 'px; height: ' . $size . 'px; background: ' . $background_color . '; border-radius: ' . ($size * 0.25) . 'px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: ' . ($size * 0.4) . 'px;">' . $initials . '</div>';
}

/**
 * Get task status badge HTML
 */
function getTaskStatusBadge($status) {
    $badges = [
        'open' => ['Open', '#10b981', '#dcfce7', '🟢'],
        'pending' => ['Pending', '#f59e0b', '#fed7aa', '🟡'],
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe', '🔵'],
        'completed' => ['Completed', '#6b7280', '#f3f4f6', '✅'],
        'cancelled' => ['Cancelled', '#ef4444', '#fee2e2', '❌']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6', '⚪'];
    return '<span style="background: ' . $badge[2] . '; color: ' . $badge[1] . '; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">' . $badge[3] . ' ' . $badge[0] . '</span>';
}

/**
 * Validate and sanitize file upload
 */
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded.';
                break;
            default:
                $errors[] = 'File upload failed.';
        }
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File is too large. Maximum size is ' . formatBytes($max_size) . '.';
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
    }
    
    // Check if it's actually an image (for image uploads)
    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            $errors[] = 'File is not a valid image.';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'extension' => $file_extension,
        'size' => $file['size']
    ];
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Validate CSRF token
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Check if user has permission for task
 */
function canAccessTask($task_id, $user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM tasks 
            WHERE id = ? AND (client_id = ? OR helper_id = ?)
            UNION
            SELECT 1 FROM applications a
            JOIN tasks t ON a.task_id = t.id
            WHERE a.task_id = ? AND a.helper_id = ?
        ");
        $stmt->execute([$task_id, $user_id, $user_id, $task_id, $user_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log activity for debugging
 */
function logActivity($message, $data = null, $level = 'INFO') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'data' => $data
    ];
    
    error_log('[HELPIFY] ' . json_encode($log_entry));
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $user_id, $limit = 10, $window = 3600, $pdo = null) {
    if (!$pdo) {
        return true; // Skip if no database connection
    }
    
    try {
        // Clean old entries
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $stmt->execute([date('Y-m-d H:i:s', time() - $window)]);
        
        // Check current count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action = ? AND user_id = ? AND created_at > ?");
        $stmt->execute([$action, $user_id, date('Y-m-d H:i:s', time() - $window)]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) {
            return false;
        }
        
        // Record this action
        $stmt = $pdo->prepare("INSERT INTO rate_limits (action, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$action, $user_id]);
        
        return true;
    } catch (PDOException $e) {
        return true; // Allow if database error
    }
}

/**
 * Generate notification HTML
 */
function generateNotification($type, $message, $dismissible = true) {
    $types = [
        'success' => ['#dcfce7', '#166534', '#10b981'],
        'error' => ['#fee2e2', '#dc2626', '#ef4444'],
        'warning' => ['#fef3c7', '#92400e', '#f59e0b'],
        'info' => ['#dbeafe', '#1e40af', '#3b82f6']
    ];
    
    $colors = $types[$type] ?? $types['info'];
    
    $html = '<div class="notification notification-' . $type . '" style="background: ' . $colors[0] . '; color: ' . $colors[1] . '; border: 1px solid ' . $colors[2] . '40; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">';
    $html .= '<span>' . htmlspecialchars($message) . '</span>';
    
    if ($dismissible) {
        $html .= '<button onclick="this.parentElement.remove()" style="background: none; border: none; color: ' . $colors[1] . '; cursor: pointer; font-size: 18px; padding: 0; margin-left: 12px;">&times;</button>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Check if date is today
 */
function isToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

/**
 * Check if date is this week
 */
function isThisWeek($date) {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $check_date = date('Y-m-d', strtotime($date));
    
    return $check_date >= $week_start && $check_date <= $week_end;
}

/**
 * Generate meta tags for SEO
 */
function generateMetaTags($title, $description, $image = null, $url = null) {
    $site_name = 'Helpify';
    $default_image = '/assets/images/logo.png';
    
    $html = '<title>' . htmlspecialchars($title . ' | ' . $site_name) . '</title>' . "\n";
    $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    
    // Open Graph tags
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta property="og:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . $site_name . '">' . "\n";
    
    if ($url) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    }
    
    // Twitter Card tags
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta name="twitter:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    
    return $html;
}
?> . number_format($amount, 0);
    }
}

/**
 * Generate user avatar with SVG fallback
 */
function generateAvatar($name, $size = 40, $background_color = null, $image_url = null) {
    if ($image_url) {
        return '<img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($name) . '" style="width: ' . $size . 'px; height: ' . $size . 'px; border-radius: ' . ($size * 0.25) . 'px; object-fit: cover;">';
    }
    
    $initials = strtoupper(substr($name, 0, 1));
    
    if (!$background_color) {
        // Generate color based on name
        $colors = ['#667eea', '#764ba2', '#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6'];
        $background_color = $colors[ord($initials) % count($colors)];
    }
    
    return '<div style="width: ' . $size . 'px; height: ' . $size . 'px; background: ' . $background_color . '; border-radius: ' . ($size * 0.25) . 'px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: ' . ($size * 0.4) . 'px;">' . $initials . '</div>';
}

/**
 * Get task status badge HTML with SVG icons
 */
function getTaskStatusBadge($status) {
    require_once 'icons.php';
    
    $badges = [
        'open' => ['Open', '#10b981', '#dcfce7', 'circle'],
        'pending' => ['Pending', '#f59e0b', '#fed7aa', 'clock'],
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe', 'activity'],
        'completed' => ['Completed', '#6b7280', '#f3f4f6', 'check-circle'],
        'cancelled' => ['Cancelled', '#ef4444', '#fee2e2', 'x-circle']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6', 'circle'];
    $icon = getIcon($badge[3], 14, '', $badge[1]);
    
    return '<span style="background: ' . $badge[2] . '; color: ' . $badge[1] . '; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">' . $icon . ' ' . $badge[0] . '</span>';
}

/**
 * Validate and sanitize file upload
 */
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded.';
                break;
            default:
                $errors[] = 'File upload failed.';
        }
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File is too large. Maximum size is ' . formatBytes($max_size) . '.';
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
    }
    
    // Check if it's actually an image (for image uploads)
    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            $errors[] = 'File is not a valid image.';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'extension' => $file_extension,
        'size' => $file['size']
    ];
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Validate CSRF token
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Check if user has permission for task
 */
function canAccessTask($task_id, $user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM tasks 
            WHERE id = ? AND (client_id = ? OR helper_id = ?)
            UNION
            SELECT 1 FROM applications a
            JOIN tasks t ON a.task_id = t.id
            WHERE a.task_id = ? AND a.helper_id = ?
        ");
        $stmt->execute([$task_id, $user_id, $user_id, $task_id, $user_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log activity for debugging
 */
function logActivity($message, $data = null, $level = 'INFO') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'data' => $data
    ];
    
    error_log('[HELPIFY] ' . json_encode($log_entry));
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $user_id, $limit = 10, $window = 3600, $pdo = null) {
    if (!$pdo) {
        return true; // Skip if no database connection
    }
    
    try {
        // Clean old entries
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $stmt->execute([date('Y-m-d H:i:s', time() - $window)]);
        
        // Check current count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action = ? AND user_id = ? AND created_at > ?");
        $stmt->execute([$action, $user_id, date('Y-m-d H:i:s', time() - $window)]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) {
            return false;
        }
        
        // Record this action
        $stmt = $pdo->prepare("INSERT INTO rate_limits (action, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$action, $user_id]);
        
        return true;
    } catch (PDOException $e) {
        return true; // Allow if database error
    }
}

/**
 * Generate notification HTML with SVG icons
 */
function generateNotification($type, $message, $dismissible = true) {
    require_once 'icons.php';
    
    $types = [
        'success' => ['#dcfce7', '#166534', '#10b981', 'check-circle'],
        'error' => ['#fee2e2', '#dc2626', '#ef4444', 'alert-circle'],
        'warning' => ['#fef3c7', '#92400e', '#f59e0b', 'alert-triangle'],
        'info' => ['#dbeafe', '#1e40af', '#3b82f6', 'info']
    ];
    
    $config = $types[$type] ?? $types['info'];
    $icon = getIcon($config[3], 16, '', $config[1]);
    
    $html = '<div class="notification notification-' . $type . '" style="background: ' . $config[0] . '; color: ' . $config[1] . '; border: 1px solid ' . $config[2] . '40; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">';
    $html .= '<div style="display: flex; align-items: center; gap: 8px;">';
    $html .= $icon;
    $html .= '<span>' . htmlspecialchars($message) . '</span>';
    $html .= '</div>';
    
    if ($dismissible) {
        $close_icon = getIcon('x', 16, '', $config[1]);
        $html .= '<button onclick="this.parentElement.remove()" style="background: none; border: none; color: ' . $config[1] . '; cursor: pointer; padding: 0; margin-left: 12px; display: flex; align-items: center;">' . $close_icon . '</button>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Check if date is today
 */
function isToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

/**
 * Check if date is this week
 */
function isThisWeek($date) {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $check_date = date('Y-m-d', strtotime($date));
    
    return $check_date >= $week_start && $check_date <= $week_end;
}

/**
 * Generate meta tags for SEO
 */
function generateMetaTags($title, $description, $image = null, $url = null) {
    $site_name = 'Helpify';
    $default_image = '/assets/images/logo.png';
    
    $html = '<title>' . htmlspecialchars($title . ' | ' . $site_name) . '</title>' . "\n";
    $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    
    // Open Graph tags
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta property="og:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . $site_name . '">' . "\n";
    
    if ($url) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    }
    
    // Twitter Card tags
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta name="twitter:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    
    return $html;
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $include_cents = false) {
    if ($include_cents || ($amount - floor($amount)) > 0) {
        return '$' . number_format($amount, 2);
    } else {
        return '$' . number_format($amount, 0);
    }
}

/**
 * Generate user avatar
 */
function generateAvatar($name, $size = 40, $background_color = null) {
    $initials = strtoupper(substr($name, 0, 1));
    
    if (!$background_color) {
        // Generate color based on name
        $colors = ['#667eea', '#764ba2', '#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#8b5cf6'];
        $background_color = $colors[ord($initials) % count($colors)];
    }
    
    return '<div style="width: ' . $size . 'px; height: ' . $size . 'px; background: ' . $background_color . '; border-radius: ' . ($size * 0.25) . 'px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: ' . ($size * 0.4) . 'px;">' . $initials . '</div>';
}

/**
 * Get task status badge HTML
 */
function getTaskStatusBadge($status) {
    $badges = [
        'open' => ['Open', '#10b981', '#dcfce7', '🟢'],
        'pending' => ['Pending', '#f59e0b', '#fed7aa', '🟡'],
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe', '🔵'],
        'completed' => ['Completed', '#6b7280', '#f3f4f6', '✅'],
        'cancelled' => ['Cancelled', '#ef4444', '#fee2e2', '❌']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6', '⚪'];
    return '<span style="background: ' . $badge[2] . '; color: ' . $badge[1] . '; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">' . $badge[3] . ' ' . $badge[0] . '</span>';
}

/**
 * Validate and sanitize file upload
 */
function validateFileUpload($file, $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded.';
                break;
            default:
                $errors[] = 'File upload failed.';
        }
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File is too large. Maximum size is ' . formatBytes($max_size) . '.';
    }
    
    // Check file type
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_types);
    }
    
    // Check if it's actually an image (for image uploads)
    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            $errors[] = 'File is not a valid image.';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'extension' => $file_extension,
        'size' => $file['size']
    ];
}

/**
 * Format bytes to human readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Generate secure random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Validate CSRF token
 */
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(16);
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF input field
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRF()) . '">';
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length - strlen($suffix)) . $suffix;
}

/**
 * Check if user has permission for task
 */
function canAccessTask($task_id, $user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM tasks 
            WHERE id = ? AND (client_id = ? OR helper_id = ?)
            UNION
            SELECT 1 FROM applications a
            JOIN tasks t ON a.task_id = t.id
            WHERE a.task_id = ? AND a.helper_id = ?
        ");
        $stmt->execute([$task_id, $user_id, $user_id, $task_id, $user_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Log activity for debugging
 */
function logActivity($message, $data = null, $level = 'INFO') {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'message' => $message,
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'data' => $data
    ];
    
    error_log('[HELPIFY] ' . json_encode($log_entry));
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $user_id, $limit = 10, $window = 3600, $pdo = null) {
    if (!$pdo) {
        return true; // Skip if no database connection
    }
    
    try {
        // Clean old entries
        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?");
        $stmt->execute([date('Y-m-d H:i:s', time() - $window)]);
        
        // Check current count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE action = ? AND user_id = ? AND created_at > ?");
        $stmt->execute([$action, $user_id, date('Y-m-d H:i:s', time() - $window)]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit) {
            return false;
        }
        
        // Record this action
        $stmt = $pdo->prepare("INSERT INTO rate_limits (action, user_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$action, $user_id]);
        
        return true;
    } catch (PDOException $e) {
        return true; // Allow if database error
    }
}

/**
 * Generate notification HTML
 */
function generateNotification($type, $message, $dismissible = true) {
    $types = [
        'success' => ['#dcfce7', '#166534', '#10b981'],
        'error' => ['#fee2e2', '#dc2626', '#ef4444'],
        'warning' => ['#fef3c7', '#92400e', '#f59e0b'],
        'info' => ['#dbeafe', '#1e40af', '#3b82f6']
    ];
    
    $colors = $types[$type] ?? $types['info'];
    
    $html = '<div class="notification notification-' . $type . '" style="background: ' . $colors[0] . '; color: ' . $colors[1] . '; border: 1px solid ' . $colors[2] . '40; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">';
    $html .= '<span>' . htmlspecialchars($message) . '</span>';
    
    if ($dismissible) {
        $html .= '<button onclick="this.parentElement.remove()" style="background: none; border: none; color: ' . $colors[1] . '; cursor: pointer; font-size: 18px; padding: 0; margin-left: 12px;">&times;</button>';
    }
    
    $html .= '</div>';
    return $html;
}

/**
 * Check if date is today
 */
function isToday($date) {
    return date('Y-m-d', strtotime($date)) === date('Y-m-d');
}

/**
 * Check if date is this week
 */
function isThisWeek($date) {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $check_date = date('Y-m-d', strtotime($date));
    
    return $check_date >= $week_start && $check_date <= $week_end;
}

/**
 * Generate meta tags for SEO
 */
function generateMetaTags($title, $description, $image = null, $url = null) {
    $site_name = 'Helpify';
    $default_image = '/assets/images/logo.png';
    
    $html = '<title>' . htmlspecialchars($title . ' | ' . $site_name) . '</title>' . "\n";
    $html .= '<meta name="description" content="' . htmlspecialchars($description) . '">' . "\n";
    
    // Open Graph tags
    $html .= '<meta property="og:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta property="og:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta property="og:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    $html .= '<meta property="og:site_name" content="' . $site_name . '">' . "\n";
    
    if ($url) {
        $html .= '<meta property="og:url" content="' . htmlspecialchars($url) . '">' . "\n";
    }
    
    // Twitter Card tags
    $html .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $html .= '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">' . "\n";
    $html .= '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">' . "\n";
    $html .= '<meta name="twitter:image" content="' . htmlspecialchars($image ?? $default_image) . '">' . "\n";
    
    return $html;
}
?>