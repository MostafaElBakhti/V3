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
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query conditions
$where_conditions = ["a.helper_id = ?"];
$params = [$user_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get applications with task and client details
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            t.title as task_title,
            t.description as task_description,
            t.budget as task_budget,
            t.location as task_location,
            t.scheduled_time,
            t.status as task_status,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            u.total_ratings as client_total_ratings
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON t.client_id = u.id
        $where_clause
        ORDER BY 
            CASE a.status 
                WHEN 'pending' THEN 1 
                WHEN 'accepted' THEN 2 
                WHEN 'rejected' THEN 3 
            END,
            a.$sort_by $sort_order
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    // Get application statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
            AVG(bid_amount) as avg_bid,
            SUM(CASE WHEN status = 'accepted' THEN bid_amount ELSE 0 END) as potential_earnings
        FROM applications 
        WHERE helper_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Calculate success rate
    $success_rate = $stats['total_applications'] > 0 ? 
        round(($stats['accepted_applications'] / $stats['total_applications']) * 100) : 0;
    
} catch (PDOException $e) {
    error_log("My applications query error: " . $e->getMessage());
    $applications = [];
    $stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0, 'avg_bid' => 0, 'potential_earnings' => 0];
    $success_rate = 0;
}

function getApplicationStatusBadge($status) {
    $badges = [
        'pending' => ['Under Review', '#f59e0b', '#fed7aa', '⏳'],
        'accepted' => ['Accepted', '#10b981', '#dcfce7', '✅'],
        'rejected' => ['Not Selected', '#ef4444', '#fee2e2', '❌']
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
    <title>My Applications | Helpify</title>
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
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
        
        /* Filters and Controls */
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
        
        /* Applications */
        .applications-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
        }
        
        .application-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            position: relative;
        }
        
        .application-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .task-info h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .client-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
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
        
        .client-name {
            font-weight: 500;
            color: #4b5563;
        }
        
        .client-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: 8px;
        }
        
        .stars {
            display: flex;
            gap: 1px;
        }
        
        .star {
            color: #fbbf24;
            font-size: 12px;
        }
        
        .status-section {
            text-align: right;
        }
        
        .bid-amount {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 8px;
        }
        
        .application-meta {
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
        
        .task-description {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            color: #4b5563;
            line-height: 1.5;
        }
        
        .proposal-preview {
            background: #fefce8;
            border: 1px solid #fde047;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        
        .proposal-title {
            font-size: 14px;
            font-weight: 600;
            color: #a16207;
            margin-bottom: 8px;
        }
        
        .proposal-text {
            color: #a16207;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .application-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
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
        
        .status-indicator {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .status-pending { background: #f59e0b; }
        .status-accepted { background: #10b981; }
        .status-rejected { background: #ef4444; }
        
        .timeline-item {
            position: relative;
            padding-left: 24px;
            margin-bottom: 12px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #d1d5db;
        }
        
        .timeline-item.active::before {
            background: #10b981;
        }
        
        .timeline-text {
            font-size: 14px;
            color: #666;
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
            
            .application-header {
                flex-direction: column;
                gap: 16px;
            }
            
            .application-meta {
                grid-template-columns: 1fr;
            }
            
            .application-actions {
                justify-content: stretch;
                flex-direction: column;
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
            
            <a href="my-applications.php" class="nav-item active">
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
                <h1 class="page-title">My Applications</h1>
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
                    <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['accepted_applications']; ?></div>
                    <div class="stat-label">Accepted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $success_rate; ?>%</div>
                    <div class="stat-label">Success Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['avg_bid'] ?? 0, 0); ?></div>
                    <div class="stat-label">Average Bid</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['potential_earnings'] ?? 0, 0); ?></div>
                    <div class="stat-label">Potential Earnings</div>
                </div>
            </div>
            
            <!-- Filters and Controls -->
            <div class="controls">
                <div class="filters">
                    <div class="filter-group">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" onchange="updateFilters()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Applications</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Not Selected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort-filter">Sort by:</label>
                        <select id="sort-filter" onchange="updateFilters()">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Application Date</option>
                            <option value="bid_amount" <?php echo $sort_by === 'bid_amount' ? 'selected' : ''; ?>>Bid Amount</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="order-filter">Order:</label>
                        <select id="order-filter" onchange="updateFilters()">
                            <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                        </select>
                    </div>
                </div>
                
                <div style="color: #666; font-size: 14px;">
                    Showing <?php echo count($applications); ?> of <?php echo $stats['total_applications']; ?> applications
                </div>
            </div>
            
            <!-- Applications List -->
            <div class="applications-container">
                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📝</div>
                        <h3>No applications found</h3>
                        <p>
                            <?php if ($status_filter === 'all'): ?>
                                You haven't applied to any tasks yet.
                            <?php else: ?>
                                No applications match your current filter.
                            <?php endif; ?>
                        </p>
                        <a href="find-tasks.php" class="btn btn-primary" style="margin-top: 16px;">
                            Find Tasks to Apply
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <div class="application-card">
                            <div class="status-indicator status-<?php echo $app['status']; ?>"></div>
                            
                            <div class="application-header">
                                <div class="task-info">
                                    <h3><?php echo htmlspecialchars($app['task_title']); ?></h3>
                                    <div class="client-info">
                                        <div class="client-avatar">
                                            <?php echo strtoupper(substr($app['client_name'], 0, 1)); ?>
                                        </div>
                                        <span class="client-name"><?php echo htmlspecialchars($app['client_name']); ?></span>
                                        <div class="client-rating">
                                            <div class="stars">
                                                <?php
                                                $rating = round($app['client_rating']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo '<span class="star">' . ($i <= $rating ? '★' : '☆') . '</span>';
                                                }
                                                ?>
                                            </div>
                                            <span style="font-size: 12px; color: #666;">
                                                (<?php echo $app['client_total_ratings']; ?>)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="status-section">
                                    <?php echo getApplicationStatusBadge($app['status']); ?>
                                    <div class="bid-amount">
                                        $<?php echo number_format($app['bid_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="application-meta">
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                        <circle cx="12" cy="10" r="3"/>
                                    </svg>
                                    <?php echo htmlspecialchars($app['task_location']); ?>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    <?php echo date('M j, Y \a\t g:i A', strtotime($app['scheduled_time'])); ?>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    Budget: $<?php echo number_format($app['task_budget'], 2); ?>
                                    <?php 
                                    $percentage = ($app['bid_amount'] / $app['task_budget']) * 100;
                                    $color = $percentage <= 85 ? '#10b981' : ($percentage <= 100 ? '#3b82f6' : '#ef4444');
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                        (<?php echo round($percentage); ?>%)
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    Applied <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="task-description">
                                <strong>Task Description:</strong><br>
                                <?php echo htmlspecialchars(substr($app['task_description'], 0, 200)); ?>
                                <?php if (strlen($app['task_description']) > 200): ?>...<?php endif; ?>
                            </div>
                            
                            <div class="proposal-preview">
                                <div class="proposal-title">📝 Your Proposal</div>
                                <div class="proposal-text">
                                    <?php echo htmlspecialchars(substr($app['proposal'], 0, 150)); ?>
                                    <?php if (strlen($app['proposal']) > 150): ?>...<?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Application Timeline -->
                            <div style="background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 16px;">
                                <div style="font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 12px;">
                                    Application Timeline
                                </div>
                                <div class="timeline-item active">
                                    <div class="timeline-text">
                                        <strong>Applied</strong> - <?php echo date('M j, Y \a\t g:i A', strtotime($app['created_at'])); ?>
                                    </div>
                                </div>
                                <?php if ($app['status'] === 'accepted'): ?>
                                    <div class="timeline-item active">
                                        <div class="timeline-text">
                                            <strong>Accepted</strong> - Task started
                                        </div>
                                    </div>
                                <?php elseif ($app['status'] === 'rejected'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-text">
                                            <strong>Not Selected</strong> - Client chose another applicant
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline-item">
                                        <div class="timeline-text">
                                            <strong>Under Review</strong> - Waiting for client response
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="application-actions">
                                <a href="task-details.php?id=<?php echo $app['task_id']; ?>" class="btn btn-secondary btn-small">
                                    View Task Details
                                </a>
                                
                                <?php if ($app['status'] === 'accepted'): ?>
                                    <a href="my-jobs.php" class="btn btn-primary btn-small">
                                        ✅ View Active Job
                                    </a>
                                <?php elseif ($app['status'] === 'pending'): ?>
                                    <button class="btn btn-secondary btn-small" disabled>
                                        ⏳ Awaiting Response
                                    </button>
                                <?php endif; ?>
                                
                                <a href="mailto:<?php echo htmlspecialchars($app['client_email']); ?>" class="btn btn-secondary btn-small">
                                    📧 Contact Client
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
        
        // Auto-refresh every 30 seconds if there are pending applications
        <?php if ($stats['pending_applications'] > 0): ?>
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Enhanced card interactions
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.application-card');
            
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
        
        // Success rate calculation and display
        <?php if ($stats['total_applications'] > 0): ?>
        const successRate = <?php echo $success_rate; ?>;
        const rateElement = document.querySelector('.stat-card:nth-child(4) .stat-value');
        
        if (successRate >= 80) {
            rateElement.style.color = '#10b981';
        } else if (successRate >= 60) {
            rateElement.style.color = '#3b82f6';
        } else if (successRate >= 40) {
            rateElement.style.color = '#f59e0b';
        } else {
            rateElement.style.color = '#ef4444';
        }
        <?php endif; ?>
    </script>
</body>
</html>