<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get task ID from URL
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$task_id) {
    $redirect_url = $_SESSION['user_type'] === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php';
    redirect($redirect_url);
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

try {
    // Get basic task details (simplified)
    $stmt = $pdo->prepare("
        SELECT t.*, u.fullname as client_name, u.email as client_email, u.rating as client_rating, u.created_at as client_joined
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $redirect_url = $_SESSION['user_type'] === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php';
        redirect($redirect_url);
    }
    
    // Count applications (simplified)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $total_applications = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM applications WHERE task_id = ? AND status = 'pending'");
    $stmt->execute([$task_id]);
    $pending_applications = $stmt->fetch()['pending'];
    
    // Check if current user has applied (for helpers)
    $user_application = null;
    if ($user_type === 'helper') {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE task_id = ? AND helper_id = ?");
        $stmt->execute([$task_id, $user_id]);
        $user_application = $stmt->fetch();
    }
    
    // Get recent applications (simplified)
    $applications = [];
    if ($user_type === 'client' && $task['client_id'] == $user_id) {
        $stmt = $pdo->prepare("
            SELECT a.*, u.fullname as helper_name, u.rating as helper_rating
            FROM applications a
            JOIN users u ON a.helper_id = u.id
            WHERE a.task_id = ?
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$task_id]);
        $applications = $stmt->fetchAll();
    }
    
    // Get client stats (simplified)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE client_id = ?");
    $stmt->execute([$task['client_id']]);
    $client_total_tasks = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM tasks WHERE client_id = ? AND status = 'completed'");
    $stmt->execute([$task['client_id']]);
    $client_completed_tasks = $stmt->fetch()['completed'];
    
} catch (PDOException $e) {
    $redirect_url = $_SESSION['user_type'] === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php';
    redirect($redirect_url);
}

// Calculate time until task
$task_time = strtotime($task['scheduled_time']);
$current_time = time();
$time_until = $task_time - $current_time;

