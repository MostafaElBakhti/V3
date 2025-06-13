<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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

    // Get earnings stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as completed_tasks,
            SUM(budget) as total_earned,
            SUM(CASE WHEN completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN budget ELSE 0 END) as week_earnings
        FROM tasks 
        WHERE helper_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $earnings_stats = $stmt->fetch();

    // Get NEW tasks (posted in last 24 hours)
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.title, t.description, t.budget, t.location, t.created_at,
            u.fullname as client_name,
            TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_ago
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        WHERE t.status = 'open' 
        AND t.client_id != ?
        AND t.id NOT IN (SELECT task_id FROM applications WHERE helper_id = ?)
        AND t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY t.created_at DESC 
        LIMIT 6
    ");
    $stmt->execute([$user_id, $user_id]);
    $new_tasks = $stmt->fetchAll();

    // Get available tasks count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as available_tasks
        FROM tasks 
        WHERE status = 'open' AND client_id != ? 
        AND id NOT IN (SELECT task_id FROM applications WHERE helper_id = ?)
    ");
    $stmt->execute([$user_id, $user_id]);
    $available_tasks = $stmt->fetch()['available_tasks'];

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

    // Get all available tasks (for the general section)
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.title, t.description, t.budget, t.location, t.created_at,
            u.fullname as client_name,
            TIMESTAMPDIFF(HOUR, t.created_at, NOW()) as hours_ago
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        WHERE t.status = 'open' 
        AND t.client_id != ?
        AND t.id NOT IN (SELECT task_id FROM applications WHERE helper_id = ?)
        ORDER BY t.created_at DESC 
        LIMIT 8
    ");
    $stmt->execute([$user_id, $user_id]);
    $available_task_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $app_stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0];
    $earnings_stats = ['completed_tasks' => 0, 'total_earned' => 0, 'week_earnings' => 0];
    $available_tasks = 0;
    $success_rate = 0;
    $recent_applications = [];
    $available_task_list = [];
    $new_tasks = [];
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
            background: linear-gradient(135deg, #cccccc 0%, #2f2936 100%);
            min-height: 100vh;
            color: #1a1a1a;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 80px;
            background: #1a1a1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }
        
        .logo {
            width: 48px;
            height: 48px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 32px;
            font-weight: 700;
            color: #1a1a1a;
            font-size: 18px;
        }
        
        .nav-item {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-item svg {
            width: 24px;
            height: 24px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 32px;
            overflow-y: auto;
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
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 400px;
            gap: 32px;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .card-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .metric-container {
            display: flex;
            align-items: baseline;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .metric-value {
            font-size: 48px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1;
        }
        
        .metric-change {
            background: #dcfce7;
            color: #166534;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .metric-change.negative {
            background: #fee2e2;
            color: #dc2626;
        }
        
        /* Application Item */
        .application-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .application-item:last-child {
            border-bottom: none;
        }
        
        .applicant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #764ba2, #667eea);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .application-info {
            flex: 1;
        }
        
        .applicant-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 2px;
            font-size: 14px;
        }
        
        .application-task {
            font-size: 12px;
            color: #666;
        }
        
        .application-time {
            font-size: 12px;
            color: #666;
        }
        
        /* Task Item */
        .task-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }
        
        .task-item:hover {
            background: #f1f5f9;
        }
        
        .task-item:last-child {
            margin-bottom: 0;
        }
        
        .task-item.new-task {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border-left: 4px solid #f59e0b;
            animation: pulse-new 2s infinite;
        }
        
        @keyframes pulse-new {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        
        .new-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            animation: bounce 1s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-5px); }
            60% { transform: translateY(-3px); }
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .task-title {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 14px;
        }
        
        .task-budget {
            font-weight: 600;
            color: #059669;
            font-size: 14px;
        }
        
        .task-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .task-description {
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }
        
        /* Sidebar Cards */
        .sidebar-card {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 24px;
            color: white;
            margin-bottom: 24px;
        }
        
        .sidebar-card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .progress-bar {
            background: #333;
            height: 8px;
            border-radius: 4px;
            margin: 16px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: white;
            height: 100%;
            width: <?php echo min($success_rate, 100); ?>%;
            border-radius: 4px;
        }
        
        .todo-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #333;
        }
        
        .todo-item:last-child {
            border-bottom: none;
        }
        
        .todo-icon {
            width: 32px;
            height: 32px;
            background: #333;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .todo-info h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .todo-info p {
            font-size: 12px;
            color: #999;
        }
        
        .meeting-card {
            background: linear-gradient(135deg, #764ba2, #667eea);
            border-radius: 16px;
            padding: 20px;
            color: white;
        }
        
        .meeting-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .sidebar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">H</div>
            
            <a href="helper-dashboard.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
            </a>
            
            <a href="find-tasks.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </a>
            
            <a href="my-applications.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
            </a>
            
            <a href="my-jobs.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
            </a>
            
            <a href="messages.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </a>
            
            <a href="settings.php" class="nav-item" style="margin-top: auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
            </a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
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
            
            <!-- Stats Grid - Most Important Metrics First -->
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <svg width="24