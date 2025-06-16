<?php
// notifications-page.php - Full notifications management page
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];
$user_type = $_SESSION['user_type'];

// Get filter parameters
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $notification_ids = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];
    
    if (!empty($notification_ids) && is_array($notification_ids)) {
        $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
        $params = array_merge($notification_ids, [$user_id]);
        
        try {
            switch ($action) {
                case 'mark_read':
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id IN ($placeholders) AND user_id = ?");
                    $stmt->execute($params);
                    $success_message = count($notification_ids) . " notification(s) marked as read.";
                    break;
                    
                case 'mark_unread':
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = FALSE WHERE id IN ($placeholders) AND user_id = ?");
                    $stmt->execute($params);
                    $success_message = count($notification_ids) . " notification(s) marked as unread.";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?");
                    $stmt->execute($params);
                    $success_message = count($notification_ids) . " notification(s) deleted.";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Error performing bulk action.";
        }
    }
}

// Build query conditions
$where_conditions = ['user_id = ?'];
$params = [$user_id];

if ($filter_type !== 'all') {
    $where_conditions[] = 'type = ?';
    $params[] = $filter_type;
}

if ($filter_status !== 'all') {
    $where_conditions[] = 'is_read = ?';
    $params[] = ($filter_status === 'read') ? 1 : 0;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get total count for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notifications $where_clause");
    $count_stmt->execute($params);
    $total_notifications = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_notifications / $per_page);
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT n.*, 
               CASE 
                   WHEN n.type = 'task_status' AND n.related_id IS NOT NULL THEN 
                       (SELECT t.title FROM tasks t WHERE t.id = n.related_id LIMIT 1)
                   WHEN n.type = 'message' AND n.related_id IS NOT NULL THEN 
                       (SELECT t.title FROM tasks t WHERE t.id = n.related_id LIMIT 1)
                   WHEN n.type = 'application' AND n.related_id IS NOT NULL THEN 
                       (SELECT t.title FROM tasks t WHERE t.id = n.related_id LIMIT 1)
                   ELSE NULL
               END as related_title
        FROM notifications n
        $where_clause
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $notifications = $stmt->fetchAll();
    
    // Get notification statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN type = 'application' THEN 1 ELSE 0 END) as applications,
            SUM(CASE WHEN type = 'message' THEN 1 ELSE 0 END) as messages,
            SUM(CASE WHEN type = 'task_status' THEN 1 ELSE 0 END) as task_updates,
            SUM(CASE WHEN type = 'review' THEN 1 ELSE 0 END) as reviews
        FROM notifications 
        WHERE user_id = ?
    ");
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    $notifications = [];
    $total_notifications = 0;
    $total_pages = 0;
    $stats = ['total' => 0, 'unread' => 0, 'applications' => 0, 'messages' => 0, 'task_updates' => 0, 'reviews' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Helpify</title>
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
            color: #1a1a1a;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar styles - same as other pages */
        .sidebar {
            width: 240px;
            background: #1a1a1a;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 24px;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            transition: width 0.3s ease;
            overflow: hidden;
        }
        
        .sidebar.collapsed {
            width: 80px;
            align-items: center;
            padding: 24px 16px;
        }
        
        .sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 32px;
        }
        
        .logo {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #1a1a1a;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .logo-text {
            color: white;
            font-size: 20px;
            font-weight: 700;
            margin-left: 16px;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .sidebar.collapsed .logo-text {
            opacity: 0;
        }
        
        .sidebar-toggle {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .sidebar-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .nav-item {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
            padding: 0 12px;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-item svg {
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }
        
        .nav-text {
            margin-left: 16px;
            font-size: 14px;
            font-weight: 500;
            opacity: 1;
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }
        
        .sidebar.collapsed .nav-text {
            opacity: 0;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 32px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.collapsed {
            margin-left: 80px;
        }
        
        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .greeting {
            color: white;
            font-size: 32px;
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .header-btn {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .header-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #1a1a1a;
            cursor: pointer;
        }
        
        /* Page Header */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .title-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Statistics */
        .notification-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .stat-item:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        .stat-item.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Filters */
        .filters-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 16px;
            align-items: center;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .bulk-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .bulk-btn {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .bulk-btn:hover {
            border-color: #3b82f6;
        }
        
        /* Notifications Content */
        .notifications-content {
            background: white;
            border-radius: 20px;
            padding: 32px;
            margin-top: 32px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .content-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            position: relative;
            cursor: pointer;
        }
        
        .checkbox input {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .checkbox input:checked + .checkmark {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        
        .checkbox input:checked + .checkmark:after {
            display: block;
        }
        
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 16px;
            width: 16px;
            background: white;
            border-radius: 2px;
            transition: all 0.2s;
        }
        
        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 5px;
            top: 2px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .notifications-list {
            display: grid;
            gap: 16px;
        }
        
        .notification-item {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            transition: all 0.2s;
            position: relative;
        }
        
        .notification-item:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }
        
        .notification-item.unread {
            background: #eff6ff;
            border-color: #3b82f6;
        }
        
        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 24px;
            right: 24px;
            width: 8px;
            height: 8px;
            background: #3b82f6;
            border-radius: 50%;
        }
        
        .notification-header {
            display: flex;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .notification-checkbox {
            margin-top: 4px;
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-icon.application {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .notification-icon.message {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .notification-icon.task_status {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }
        
        .notification-icon.review {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-message {
            font-size: 16px;
            color: #1a1a1a;
            line-height: 1.5;
            margin-bottom: 8px;
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-time {
            font-size: 14px;
            color: #64748b;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
        }
        
        .notification-action-btn {
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 12px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .notification-action-btn:hover {
            background: #e2e8f0;
        }
        
        .notification-action-btn.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .notification-action-btn.primary:hover {
            background: #2563eb;
        }
        
        .notification-action-btn.danger {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }
        
        .notification-action-btn.danger:hover {
            background: #dc2626;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
        }
        
        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .pagination-btn:hover {
            background: #f8fafc;
            border-color: #3b82f6;
        }
        
        .pagination-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .empty-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .empty-description {
            font-size: 16px;
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .notification-stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .main-content.collapsed {
                margin-left: 80px;
            }
            
            .notification-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .notifications-content {
                padding: 20px;
            }
            
            .header-top {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar expanded" id="sidebar">
            <div class="sidebar-header">
                <div style="display: flex; align-items: center;">
                    <div class="logo">H</div>
                    <span class="logo-text">Helpify</span>
                </div>
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                </button>
            </div>
            
            <?php if ($user_type === 'helper'): ?>
                <a href="helper-dashboard.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                    </svg>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                <a href="find-tasks.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <span class="nav-text">Find Tasks</span>
                </a>
                
                <a href="my-applications.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14,2 14,8 20,8"/>
                    </svg>
                    <span class="nav-text">Applications</span>
                </a>
                
                <a href="my-jobs.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                        <line x1="8" y1="21" x2="16" y2="21"/>
                        <line x1="12" y1="17" x2="12" y2="21"/>
                    </svg>
                    <span class="nav-text">My Jobs</span>
                </a>
                
                <a href="helper-messages.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="nav-text">Messages</span>
                </a>
            <?php else: ?>
                <!-- Client navigation items would go here -->
            <?php endif; ?>
            
            <a href="notifications-page.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="nav-text">Notifications</span>
            </a>
            
            <a href="settings.php" class="nav-item" style="margin-top: auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <span class="nav-text">Settings</span>
            </a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Header -->
            <div class="header">
                <h1 class="greeting">Good morning, <?php echo explode(' ', $fullname)[0]; ?>!</h1>
                <div class="header-actions">
                    <button class="header-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </button>
                    
                    <!-- Notification Bell -->
                    <div class="header-btn notification-bell" style="position: relative;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </div>
                    
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($fullname, 0, 1)); ?>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <div class="header-top">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                        </div>
                        Notifications
                    </h1>
                </div>
                
                <!-- Notification Statistics -->
                <div class="notification-stats">
                    <div class="stat-item <?php echo $filter_status === 'all' && $filter_type === 'all' ? 'active' : ''; ?>" onclick="filterNotifications('all', 'all')">
                        <div class="stat-number"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-item <?php echo $filter_status === 'unread' ? 'active' : ''; ?>" onclick="filterNotifications('all', 'unread')">
                        <div class="stat-number"><?php echo $stats['unread']; ?></div>
                        <div class="stat-label">Unread</div>
                    </div>
                    <div class="stat-item <?php echo $filter_type === 'application' ? 'active' : ''; ?>" onclick="filterNotifications('application', 'all')">
                        <div class="stat-number"><?php echo $stats['applications']; ?></div>
                        <div class="stat-label">Applications</div>
                    </div>
                    <div class="stat-item <?php echo $filter_type === 'message' ? 'active' : ''; ?>" onclick="filterNotifications('message', 'all')">
                        <div class="stat-number"><?php echo $stats['messages']; ?></div>
                        <div class="stat-label">Messages</div>
                    </div>
                    <div class="stat-item <?php echo $filter_type === 'task_status' ? 'active' : ''; ?>" onclick="filterNotifications('task_status', 'all')">
                        <div class="stat-number"><?php echo $stats['task_updates']; ?></div>
                        <div class="stat-label">Task Updates</div>
                    </div>
                    <div class="stat-item <?php echo $filter_type === 'review' ? 'active' : ''; ?>" onclick="filterNotifications('review', 'all')">
                        <div class="stat-number"><?php echo $stats['reviews']; ?></div>
                        <div class="stat-label">Reviews</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="" id="filtersForm">
                        <div class="filters-row">
                            <select class="filter-select" name="type" onchange="document.getElementById('filtersForm').submit()">
                                <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="application" <?php echo $filter_type === 'application' ? 'selected' : ''; ?>>Applications</option>
                                <option value="message" <?php echo $filter_type === 'message' ? 'selected' : ''; ?>>Messages</option>
                                <option value="task_status" <?php echo $filter_type === 'task_status' ? 'selected' : ''; ?>>Task Updates</option>
                                <option value="review" <?php echo $filter_type === 'review' ? 'selected' : ''; ?>>Reviews</option>
                            </select>
                            
                            <select class="filter-select" name="status" onchange="document.getElementById('filtersForm').submit()">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="unread" <?php echo $filter_status === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                                <option value="read" <?php echo $filter_status === 'read' ? 'selected' : ''; ?>>Read Only</option>
                            </select>
                            
                            <div class="bulk-actions">
                                <button type="button" class="bulk-btn" onclick="markAllAsRead()" <?php echo $stats['unread'] == 0 ? 'disabled' : ''; ?>>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20,6 9,17 4,12"/>
                                    </svg>
                                    Mark All Read
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Notifications Content -->
            <div class="notifications-content">
                <div class="content-header">
                    <h2 class="content-title">Your Notifications</h2>
                    <div class="select-all-container">
                        <label class="checkbox">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            <span class="checkmark"></span>
                        </label>
                        <span>Select all</span>
                    </div>
                </div>
                
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                            </svg>
                        </div>
                        <h3 class="empty-title">No Notifications Found</h3>
                        <p class="empty-description">
                            <?php if ($filter_type !== 'all' || $filter_status !== 'all'): ?>
                                No notifications match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                                You don't have any notifications yet. When you receive new notifications, they'll appear here.
                            <?php endif; ?>
                        </p>
                        <?php if ($filter_type !== 'all' || $filter_status !== 'all'): ?>
                            <a href="notifications-page.php" class="notification-action-btn primary">Clear Filters</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" id="bulkForm">
                        <div class="notifications-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="notification-header">
                                        <label class="checkbox notification-checkbox">
                                            <input type="checkbox" name="notification_ids[]" value="<?php echo $notification['id']; ?>" class="notification-select">
                                            <span class="checkmark"></span>
                                        </label>
                                        
                                        <div class="notification-icon <?php echo $notification['type']; ?>">
                                            <?php
                                            $icons = [
                                                'application' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>',
                                                'message' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                                                'task_status' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
                                                'review' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/></svg>'
                                            ];
                                            echo $icons[$notification['type']] ?? $icons['message'];
                                            ?>
                                        </div>
                                        
                                        <div class="notification-content">
                                            <div class="notification-message">
                                                <?php echo nl2br(htmlspecialchars($notification['content'])); ?>
                                                <?php if ($notification['related_title']): ?>
                                                    <br><small style="color: #64748b;">Task: <?php echo htmlspecialchars($notification['related_title']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="notification-meta">
                                                <span class="notification-time">
                                                    <?php 
                                                    $time_diff = time() - strtotime($notification['created_at']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . ' minutes ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . ' hours ago';
                                                    } else {
                                                        echo date('M j, Y \a\t g:i A', strtotime($notification['created_at']));
                                                    }
                                                    ?>
                                                </span>
                                                <div class="notification-actions">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <button type="button" class="notification-action-btn" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                            Mark as read
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php
                                                    // Generate action link based on notification type
                                                    $action_link = '#';
                                                    $action_text = 'View';
                                                    
                                                    switch ($notification['type']) {
                                                        case 'message':
                                                            if ($notification['related_id']) {
                                                                if ($user_type === 'helper') {
                                                                    $action_link = "helper-messages.php?task_id=" . $notification['related_id'];
                                                                } else {
                                                                    $action_link = "client-messages.php?task_id=" . $notification['related_id'];
                                                                }
                                                                $action_text = 'Reply';
                                                            }
                                                            break;
                                                        case 'application':
                                                            if ($user_type === 'helper') {
                                                                $action_link = 'my-applications.php';
                                                            } else {
                                                                $action_link = 'client-dashboard.php';
                                                            }
                                                            $action_text = 'View';
                                                            break;
                                                        case 'task_status':
                                                            if ($notification['related_id']) {
                                                                $action_link = "task-details.php?id=" . $notification['related_id'];
                                                                $action_text = 'View Task';
                                                            }
                                                            break;
                                                        case 'review':
                                                            if ($user_type === 'helper') {
                                                                $action_link = 'my-jobs.php';
                                                            } else {
                                                                $action_link = 'client-dashboard.php';
                                                            }
                                                            $action_text = 'View';
                                                            break;
                                                    }
                                                    ?>
                                                    
                                                    <?php if ($action_link !== '#'): ?>
                                                        <a href="<?php echo $action_link; ?>" class="notification-action-btn primary">
                                                            <?php echo $action_text; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="notification-action-btn danger" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Bulk Actions Bar -->
                        <div id="bulkActionsBar" style="display: none; margin-top: 24px; padding: 16px; background: #f8fafc; border-radius: 12px; display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span id="selectedCount">0 notifications selected</span>
                                <div style="display: flex; gap: 8px;">
                                    <button type="submit" name="bulk_action" value="mark_read" class="notification-action-btn">
                                        Mark as read
                                    </button>
                                    <button type="submit" name="bulk_action" value="mark_unread" class="notification-action-btn">
                                        Mark as unread
                                    </button>
                                    <button type="submit" name="bulk_action" value="delete" class="notification-action-btn danger" onclick="return confirm('Are you sure you want to delete the selected notifications?')">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="pagination-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="15,18 9,12 15,6"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="pagination-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9,18 15,12 9,6"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            sidebar.classList.toggle('expanded');
            mainContent.classList.toggle('collapsed');
            
            // Update toggle icon
            const toggleBtn = sidebar.querySelector('.sidebar-toggle svg');
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.innerHTML = '<path d="m9 18 6-6-6-6"/>';
            } else {
                toggleBtn.innerHTML = '<path d="m15 18-6-6 6-6"/>';
            }
        }
        
        function filterNotifications(type, status) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('type', type);
            currentUrl.searchParams.set('status', status);
            currentUrl.searchParams.delete('page'); // Reset to first page
            window.location.href = currentUrl.toString();
        }
        
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const notificationCheckboxes = document.querySelectorAll('.notification-select');
            
            notificationCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActionsBar();
        }
        
        function updateBulkActionsBar() {
            const selectedCheckboxes = document.querySelectorAll('.notification-select:checked');
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedCheckboxes.length > 0) {
                bulkActionsBar.style.display = 'block';
                selectedCount.textContent = `${selectedCheckboxes.length} notification${selectedCheckboxes.length > 1 ? 's' : ''} selected`;
            } else {
                bulkActionsBar.style.display = 'none';
            }
        }
        
        // Add event listeners to individual checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const notificationCheckboxes = document.querySelectorAll('.notification-select');
            notificationCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkActionsBar);
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 5000);
            });
        });
        
        async function markAsRead(notificationId) {
            try {
                const formData = new FormData();
                formData.append('notification_id', notificationId);
                
                const response = await fetch('notifications.php?action=mark_read', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update UI
                    const notificationItem = document.querySelector(`input[value="${notificationId}"]`).closest('.notification-item');
                    notificationItem.classList.remove('unread');
                    
                    // Remove the "Mark as read" button
                    const markReadBtn = notificationItem.querySelector('button[onclick*="markAsRead"]');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                    
                    showToast('success', 'Notification marked as read');
                } else {
                    showToast('error', 'Failed to mark notification as read');
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
                showToast('error', 'Failed to mark notification as read');
            }
        }
        
        async function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('notification_id', notificationId);
                
                const response = await fetch('notifications.php?action=delete', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Remove from UI
                    const notificationItem = document.querySelector(`input[value="${notificationId}"]`).closest('.notification-item');
                    notificationItem.style.opacity = '0';
                    notificationItem.style.transform = 'translateX(-100%)';
                    
                    setTimeout(() => {
                        notificationItem.remove();
                        
                        // Check if no notifications left
                        const remainingNotifications = document.querySelectorAll('.notification-item');
                        if (remainingNotifications.length === 0) {
                            location.reload(); // Reload to show empty state
                        }
                    }, 300);
                    
                    showToast('success', 'Notification deleted');
                } else {
                    showToast('error', 'Failed to delete notification');
                }
            } catch (error) {
                console.error('Error deleting notification:', error);
                showToast('error', 'Failed to delete notification');
            }
        }
        
        async function markAllAsRead() {
            if (!confirm('Are you sure you want to mark all notifications as read?')) {
                return;
            }
            
            try {
                const response = await fetch('notifications.php?action=mark_all_read', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update UI
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        
                        // Remove "Mark as read" buttons
                        const markReadBtn = item.querySelector('button[onclick*="markAsRead"]');
                        if (markReadBtn) {
                            markReadBtn.remove();
                        }
                    });
                    
                    // Disable the "Mark All Read" button
                    const markAllBtn = document.querySelector('button[onclick="markAllAsRead()"]');
                    if (markAllBtn) {
                        markAllBtn.disabled = true;
                        markAllBtn.style.opacity = '0.5';
                    }
                    
                    showToast('success', 'All notifications marked as read');
                } else {
                    showToast('error', 'Failed to mark all notifications as read');
                }
            } catch (error) {
                console.error('Error marking all notifications as read:', error);
                showToast('error', 'Failed to mark all notifications as read');
            }
        }
        
        function showToast(type, message) {
            // Create toast container if it doesn't exist
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container';
                toastContainer.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 10000;
                    pointer-events: none;
                `;
                document.body.appendChild(toastContainer);
            }
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.style.cssText = `
                background: white;
                border-radius: 12px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                border-left: 4px solid ${type === 'success' ? '#10b981' : '#ef4444'};
                min-width: 300px;
                max-width: 400px;
                opacity: 0;
                transform: translateX(400px);
                transition: all 0.3s ease;
                pointer-events: auto;
                position: relative;
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            
            const iconSvg = type === 'success' 
                ? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                : '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            
            toast.innerHTML = `
                ${iconSvg}
                <span style="color: #1a1a1a; font-weight: 500;">${message}</span>
                <button onclick="this.parentElement.remove()" style="position: absolute; top: 8px; right: 8px; background: none; border: none; color: #94a3b8; cursor: pointer; padding: 4px; border-radius: 4px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18"/>
                        <path d="M6 6l12 12"/>
                    </svg>
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Trigger animation
            setTimeout(() => {
                toast.style.opacity = '1';
                toast.style.transform = 'translateX(0)';
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(400px)';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + A to select all
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
                e.preventDefault();
                document.getElementById('selectAll').click();
            }
            
            // Delete key to delete selected
            if (e.key === 'Delete') {
                const selectedCheckboxes = document.querySelectorAll('.notification-select:checked');
                if (selectedCheckboxes.length > 0) {
                    document.querySelector('button[name="bulk_action"][value="delete"]').click();
                }
            }
            
            // Escape to clear selection
            if (e.key === 'Escape') {
                document.getElementById('selectAll').checked = false;
                document.querySelectorAll('.notification-select').forEach(cb => cb.checked = false);
                updateBulkActionsBar();
            }
        });
        
        // Auto-refresh every 2 minutes
        setInterval(() => {
            // Only refresh if no notifications are selected
            const selectedCheckboxes = document.querySelectorAll('.notification-select:checked');
            if (selectedCheckboxes.length === 0) {
                location.reload();
            }
        }, 120000);
    </script>
</body>
</html>