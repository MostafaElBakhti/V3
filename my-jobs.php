<?php
require_once 'config.php';


// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'scheduled_time';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// Handle job status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $task_id = intval($_POST['task_id']);
    $action = $_POST['action'];
    
    try {
        // Verify the task belongs to this helper
        $verify_stmt = $pdo->prepare("
            SELECT t.*, u.fullname as client_name 
            FROM tasks t 
            JOIN users u ON t.client_id = u.id 
            WHERE t.id = ? AND t.helper_id = ?
        ");
        $verify_stmt->execute([$task_id, $user_id]);
        $task = $verify_stmt->fetch();
        
        if ($task) {
            if ($action === 'start_job' && $task['status'] === 'in_progress') {
                // Job is already in progress, just confirm
                $success_message = "Job is ready to start!";
                
            } elseif ($action === 'complete_job' && $task['status'] === 'in_progress') {
                // Mark task as completed
                $update_stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET status = 'completed', updated_at = NOW() 
                    WHERE id = ? AND helper_id = ?
                ");
                $update_stmt->execute([$task_id, $user_id]);
                
                // Create notification for client
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'task_status', ?, ?, NOW())
                ");
                $notification_content = "Task '" . $task['title'] . "' has been completed by " . $fullname;
                $notification_stmt->execute([$task['client_id'], $notification_content, $task_id]);
                
                $success_message = "Job marked as completed! The client has been notified.";
                
            } elseif ($action === 'request_cancellation') {
                // This would typically involve more complex logic
                // For now, we'll just create a notification
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'task_status', ?, ?, NOW())
                ");
                $notification_content = $fullname . " has requested to cancel the task '" . $task['title'] . "'";
                $notification_stmt->execute([$task['client_id'], $notification_content, $task_id]);
                
                $success_message = "Cancellation request sent to client.";
            }
        } else {
            $error_message = "Task not found or you don't have permission to modify it.";
        }
    } catch (PDOException $e) {
        $error_message = "Error updating job status. Please try again.";
    }
}

// Build the query for jobs
$where_conditions = ['t.helper_id = ?'];
$params = [$user_id];

