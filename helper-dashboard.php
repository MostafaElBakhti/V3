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

    // Get available tasks
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.title, t.description, t.budget, t.location, t.created_at,
            u.fullname as client_name
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        WHERE t.status = 'open' 
        AND t.id NOT IN (SELECT task_id FROM applications WHERE helper_id = ?)
        ORDER BY t.created_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$user_id]);
    $available_task_list = $stmt->fetchAll();

} catch (PDOException $e) {
    $app_stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0];
    $earnings_stats = ['completed_tasks' => 0, 'total_earned' => 0, 'week_earnings' => 0];
    $available_tasks = 0;
    $success_rate = 0;
    $recent_applications = [];
    $available_task_list = [];
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
        }
        
        .task-item:hover {
            background: #f1f5f9;
        }
        
        .task-item:last-child {
            margin-bottom: 0;
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
                    <div class="stat-value" style="color: white; font-size: 42px;">$<?php echo number_format($earnings_stats['total_earned'] ?? 0); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">💰 Total earnings</div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white;">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
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
                    <div class="stat-value" style="color: white; font-size: 42px;"><?php echo $available_tasks; ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">🎯 Available tasks to apply</div>
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
                    <div class="stat-value"><?php echo $app_stats['pending_applications']; ?></div>
                    <div class="stat-label">Applications pending</div>
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
                    <div class="stat-value">$<?php echo number_format($earnings_stats['week_earnings'] ?? 0); ?></div>
                    <div class="stat-label">This week's earnings</div>
                </div>
            </div>
            
            <!-- Primary Action Banner -->
            <?php if ($available_tasks > 0): ?>
            <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">🎯 <?php echo $available_tasks; ?> New Tasks Available!</h2>
                    <p style="font-size: 16px; opacity: 0.9;">Fresh opportunities are waiting. Apply now to increase your earnings!</p>
                </div>
                <a href="find-tasks.php" style="background: white; color: #3b82f6; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Find Tasks →
                </a>
            </div>
            <?php elseif ($app_stats['pending_applications'] > 0): ?>
            <div style="background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">⏳ <?php echo $app_stats['pending_applications']; ?> Applications Under Review</h2>
                    <p style="font-size: 16px; opacity: 0.9;">Clients are reviewing your applications. Check for updates and apply to more tasks!</p>
                </div>
                <a href="my-applications.php" style="background: white; color: #f59e0b; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Check Status →
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Content Grid - Prioritized Layout -->
            <div class="content-grid">
                <!-- Available Tasks - MOST IMPORTANT -->
                <div class="content-card" style="grid-column: 1 / 3;">
                    <div class="card-header">
                        <h2 class="card-title">🎯 Available Tasks - Apply Now!</h2>
                        <a href="find-tasks.php" style="color: #764ba2; text-decoration: none; font-weight: 600; font-size: 14px;">View all →</a>
                    </div>
                    
                    <?php if (empty($available_task_list)): ?>
                        <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 12px; border: 2px dashed #e9ecef;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                            <h3 style="color: #333; margin-bottom: 8px;">No available tasks right now</h3>
                            <p style="color: #666; margin-bottom: 20px;">Check back later for new opportunities, or update your profile to get better matches.</p>
                            <a href="profile.php" style="background: #764ba2; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">Update Profile</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($available_task_list as $task): ?>
                            <div class="task-item" style="background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 16px; border-left: 4px solid #764ba2;">
                                <div class="task-header">
                                    <div class="task-title" style="font-size: 16px; font-weight: 700;"><?php echo sanitize($task['title']); ?></div>
                                    <div class="task-budget" style="font-size: 18px; font-weight: 700;">$<?php echo number_format($task['budget'], 0); ?></div>
                                </div>
                                <div class="task-meta" style="margin: 8px 0; color: #666;">
                                    👤 <?php echo sanitize($task['client_name']); ?> • 📍 <?php echo sanitize($task['location']); ?> • 📅 Posted <?php echo date('M j', strtotime($task['created_at'])); ?>
                                </div>
                                <div class="task-description" style="margin: 12px 0; line-height: 1.5;">
                                    <?php echo sanitize(substr($task['description'], 0, 200)); ?><?php echo strlen($task['description']) > 200 ? '...' : ''; ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px;">
                                    <div style="font-size: 12px; color: #666;">
                                        💼 <?php echo ucfirst(str_replace('_', ' ', 'open')); ?> • 🕒 <?php echo date('g:i A', strtotime($task['created_at'])); ?>
                                    </div>
                                    <a href="find-tasks.php?task_id=<?php echo $task['id']; ?>" style="background: #764ba2; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 14px;">Apply Now</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Secondary Content -->
                <div>
                    <!-- Performance Stats -->
                    <div class="content-card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <h2 class="card-title">📊 Your Performance</h2>
                        </div>
                        <div style="display: grid; gap: 16px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                <span style="font-weight: 600;">Success Rate</span>
                                <span style="font-size: 18px; font-weight: 700; color: #059669;"><?php echo $success_rate; ?>%</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                <span style="font-weight: 600;">Applications Sent</span>
                                <span style="font-size: 18px; font-weight: 700; color: #059669;"><?php echo $app_stats['total_applications']; ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8f9fa; border-radius: 8px;">
                                <span style="font-weight: 600;">Jobs Completed</span>
                                <span style="font-size: 18px; font-weight: 700; color: #059669;"><?php echo $earnings_stats['completed_tasks']; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Items -->
                    <div class="sidebar-card">
                        <h3>🎯 Action Items</h3>
                        
                        <?php if ($available_tasks > 0): ?>
                        <div class="todo-item" style="border-color: #3b82f6;">
                            <div class="todo-icon" style="background: #3b82f6;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4 style="color: #3b82f6;">Apply to <?php echo $available_tasks; ?> new tasks</h4>
                                <p>Fresh opportunities available</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($app_stats['pending_applications'] > 0): ?>
                        <div class="todo-item" style="border-color: #f59e0b;">
                            <div class="todo-icon" style="background: #f59e0b;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4 style="color: #f59e0b;">Check <?php echo $app_stats['pending_applications']; ?> pending applications</h4>
                                <p>Waiting for client response</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Reply to client messages</h4>
                                <p>Stay responsive to clients</p>
                            </div>
                        </div>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Update your portfolio</h4>
                                <p>Add recent work examples</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Applications -->
            <div class="content-card" style="margin-top: 32px;">
                <div class="card-header">
                    <h2 class="card-title">📄 Your Recent Applications</h2>
                    <a href="my-applications.php" style="color: #764ba2; text-decoration: none; font-weight: 600; font-size: 14px;">View all →</a>
                </div>
                
                <?php if (empty($recent_applications)): ?>
                    <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 12px;">
                        <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                        <h3 style="color: #333; margin-bottom: 8px;">No applications yet</h3>
                        <p style="color: #666; margin-bottom: 20px;">Start applying to tasks to build your portfolio and earn money.</p>
                        <a href="find-tasks.php" style="background: #764ba2; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">Find Tasks to Apply</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_applications as $app): ?>
                        <div class="application-item" style="background: #f8f9fa; border-radius: 12px; padding: 16px; margin-bottom: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="applicant-avatar">
                                    <?php echo strtoupper(substr($app['client_name'], 0, 1)); ?>
                                </div>
                                <div class="application-info" style="flex: 1;">
                                    <div class="applicant-name"><?php echo sanitize($app['client_name']); ?></div>
                                    <div class="application-task">Task: <?php echo sanitize($app['title']); ?></div>
                                    <div style="font-size: 12px; color: #059669; font-weight: 600;">💰 Your bid: $<?php echo number_format($app['bid_amount'], 0); ?> / Budget: $<?php echo number_format($app['budget'], 0); ?></div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="margin-bottom: 8px;">
                                        <span style="padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; 
                                            <?php 
                                            switch($app['status']) {
                                                case 'pending': echo 'background: #fff3cd; color: #856404;'; break;
                                                case 'accepted': echo 'background: #dcfce7; color: #166534;'; break;
                                                case 'rejected': echo 'background: #fee2e2; color: #dc2626;'; break;
                                            }
                                            ?>">
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </div>
                                    <div class="application-time" style="font-size: 12px; color: #666;">
                                        <?php 
                                        $time_diff = time() - strtotime($app['created_at']);
                                        if ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . ' min ago';
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . ' hrs ago';
                                        } else {
                                            echo date('M j', strtotime($app['created_at']));
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>="14" rx="2" ry="2"/>
                                    <line x1="8" y1="21" x2="16" y2="21"/>
                                    <line x1="12" y1="17" x2="12" y2="21"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Complete home repair job</h4>
                                <p>Today at 2:00 pm</p>
                            </div>
                        </div>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Update profile portfolio</h4>
                                <p>Tomorrow at 11:00 am</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Client Meeting -->
                    <div class="meeting-card">
                        <div class="meeting-status">
                            <div class="status-dot"></div>
                            <span style="font-size: 12px; font-weight: 600;">Today at 4:00 PM</span>
                        </div>
                        <h3 style="margin-bottom: 8px;">Client consultation</h3>
                        <p style="font-size: 14px; opacity: 0.9;">You have been invited to discuss a gardening project with your client Sarah Johnson.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>