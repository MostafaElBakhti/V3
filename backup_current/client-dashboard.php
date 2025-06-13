<?php
// Add security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' https:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' https:;");

require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Task creation logic
$modal_alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'], $_POST['description'], $_POST['location'], $_POST['scheduled_date'], $_POST['scheduled_time'], $_POST['budget'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $modal_alert = '<div class="alert alert-error"><strong>Error:</strong> Invalid request. Please try again.</div>';
    } else {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $location = sanitize($_POST['location']);
        $budget = filter_var($_POST['budget'], FILTER_VALIDATE_FLOAT);
        $scheduled_date = $_POST['scheduled_date'];
        $scheduled_time = $_POST['scheduled_time'];
        $errors = [];

        // Enhanced input validation
        if (empty($title) || strlen($title) < 5 || strlen($title) > 100) {
            $errors[] = 'Task title must be between 5 and 100 characters long.';
        }
        if (empty($description) || strlen($description) < 20 || strlen($description) > 2000) {
            $errors[] = 'Task description must be between 20 and 2000 characters long.';
        }
        if (empty($location) || strlen($location) > 200) {
            $errors[] = 'Location is required and must not exceed 200 characters.';
        }
        if ($budget === false || $budget < 10 || $budget > 10000) {
            $errors[] = 'Budget must be a valid number between $10 and $10,000.';
        }
        if (empty($scheduled_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduled_date)) {
            $errors[] = 'Invalid scheduled date format.';
        } else {
            $scheduled_datetime = DateTime::createFromFormat('Y-m-d', $scheduled_date);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            if ($scheduled_datetime < $today) {
                $errors[] = 'Scheduled date cannot be in the past.';
            }
        }
        if (empty($scheduled_time) || !preg_match('/^\d{2}:\d{2}$/', $scheduled_time)) {
            $errors[] = 'Invalid scheduled time format.';
        }

        // Create scheduled datetime
        if (empty($errors)) {
            $scheduled_datetime_str = $scheduled_date . ' ' . $scheduled_time;
            $scheduled_datetime_obj = DateTime::createFromFormat('Y-m-d H:i', $scheduled_datetime_str);
            if (!$scheduled_datetime_obj) {
                $errors[] = 'Invalid date/time format.';
            } else {
                $now = new DateTime();
                if ($scheduled_datetime_obj < $now) {
                    $errors[] = 'Scheduled date and time cannot be in the past.';
                }
            }
        }

        if (!empty($errors)) {
            $modal_alert = '<div class="alert alert-error"><strong>Please fix the following errors:</strong><ul style="margin: 8px 0 0 20px;">'.implode('', array_map(function($e){return '<li>'.htmlspecialchars($e).'</li>';}, $errors)).'</ul></div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO tasks (client_id, title, description, location, scheduled_time, budget, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
                if ($stmt->execute([$user_id, $title, $description, $location, $scheduled_datetime_str, $budget])) {
                    $modal_alert = '<div class="alert alert-success"><strong>Success!</strong> Task created successfully! Your task has been posted and helpers can now apply.</div>';
                    echo '<script>setTimeout(function(){ location.reload(); }, 1500);</script>';
                } else {
                    $modal_alert = '<div class="alert alert-error"><strong>Error:</strong> Failed to create task. Please try again.</div>';
                }
            } catch (PDOException $e) {
                error_log("Task creation error: " . $e->getMessage());
                $modal_alert = '<div class="alert alert-error"><strong>Error:</strong> An unexpected error occurred. Please try again later.</div>';
            }
        }
    }
}

