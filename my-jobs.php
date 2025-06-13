<?php
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'scheduled_time';
$sort_order = $_GET['order'] ?? 'ASC';

// Build query conditions
$where_conditions = ["t.helper_id = ?"];
$params = [$user_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get jobs (tasks where user is the assigned helper)
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            u.total_ratings as client_total_ratings,
            a.bid_amount,
            a.created_at as application_date
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        LEFT JOIN applications a ON t.id = a.task_id AND a.helper_id = t.helper_id
        $where_clause
        ORDER BY t.$sort_by $sort_order
    ");
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();
    
    // Get job statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN status = 'completed' THEN budget ELSE 0 END) as total_earnings,
            SUM(CASE WHEN status = 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN budget ELSE 0 END) as month_earnings
        FROM tasks 
        WHERE helper_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("My jobs query error: " . $e->getMessage());
    $jobs = [];
    $stats = ['total_jobs' => 0, 'active_jobs' => 0, 'completed_jobs' => 0, 'total_earnings' => 0, 'month_earnings' => 0];
}

function getJobStatusBadge($status) {
    $badges = [
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe', '🔄'],
        'completed' => ['Completed', '#10b981', '#dcfce7', '✅'],
        'cancelled' => ['Cancelled', '#ef4444', '#fee2e2', '❌']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6', '⚪'];
    return sprintf(
        '<span style="background: %s; color: %s; padding: 6px 12px; border-radius: 16px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">%s %s</span>',
        $badge[2], $badge[1], $badge[3], $badge[0]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs | Helpify</title>
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
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            color: white;
            font-size: 32px;
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
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
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        /* Filters */
        .controls {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .filters {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }
        
        /* Jobs Container */
        .jobs-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
        }
        
        .job-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .job-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .job-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .client-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .client-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .job-earnings {
            text-align: right;
        }
        
        .earnings-amount {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 4px;
        }
        
        .earnings-label {
            font-size: 12px;
            color: #666;
        }
        
        .job-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .meta-item svg {
            width: 16px;
            height: 16px;
        }
        
        .job-description {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            color: #4b5563;
            line-height: 1.5;
        }
        
        .job-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .progress-indicator {
            background: #e5e7eb;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 12px;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .time-remaining {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 16px;
            font-size: 12px;
            color: #92400e;
            display: inline-block;
        }
        
        .time-overdue {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .sidebar {
                display: none;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters {
                justify-content: center;
            }
            
            .job-header {
                flex-direction: column;
                gap: 16px;
            }
            
            .job-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">H</div>
            
            <a href="helper-dashboard.php" class="nav-item">
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
            
            <a href="my-jobs.php" class="nav-item active">
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
                <h1 class="page-title">My Jobs</h1>
                <div class="header-actions">
                    <a href="find-tasks.php" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        Find More Tasks
                    </a>
                    <a href="helper-dashboard.php" class="btn btn-secondary">
                        Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_jobs']; ?></div>
                    <div class="stat-label">Total Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['active_jobs']; ?></div>
                    <div class="stat-label">Active Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['completed_jobs']; ?></div>
                    <div class="stat-label">Completed Jobs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_earnings']); ?></div>
                    <div class="stat-label">Total Earnings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['month_earnings']); ?></div>
                    <div class="stat-label">This Month</div>
                </div>
            </div>
            
            <!-- Filters and Controls -->
            <div class="controls">
                <div class="filters">
                    <div class="filter-group">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" onchange="updateFilters()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Jobs</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort-filter">Sort by:</label>
                        <select id="sort-filter" onchange="updateFilters()">
                            <option value="scheduled_time" <?php echo $sort_by === 'scheduled_time' ? 'selected' : ''; ?>>Scheduled Time</option>
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                            <option value="budget" <?php echo $sort_by === 'budget' ? 'selected' : ''; ?>>Payment Amount</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="order-filter">Order:</label>
                        <select id="order-filter" onchange="updateFilters()">
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Earliest First</option>
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Latest First</option>
                        </select>
                    </div>
                </div>
                
                <div style="color: #666; font-size: 14px;">
                    Showing <?php echo count($jobs); ?> of <?php echo $stats['total_jobs']; ?> jobs
                </div>
            </div>
            
            <!-- Jobs List -->
            <div class="jobs-container">
                <?php if (empty($jobs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">💼</div>
                        <h3>No jobs found</h3>
                        <p>
                            <?php if ($status_filter === 'all'): ?>
                                You haven't been assigned to any jobs yet. Apply to tasks to get started!
                            <?php else: ?>
                                No jobs match your current filter.
                            <?php endif; ?>
                        </p>
                        <a href="find-tasks.php" class="btn btn-primary" style="margin-top: 16px;">
                            Find Tasks to Apply
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <div>
                                    <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                    <div class="client-info">
                                        <div class="client-avatar">
                                            <?php echo strtoupper(substr($job['client_name'], 0, 1)); ?>
                                        </div>
                                        <span style="font-weight: 500; color: #4b5563;">
                                            <?php echo htmlspecialchars($job['client_name']); ?>
                                        </span>
                                        <div style="margin-left: 8px;">
                                            <?php echo getJobStatusBadge($job['status']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="job-earnings">
                                    <div class="earnings-amount">$<?php echo number_format($job['budget'], 2); ?></div>
                                    <div class="earnings-label">
                                        <?php if ($job['bid_amount']): ?>
                                            Your bid: $<?php echo number_format($job['bid_amount'], 2); ?>
                                        <?php else: ?>
                                            Task payment
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php
                            // Calculate time remaining/overdue
                            $scheduled_time = strtotime($job['scheduled_time']);
                            $current_time = time();
                            $time_diff = $scheduled_time - $current_time;
                            
                            if ($job['status'] === 'in_progress'):
                                if ($time_diff > 0): ?>
                                    <div class="time-remaining">
                                        ⏰ Scheduled in <?php 
                                        if ($time_diff < 3600) {
                                            echo ceil($time_diff / 60) . ' minutes';
                                        } elseif ($time_diff < 86400) {
                                            echo ceil($time_diff / 3600) . ' hours';
                                        } else {
                                            echo ceil($time_diff / 86400) . ' days';
                                        }
                                        ?>
                                    </div>
                                <?php elseif ($time_diff > -86400): ?>
                                    <div class="time-remaining time-overdue">
                                        🚨 Overdue by <?php 
                                        $overdue = abs($time_diff);
                                        if ($overdue < 3600) {
                                            echo ceil($overdue / 60) . ' minutes';
                                        } else {
                                            echo ceil($overdue / 3600) . ' hours';
                                        }
                                        ?>
                                    </div>
                                <?php endif;
                            endif; ?>
                            
                            <div class="job-meta">
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($job['scheduled_time'])); ?>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    <?php if ($job['application_date']): ?>
                                        Applied <?php echo date('M j, Y', strtotime($job['application_date'])); ?>
                                    <?php else: ?>
                                        Direct assignment
                                    <?php endif; ?>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    <a href="messages.php?task_id=<?php echo $job['id']; ?>&with=<?php echo $job['client_id']; ?>" 
                                       style="color: #667eea; text-decoration: none;">
                                        Message Client
                                    </a>
                                </div>
                            </div>
                            
                            <div class="job-description">
                                <?php echo htmlspecialchars(substr($job['description'], 0, 200)); ?>
                                <?php if (strlen($job['description']) > 200): ?>...<?php endif; ?>
                            </div>
                            
                            <?php if ($job['status'] === 'in_progress'): ?>
                                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                    <div style="font-size: 14px; font-weight: 600; color: #0369a1; margin-bottom: 8px;">
                                        📋 Job Progress
                                    </div>
                                    <div class="progress-indicator">
                                        <div class="progress-bar" style="width: 75%;"></div>
                                    </div>
                                    <div style="font-size: 12px; color: #0284c7;">
                                        Task in progress - Complete and mark as finished when done
                                    </div>
                                </div>
                            <?php elseif ($job['status'] === 'completed'): ?>
                                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                    <div style="font-size: 14px; font-weight: 600; color: #166534; margin-bottom: 4px;">
                                        ✅ Job Completed
                                    </div>
                                    <div style="font-size: 12px; color: #15803d;">
                                        Completed on <?php echo date('M j, Y', strtotime($job['updated_at'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="job-actions">
                                <a href="task-details.php?id=<?php echo $job['id']; ?>" class="btn btn-secondary btn-small">
                                    View Details
                                </a>
                                
                                <?php if ($job['status'] === 'in_progress'): ?>
                                    <button onclick="markComplete(<?php echo $job['id']; ?>)" class="btn btn-primary btn-small">
                                        ✅ Mark Complete
                                    </button>
                                <?php elseif ($job['status'] === 'completed'): ?>
                                    <button class="btn btn-secondary btn-small" disabled>
                                        Completed
                                    </button>
                                <?php endif; ?>
                                
                                <a href="mailto:<?php echo htmlspecialchars($job['client_email']); ?>" class="btn btn-secondary btn-small">
                                    📧 Email Client
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function updateFilters() {
            const status = document.getElementById('status-filter').value;
            const sort = document.getElementById('sort-filter').value;
            const order = document.getElementById('order-filter').value;
            
            const url = new URL(window.location);
            url.searchParams.set('status', status);
            url.searchParams.set('sort', sort);
            url.searchParams.set('order', order);
            
            window.location.href = url.toString();
        }
        
        function markComplete(jobId) {
            if (confirm('Are you sure you want to mark this job as complete? This action cannot be undone.')) {
                // In a real implementation, this would make an AJAX call to update the job status
                // For now, we'll just show an alert
                alert('Job completion feature would be implemented here. This would:\n\n' +
                      '• Mark the task as completed\n' +
                      '• Process payment\n' +
                      '• Allow client to leave a review\n' +
                      '• Update your earnings');
                
                // You could implement this with:
                // fetch('update-job-status.php', {
                //     method: 'POST',
                //     headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                //     body: `job_id=${jobId}&status=completed`
                // }).then(response => response.json())
                //   .then(data => {
                //       if (data.success) {
                //           location.reload();
                //       }
                //   });
            }
        }
        
        // Auto-refresh active jobs every 30 seconds
        <?php if ($stats['active_jobs'] > 0): ?>
        setInterval(function() {
            if (!document.hidden) {
                // Only refresh if we're showing active jobs
                const statusFilter = document.getElementById('status-filter').value;
                if (statusFilter === 'all' || statusFilter === 'in_progress') {
                    location.reload();
                }
            }
        }, 30000);
        <?php endif; ?>
        
        // Enhanced card interactions
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.job-card');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 12px 30px rgba(0, 0, 0, 0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 8px 25px rgba(0, 0, 0, 0.15)';
                });
            });
        });
        
        // Highlight overdue jobs
        document.addEventListener('DOMContentLoaded', function() {
            const overdueElements = document.querySelectorAll('.time-overdue');
            overdueElements.forEach(element => {
                const card = element.closest('.job-card');
                if (card) {
                    card.style.borderLeft = '4px solid #ef4444';
                }
            });
        });
    </script>
</body>
</html>