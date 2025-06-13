<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Handle task status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $task_id = intval($_POST['task_id']);
        $action = $_POST['action'];
        
        // Verify task ownership
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND client_id = ?");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        
        if ($task) {
            switch ($action) {
                case 'cancel':
                    $stmt = $pdo->prepare("UPDATE tasks SET status = 'cancelled' WHERE id = ?");
                    $stmt->execute([$task_id]);
                    $_SESSION['success_message'] = 'Task cancelled successfully.';
                    break;
                    
                case 'reopen':
                    $stmt = $pdo->prepare("UPDATE tasks SET status = 'open' WHERE id = ?");
                    $stmt->execute([$task_id]);
                    $_SESSION['success_message'] = 'Task reopened successfully.';
                    break;
                    
                case 'mark_completed':
                    $stmt = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?");
                    $stmt->execute([$task_id]);
                    $_SESSION['success_message'] = 'Task marked as completed.';
                    break;
            }
        }
    } catch (PDOException $e) {
        error_log("Task update error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to update task.';
    }
    
    redirect('my-tasks.php');
}

// Get filter and sort parameters
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query conditions
$where_conditions = ["client_id = ?"];
$params = [$user_id];

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get all tasks with statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            COUNT(DISTINCT a.id) as application_count,
            COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_applications,
            COUNT(DISTINCT CASE WHEN a.status = 'accepted' THEN a.id END) as accepted_applications,
            MAX(a.created_at) as latest_application
        FROM tasks t
        LEFT JOIN applications a ON t.id = a.task_id
        $where_clause
        GROUP BY t.id
        ORDER BY t.$sort_by $sort_order
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Get task statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tasks,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
            SUM(budget) as total_budget,
            SUM(CASE WHEN status = 'completed' THEN budget ELSE 0 END) as spent_budget
        FROM tasks 
        WHERE client_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("My tasks query error: " . $e->getMessage());
    $tasks = [];
    $stats = ['total_tasks' => 0, 'open_tasks' => 0, 'in_progress_tasks' => 0, 'completed_tasks' => 0, 'cancelled_tasks' => 0, 'total_budget' => 0, 'spent_budget' => 0];
}

