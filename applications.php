<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$task_id_filter = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $application_id = intval($_POST['application_id']);
        $action = $_POST['action'];
        
        // Verify application belongs to user's task
        $stmt = $pdo->prepare("
            SELECT a.*, t.client_id, t.title as task_title 
            FROM applications a
            JOIN tasks t ON a.task_id = t.id
            WHERE a.id = ? AND t.client_id = ?
        ");
        $stmt->execute([$application_id, $user_id]);
        $application = $stmt->fetch();
        
        if ($application) {
            switch ($action) {
                case 'accept':
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Accept this application
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'accepted' WHERE id = ?");
                    $stmt->execute([$application_id]);
                    
                    // Reject all other applications for this task
                    $stmt = $pdo->prepare("
                        UPDATE applications 
                        SET status = 'rejected' 
                        WHERE task_id = ? AND id != ? AND status = 'pending'
                    ");
                    $stmt->execute([$application['task_id'], $application_id]);
                    
                    // Update task status and assign helper
                    $stmt = $pdo->prepare("
                        UPDATE tasks 
                        SET status = 'in_progress', helper_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$application['helper_id'], $application['task_id']]);
                    
                    $pdo->commit();
                    $_SESSION['success_message'] = 'Application accepted! The task is now in progress.';
                    break;
                    
                case 'reject':
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'rejected' WHERE id = ?");
                    $stmt->execute([$application_id]);
                    $_SESSION['success_message'] = 'Application rejected.';
                    break;
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Application action error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Failed to process application.';
    }
    
    redirect('applications.php' . ($task_id_filter ? "?task_id=$task_id_filter" : ''));
}

// Get applications with task and helper details
try {
    $where_conditions = ["t.client_id = ?"];
    $params = [$user_id];
    
    if ($task_id_filter) {
        $where_conditions[] = "t.id = ?";
        $params[] = $task_id_filter;
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            t.title as task_title,
            t.budget as task_budget,
            t.status as task_status,
            t.scheduled_time,
            u.fullname as helper_name,
            u.email as helper_email,
            u.rating as helper_rating,
            u.total_ratings as helper_total_ratings,
            u.bio as helper_bio,
            u.created_at as helper_joined
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON a.helper_id = u.id
        $where_clause
        ORDER BY 
            CASE a.status 
                WHEN 'pending' THEN 1 
                WHEN 'accepted' THEN 2 
                WHEN 'rejected' THEN 3 
            END,
            a.created_at DESC
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    // Get task info if filtering by task
    $task_info = null;
    if ($task_id_filter) {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND client_id = ?");
        $stmt->execute([$task_id_filter, $user_id]);
        $task_info = $stmt->fetch();
    }
    
    // Get application statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications,
            AVG(a.bid_amount) as avg_bid
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        WHERE t.client_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Applications query error: " . $e->getMessage());
    $applications = [];
    $stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0, 'avg_bid' => 0];
}

function getApplicationStatusBadge($status) {
    $badges = [
        'pending' => ['Pending Review', '#f59e0b', '#fed7aa', '⏳'],
        'accepted' => ['Accepted', '#10b981', '#dcfce7', '✅'],
        'rejected' => ['Rejected', '#ef4444', '#fee2e2', '❌']
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
    <title>Applications | Helpify</title>
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
        
        .btn-danger {
            background: #ef4444;
            color: white;
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
        
        /* Task Filter */
        .task-filter {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
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
        
        .helper-info {
            display: flex;
            gap: 16px;
        }
        
        .helper-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        .helper-details h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .helper-rating {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }
        
        .stars {
            display: flex;
            gap: 2px;
        }
        
        .star {
            color: #fbbf24;
            font-size: 16px;
        }
        
        .rating-text {
            font-size: 14px;
            color: #666;
        }
        
        .application-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
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
        
        .bid-amount {
            font-size: 24px;
            font-weight: 700;
            color: #10b981;
        }
        
        .proposal-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .proposal-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .proposal-text {
            color: #4b5563;
            line-height: 1.6;
            white-space: pre-line;
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
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }
        
        .modal-header {
            margin-bottom: 16px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .sidebar {
                display: none;
            }
            
            .application-header {
                flex-direction: column;
                gap: 16px;
            }
            
            .application-actions {
                justify-content: stretch;
                flex-direction: column;
            }
            
            .helper-info {
                flex-direction: column;
                text-align: center;
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
            
            <a href="my-tasks.php" class="nav-item">
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
            
            <a href="applications.php" class="nav-item active">
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
                <h1 class="page-title">Applications</h1>
                <div class="header-actions">
                    <?php if ($task_id_filter): ?>
                        <a href="applications.php" class="btn btn-secondary">
                            View All Applications
                        </a>
                    <?php endif; ?>
                    <a href="my-tasks.php" class="btn btn-secondary">
                        My Tasks
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
            
            <!-- Task Filter Info -->
            <?php if ($task_info): ?>
                <div class="task-filter">
                    <h3 style="margin-bottom: 8px;">Showing applications for:</h3>
                    <h2 style="color: #1a1a1a; margin-bottom: 4px;"><?php echo htmlspecialchars($task_info['title']); ?></h2>
                    <p style="color: #666;">Budget: $<?php echo number_format($task_info['budget'], 2); ?> • Status: <?php echo ucfirst($task_info['status']); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['accepted_applications']; ?></div>
                    <div class="stat-label">Accepted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['rejected_applications']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['avg_bid'] ?? 0, 0); ?></div>
                    <div class="stat-label">Average Bid</div>
                </div>
            </div>
            
            <!-- Applications List -->
            <div class="applications-container">
                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h3>No applications found</h3>
                        <p>
                            <?php if ($task_id_filter): ?>
                                This task hasn't received any applications yet.
                            <?php else: ?>
                                You haven't received any applications for your tasks yet.
                            <?php endif; ?>
                        </p>
                        <?php if (!$task_id_filter): ?>
                            <a href="post-task.php" class="btn btn-primary" style="margin-top: 16px;">
                                Post a New Task
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <div class="application-card">
                            <div class="application-header">
                                <div class="helper-info">
                                    <div class="helper-avatar">
                                        <?php echo strtoupper(substr($app['helper_name'], 0, 1)); ?>
                                    </div>
                                    <div class="helper-details">
                                        <h3><?php echo htmlspecialchars($app['helper_name']); ?></h3>
                                        <div class="helper-rating">
                                            <div class="stars">
                                                <?php
                                                $rating = round($app['helper_rating']);
                                                for ($i = 1; $i <= 5; $i++) {
                                                    echo '<span class="star">' . ($i <= $rating ? '★' : '☆') . '</span>';
                                                }
                                                ?>
                                            </div>
                                            <span class="rating-text">
                                                (<?php echo $app['helper_total_ratings']; ?> review<?php echo $app['helper_total_ratings'] != 1 ? 's' : ''; ?>)
                                            </span>
                                        </div>
                                        <div style="font-size: 14px; color: #666;">
                                            Member since <?php echo date('F Y', strtotime($app['helper_joined'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <?php echo getApplicationStatusBadge($app['status']); ?>
                                    <div class="bid-amount" style="margin-top: 8px;">
                                        $<?php echo number_format($app['bid_amount'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Task Info (if not filtering by task) -->
                            <?php if (!$task_id_filter): ?>
                                <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                    <div style="font-size: 14px; color: #0369a1;">
                                        <strong>Task:</strong> <?php echo htmlspecialchars($app['task_title']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #0284c7; margin-top: 4px;">
                                        Budget: $<?php echo number_format($app['task_budget'], 2); ?> • 
                                        Scheduled: <?php echo date('M j, Y', strtotime($app['scheduled_time'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="application-meta">
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    Applied <?php echo date('M j, Y \a\t g:i A', strtotime($app['created_at'])); ?>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    Bid vs Budget: 
                                    <?php 
                                    $percentage = ($app['bid_amount'] / $app['task_budget']) * 100;
                                    $color = $percentage <= 85 ? '#10b981' : ($percentage <= 100 ? '#3b82f6' : '#ef4444');
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 600;">
                                        <?php echo round($percentage); ?>%
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    <a href="mailto:<?php echo htmlspecialchars($app['helper_email']); ?>" style="color: #667eea; text-decoration: none;">
                                        Contact Helper
                                    </a>
                                </div>
                            </div>
                            
                            <div class="proposal-section">
                                <div class="proposal-title">💬 Helper's Proposal</div>
                                <div class="proposal-text"><?php echo nl2br(htmlspecialchars($app['proposal'])); ?></div>
                            </div>
                            
                            <?php if ($app['helper_bio']): ?>
                                <div style="background: #fefce8; border: 1px solid #fde047; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                                    <div style="font-size: 14px; font-weight: 600; color: #a16207; margin-bottom: 4px;">About the Helper</div>
                                    <div style="font-size: 14px; color: #a16207;"><?php echo htmlspecialchars($app['helper_bio']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($app['status'] === 'pending' && $app['task_status'] === 'open'): ?>
                                <div class="application-actions">
                                    <button onclick="showModal('reject', <?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['helper_name']); ?>')" class="btn btn-secondary btn-small">
                                        ❌ Reject
                                    </button>
                                    <button onclick="showModal('accept', <?php echo $app['id']; ?>, '<?php echo htmlspecialchars($app['helper_name']); ?>')" class="btn btn-primary btn-small">
                                        ✅ Accept & Start Task
                                    </button>
                                </div>
                            <?php elseif ($app['status'] === 'accepted'): ?>
                                <div style="background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px; text-align: center; color: #166534; font-weight: 600;">
                                    ✅ Application Accepted - Task is now in progress with this helper
                                </div>
                            <?php elseif ($app['status'] === 'rejected'): ?>
                                <div style="background: #fee2e2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; text-align: center; color: #dc2626; font-weight: 600;">
                                    ❌ Application Rejected
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle"></h3>
            </div>
            <div id="modalMessage"></div>
            <div class="modal-actions">
                <button onclick="hideModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="confirmAction()" class="btn btn-primary" id="confirmButton">Confirm</button>
            </div>
        </div>
    </div>
    
    <!-- Hidden form for actions -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="application_id" id="actionApplicationId">
        <input type="hidden" name="action" id="actionType">
    </form>
    
    <script>
        let currentAction = '';
        let currentApplicationId = 0;
        let currentHelperName = '';
        
        function showModal(action, applicationId, helperName) {
            currentAction = action;
            currentApplicationId = applicationId;
            currentHelperName = helperName;
            
            const modal = document.getElementById('confirmModal');
            const title = document.getElementById('modalTitle');
            const message = document.getElementById('modalMessage');
            const confirmButton = document.getElementById('confirmButton');
            
            if (action === 'accept') {
                title.textContent = 'Accept Application';
                message.innerHTML = `
                    <p>Are you sure you want to accept <strong>${helperName}</strong>'s application?</p>
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 12px; margin: 12px 0; color: #856404;">
                        <strong>⚠️ Important:</strong> This will:
                        <ul style="margin: 8px 0 0 20px;">
                            <li>Start the task and change its status to "In Progress"</li>
                            <li>Automatically reject all other pending applications</li>
                            <li>Assign this helper to your task</li>
                        </ul>
                    </div>
                `;
                confirmButton.textContent = 'Accept & Start Task';
                confirmButton.className = 'btn btn-primary';
            } else {
                title.textContent = 'Reject Application';
                message.innerHTML = `
                    <p>Are you sure you want to reject <strong>${helperName}</strong>'s application?</p>
                    <p style="color: #666; font-size: 14px; margin-top: 8px;">This action cannot be undone.</p>
                `;
                confirmButton.textContent = 'Reject Application';
                confirmButton.className = 'btn btn-danger';
            }
            
            modal.classList.add('active');
        }
        
        function hideModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }
        
        function confirmAction() {
            document.getElementById('actionApplicationId').value = currentApplicationId;
            document.getElementById('actionType').value = currentAction;
            document.getElementById('actionForm').submit();
        }
        
        // Close modal when clicking outside
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideModal();
            }
        });
        
        // Auto-refresh every 30 seconds if there are pending applications
        <?php if ($stats['pending_applications'] > 0): ?>
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 30000);
        <?php endif; ?>
        
        // Highlight new applications (could be enhanced with localStorage)
        document.addEventListener('DOMContentLoaded', function() {
            const applications = document.querySelectorAll('.application-card');
            applications.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-2px)';
                });
            });
        });
    </script>
</body>
</html> echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pending_applications']; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php