<?php
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Fetch helper statistics and data
try {
    // Get application stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
        FROM applications 
        WHERE helper_id = ?
    ");
    $stmt->execute([$user_id]);
    $app_stats = $stmt->fetch();

    // Get earnings stats - Fixed query with proper column reference
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as completed_tasks,
            COALESCE(SUM(budget), 0) as total_earned,
            COALESCE(SUM(CASE WHEN updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN budget ELSE 0 END), 0) as week_earnings
        FROM tasks 
        WHERE helper_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $earnings_stats = $stmt->fetch();

    // Simplified query for available tasks - this should work better
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.email as client_email,
            (SELECT COUNT(*) FROM applications WHERE task_id = t.id) as total_applications
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        WHERE t.status = 'open' 
        AND t.client_id != ? 
        AND t.id NOT IN (
            SELECT DISTINCT task_id 
            FROM applications 
            WHERE helper_id = ? 
            AND status IN ('pending', 'accepted')
        )
        ORDER BY t.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $user_id]);
    $available_task_list = $stmt->fetchAll();

    // Count available tasks
    $available_tasks = count($available_task_list);

    // Get success rate
    $success_rate = $app_stats['total_applications'] > 0 ? 
        round(($app_stats['accepted_applications'] / $app_stats['total_applications']) * 100) : 0;

    // Get recent applications with task details
    $stmt = $pdo->prepare("
        SELECT 
            a.id, a.status, a.bid_amount, a.created_at,
            t.title, t.budget, t.location,
            u.fullname as client_name
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON t.client_id = u.id
        WHERE a.helper_id = ? 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_applications = $stmt->fetchAll();

    // Debug logging
    error_log("Helper Dashboard Debug - User ID: $user_id");
    error_log("Available tasks found: " . count($available_task_list));
    
    // Let's also check total open tasks for comparison
    $debug_stmt = $pdo->query("SELECT COUNT(*) as total_open FROM tasks WHERE status = 'open'");
    $total_open_tasks = $debug_stmt->fetch()['total_open'];
    error_log("Total open tasks in system: $total_open_tasks");

} catch (PDOException $e) {
    error_log("Database error in helper dashboard: " . $e->getMessage());
    error_log("SQL Error: " . $e->getCode());
    
    // Set default values
    $app_stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0];
    $earnings_stats = ['completed_tasks' => 0, 'total_earned' => 0, 'week_earnings' => 0];
    $available_tasks = 0;
    $success_rate = 0;
    $recent_applications = [];
    $available_task_list = [];
}