// Fetch client statistics and data
try {
    // Get task counts and spending
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_tasks,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as active_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'completed' THEN budget ELSE 0 END) as total_spent,
            SUM(CASE WHEN status = 'open' THEN budget ELSE 0 END) as pending_budget
        FROM tasks 
        WHERE client_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    // Get week spending (last 7 days)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(budget), 0) as week_spending
        FROM tasks 
        WHERE client_id = ? AND status = 'completed' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$user_id]);
    $week_spending = $stmt->fetch()['week_spending'];

    // Get new helpers count (applications in last 7 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT a.helper_id) as new_helpers
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        WHERE t.client_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$user_id]);
    $new_helpers = $stmt->fetch()['new_helpers'];

    // Get pending applications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_applications
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        WHERE t.client_id = ? AND a.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_apps = $stmt->fetch()['pending_applications'];

    // Get recent applications with helper details
    $stmt = $pdo->prepare("
        SELECT 
            a.id, a.proposal, a.bid_amount, a.created_at, a.status,
            u.fullname as helper_name, u.email as helper_email,
            t.title as task_title
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON a.helper_id = u.id
        WHERE t.client_id = ? 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_applications = $stmt->fetchAll();

    // Get recent tasks
    $stmt = $pdo->prepare("
        SELECT id, title, status, budget, created_at, scheduled_time
        FROM tasks 
        WHERE client_id = ? 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$user_id]);
    $recent_tasks = $stmt->fetchAll();

} catch (PDOException $e) {
    $stats = ['total_tasks' => 0, 'open_tasks' => 0, 'active_tasks' => 0, 'completed_tasks' => 0, 'total_spent' => 0, 'pending_budget' => 0];
    $week_spending = 0;
    $new_helpers = 0;
    $pending_apps = 0;
    $recent_applications = [];
    $recent_tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="client.css">
    <style>
        /* Only keep critical inline styles that need to be loaded immediately */
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
            background: linear-gradient(135deg, #667eea, #764ba2);
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
        
        .task-status {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-open { background: #dbeafe; color: #1e40af; }
        .status-in_progress { background: #fed7aa; color: #c2410c; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-pending { background: #f3e8ff; color: #7c3aed; }
        
        .task-meta {
            font-size: 12px;
            color: #666;
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
            width: 65%;
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
            background: linear-gradient(135deg, #667eea, #764ba2);
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
        
        /* Modal Styles */
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
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        
        .modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.8) translateY(50px);
            transition: all 0.3s ease;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            position: relative;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
        }
        
        .modal-header {
            padding: 32px 32px 0;
            text-align: center;
        }
        
        .modal-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .modal-header p {
            color: #666;
            font-size: 16px;
        }
        
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border: none;
            background: #f8f9fa;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: #e9ecef;
        }
        
        .modal-body {
            padding: 32px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .budget-input {
            position: relative;
        }
        
        .budget-input::before {
            content: '$';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: #666;
            z-index: 1;
        }
        
        .budget-input input {
            padding-left: 40px;
        }
        
        .modal-footer {
            padding: 0 32px 32px;
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
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
            
            <a href="client-dashboard.php" class="nav-item active">
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
            
            <!-- Stats Grid -->
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
                    <div class="stat-value" style="color: white; font-size: 42px;">$<?php echo number_format($stats['pending_budget']); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">💰 Active task budget</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
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
                    <div class="stat-value"><?php echo $new_helpers; ?></div>
                    <div class="stat-label">New helpers this week</div>
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
                    <div class="stat-value"><?php echo $pending_apps; ?></div>
                    <div class="stat-label">Applications pending review</div>
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
                    <div class="stat-value">$<?php echo number_format($week_spending, 0); ?></div>
                    <div class="stat-label">This week's spending</div>
                </div>
            </div>
            
            <!-- Attractive Action Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
                <!-- Add Task Card -->
                <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 20px; padding: 32px; color: white; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;" 
                     onclick="openTaskModal()" 
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 20px 40px rgba(59, 130, 246, 0.3)'" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'">
                    
                    <!-- Background decoration -->
                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none;"></div>
                    <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none;"></div>
                    
                    <div style="position: relative; z-index: 2;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="16"/>
                                    <line x1="8" y1="12" x2="16" y2="12"/>
                                </svg>
                            </div>
                            <div>
                                <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">Add New Task</h3>
                                <p style="opacity: 0.9; font-size: 14px;">Get help with anything</p>
                            </div>
                        </div>
                        
                        <p style="font-size: 16px; opacity: 0.9; line-height: 1.5; margin-bottom: 24px;">
                            Post a task and connect with skilled helpers in your area. From home repairs to personal assistance.
                        </p>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 18px; font-weight: 600;">Create Task →</span>
                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <line x1="7" y1="17" x2="17" y2="7"/>
                                    <polyline points="7,7 17,7 17,17"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- See Stats Card -->
                <div style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 20px; padding: 32px; color: white; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;" 
                     onclick="window.location.href='my-tasks.php'" 
                     onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 20px 40px rgba(16, 185, 129, 0.3)'" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'">
                    
                    <!-- Background decoration -->
                    <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%; pointer-events: none;"></div>
                    <div style="position: absolute; bottom: -30px; left: -30px; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; pointer-events: none;"></div>
                    
                    <div style="position: relative; z-index: 2;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                            <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">View Statistics</h3>
                                <p style="opacity: 0.9; font-size: 14px;">Track your progress</p>
                            </div>
                        </div>
                        
                        <p style="font-size: 16px; opacity: 0.9; line-height: 1.5; margin-bottom: 24px;">
                            Monitor your tasks, spending, and helper performance. Get insights into your productivity.
                        </p>
                        
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 18px; font-weight: 600;">View Details →</span>
                            <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <line x1="7" y1="17" x2="17" y2="7"/>
                                    <polyline points="7,7 17,7 17,17"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Priority Action Banner -->
            <?php if ($pending_apps > 0): ?>
            <div style="background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px;">🚨 <?php echo $pending_apps; ?> Applications Need Your Review!</h2>
                    <p style="font-size: 16px; opacity: 0.9;">Helpers are waiting for your response. Review applications to get your tasks started.</p>
                </div>
                <a href="applications.php" style="background: white; color: #ef4444; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    Review Now →
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Content Grid -->
            <div class="content-grid">
                <!-- New Helpers -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">New helpers</h2>
                        <button class="stat-menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="metric-container">
                        <div class="metric-value"><?php echo $new_helpers; ?></div>
                        <div class="metric-change">+ 18.7%</div>
                    </div>
                </div>
                
                <!-- Applications Pending -->
                <div class="content-card">
                    <div class="card-header">
                        <h2 class="card-title">Applications pending</h2>
                        <button class="stat-menu">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="1"/>
                                <circle cx="12" cy="5" r="1"/>
                                <circle cx="12" cy="19" r="1"/>
                            </svg>
                        </button>
                    </div>
                    <div class="metric-container">
                        <div class="metric-value"><?php echo $pending_apps; ?></div>
                        <div class="metric-change negative">+ 2.7%</div>
                    </div>
                </div>
                
                <!-- Sidebar Content -->
                <div>
                    <!-- Task Status -->
                    <div class="sidebar-card">
                        <h3>Task completion status</h3>
                        <p style="color: #999; font-size: 14px;">In progress</p>
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <p style="color: #999; font-size: 12px;">Estimated completion<br><strong style="color: white;">4-5 business days</strong></p>
                        <button style="background: white; color: #1a1a1a; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin-top: 16px; width: 100%; cursor: pointer;">View status</button>
                    </div>
                    
                    <!-- Your To-Do List -->
                    <div class="sidebar-card">
                        <h3>Your to-do list</h3>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Review applications</h4>
                                <p>Today at 9:00 am</p>
                            </div>
                        </div>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12,6 12,12 16,14"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Post new cleaning task</h4>
                                <p>Today at 10:00 am</p>
                            </div>
                        </div>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Check task progress</h4>
                                <p>Today at 2:00 pm</p>
                            </div>
                        </div>
                        
                        <div class="todo-item">
                            <div class="todo-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="todo-info">
                                <h4>Rate completed work</h4>
                                <p>Tomorrow at 11:00 am</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Helper Meeting -->
                    <div class="meeting-card">
                        <div class="meeting-status">
                            <div class="status-dot"></div>
                            <span style="font-size: 12px; font-weight: 600;">Today at 3:00 PM</span>
                        </div>
                        <h3 style="margin-bottom: 8px;">Helper consultation</h3>
                        <p style="font-size: 14px; opacity: 0.9;">You have been invited to discuss a home renovation project with your selected helper.</p>
                    </div>
                </div>
            </div>
            
            <!-- Recent Applications -->
            <div class="content-card" style="grid-column: 1 / -1; margin-top: 32px;">
                <div class="card-header">
                    <h2 class="card-title">Recent applications</h2>
                </div>
                
                <?php if (empty($recent_applications)): ?>
                    <p style="color: #666; text-align: center; padding: 40px;">No applications yet. <a href="post-task.php" style="color: #667eea;">Post your first task</a> to start receiving applications.</p>
                <?php else: ?>
                    <?php foreach ($recent_applications as $app): ?>
                        <div class="application-item">
                            <div class="applicant-avatar">
                                <?php echo strtoupper(substr($app['helper_name'], 0, 1)); ?>
                            </div>
                            <div class="application-info">
                                <div class="applicant-name"><?php echo sanitize($app['helper_name']); ?></div>
                                <div class="application-task">Applied for: <?php echo sanitize($app['task_title']); ?></div>
                            </div>
                            <div class="application-time">
                                <?php 
                                $time_diff = time() - strtotime($app['created_at']);
                                if ($time_diff < 3600) {
                                    echo floor($time_diff / 60) . ' minutes ago';
                                } elseif ($time_diff < 86400) {
                                    echo floor($time_diff / 3600) . ' hours ago';
                                } else {
                                    echo date('M j \a\t g:i A', strtotime($app['created_at']));
                                }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
        
        <!-- Task Creation Modal -->
        <div class="modal-overlay" id="taskModal">
            <div class="modal-content">
                <button class="modal-close" onclick="closeTaskModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
                
                <div class="modal-header">
                    <h2>Create New Task</h2>
                    <p>Describe what you need help with and connect with skilled helpers.</p>
                </div>
                
                <form id="taskForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="modal-body">
                        <div id="modalAlerts"><?php echo $modal_alert; ?></div>
                        
                        <div class="form-group">
                            <label for="task_title">Task Title</label>
                            <input type="text" id="task_title" name="title" required 
                                   placeholder="e.g., Help with garden cleanup" maxlength="255">
                            <div class="help-text">Be specific and clear about what you need help with</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_description">Task Description</label>
                            <textarea id="task_description" name="description" required 
                                      placeholder="Provide detailed information about the task, requirements, and any special instructions..."></textarea>
                            <div class="help-text">Include all important details (minimum 20 characters)</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_location">Location</label>
                            <input type="text" id="task_location" name="location" required 
                                   placeholder="e.g., Downtown, New York, NY">
                            <div class="help-text">Provide the area where the task will be performed</div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="task_date">Scheduled Date</label>
                                <input type="date" id="task_date" name="scheduled_date" required>
                                <div class="help-text">When do you need this done?</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="task_time">Scheduled Time</label>
                                <input type="time" id="task_time" name="scheduled_time" required>
                                <div class="help-text">Preferred start time</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="task_budget">Budget</label>
                            <div class="budget-input">
                                <input type="number" id="task_budget" name="budget" required 
                                       min="10" max="10000" step="1" placeholder="100">
                            </div>
                            <div class="help-text">Set a fair budget ($10 - $10,000)</div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeTaskModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Task Button -->
<button id="openTaskModal">Add New Task</button>

<!-- Modal Form -->
<div id="taskModal" style="display:none;">
  <form id="addTaskForm">
    <input type="text" name="title" placeholder="Task Title" required>
    <textarea name="description" placeholder="Task Description" required></textarea>
    <input type="text" name="location" placeholder="Location" required>
    <input type="datetime-local" name="scheduled_time" required>
    <input type="number" step="0.01" name="budget" placeholder="Budget" required>
    <button type="submit">Add Task</button>
    <button type="button" id="closeTaskModal">Cancel</button>
  </form>
</div>

        
        <script>
            // Modal Functions
            function openTaskModal() {
                const modal = document.getElementById('taskModal');
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                document.getElementById('task_date').setAttribute('min', new Date().toISOString().split('T')[0]);
            }
            function closeTaskModal() {
                const modal = document.getElementById('taskModal');
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
                document.getElementById('taskForm').reset();
                document.getElementById('modalAlerts').innerHTML = '';
            }
            document.getElementById('taskModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeTaskModal();
                }
            });
            // Auto-resize textarea
            document.getElementById('task_description').addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        </script>
        
    </div>
</body>
</html>