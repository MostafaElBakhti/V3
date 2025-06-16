<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Fetch client statistics and data
try {
    // Get task counts and spending
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tasks,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'completed' THEN budget ELSE 0 END) as total_spent,
            SUM(CASE WHEN status = 'open' THEN budget ELSE 0 END) as pending_budget
        FROM tasks 
        WHERE client_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    // Get week spending (last 7 days)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(budget), 0) as week_spending
        FROM tasks 
        WHERE client_id = ? AND status = 'completed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$user_id]);
    $week_spending = $stmt->fetch()['week_spending'];

    // Get new helpers count (applications in last 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.helper_id) as new_helpers
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        WHERE t.client_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$user_id]);
    $new_helpers = $stmt->fetch()['new_helpers'];

    // Get pending applications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_applications
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        WHERE t.client_id = ? AND a.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_apps = $stmt->fetch()['pending_applications'];

    // Get recent applications with helper details
    $stmt = $pdo->prepare("
        SELECT 
            a.id, a.proposal, a.bid_amount, a.created_at, a.status,
            u.fullname as helper_name, u.email as helper_email,
            t.title as task_title
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON a.helper_id = u.id
        WHERE t.client_id = ? 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_applications = $stmt->fetchAll();

    // Get recent tasks
    $stmt = $pdo->prepare("
        SELECT id, title, status, budget, created_at, scheduled_time
        FROM tasks 
        WHERE client_id = ? 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$user_id]);
    $recent_tasks = $stmt->fetchAll();

    // Get all tasks for the client
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            COUNT(DISTINCT a.id) as application_count,
            COUNT(DISTINCT CASE WHEN a.status = 'accepted' THEN a.id END) as accepted_applications
        FROM tasks t
        LEFT JOIN applications a ON t.id = a.task_id
        WHERE t.client_id = ?
        GROUP BY t.id
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $all_tasks = $stmt->fetchAll();

} catch (PDOException $e) {
    $stats = ['total_tasks' => 0, 'open_tasks' => 0, 'active_tasks' => 0, 'completed_tasks' => 0, 'total_spent' => 0, 'pending_budget' => 0];
    $week_spending = 0;
    $new_helpers = 0;
    $pending_apps = 0;
    $recent_applications = [];
    $recent_tasks = [];
    $all_tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | Helpify</title>
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
        
        .stat-menu {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
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
        }
        
        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .task-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            line-height: 1.3;
        }
        
        .task-status {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-open { 
            background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
            color: white; 
        }
        .status-in_progress { 
            background: linear-gradient(135deg, #f59e0b, #d97706); 
            color: white; 
        }
        .status-completed { 
            background: linear-gradient(135deg, #10b981, #059669); 
            color: white; 
        }
        .status-pending { 
            background: linear-gradient(135deg, #8b5cf6, #7c3aed); 
            color: white; 
        }
        
        .task-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 15px;
        }
        
        .task-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .task-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
        }
        
        .task-detail svg {
            width: 18px;
            height: 18px;
            color: #3b82f6;
        }
        
        .task-actions {
            display: flex;
            gap: 12px;
        }
        
        .task-btn {
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
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
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }
        
        .task-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.8) translateY(50px);
            transition: all 0.3s ease;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            position: relative;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }
        
        .modal-header {
            padding: 32px 32px 0;
            text-align: center;
        }
        
        .modal-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .modal-header p {
            color: #666;
            font-size: 16px;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border: none;
            background: #f8f9fa;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: #e9ecef;
        }
        
        .modal-body {
            padding: 32px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .budget-input {
            position: relative;
        }
        
        .budget-input::before {
            content: '$';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: #666;
            z-index: 1;
        }
        
        .budget-input input {
            padding-left: 40px;
        }
        
        .modal-footer {
            padding: 0 32px 32px;
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
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
            
            .task-details {
                grid-template-columns: 1fr;
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
            
            <a href="client-dashboard.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <a href="my-tasks.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
                <span class="nav-text">My Tasks</span>
            </a>
            
            <a href="post-task.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                <span class="nav-text">Post Task</span>
            </a>
            
            <a href="applications.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
                <span class="nav-text">Applications</span>
            </a>
            
            <a href="messages.php" class="nav-item">
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
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Header -->
            <div class="header">
                <h1 class="greeting">Good morning, <?php echo explode(' ', $fullname)[0]; ?>!</h1>
                <div class="header-actions">
                    <button class="header-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                    </button>
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
                        <button class="stat-menu" style="color: white;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="stat-value" style="color: white; font-size: 42px;">$<?php echo number_format($stats['pending_budget']); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">Active task budget</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <button class="stat-menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="stat-value"><?php echo $new_helpers; ?></div>
                    <div class="stat-label">New helpers this week</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                        <button class="stat-menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="stat-value"><?php echo $pending_apps; ?></div>
                    <div class="stat-label">Applications pending review</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="3" width="15" height="13"/>
                                <polygon points="16,6 18,6 20,20 8,20"/>
                            </svg>
                        </div>
                        <button class="stat-menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="stat-value">$<?php echo number_format($week_spending, 0); ?></div>
                    <div class="stat-label">This week's spending</div>
                </div>
            </div>
            
            <!-- Attractive Action Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
                <!-- Add Task Card -->
                <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 20px; padding: 32px; color: white; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;" 
                     onclick="openTaskModal()" 
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 20px 40px rgba(59, 130, 246, 0.3)'" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'">
                    
                    <!-- Background decoration -->
                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none;"></div>
                    <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none;"></div>
                    
                    <div style="position: relative; z-index: 2;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="16"/>
                                    <line x1="8" y1="12" x2="16" y2="12"/>
                                </svg>
                            </div>
                            <div>
                                <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">Add New Task</h3>
                                <p style="opacity: 0.9; font-size: 14px;">Get help with anything</p>
                            </div>
                        </div>
                        
                        <p style="font-size: 16px; opacity: 0.9; line-height: 1.5; margin-bottom: 24px;">
                            Post a task and connect with skilled helpers in your area. From home repairs to personal assistance.
                        </p>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 18px; font-weight: 600;">Create Task →</span>
                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <line x1="7" y1="17" x2="17" y2="7"/>
                                    <polyline points="7,7 17,7 17,17"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- See Stats Card -->
                <div style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 20px; padding: 32px; color: white; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;" 
                     onclick="window.location.href='my-tasks.php'" 
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 20px 40px rgba(16, 185, 129, 0.3)'" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'">
                    
                    <!-- Background decoration -->
                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none;"></div>
                    <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none;"></div>
                    
                    <div style="position: relative; z-index: 2;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">View Statistics</h3>
                                <p style="opacity: 0.9; font-size: 14px;">Track your progress</p>
                            </div>
                        </div>
                        
                        <p style="font-size: 16px; opacity: 0.9; line-height: 1.5; margin-bottom: 24px;">
                            Monitor your tasks, spending, and helper performance. Get insights into your productivity.
                        </p>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 18px; font-weight: 600;">View Details →</span>
                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <line x1="7" y1="17" x2="17" y2="7"/>
                                    <polyline points="7,7 17,7 17,17"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Priority Action Banner -->
            <?php if ($pending_apps > 0): ?>
            <div style="background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4m0 4h.01"/>
                        </svg>
                        <?php echo $pending_apps; ?> Applications Need Your Review!
                    </h2>
                    <p style="font-size: 16px; opacity: 0.9;">Helpers are waiting for your response. Review applications to get your tasks started.</p>
                </div>
                <a href="applications.php" style="background: white; color: #ef4444; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Review Now →
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Tasks Section -->
            <div class="tasks-section">
                <div class="tasks-header">
                    <h2 class="tasks-title">
                        <div class="title-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                        Your Tasks
                    </h2>
                    <button class="task-btn task-btn-primary" onclick="openTaskModal()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Create New Task
                    </button>
                </div>
                
                <div class="task-list">
                    <?php if (empty($all_tasks)): ?>
                        <div style="text-align: center; padding: 80px 20px; background: linear-gradient(135deg, #f8fafc, #e2e8f0); border-radius: 16px; border: 2px dashed #cbd5e1;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                </svg>
                            </div>
                            <h3 style="color: #333; margin-bottom: 12px; font-size: 24px; font-weight: 600;">No Tasks Yet</h3>
                            <p style="color: #666; margin-bottom: 24px; font-size: 16px; line-height: 1.5;">
                                Start by creating your first task. Get help with anything from home repairs to personal assistance.
                            </p>
                            <button onclick="openTaskModal()" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 14px 28px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                                Create Your First Task
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($all_tasks as $task): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <span class="task-status status-<?php echo $task['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                    </span>
                                </div>
                                
                                <p class="task-description"><?php echo htmlspecialchars(substr($task['description'], 0, 150)) . '...'; ?></p>
                                
                                <div class="task-details">
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <?php echo date('M d, Y', strtotime($task['scheduled_time'])); ?>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        $<?php echo number_format($task['budget'], 2); ?>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <?php echo $task['application_count']; ?> Applications
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
                                    <?php if ($task['status'] === 'open'): ?>
                                        <button class="task-btn task-btn-secondary" onclick="editTask(<?php echo $task['id']; ?>)">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                            Edit
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <!-- Task Creation Modal -->
        <div class="modal-overlay" id="taskModal">
            <div class="modal-content">
                <button class="modal-close" onclick="closeTaskModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                
                <div class="modal-header">
                    <h2>Create New Task</h2>
                    <p>Describe what you need help with and connect with skilled helpers.</p>
                </div>
                
                <form id="taskForm" method="POST" action="">
                    <div class="modal-body">
                        <div id="modalAlerts"></div>
                        
                        <div class="form-group">
                            <label for="task_title">Task Title</label>
                            <input type="text" id="task_title" name="title" required 
                                   placeholder="e.g., Help with garden cleanup" maxlength="255">
                            <div class="help-text">Be specific and clear about what you need help with</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_description">Task Description</label>
                            <textarea id="task_description" name="description" required 
                                      placeholder="Provide detailed information about the task, requirements, and any special instructions..."></textarea>
                            <div class="help-text">Include all important details (minimum 20 characters)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_location">Location</label>
                            <input type="text" id="task_location" name="location" required 
                                   placeholder="e.g., Downtown, New York, NY">
                            <div class="help-text">Provide the area where the task will be performed</div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="task_date">Scheduled Date</label>
                                <input type="date" id="task_date" name="scheduled_date" required>
                                <div class="help-text">When do you need this done?</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="task_time">Scheduled Time</label>
                                <input type="time" id="task_time" name="scheduled_time" required>
                                <div class="help-text">Preferred start time</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_budget">Budget</label>
                            <div class="budget-input">
                                <input type="number" id="task_budget" name="budget" required 
                                       min="10" max="10000" step="1" placeholder="100">
                            </div>
                            <div class="help-text">Set a fair budget ($10 - $10,000)</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
            // Implement task details view
            window.location.href = `task-details.php?id=${taskId}`;
        }
        
        function editTask(taskId) {
            // Implement task edit functionality
            window.location.href = `edit-task.php?id=${taskId}`;
        }
        
        function openTaskModal() {
            const modal = document.getElementById('taskModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Set minimum date to today
            document.getElementById('task_date').setAttribute('min', new Date().toISOString().split('T')[0]);
        }
        
        function closeTaskModal() {
            const modal = document.getElementById('taskModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            
            // Clear form
            document.getElementById('taskForm').reset();
            document.getElementById('modalAlerts').innerHTML = '';
        }
        
        // Close modal when clicking outside
        document.getElementById('taskModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTaskModal();
            }
        });
        
        // Handle form submission
        document.getElementById('taskForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const alertsDiv = document.getElementById('modalAlerts');
            
            // Clear previous alerts
            alertsDiv.innerHTML = '';
            
            // Basic validation
            const title = formData.get('title').trim();
            const description = formData.get('description').trim();
            const budget = parseFloat(formData.get('budget'));
            const scheduledDate = formData.get('scheduled_date');
            const scheduledTime = formData.get('scheduled_time');
            
            let errors = [];
            
            if (title.length < 5) {
                errors.push('Task title must be at least 5 characters long.');
            }
            
            if (description.length < 20) {
                errors.push('Task description must be at least 20 characters long.');
            }
            
            if (budget < 10 || budget > 10000) {
                errors.push('Budget must be between $10 and $10,000.');
            }
            
            if (!scheduledDate || !scheduledTime) {
                errors.push('Please select both date and time.');
            }
            
            // Check if date is not in the past
            if (scheduledDate && scheduledTime) {
                const scheduledDateTime = new Date(scheduledDate + ' ' + scheduledTime);
                const now = new Date();
                
                if (scheduledDateTime < now) {
                    errors.push('Scheduled date and time cannot be in the past.');
                }
            }
            
            if (errors.length > 0) {
                alertsDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin: 8px 0 0 20px;">
                            ${errors.map(error => `<li>${error}</li>`).join('')}
                        </ul>
                    </div>
                `;
                return;
            }
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Creating...';
            
            // Submit form via AJAX
            fetch('create-task-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Success!</strong> ${data.message}
                        </div>
                    `;
                    
                    // Clear form
                    this.reset();
                    
                    // Close modal and refresh page after 2 seconds
                    setTimeout(() => {
                        closeTaskModal();
                        location.reload();
                    }, 2000);
                } else {
                    alertsDiv.innerHTML = `
                        <div class="alert alert-error">
                            <strong>Error:</strong> ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                alertsDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Error:</strong> Something went wrong. Please try again.
                    </div>
                `;
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
        
        // Auto-resize textarea
        document.getElementById('task_description').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </button>
                    <button class="header