// Handle AJAX refresh request
if (isset($_GET['refresh']) && $_GET['refresh'] === 'tasks') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'available_tasks' => $available_tasks,
        'tasks' => $available_task_list
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helper Dashboard | Helpify</title>
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
        
        /* Sidebar */
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
            opacity: 1;
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
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .tasks-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 32px;
            margin-top: 32px;
        }
        
        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .tasks-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .title-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .refresh-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .refresh-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .task-list {
            display: grid;
            gap: 20px;
        }
        
        .task-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        
        /* .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
            border-color: #e2e8f0;
        } */
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f1f5f9;
            position: relative;
        }
        
        .task-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .task-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.4;
            margin-right: 16px;
            letter-spacing: -0.01em;
            background: linear-gradient(135deg, #1a1a1a 0%, #4b5563 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .task-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .task-description {
            color: #4b5563;
            line-height: 1.8;
            margin: 20px 0 28px;
            font-size: 15px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            padding: 0 4px;
            position: relative;
            font-weight: 400;
            letter-spacing: 0.01em;
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .task-description::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to bottom, rgba(248, 250, 252, 0), #f8fafc);
            pointer-events: none;
            border-radius: 0 0 8px 8px;
        }
        
        .task-description p {
            margin: 0;
            padding: 0;
        }
        
        .task-description p:not(:last-child) {
            margin-bottom: 12px;
        }
        
        .task-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .task-detail {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
        }
        
        .task-detail svg {
            width: 20px;
            height: 20px;
            color: #3b82f6;
            flex-shrink: 0;
        }
        
        .task-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }
        
        .task-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .task-btn-primary {
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            color: white;
        }
        
        .task-btn-secondary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .task-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .client-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .client-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            flex-shrink: 0;
        }
        
        .client-name {
            font-size: 14px;
            color: #475569;
            font-weight: 500;
            flex: 1;
        }
        
        .no-tasks {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
        }
        
        .no-tasks-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .debug-info {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .sidebar.collapsed {
                width: 80px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .task-details {
                grid-template-columns: 1fr;
            }
        }

        .notification-bell {
    position: relative;
    cursor: pointer;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    animation: pulse 2s infinite;
}

.notification-badge.hidden {
    display: none;
}

/* Notification Dropdown */
.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 380px;
    max-height: 480px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.notification-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.notification-actions {
    display: flex;
    gap: 8px;
}

.notification-action-btn {
    padding: 6px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.notification-action-btn:hover {
    background: #e2e8f0;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: 20px;
    right: 20px;
    width: 8px;
    height: 8px;
    background: #3b82f6;
    border-radius: 50%;
}

.notification-content {
    display: flex;
    gap: 12px;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
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

.notification-text {
    flex: 1;
}

.notification-message {
    font-size: 14px;
    color: #1a1a1a;
    line-height: 1.4;
    margin-bottom: 4px;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-time {
    font-size: 12px;
    color: #64748b;
}

.notification-delete {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    opacity: 0;
    transition: all 0.2s;
}

.notification-item:hover .notification-delete {
    opacity: 1;
}

.notification-delete:hover {
    background: #fef2f2;
    color: #ef4444;
}

.notification-empty {
    padding: 40px 24px;
    text-align: center;
    color: #64748b;
}

.notification-empty-icon {
    width: 48px;
    height: 48px;
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.notification-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    text-align: center;
}

.view-all-btn {
    color: #3b82f6;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s;
}

.view-all-btn:hover {
    background: #eff6ff;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    pointer-events: none;
}

.toast {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-left: 4px solid #3b82f6;
    min-width: 300px;
    max-width: 400px;
    opacity: 0;
    transform: translateX(400px);
    transition: all 0.3s ease;
    pointer-events: auto;
    position: relative;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast.success {
    border-left-color: #10b981;
}

.toast.error {
    border-left-color: #ef4444;
}

.toast.warning {
    border-left-color: #f59e0b;
}

.toast-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.toast-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.toast-text {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 4px;
    font-size: 14px;
}

.toast-message {
    color: #64748b;
    font-size: 13px;
    line-height: 1.4;
}

.toast-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.toast-close:hover {
    background: #f1f5f9;
    color: #64748b;
}

/* Pulse animation for notification badge */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

@media (max-width: 768px) {
    .notification-dropdown {
        width: calc(100vw - 32px);
        right: -100px;
    }
    
    .toast-container {
        top: 10px;
        right: 10px;
        left: 10px;
    }
    
    .toast {
        min-width: auto;
        max-width: none;
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
            
            <a href="helper-dashboard.php" class="nav-item active">
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
            
            <a href="settings.php" class="nav-item" style="margin-top: auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <span class="nav-text">Settings</span>
            </a>

            <a href="logout.php" class="nav-item" style="margin-top: 16px; color: #ef4444;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
                <span class="nav-text">Logout</span>
            </a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
<!-- Header -->
<div class="header">
    <h1 class="greeting">Good morning, <?php echo explode(' ', $fullname)[0]; ?>!</h1>
    <div class="header-actions">
        
        <div class="user-avatar">
            <?php echo strtoupper(substr($fullname, 0, 1)); ?>
        </div>
    </div>
</div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color: white;">$<?php echo number_format($earnings_stats['total_earned'] ?? 0); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">Total earnings</div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white;">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color: white;" id="availableTasksCount"><?php echo $available_tasks; ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">Available tasks</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $success_rate; ?>%</div>
                    <div class="stat-label">Success rate</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $app_stats['pending_applications']; ?></div>
                    <div class="stat-label">Applications pending</div>
                </div>
            </div>
            
            <!-- Primary Action Banner -->
            <?php if ($available_tasks > 0): ?>
            <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <?php echo $available_tasks; ?> New Tasks Available!
                    </h2>
                    <p style="font-size: 16px; opacity: 0.9;">Fresh opportunities are waiting. Apply now to increase your earnings!</p>
                </div>
                <button onclick="refreshTasks()" style="background: white; color: #3b82f6; padding: 16px 32px; border-radius: 12px; border: none; font-weight: 700; font-size: 16px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Refresh Tasks
                </button>
            </div>
            <?php elseif ($app_stats['pending_applications'] > 0): ?>
            <div style="background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                        <?php echo $app_stats['pending_applications']; ?> Applications Under Review
                    </h2>
                    <p style="font-size: 16px; opacity: 0.9;">Clients are reviewing your applications. Check for updates and apply to more tasks!</p>
                </div>
                <a href="my-applications.php" style="background: white; color: #f59e0b; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Check Status →
                </a>
            </div>
            <?php else: ?>
            <div style="background: linear-gradient(135deg, #6b7280, #4b5563); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        Looking for New Tasks
                    </h2>
                    <p style="font-size: 16px; opacity: 0.9;">No new tasks available right now. We'll notify you when opportunities arise!</p>
                </div>
                <button onclick="refreshTasks()" style="background: white; color: #4b5563; padding: 16px 32px; border-radius: 12px; border: none; font-weight: 700; font-size: 16px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Refresh Now
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Available Tasks Section -->
            <div class="tasks-section">
                <div class="tasks-header">
                    <h2 class="tasks-title">
                        <div class="title-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="12" cy="12" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                        </div>
                        Available Tasks
                    </h2>
                    <button class="refresh-btn" onclick="refreshTasks()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"/>
                            <polyline points="1 20 1 14 7 14"/>
                            <path d="m3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                        Refresh
                    </button>
                </div>
                
                <!-- Debug Info - Remove this in production -->
                <?php if ($available_tasks === 0): ?>
                <div class="debug-info">
                    <strong>Debug Info:</strong> No tasks found for user ID <?php echo $user_id; ?>. 
                    <?php if (isset($total_open_tasks)): ?>
                    Total open tasks in system: <?php echo $total_open_tasks; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="task-list" id="taskList">
                    <?php if (empty($available_task_list)): ?>
                        <div class="no-tasks">
                            <div class="no-tasks-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <circle cx="12" cy="12" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                            </div>
                            <h3 style="color: #333; margin-bottom: 12px; font-size: 24px; font-weight: 600;">No Available Tasks</h3>
                            <p style="color: #666; margin-bottom: 24px; font-size: 16px; line-height: 1.5;">
                                <?php if ($available_tasks === 0 && isset($total_open_tasks) && $total_open_tasks > 0): ?>
                                    There are <?php echo $total_open_tasks; ?> open tasks in the system, but they may be from you or you may have already applied to them.
                                <?php else: ?>
                                    No tasks available at the moment. Check back later for new opportunities!
                                <?php endif; ?>
                            </p>
                            <button onclick="refreshTasks()" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 14px 28px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                                Refresh Tasks
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($available_task_list as $task): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <span class="task-status">Open</span>
                                </div>
                                
                                <p class="task-description">
                                    <?php echo htmlspecialchars(substr($task['description'], 0, 150)) . (strlen($task['description']) > 150 ? '...' : ''); ?>
                                </p>
                                
                                <div class="task-details">
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <?php echo date('M d, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <strong style="color: #10b981;">$<?php echo number_format($task['budget'], 2); ?></strong>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <?php echo htmlspecialchars($task['location']); ?>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <?php echo $task['total_applications']; ?> Applications
                                    </div>
                                </div>
                                
                                <div class="client-info">
                                    <div class="client-avatar">
                                        <?php echo strtoupper(substr($task['client_name'], 0, 1)); ?>
                                    </div>
                                    <div class="client-name">
                                        Posted by <?php echo htmlspecialchars($task['client_name']); ?>
                                        <span style="color: #999; font-size: 12px; margin-left: 8px;">
                                            <?php 
                                            $time_diff = time() - strtotime($task['created_at']);
                                            if ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . ' min ago';
                                            } elseif ($time_diff < 86400) {
                                                echo floor($time_diff / 3600) . ' hrs ago';
                                            } else {
                                                echo date('M j', strtotime($task['created_at']));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="task-actions">
                                    <button class="task-btn task-btn-primary" onclick="viewTask(<?php echo $task['id']; ?>)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        View Details
                                    </button>
                                    <button class="task-btn task-btn-secondary" onclick="applyTask(<?php echo $task['id']; ?>)">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Apply Now
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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
        
        function viewTask(taskId) {
            window.location.href = `task-details.php?id=${taskId}`;
        }
        
        function applyTask(taskId) {
            window.location.href = `apply-task.php?id=${taskId}`;
        }
        
        function refreshTasks() {
            console.log('Refreshing tasks...');
            
            // Show loading state
            const refreshBtn = document.querySelector('.refresh-btn');
            const originalText = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px; animation: spin 1s linear infinite;"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="m3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>Loading...';
            refreshBtn.disabled = true;
            
            // Fetch updated tasks
            fetch('?refresh=tasks')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the tasks count
                        document.getElementById('availableTasksCount').textContent = data.available_tasks;
                        
                        // Refresh the page to show updated tasks
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        console.error('Failed to refresh tasks');
                        // Reset button
                        refreshBtn.innerHTML = originalText;
                        refreshBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error refreshing tasks:', error);
                    // Reset button
                    refreshBtn.innerHTML = originalText;
                    refreshBtn.disabled = false;
                });
        }
        
        // Auto-refresh every 60 seconds
        setInterval(refreshTasks, 60000);
        
        // Add spinning animation for refresh button
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>