function getStatusBadge($status) {
    $badges = [
        'open' => ['Open', '#10b981', '#dcfce7', '🟢'],
        'pending' => ['Pending', '#f59e0b', '#fed7aa', '🟡'],
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe', '🔵'],
        'completed' => ['Completed', '#6b7280', '#f3f4f6', '✅'],
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
    <title>My Tasks | Helpify</title>
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
        
        /* Task Cards */
        .tasks-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
        }
        
        .tasks-grid {
            display: grid;
            gap: 20px;
        }
        
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .task-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
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
            margin-bottom: 8px;
        }
        
        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-size: 14px;
        }
        
        .meta-item svg {
            width: 16px;
            height: 16px;
        }
        
        .task-description {
            color: #4b5563;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        
        .task-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-top: 1px solid #e5e7eb;
            margin-top: 16px;
        }
        
        .stats-left {
            display: flex;
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .stat-text {
            font-size: 12px;
            color: #666;
        }
        
        .task-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
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
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
        }
        
        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
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
            
            .task-meta {
                grid-template-columns: 1fr;
            }
            
            .task-stats {
                flex-direction: column;
                gap: 12px;
            }
            
            .stats-left {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">H</div>
            
            <a href="client-dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
            </a>
            
            <a href="my-tasks.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
            </a>
            
            <a href="post-task.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
            </a>
            
            <a href="applications.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
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
                <h1 class="page-title">My Tasks</h1>
                <div class="header-actions">
                    <a href="post-task.php" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Post New Task
                    </a>
                    <a href="client-dashboard.php" class="btn btn-secondary">
                        Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_tasks']; ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['open_tasks']; ?></div>
                    <div class="stat-label">Open Tasks</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['in_progress_tasks']; ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['completed_tasks']; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_budget']); ?></div>
                    <div class="stat-label">Total Budget</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['spent_budget']); ?></div>
                    <div class="stat-label">Amount Spent</div>
                </div>
            </div>
            
            <!-- Filters and Controls -->
            <div class="controls">
                <div class="filters">
                    <div class="filter-group">
                        <label for="status-filter">Status:</label>
                        <select id="status-filter" onchange="updateFilters()">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Tasks</option>
                            <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort-filter">Sort by:</label>
                        <select id="sort-filter" onchange="updateFilters()">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                            <option value="scheduled_time" <?php echo $sort_by === 'scheduled_time' ? 'selected' : ''; ?>>Scheduled Time</option>
                            <option value="budget" <?php echo $sort_by === 'budget' ? 'selected' : ''; ?>>Budget</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title</option>
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
                    Showing <?php echo count($tasks); ?> of <?php echo $stats['total_tasks']; ?> tasks
                </div>
            </div>
            
            <!-- Tasks List -->
            <div class="tasks-container">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h3>No tasks found</h3>
                        <p>
                            <?php if ($status_filter === 'all'): ?>
                                You haven't posted any tasks yet.
                            <?php else: ?>
                                No tasks match your current filter.
                            <?php endif; ?>
                        </p>
                        <a href="post-task.php" class="btn btn-primary" style="margin-top: 16px;">
                            Post Your First Task
                        </a>
                    </div>
                <?php else: ?>
                    <div class="tasks-grid">
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <div>
                                        <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                        <?php echo getStatusBadge($task['status']); ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 20px; font-weight: 700; color: #10b981;">
                                            $<?php echo number_format($task['budget'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="task-meta">
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        <?php echo htmlspecialchars($task['location']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                        <?php echo date('M j, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        Posted <?php echo date('M j, Y', strtotime($task['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="task-description">
                                    <?php echo htmlspecialchars(substr($task['description'], 0, 150)); ?>
                                    <?php if (strlen($task['description']) > 150): ?>...<?php endif; ?>
                                </div>
                                
                                <div class="task-stats">
                                    <div class="stats-left">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $task['application_count']; ?></div>
                                            <div class="stat-text">Applications</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $task['pending_applications']; ?></div>
                                            <div class="stat-text">Pending</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo $task['accepted_applications']; ?></div>
                                            <div class="stat-text">Accepted</div>
                                        </div>
                                    </div>
                                    
                                    <div class="task-actions">
                                        <a href="task-details.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary btn-small">
                                            View Details
                                        </a>
                                        
                                        <?php if ($task['application_count'] > 0): ?>
                                            <a href="applications.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary btn-small">
                                                Review Applications (<?php echo $task['pending_applications']; ?>)
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($task['status'] === 'open'): ?>
                                            <button onclick="confirmAction(<?php echo $task['id']; ?>, 'cancel', 'cancel this task')" class="btn btn-secondary btn-small">
                                                Cancel
                                            </button>
                                        <?php elseif ($task['status'] === 'cancelled'): ?>
                                            <button onclick="confirmAction(<?php echo $task['id']; ?>, 'reopen', 'reopen this task')" class="btn btn-primary btn-small">
                                                Reopen
                                            </button>
                                        <?php elseif ($task['status'] === 'in_progress'): ?>
                                            <button onclick="confirmAction(<?php echo $task['id']; ?>, 'mark_completed', 'mark this task as completed')" class="btn btn-primary btn-small">
                                                Mark Complete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Hidden form for task actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="task_id" id="actionTaskId">
        <input type="hidden" name="action" id="actionType">
    </form>
    
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
        
        function confirmAction(taskId, action, actionText) {
            if (confirm(`Are you sure you want to ${actionText}?`)) {
                document.getElementById('actionTaskId').value = taskId;
                document.getElementById('actionType').value = action;
                document.getElementById('actionForm').submit();
            }
        }
        
        // Auto-refresh every 30 seconds if there are pending applications
        <?php if (array_sum(array_column($tasks, 'pending_applications')) > 0): ?>
        setInterval(function() {
            // Only refresh if page is visible
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>