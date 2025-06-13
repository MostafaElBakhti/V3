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
    // Get task details with client information
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            u.total_ratings as client_total_ratings,
            u.bio as client_bio,
            u.created_at as client_joined,
            COUNT(DISTINCT a.id) as total_applications,
            COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_applications,
            COUNT(DISTINCT ct.id) as client_total_tasks,
            COUNT(DISTINCT CASE WHEN ct.status = 'completed' THEN ct.id END) as client_completed_tasks
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        LEFT JOIN applications a ON t.id = a.task_id
        LEFT JOIN tasks ct ON u.id = ct.client_id
        WHERE t.id = ?
        GROUP BY t.id, u.id
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        $redirect_url = $_SESSION['user_type'] === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php';
        redirect($redirect_url);
    }
    
    // Check if current user has already applied (for helpers)
    $user_application = null;
    if ($user_type === 'helper') {
        $stmt = $pdo->prepare("
            SELECT * FROM applications 
            WHERE task_id = ? AND helper_id = ?
        ");
        $stmt->execute([$task_id, $user_id]);
        $user_application = $stmt->fetch();
    }
    
    // Get recent applications for this task (show to task owner or if user is helper who applied)
    $applications = [];
    if ($user_type === 'client' && $task['client_id'] == $user_id) {
        $stmt = $pdo->prepare("
            SELECT 
                a.id, a.proposal, a.bid_amount, a.created_at, a.status,
                u.fullname as helper_name,
                u.email as helper_email,
                u.rating as helper_rating,
                u.total_ratings as helper_total_ratings,
                u.bio as helper_bio
            FROM applications a
            JOIN users u ON a.helper_id = u.id
            WHERE a.task_id = ?
            ORDER BY 
                CASE a.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'accepted' THEN 2 
                    WHEN 'rejected' THEN 3 
                END,
                a.created_at DESC
        ");
        $stmt->execute([$task_id]);
        $applications = $stmt->fetchAll();
    } elseif ($user_type === 'helper') {
        // Show limited application info for helpers (just counts and basic info)
        $stmt = $pdo->prepare("
            SELECT 
                a.id, a.bid_amount, a.created_at, a.status,
                u.fullname as helper_name,
                u.rating as helper_rating,
                u.total_ratings as helper_total_ratings
            FROM applications a
            JOIN users u ON a.helper_id = u.id
            WHERE a.task_id = ?
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$task_id]);
        $applications = $stmt->fetchAll();
    }
    
    // Get client's recent completed tasks (for reputation)
    $stmt = $pdo->prepare("
        SELECT t.title, t.budget, t.created_at, t.status
        FROM tasks t
        WHERE t.client_id = ? AND t.status = 'completed'
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$task['client_id']]);
    $client_recent_tasks = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Task details error: " . $e->getMessage());
    $redirect_url = $_SESSION['user_type'] === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php';
    redirect($redirect_url);
}

// Calculate time until task
$task_time = strtotime($task['scheduled_time']);
$current_time = time();
$time_until = $task_time - $current_time;

// Format task status
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
        '<span style="background: %s; color: %s; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">%s %s</span>',
        $badge[2], $badge[1], $badge[3], $badge[0]
    );
}