// Add status filter
if ($status_filter !== 'all') {
    $where_conditions[] = 't.status = ?';
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Validate sort parameters
$allowed_sorts = ['scheduled_time', 'created_at', 'budget', 'title', 'status'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'scheduled_time';
}

try {
    // Get jobs with client details
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            u.total_ratings,
            (SELECT COUNT(*) FROM reviews WHERE task_id = t.id) as review_count,
            (SELECT rating FROM reviews WHERE task_id = t.id AND reviewer_id = ?) as my_rating
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        $where_clause
        ORDER BY t.$sort_by $sort_order
    ");
    $stmt->execute(array_merge([$user_id], $params));
    $jobs = $stmt->fetchAll();
    
    // Get job statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN status = 'completed' THEN budget ELSE 0 END) as total_earned,
            AVG(CASE WHEN status = 'completed' THEN budget ELSE NULL END) as avg_job_value
        FROM tasks 
        WHERE helper_id = ?
    ");
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
    // Get upcoming jobs (next 7 days)
    $upcoming_stmt = $pdo->prepare("
        SELECT COUNT(*) as upcoming_jobs
        FROM tasks 
        WHERE helper_id = ? 
        AND status = 'in_progress' 
        AND scheduled_time BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    ");
    $upcoming_stmt->execute([$user_id]);
    $upcoming_count = $upcoming_stmt->fetch()['upcoming_jobs'];
    
    // Get recent earnings (last 30 days)
    $recent_earnings_stmt = $pdo->prepare("
        SELECT COALESCE(SUM(budget), 0) as recent_earnings
        FROM tasks 
        WHERE helper_id = ? 
        AND status = 'completed' 
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $recent_earnings_stmt->execute([$user_id]);
    $recent_earnings = $recent_earnings_stmt->fetch()['recent_earnings'];
    
} catch (PDOException $e) {
    $jobs = [];
    $stats = ['total_jobs' => 0, 'active_jobs' => 0, 'completed_jobs' => 0, 'total_earned' => 0, 'avg_job_value' => 0];
    $upcoming_count = 0;
    $recent_earnings = 0;
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
        
        .header-actions {
            display: flex;
            gap: 12px;
        }
        
        .header-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            text-decoration: none;
        }
        
        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        /* Statistics */
        .job-stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .stat-item:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
        
        .stat-item.active {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Filters */
        .filters-section {
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px;
        }
        
        .filters-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 16px;
            align-items: center;
        }
        
        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 200px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .sort-controls {
            display: flex;
            gap: 8px;
        }
        
        .sort-btn {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
        }
        
        .sort-btn:hover {
            border-color: #10b981;
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        /* Jobs Grid */
        .jobs-grid {
            display: grid;
            gap: 24px;
            margin-top: 32px;
        }
        
        .job-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        
        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .job-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.3;
            flex: 1;
            margin-right: 16px;
        }
        
        .job-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        
        .status-in_progress { 
            background: linear-gradient(135deg, #f59e0b, #d97706); 
            color: white; 
        }
        .status-completed { 
            background: linear-gradient(135deg, #10b981, #059669); 
            color: white; 
        }
        .status-cancelled { 
            background: linear-gradient(135deg, #ef4444, #dc2626); 
            color: white; 
        }
        
        .job-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
            font-size: 16px;
        }
        
        .job-meta {
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
        
        .budget-highlight {
            color: #10b981;
            font-weight: 700;
            font-size: 18px;
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
        
        .client-rating {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stars {
            display: flex;
            gap: 2px;
        }
        
        .star {
            width: 14px;
            height: 14px;
            color: #fbbf24;
        }
        
        .rating-text {
            font-size: 14px;
            color: #64748b;
        }
        
        .progress-section {
            margin-bottom: 24px;
        }
        
        .progress-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .progress-label {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .time-remaining {
            font-size: 14px;
            color: #64748b;
        }
        
        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #10b981, #059669);
            transition: width 0.3s ease;
        }
        
        .job-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .job-btn {
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
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-outline {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .job-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .urgent-banner {
            position: absolute;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
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
            .job-stats {
                grid-template-columns: repeat(3, 1fr);
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
            
            .job-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .job-meta {
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
            
            .job-actions {
                flex-direction: column;
            }
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
        .notification-bell {
    position: relative;
    cursor: pointer;
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #ef4444;
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
    animation: pulse 2s infinite;
}

.notification-badge.hidden {
    display: none;
}

/* Notification Dropdown */
.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    width: 380px;
    max-height: 480px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
}

.notification-dropdown.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.notification-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}

.notification-actions {
    display: flex;
    gap: 8px;
}

.notification-action-btn {
    padding: 6px 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 12px;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.notification-action-btn:hover {
    background: #e2e8f0;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.notification-item:hover {
    background: #f8fafc;
}

.notification-item.unread {
    background: #eff6ff;
    border-left: 4px solid #3b82f6;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    top: 20px;
    right: 20px;
    width: 8px;
    height: 8px;
    background: #3b82f6;
    border-radius: 50%;
}

.notification-content {
    display: flex;
    gap: 12px;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-icon.application {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
}

.notification-icon.message {
    background: linear-gradient(135deg, #10b981, #059669);
}

.notification-icon.task_status {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.notification-icon.review {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed);
}

.notification-text {
    flex: 1;
}

.notification-message {
    font-size: 14px;
    color: #1a1a1a;
    line-height: 1.4;
    margin-bottom: 4px;
}

.notification-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-time {
    font-size: 12px;
    color: #64748b;
}

.notification-delete {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    opacity: 0;
    transition: all 0.2s;
}

.notification-item:hover .notification-delete {
    opacity: 1;
}

.notification-delete:hover {
    background: #fef2f2;
    color: #ef4444;
}

.notification-empty {
    padding: 40px 24px;
    text-align: center;
    color: #64748b;
}

.notification-empty-icon {
    width: 48px;
    height: 48px;
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.notification-footer {
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
    text-align: center;
}

.view-all-btn {
    color: #3b82f6;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 8px;
    transition: all 0.2s;
}

.view-all-btn:hover {
    background: #eff6ff;
}

/* Toast Notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    pointer-events: none;
}

.toast {
    background: white;
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    border-left: 4px solid #3b82f6;
    min-width: 300px;
    max-width: 400px;
    opacity: 0;
    transform: translateX(400px);
    transition: all 0.3s ease;
    pointer-events: auto;
    position: relative;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast.success {
    border-left-color: #10b981;
}

.toast.error {
    border-left-color: #ef4444;
}

.toast.warning {
    border-left-color: #f59e0b;
}

.toast-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.toast-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.toast-text {
    flex: 1;
}

.toast-title {
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 4px;
    font-size: 14px;
}

.toast-message {
    color: #64748b;
    font-size: 13px;
    line-height: 1.4;
}

.toast-close {
    position: absolute;
    top: 8px;
    right: 8px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.toast-close:hover {
    background: #f1f5f9;
    color: #64748b;
}

/* Pulse animation for notification badge */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

@media (max-width: 768px) {
    .notification-dropdown {
        width: calc(100vw - 32px);
        right: -100px;
    }
    
    .toast-container {
        top: 10px;
        right: 10px;
        left: 10px;
    }
    
    .toast {
        min-width: auto;
        max-width: none;
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
            
            <a href="find-tasks.php" class="nav-item">
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
            
            <a href="my-jobs.php" class="nav-item active">
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
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                <line x1="8" y1="21" x2="16" y2="21"/>
                                <line x1="12" y1="17" x2="12" y2="21"/>
                            </svg>
                        </div>
                        My Jobs
                    </h1>
                    <div class="header-actions">
                        <a href="find-tasks.php" class="header-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                            Find More Tasks
                        </a>
                        <a href="my-applications.php" class="header-btn" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                            View Applications
                        </a>
                    </div>
                </div>
                
                <!-- Job Statistics -->
                <div class="job-stats">
                    <div class="stat-item <?php echo $status_filter === 'all' ? 'active' : ''; ?>" onclick="filterByStatus('all')">
                        <div class="stat-number"><?php echo $stats['total_jobs']; ?></div>
                        <div class="stat-label">Total Jobs</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>" onclick="filterByStatus('in_progress')">
                        <div class="stat-number"><?php echo $stats['active_jobs']; ?></div>
                        <div class="stat-label">Active Jobs</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" onclick="filterByStatus('completed')">
                        <div class="stat-number"><?php echo $stats['completed_jobs']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($stats['total_earned']); ?></div>
                        <div class="stat-label">Total Earned</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $upcoming_count; ?></div>
                        <div class="stat-label">Upcoming (7 days)</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="" id="filtersForm">
                        <div class="filters-row">
                            <select class="filter-select" name="status" onchange="document.getElementById('filtersForm').submit()">
                                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Jobs</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>Active Jobs</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed Jobs</option>
                            </select>
                            
                            <select class="filter-select" name="sort" onchange="document.getElementById('filtersForm').submit()">
                                <option value="scheduled_time" <?php echo $sort_by === 'scheduled_time' ? 'selected' : ''; ?>>Sort by Schedule</option>
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Sort by Date Added</option>
                                <option value="budget" <?php echo $sort_by === 'budget' ? 'selected' : ''; ?>>Sort by Budget</option>
                                <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Sort by Title</option>
                                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                            </select>
                            
                            <div class="sort-controls">
                                <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order === 'ASC' ? 'desc' : 'asc'; ?>" class="sort-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <?php if ($sort_order === 'ASC'): ?>
                                            <path d="M7 13l3 3 3-3"/>
                                            <path d="M7 6l3 3 3-3"/>
                                        <?php else: ?>
                                            <path d="M7 17l3-3 3 3"/>
                                            <path d="M7 7l3 3 3-3"/>
                                        <?php endif; ?>
                                    </svg>
                                    <?php echo $sort_order === 'ASC' ? 'Ascending' : 'Descending'; ?>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Jobs Grid -->
            <div class="jobs-grid">
                <?php if (empty($jobs)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                <line x1="8" y1="21" x2="16" y2="21"/>
                                <line x1="12" y1="17" x2="12" y2="21"/>
                            </svg>
                        </div>
                        <?php if ($status_filter !== 'all'): ?>
                            <h2 class="empty-title">No <?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?> Jobs</h2>
                            <p class="empty-description">
                                You don't have any <?php echo str_replace('_', ' ', $status_filter); ?> jobs at the moment.
                            </p>
                            <a href="?status=all" class="header-btn" style="margin: 0 auto;">
                                View All Jobs
                            </a>
                        <?php else: ?>
                            <h2 class="empty-title">No Jobs Yet</h2>
                            <p class="empty-description">
                                You haven't been assigned to any jobs yet. Start by applying to tasks to get your first job!
                            </p>
                            <a href="find-tasks.php" class="header-btn" style="margin: 0 auto;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                Find Tasks to Apply
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <div class="job-card">
                            <?php 
                            // Check if job is urgent (within 24 hours)
                            $time_until = strtotime($job['scheduled_time']) - time();
                            $is_urgent = $time_until > 0 && $time_until < 86400 && $job['status'] === 'in_progress';
                            ?>
                            
                            <?php if ($is_urgent): ?>
                                <div class="urgent-banner">
                                    🔥 URGENT - Due Today!
                                </div>
                            <?php endif; ?>
                            
                            <div class="job-header">
                                <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <span class="job-status status-<?php echo $job['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </div>
                            
                            <p class="job-description">
                                <?php echo htmlspecialchars(substr($job['description'], 0, 200)) . (strlen($job['description']) > 200 ? '...' : ''); ?>
                            </p>
                            
                            <div class="job-meta">
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    <span><?php echo date('M j, Y \a\t g:i A', strtotime($job['scheduled_time'])); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    <span class="budget-highlight">$<?php echo number_format($job['budget'], 2); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <polyline points="12,6 12,12 16,14"/>
                                    </svg>
                                    <span>
                                        <?php 
                                        $time_diff = time() - strtotime($job['created_at']);
                                        if ($time_diff < 86400) {
                                            echo 'Added today';
                                        } else {
                                            echo 'Added ' . floor($time_diff / 86400) . ' days ago';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="client-section">
                                <div class="client-avatar">
                                    <?php echo strtoupper(substr($job['client_name'], 0, 1)); ?>
                                </div>
                                <div class="client-info">
                                    <div class="client-name"><?php echo htmlspecialchars($job['client_name']); ?></div>
                                    <div class="client-rating">
                                        <div class="stars">
                                            <?php 
                                            $rating = $job['client_rating'] ? floatval($job['client_rating']) : 0;
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <svg class="star" viewBox="0 0 24 24" fill="<?php echo $i <= $rating ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-text">
                                            <?php echo $rating > 0 ? number_format($rating, 1) . ' rating' : 'New client'; ?>
                                            <?php if ($job['total_ratings'] > 0): ?>
                                                (<?php echo $job['total_ratings']; ?> reviews)
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($job['status'] === 'in_progress'): ?>
                                <div class="progress-section">
                                    <div class="progress-header">
                                        <div class="progress-label">Time Progress</div>
                                        <div class="time-remaining">
                                            <?php 
                                            $scheduled_time = strtotime($job['scheduled_time']);
                                            $current_time = time();
                                            
                                            if ($current_time < $scheduled_time) {
                                                $time_diff = $scheduled_time - $current_time;
                                                if ($time_diff < 3600) {
                                                    echo floor($time_diff / 60) . ' minutes until start';
                                                } elseif ($time_diff < 86400) {
                                                    echo floor($time_diff / 3600) . ' hours until start';
                                                } else {
                                                    echo floor($time_diff / 86400) . ' days until start';
                                                }
                                                $progress = 0;
                                            } else {
                                                // Assume 4 hours for completion after start time
                                                $expected_duration = 4 * 3600; // 4 hours
                                                $elapsed = $current_time - $scheduled_time;
                                                $progress = min(100, ($elapsed / $expected_duration) * 100);
                                                
                                                if ($progress < 100) {
                                                    $remaining = $expected_duration - $elapsed;
                                                    if ($remaining > 0) {
                                                        echo 'Est. ' . floor($remaining / 3600) . 'h ' . floor(($remaining % 3600) / 60) . 'm remaining';
                                                    } else {
                                                        echo 'Ready to complete';
                                                    }
                                                } else {
                                                    echo 'Ready to complete';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo isset($progress) ? $progress : 0; ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="job-actions">
                                <?php if ($job['status'] === 'in_progress'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmComplete()">
                                        <input type="hidden" name="task_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="action" value="complete_job">
                                        <button type="submit" class="job-btn btn-primary">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20,6 9,17 4,12"/>
                                            </svg>
                                            Mark as Completed
                                        </button>
                                    </form>
                                    
                                    <a href="helper-messages.php?task_id=<?php echo $job['id']; ?>&client_id=<?php echo $job['client_id']; ?>" class="job-btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        Message Client
                                    </a>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirmCancellation()">
                                        <input type="hidden" name="task_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="action" value="request_cancellation">
                                        <button type="submit" class="job-btn btn-danger">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"/>
                                                <line x1="6" y1="6" x2="18" y2="18"/>
                                            </svg>
                                            Request Cancellation
                                        </button>
                                    </form>
                                <?php elseif ($job['status'] === 'completed'): ?>
                                    <a href="helper-messages.php?task_id=<?php echo $job['id']; ?>&client_id=<?php echo $job['client_id']; ?>" class="job-btn btn-outline">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        View Messages
                                    </a>
                                    
                                    <?php if (!$job['my_rating']): ?>
                                        <a href="rate-client.php?task_id=<?php echo $job['id']; ?>" class="job-btn btn-secondary">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                            </svg>
                                            Rate Client
                                        </a>
                                    <?php else: ?>
                                        <span class="job-btn btn-outline" style="cursor: not-allowed; opacity: 0.6;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20,6 9,17 4,12"/>
                                            </svg>
                                            Rated
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <a href="task-details.php?id=<?php echo $job['id']; ?>" class="job-btn btn-outline">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
            
            // Update toggle icon
            const toggleBtn = sidebar.querySelector('.sidebar-toggle svg');
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.innerHTML = '<path d="m9 18 6-6-6-6"/>';
            } else {
                toggleBtn.innerHTML = '<path d="m15 18-6-6 6-6"/>';
            }
        }
        
        function filterByStatus(status) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('status', status);
            window.location.href = currentUrl.toString();
        }
        
        function confirmComplete() {
            return confirm('Are you sure you want to mark this job as completed? This action will notify the client and cannot be undone.');
        }
        
        function confirmCancellation() {
            return confirm('Are you sure you want to request cancellation? This will notify the client and may affect your rating.');
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 5000);
            });
        });
        
        // Add loading state to action buttons
        document.querySelectorAll('form[method="POST"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                const originalText = button.innerHTML;
                
                button.disabled = true;
                button.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <path d="M21 12a9 9 0 11-6.219-8.56"/>
                    </svg>
                    Processing...
                `;
                
                // Re-enable after 5 seconds in case of errors
                setTimeout(() => {
                    if (button.disabled) {
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                }, 5000);
            });
        });
        
        // Real-time updates for urgent jobs
        setInterval(function() {
            // Update urgent banners and time remaining
            document.querySelectorAll('.time-remaining').forEach(element => {
                // This would typically fetch updated data via AJAX
                // For now, we'll just refresh the page every 5 minutes for active jobs
            });
        }, 300000); // 5 minutes
        
        // Add spinning animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                // Focus on filters if available
                const statusSelect = document.querySelector('select[name="status"]');
                if (statusSelect) {
                    statusSelect.focus();
                }
            }
            
            // Escape to clear filters
            if (e.key === 'Escape') {
                window.location.href = 'my-jobs.php';
            }
        });
        
        // Enhanced progress bars animation
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
        });
        
        // Job card interactions
        document.querySelectorAll('.job-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-4px)';
            });
        });
        
        // Client rating interactions
        document.querySelectorAll('.client-rating').forEach(rating => {
            const stars = rating.querySelectorAll('.star');
            stars.forEach((star, index) => {
                star.addEventListener('mouseenter', function() {
                    for (let i = 0; i <= index; i++) {
                        stars[i].style.color = '#fbbf24';
                        stars[i].setAttribute('fill', 'currentColor');
                    }
                });
                
                star.addEventListener('mouseleave', function() {
                    // Reset to original state
                    stars.forEach((s, i) => {
                        const originalRating = parseFloat(rating.closest('.client-section').dataset.rating || 0);
                        if (i < originalRating) {
                            s.style.color = '#fbbf24';
                            s.setAttribute('fill', 'currentColor');
                        } else {
                            s.style.color = '#fbbf24';
                            s.setAttribute('fill', 'none');
                        }
                    });
                });
            });
        });
        
        // Status badge animations
        document.querySelectorAll('.job-status').forEach(status => {
            if (status.textContent.includes('In Progress')) {
                status.style.animation = 'pulse 2s infinite';
            }
        });
        
        // Smart notifications for upcoming deadlines
        function checkUpcomingDeadlines() {
            const urgentBanners = document.querySelectorAll('.urgent-banner');
            if (urgentBanners.length > 0) {
                // Show browser notification if supported
                if ('Notification' in window && Notification.permission === 'granted') {
                    new Notification('Urgent Jobs Due Today!', {
                        body: `You have ${urgentBanners.length} job(s) due today. Don't forget to complete them!`,
                        icon: '/path/to/icon.png'
                    });
                }
            }
        }
        
        // Request notification permission on page load
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Check for urgent jobs every 30 minutes
        setInterval(checkUpcomingDeadlines, 1800000);
        
        // Initial check
        checkUpcomingDeadlines();
        
        // Print functionality for job details
        function printJobDetails(taskId) {
            const jobCard = document.querySelector(`[data-task-id="${taskId}"]`);
            if (jobCard) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Job Details</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                .job-card { border: 1px solid #ccc; padding: 20px; margin-bottom: 20px; }
                                .job-title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                                .job-meta { margin: 10px 0; }
                                @media print { body { margin: 0; } }
                            </style>
                        </head>
                        <body>
                            ${jobCard.innerHTML}
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        }
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>