// Simple status badges
function getStatusBadge($status) {
    $badges = [
        'open' => ['Open', '#10b981', '#dcfce7'],
        'in_progress' => ['In Progress', '#3b82f6', '#dbeafe'],
        'completed' => ['Completed', '#6b7280', '#f3f4f6'],
        'cancelled' => ['Cancelled', '#ef4444', '#fee2e2']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6'];
    return sprintf(
        '<span style="background: %s; color: %s; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">%s</span>',
        $badge[2], $badge[1], $badge[0]
    );
}

function getApplicationStatusBadge($status) {
    $badges = [
        'pending' => ['Pending', '#f59e0b', '#fed7aa'],
        'accepted' => ['Accepted', '#10b981', '#dcfce7'],
        'rejected' => ['Rejected', '#ef4444', '#fee2e2']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6'];
    return sprintf(
        '<span style="background: %s; color: %s; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">%s</span>',
        $badge[2], $badge[1], $badge[0]
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($task['title']); ?> | Helpify</title>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 16px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 500;
            margin-bottom: 32px;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 32px;
        }
        
        .task-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .task-header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 24px;
            margin-bottom: 32px;
        }
        
        .task-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 16px;
            line-height: 1.2;
        }
        
        .task-meta {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }
        
        .task-budget {
            font-size: 28px;
            font-weight: 700;
            color: #10b981;
        }
        
        .task-location, .task-time {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-weight: 500;
        }
        
        .section {
            margin-bottom: 32px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .description {
            font-size: 16px;
            line-height: 1.6;
            color: #374151;
            background: #f8f9fa;
            padding: 24px;
            border-radius: 16px;
            border-left: 4px solid #667eea;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.2s;
        }
        
        .info-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
        }
        
        .info-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .info-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        .sidebar-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }
        
        .client-profile {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .client-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin: 0 auto 16px;
        }
        
        .client-name {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .client-rating {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .stars {
            display: flex;
            gap: 2px;
        }
        
        .star {
            color: #fbbf24;
            font-size: 18px;
        }
        
        .rating-text {
            font-size: 14px;
            color: #666;
        }
        
        .client-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .btn {
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
        }
        
        .btn-disabled {
            background: #e5e7eb;
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        .application-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        
        .applicant-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .application-info {
            flex: 1;
        }
        
        .applicant-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .application-bid {
            font-size: 18px;
            color: #10b981;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .application-time {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .application-proposal {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.4;
            margin-top: 8px;
        }
        
        .application-status {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        
        .helper-rating {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }
        
        .alert-warning {
            background: #fed7aa;
            border: 1px solid #fdba74;
            color: #c2410c;
        }
        
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .task-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .application-item {
                flex-direction: column;
                text-align: center;
            }
            
            .application-status {
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?php echo $user_type === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php'; ?>" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to Dashboard
        </a>
        
        <div class="main-grid">
            <!-- Main Task Details -->
            <div class="task-card">
                <div class="task-header">
                    <h1 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h1>
                    <div class="task-meta">
                        <div class="task-budget">$<?php echo number_format($task['budget'], 2); ?></div>
                        <?php echo getStatusBadge($task['status']); ?>
                        <div class="task-location">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <?php echo htmlspecialchars($task['location']); ?>
                        </div>
                        <div class="task-time">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12,6 12,12 16,14"/>
                            </svg>
                            <?php echo date('F j, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?>
                        </div>
                    </div>
                </div>
                
                <div class="section">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                        </svg>
                        Task Description
                    </h2>
                    <div class="description">
                        <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                    </div>
                </div>
                
                <div class="section">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Task Information
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-value"><?php echo $total_applications; ?></div>
                            <div class="info-label">Total Applications</div>
                        </div>
                        <div class="info-item">
                            <div class="info-value"><?php echo $pending_applications; ?></div>
                            <div class="info-label">Pending Review</div>
                        </div>
                        <div class="info-item">
                            <div class="info-value"><?php echo date('M j, Y', strtotime($task['created_at'])); ?></div>
                            <div class="info-label">Posted On</div>
                        </div>
                        <div class="info-item">
                            <div class="info-value">
                                <?php 
                                if ($time_until > 0) {
                                    $days = floor($time_until / 86400);
                                    if ($days > 0) {
                                        echo $days . ' day' . ($days != 1 ? 's' : '');
                                    } else {
                                        $hours = floor($time_until / 3600);
                                        echo $hours . ' hour' . ($hours != 1 ? 's' : '');
                                    }
                                } else {
                                    echo 'Overdue';
                                }
                                ?>
                            </div>
                            <div class="info-label">Time Until Task</div>
                        </div>
                    </div>
                </div>
                
                <!-- Applications Section -->
                <?php if (!empty($applications)): ?>
                <div class="section">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        Recent Applications
                    </h2>
                    
                    <?php foreach ($applications as $app): ?>
                        <div class="application-item">
                            <div class="applicant-avatar">
                                <?php echo strtoupper(substr($app['helper_name'], 0, 1)); ?>
                            </div>
                            <div class="application-info">
                                <div class="applicant-name"><?php echo htmlspecialchars($app['helper_name']); ?></div>
                                <div class="application-bid">Bid: $<?php echo number_format($app['bid_amount'], 2); ?></div>
                                <div class="application-time">Applied <?php echo date('M j, Y', strtotime($app['created_at'])); ?></div>
                                
                                <?php if (!empty($app['proposal'])): ?>
                                    <div class="application-proposal">
                                        <strong>Proposal:</strong> <?php echo htmlspecialchars(substr($app['proposal'], 0, 150)); ?>
                                        <?php if (strlen($app['proposal']) > 150): ?>...<?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="application-status">
                                <?php echo getApplicationStatusBadge($app['status']); ?>
                                <div class="helper-rating">
                                    <div class="stars">
                                        <?php
                                        $rating = round($app['helper_rating']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo '<span class="star" style="font-size: 14px;">' . ($i <= $rating ? '★' : '☆') . '</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="applications.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                            Review All Applications
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div>
                <!-- Client Profile -->
                <div class="sidebar-card">
                    <div class="client-profile">
                        <div class="client-avatar">
                            <?php echo strtoupper(substr($task['client_name'], 0, 1)); ?>
                        </div>
                        <div class="client-name"><?php echo htmlspecialchars($task['client_name']); ?></div>
                        
                        <div class="client-rating">
                            <div class="stars">
                                <?php
                                $rating = round($task['client_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<span class="star">' . ($i <= $rating ? '★' : '☆') . '</span>';
                                }
                                ?>
                            </div>
                            <span class="rating-text">Client rating</span>
                        </div>
                        
                        <div class="client-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $client_total_tasks; ?></div>
                                <div class="stat-label">Tasks Posted</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $client_completed_tasks; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        
                        <p style="font-size: 12px; color: #999; margin-top: 16px;">
                            Member since <?php echo date('F Y', strtotime($task['client_joined'])); ?>
                        </p>
                    </div>
                    
                    <!-- Action Buttons for Helpers -->
                    <?php if ($user_type === 'helper' && $task['client_id'] != $user_id): ?>
                        <div class="action-buttons">
                            <?php if ($task['status'] === 'open'): ?>
                                <?php if ($user_application): ?>
                                    <div class="alert alert-info">
                                        <strong>Application Status:</strong> <?php echo ucfirst($user_application['status']); ?><br>
                                        <small>Your bid: $<?php echo number_format($user_application['bid_amount'], 2); ?></small>
                                    </div>
                                    <button class="btn btn-disabled" disabled>
                                        Application Submitted
                                    </button>
                                <?php else: ?>
                                    <a href="apply-task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        Apply for This Task
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <strong>Task Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?><br>
                                    This task is no longer accepting applications.
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($user_type === 'helper'): ?>
                                <a href="helper-messages.php?task_id=<?php echo $task['id']; ?>&client_id=<?php echo $task['client_id']; ?>" class="btn btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Message Client
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons for Clients -->
                    <?php if ($user_type === 'client' && $task['client_id'] == $user_id): ?>
                        <div class="action-buttons">
                            <?php if ($pending_applications > 0): ?>
                                <a href="applications.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg>
                                    Review Applications (<?php echo $pending_applications; ?>)
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($task['helper_id']): ?>
                                <a href="messages.php?task_id=<?php echo $task['id']; ?>&helper_id=<?php echo $task['helper_id']; ?>" class="btn btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Message Helper
                                </a>
                            <?php endif; ?>
                            
                            <a href="my-tasks.php" class="btn btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                </svg>
                                Manage All Tasks
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.task-card, .sidebar-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>