function getApplicationStatusBadge($status) {
    $badges = [
        'pending' => ['Pending Review', '#f59e0b', '#fed7aa', '⏳'],
        'accepted' => ['Accepted', '#10b981', '#dcfce7', '✅'],
        'rejected' => ['Not Selected', '#ef4444', '#fee2e2', '❌']
    ];
    
    $badge = $badges[$status] ?? ['Unknown', '#6b7280', '#f3f4f6', '⚪'];
    return sprintf(
        '<span style="background: %s; color: %s; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">%s %s</span>',
        $badge[2], $badge[1], $badge[3], $badge[0]
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
            transform: translateX(-4px);
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
        
        .task-location {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-weight: 500;
        }
        
        .task-time {
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
            position: relative;
        }
        
        .description.expandable {
            max-height: 200px;
            overflow: hidden;
            cursor: pointer;
        }
        
        .description.expandable::after {
            content: 'Click to read more...';
            position: absolute;
            bottom: 10px;
            right: 20px;
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .description.expanded {
            max-height: none;
            cursor: default;
        }
        
        .description.expanded::after {
            display: none;
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
            transition: all 0.3s;
        }
        
        .client-avatar:hover {
            transform: scale(1.05);
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
            transition: all 0.2s;
        }
        
        .stat-item:hover {
            background: #f1f5f9;
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
        
        .applications-section {
            margin-top: 32px;
        }
        
        .application-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 16px;
            transition: all 0.2s;
        }
        
        .application-item:hover {
            background: #f1f5f9;
            transform: translateY(-2px);
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
        
        .time-countdown {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .countdown-title {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .countdown-time {
            font-size: 24px;
            font-weight: 700;
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
        
        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .recent-tasks {
            margin-top: 20px;
        }
        
        .recent-task-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.2s;
        }
        
        .recent-task-item:hover {
            background: #f1f5f9;
            transform: translateX(4px);
        }
        
        .recent-task-title {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .recent-task-meta {
            font-size: 12px;
            color: #666;
        }
        
        .competition-info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .competition-title {
            font-size: 16px;
            font-weight: 600;
            color: #0369a1;
            margin-bottom: 8px;
        }
        
        .competition-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .competition-stat {
            text-align: center;
            padding: 8px;
            background: white;
            border-radius: 8px;
        }
        
        .competition-stat-value {
            font-size: 18px;
            font-weight: 700;
            color: #0369a1;
        }
        
        .competition-stat-label {
            font-size: 12px;
            color: #0284c7;
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
                    <div class="description" id="taskDescription">
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
                            <div class="info-value"><?php echo $task['total_applications']; ?></div>
                            <div class="info-label">Total Applications</div>
                        </div>
                        <div class="info-item">
                            <div class="info-value"><?php echo $task['pending_applications']; ?></div>
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
                <div class="applications-section">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <?php if ($user_type === 'client' && $task['client_id'] == $user_id): ?>
                            Applications (<?php echo count($applications); ?>)
                        <?php else: ?>
                            Recent Applications
                        <?php endif; ?>
                    </h2>
                    
                    <?php foreach ($applications as $app): ?>
                        <div class="application-item">
                            <div class="applicant-avatar">
                                <?php echo strtoupper(substr($app['helper_name'], 0, 1)); ?>
                            </div>
                            <div class="application-info">
                                <div class="applicant-name"><?php echo htmlspecialchars($app['helper_name']); ?></div>
                                <div class="application-bid">Bid: $<?php echo number_format($app['bid_amount'], 2); ?></div>
                                <div class="application-time">Applied <?php echo date('M j, Y \a\t g:i A', strtotime($app['created_at'])); ?></div>
                                
                                <?php if ($user_type === 'client' && $task['client_id'] == $user_id && !empty($app['proposal'])): ?>
                                    <div class="application-proposal">
                                        <strong>Proposal:</strong> <?php echo htmlspecialchars(substr($app['proposal'], 0, 200)); ?>
                                        <?php if (strlen($app['proposal']) > 200): ?>...<?php endif; ?>
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
                                    <span style="font-size: 12px; color: #666;">
                                        (<?php echo $app['helper_total_ratings']; ?> reviews)
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($user_type === 'client' && $task['client_id'] == $user_id && count($applications) > 0): ?>
                        <div style="text-align: center; margin-top: 20px;">
                            <a href="applications.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                                Review All Applications
                            </a>
                        </div>
                    <?php endif; ?>
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
                            <span class="rating-text">
                                (<?php echo $task['client_total_ratings']; ?> review<?php echo $task['client_total_ratings'] != 1 ? 's' : ''; ?>)
                            </span>
                        </div>
                        
                        <div class="client-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $task['client_total_tasks']; ?></div>
                                <div class="stat-label">Tasks Posted</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $task['client_completed_tasks']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        
                        <?php if ($task['client_bio']): ?>
                        <div style="text-align: left; padding: 16px; background: #f8f9fa; border-radius: 12px; margin: 16px 0;">
                            <strong style="color: #374151; font-size: 14px;">About:</strong>
                            <p style="margin-top: 8px; font-size: 14px; color: #666; line-height: 1.4;">
                                <?php echo htmlspecialchars($task['client_bio']); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
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
                                        <?php if ($user_application['status'] === 'pending'): ?>
                                            <br><small>Applied on: <?php echo date('M j, Y', strtotime($user_application['created_at'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-disabled" disabled>
                                        <?php if ($user_application['status'] === 'pending'): ?>
                                            ⏳ Application Under Review
                                        <?php elseif ($user_application['status'] === 'accepted'): ?>
                                            ✅ Application Accepted
                                        <?php else: ?>
                                            ❌ Application Not Selected
                                        <?php endif; ?>
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
                            
                            <a href="messages.php?task_id=<?php echo $task['id']; ?>&with=<?php echo $task['client_id']; ?>" class="btn btn-secondary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                 </svg>
                            Message Client
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons for Clients -->
                    <?php if ($user_type === 'client' && $task['client_id'] == $user_id): ?>
                        <div class="action-buttons">
                            <?php if ($task['pending_applications'] > 0): ?>
                                <a href="applications.php?task_id=<?php echo $task['id']; ?>" class="btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg>
                                    Review Applications (<?php echo $task['pending_applications']; ?>)
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
                
                <!-- Time Countdown -->
                <?php if ($task['status'] === 'open' && $time_until > 0): ?>
                <div class="time-countdown">
                    <div class="countdown-title">Task starts in:</div>
                    <div class="countdown-time" id="countdown">
                        <?php 
                        if ($time_until > 86400) {
                            echo floor($time_until / 86400) . ' days, ' . floor(($time_until % 86400) / 3600) . ' hours';
                        } else if ($time_until > 3600) {
                            echo floor($time_until / 3600) . ' hours, ' . floor(($time_until % 3600) / 60) . ' minutes';
                        } else {
                            echo floor($time_until / 60) . ' minutes';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Competition Info for Helpers -->
                <?php if ($user_type === 'helper' && $task['status'] === 'open'): ?>
                <div class="competition-info">
                    <div class="competition-title">📊 Competition Analysis</div>
                    <div class="competition-stats">
                        <div class="competition-stat">
                            <div class="competition-stat-value"><?php echo $task['total_applications']; ?></div>
                            <div class="competition-stat-label">Total Apps</div>
                        </div>
                        <div class="competition-stat">
                            <div class="competition-stat-value">
                                <?php 
                                if ($task['total_applications'] < 3) echo "Low";
                                elseif ($task['total_applications'] < 6) echo "Medium";
                                else echo "High";
                                ?>
                            </div>
                            <div class="competition-stat-label">Competition</div>
                        </div>
                    </div>
                    <div style="margin-top: 12px; font-size: 14px; color: #0369a1;">
                        <?php if ($task['total_applications'] < 3): ?>
                            🟢 Great opportunity! Low competition gives you a higher chance of winning.
                        <?php elseif ($task['total_applications'] < 6): ?>
                            🟡 Moderate competition. Make your proposal stand out!
                        <?php else: ?>
                            🔴 High competition. You'll need an exceptional proposal to win.
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Client's Recent Tasks -->
                <?php if (!empty($client_recent_tasks)): ?>
                <div class="sidebar-card">
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #1a1a1a;">
                        Recent Completed Tasks
                    </h3>
                    <div class="recent-tasks">
                        <?php foreach ($client_recent_tasks as $recent_task): ?>
                            <div class="recent-task-item">
                                <div class="recent-task-title"><?php echo htmlspecialchars($recent_task['title']); ?></div>
                                <div class="recent-task-meta">
                                    $<?php echo number_format($recent_task['budget'], 2); ?> • 
                                    <?php echo date('M Y', strtotime($recent_task['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Countdown timer for task start time
        <?php if ($task['status'] === 'open' && $time_until > 0): ?>
        function updateCountdown() {
            const taskTime = <?php echo $task_time * 1000; ?>; // Convert to milliseconds
            const now = new Date().getTime();
            const timeLeft = taskTime - now;
            
            const countdownElement = document.getElementById('countdown');
            const titleElement = document.querySelector('.countdown-title');
            const countdownContainer = document.querySelector('.time-countdown');
            
            if (timeLeft > 0) {
                const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                
                let countdownText = '';
                if (days > 0) {
                    countdownText = `${days} day${days !== 1 ? 's' : ''}, ${hours} hour${hours !== 1 ? 's' : ''}`;
                } else if (hours > 0) {
                    countdownText = `${hours} hour${hours !== 1 ? 's' : ''}, ${minutes} minute${minutes !== 1 ? 's' : ''}`;
                } else if (minutes > 0) {
                    countdownText = `${minutes} minute${minutes !== 1 ? 's' : ''}, ${seconds} second${seconds !== 1 ? 's' : ''}`;
                } else {
                    countdownText = `${seconds} second${seconds !== 1 ? 's' : ''}`;
                }
                
                countdownElement.textContent = countdownText;
            } else {
                countdownElement.textContent = 'Task time has arrived!';
                titleElement.textContent = 'Status:';
                countdownContainer.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
                countdownContainer.style.animation = 'none';
            }
        }
        
        // Update countdown every second
        setInterval(updateCountdown, 1000);
        updateCountdown(); // Initial call
        <?php endif; ?>
        
        // Enhanced description handling
        document.addEventListener('DOMContentLoaded', function() {
            const description = document.getElementById('taskDescription');
            if (description && description.scrollHeight > 200) {
                description.classList.add('expandable');
                
                description.addEventListener('click', function() {
                    if (this.classList.contains('expandable')) {
                        this.classList.remove('expandable');
                        this.classList.add('expanded');
                    }
                });
            }
        });
        
        // Auto-refresh for real-time updates
        let refreshInterval;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                if (!document.hidden) {
                    // Check for updates via AJAX
                    fetch(`task-details.php?id=<?php echo $task_id; ?>&ajax=1`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.applications_count !== <?php echo $task['total_applications']; ?>) {
                                // Applications count changed, refresh page
                                location.reload();
                            }
                        })
                        .catch(error => {
                            // Silently handle errors
                        });
                }
            }, 15000); // Check every 15 seconds
        }
        
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        }
        
        // Start auto-refresh for open tasks
        <?php if ($task['status'] === 'open'): ?>
        startAutoRefresh();
        
        // Stop refresh when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
        <?php endif; ?>
        
        // Smooth animations on load
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
        
        // Enhanced hover effects
        document.querySelectorAll('.info-item, .stat-item, .recent-task-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px) scale(1.02)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>