<?php
// Simple Find Tasks Page - Educational Version
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get user data from session
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get simple filters from URL
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$location_filter = isset($_GET['location']) ? trim($_GET['location']) : '';

// Initialize default values
$tasks = [];
$total_available = 0;
$min_budget = 0;
$max_budget = 0;
$avg_budget = 0;

try {
    // 1. Build simple query for available tasks
    $sql = "SELECT t.*, u.fullname as client_name 
            FROM tasks t 
            JOIN users u ON t.client_id = u.id 
            WHERE t.status = 'open' 
            AND t.client_id != ?";
    $params = [$user_id];

    // Add search filter if provided
    if (!empty($search_query)) {
        $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }

    // Add location filter if provided
    if (!empty($location_filter)) {
        $sql .= " AND t.location LIKE ?";
        $params[] = '%' . $location_filter . '%';
    }

    // Order by newest first
    $sql .= " ORDER BY t.created_at DESC LIMIT 20";

    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    // 2. For each task, get application count (simple way)
    foreach ($tasks as &$task) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE task_id = ?");
        $stmt->execute([$task['id']]);
        $task['total_applications'] = $stmt->fetch()['count'];
    }

    // 3. Get basic statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE status = 'open' AND client_id != ?");
    $stmt->execute([$user_id]);
    $total_available = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT MIN(budget) as min_budget, MAX(budget) as max_budget, AVG(budget) as avg_budget FROM tasks WHERE status = 'open' AND client_id != ?");
    $stmt->execute([$user_id]);
    $budget_stats = $stmt->fetch();
    $min_budget = $budget_stats['min_budget'] ? $budget_stats['min_budget'] : 0;
    $max_budget = $budget_stats['max_budget'] ? $budget_stats['max_budget'] : 0;
    $avg_budget = $budget_stats['avg_budget'] ? $budget_stats['avg_budget'] : 0;

} catch (PDOException $e) {
    // If error, keep default values
    error_log("Find Tasks error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Tasks | Helpify</title>
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
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .task-stats-banner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Search & Filters */
        .search-filters {
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 16px;
        }
        
        .search-input {
            width: 100%;
            padding: 16px 16px 16px 56px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .filter-input {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: white;
            transition: all 0.2s;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        
        .filter-btn {
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .filter-btn:hover {
            transform: translateY(-1px);
        }
        
        /* Tasks Container */
        .tasks-container {
            margin-top: 32px;
        }
        
        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .tasks-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .tasks-count {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
        }
        
        .tasks-grid {
            display: grid;
            gap: 24px;
        }
        
        .task-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        
        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .task-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.3;
            flex: 1;
            margin-right: 16px;
        }
        
        .task-budget {
            font-size: 28px;
            font-weight: 700;
            color: #10b981;
            text-align: right;
        }
        
        .budget-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .task-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
            font-size: 16px;
        }
        
        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 16px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
        }
        
        .meta-item svg {
            width: 20px;
            height: 20px;
            color: #10b981;
            flex-shrink: 0;
        }
        
        .client-section {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .client-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 18px;
        }
        
        .client-info {
            flex: 1;
        }
        
        .client-name {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .task-badges {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .task-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-new {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .badge-popular {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .task-actions {
            display: flex;
            gap: 12px;
        }
        
        .task-btn {
            padding: 14px 24px;
            border-radius: 12px;
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
            text-decoration: none;
        }
        
        .task-btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .task-btn-secondary {
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            color: white;
        }
        
        .task-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        
        .empty-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
        }
        
        .empty-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 16px;
        }
        
        .empty-description {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .task-stats-banner {
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
            
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .filter-actions {
                justify-content: stretch;
            }
            
            .filter-btn {
                flex: 1;
                justify-content: center;
            }
            
            .task-stats-banner {
                grid-template-columns: 1fr;
            }
            
            .task-meta {
                grid-template-columns: 1fr;
            }
            
            .page-header {
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
            
            <a href="helper-dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <a href="find-tasks.php" class="nav-item active">
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

            <!-- Page Header -->
            <div class="page-header">
                <div class="header-top">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                        </div>
                        Find Tasks
                    </h1>
                </div>
                
                <!-- Task Statistics -->
                <div class="task-stats-banner">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_available; ?></div>
                        <div class="stat-label">Available Tasks</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($avg_budget, 0); ?></div>
                        <div class="stat-label">Average Budget</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($min_budget); ?></div>
                        <div class="stat-label">Min Budget</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($max_budget); ?></div>
                        <div class="stat-label">Max Budget</div>
                    </div>
                </div>
                
                <!-- Search & Filters -->
                <div class="search-filters">
                    <form method="GET" action="">
                        <div class="search-box">
                            <svg class="search-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                            <input type="text" class="search-input" name="search" 
                                   placeholder="Search tasks by title or description..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label class="filter-label">Location</label>
                                <input type="text" class="filter-input" name="location" 
                                       placeholder="Enter city or area..." 
                                       value="<?php echo htmlspecialchars($location_filter); ?>">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="filter-btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"/>
                                        <path d="m21 21-4.35-4.35"/>
                                    </svg>
                                    Search
                                </button>
                                <a href="find-tasks.php" class="filter-btn btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18"/>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tasks Container -->
            <div class="tasks-container">
                <div class="tasks-header">
                    <div>
                        <h2 class="tasks-title">Available Tasks</h2>
                        <p class="tasks-count"><?php echo count($tasks); ?> tasks found</p>
                    </div>
                </div>
                
                <div class="tasks-grid">
                    <?php if (empty($tasks)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                </svg>
                            </div>
                            <h3 class="empty-title">No Tasks Found</h3>
                            <p class="empty-description">
                                <?php if (!empty($search_query) || !empty($location_filter)): ?>
                                    No tasks match your search. Try different keywords or clear the filters.
                                <?php else: ?>
                                    No tasks are currently available. Check back later for new opportunities!
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($search_query) || !empty($location_filter)): ?>
                                <a href="find-tasks.php" class="task-btn task-btn-primary" style="max-width: 200px; margin: 0 auto;">
                                    Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-card">
                                <div class="task-badges">
                                    <?php 
                                    // Check if task is new (created within 24 hours)
                                    $created_hours_ago = (time() - strtotime($task['created_at'])) / 3600;
                                    ?>
                                    
                                    <?php if ($created_hours_ago < 24): ?>
                                        <span class="task-badge badge-new">New</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['total_applications'] > 3): ?>
                                        <span class="task-badge badge-popular">Popular</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="task-header">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <div>
                                        <div class="task-budget">$<?php echo number_format($task['budget'], 2); ?></div>
                                        <div class="budget-label">Budget</div>
                                    </div>
                                </div>
                                
                                <p class="task-description">
                                    <?php 
                                    $description = htmlspecialchars($task['description']);
                                    echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                                    ?>
                                </p>
                                
                                <div class="task-meta">
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        <span><?php echo date('M j, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($task['location']); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <span><?php echo $task['total_applications']; ?> application<?php echo $task['total_applications'] != 1 ? 's' : ''; ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                        <span>Posted <?php 
                                        $time_ago = time() - strtotime($task['created_at']);
                                        if ($time_ago < 3600) {
                                            echo floor($time_ago / 60) . ' min ago';
                                        } elseif ($time_ago < 86400) {
                                            echo floor($time_ago / 3600) . ' hrs ago';
                                        } else {
                                            echo floor($time_ago / 86400) . ' days ago';
                                        }
                                        ?></span>
                                    </div>
                                </div>
                                
                                <div class="client-section">
                                    <div class="client-avatar">
                                        <?php echo strtoupper(substr($task['client_name'], 0, 1)); ?>
                                    </div>
                                    <div class="client-info">
                                        <div class="client-name"><?php echo htmlspecialchars($task['client_name']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="task-actions">
                                    <a href="task-details.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        View Details
                                    </a>
                                    <a href="apply-task.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Apply Now
                                    </a>
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
        }
    </script>
</body>
</html>