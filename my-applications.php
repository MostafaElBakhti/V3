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
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Validate sort parameters
$allowed_sorts = ['created_at', 'bid_amount', 'status'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

// Build the query
$where_conditions = ['a.helper_id = ?'];
$params = [$user_id];

// Add status filter
if ($status_filter !== 'all') {
    $where_conditions[] = 'a.status = ?';
    $params[] = $status_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get applications with task and client details
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            t.title as task_title,
            t.budget as task_budget,
            t.location as task_location,
            t.scheduled_time,
            t.status as task_status,
            t.description as task_description,
            t.client_id,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            u.total_ratings
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON t.client_id = u.id
        $where_clause
        ORDER BY a.$sort_by $sort_order
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

    // Get application statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
        FROM applications 
        WHERE helper_id = ?
    ");
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();

    // Calculate success rate
    $success_rate = $stats['total_applications'] > 0 ? 
        round(($stats['accepted_applications'] / $stats['total_applications']) * 100) : 0;

} catch (PDOException $e) {
    error_log("Database error in my-applications.php: " . $e->getMessage());
    $applications = [];
    $stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0];
    $success_rate = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/my-application.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1a1a1a;
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
            
            <a href="my-applications.php" class="nav-item active">
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
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                        My Applications
                    </h1>
                    <div class="success-rate">
                        <div class="success-number"><?php echo $success_rate; ?>%</div>
                        <div class="success-label">Success Rate</div>
                    </div>
                </div>
                
                <!-- Application Statistics -->
                <div class="app-stats">
                    <div class="stat-item <?php echo $status_filter === 'all' ? 'active' : ''; ?>" onclick="filterByStatus('all')">
                        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" onclick="filterByStatus('pending')">
                        <div class="stat-number"><?php echo $stats['pending_applications']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>" onclick="filterByStatus('accepted')">
                        <div class="stat-number"><?php echo $stats['accepted_applications']; ?></div>
                        <div class="stat-label">Accepted</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" onclick="filterByStatus('rejected')">
                        <div class="stat-number"><?php echo $stats['rejected_applications']; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="" id="filtersForm">
                        <div class="filters-row">
                            <select class="filter-select" name="sort" onchange="document.getElementById('filtersForm').submit()">
                                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Sort by Date Applied</option>
                                <option value="bid_amount" <?php echo $sort_by === 'bid_amount' ? 'selected' : ''; ?>>Sort by Bid Amount</option>
                                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Sort by Status</option>
                            </select>
                            
                            <div class="sort-controls">
                                <button type="submit" class="sort-btn" name="order" value="<?php echo $sort_order === 'DESC' ? 'asc' : 'desc'; ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <?php if ($sort_order === 'DESC'): ?>
                                            <path d="M7 13l3 3 3-3"/>
                                            <path d="M7 6l3 3 3-3"/>
                                        <?php else: ?>
                                            <path d="M7 17l3-3 3 3"/>
                                            <path d="M7 7l3 3 3-3"/>
                                        <?php endif; ?>
                                    </svg>
                                    <?php echo $sort_order === 'DESC' ? 'Newest' : 'Oldest'; ?>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    </form>
                </div>
            </div>
            
            <!-- Applications Grid -->
            <div class="applications-grid">
                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="9" y1="13" x2="15" y2="13"/>
                                <line x1="9" y1="17" x2="13" y2="17"/>
                            </svg>
                        </div>
                        <?php if ($status_filter !== 'all'): ?>
                            <h3 class="empty-title">No <?php echo ucfirst($status_filter); ?> Applications</h3>
                            <p class="empty-description">
                                You don't have any <?php echo $status_filter; ?> applications yet. 
                                <?php if ($status_filter === 'accepted'): ?>
                                    Keep applying to tasks to increase your chances of getting accepted!
                                <?php endif; ?>
                            </p>
                            <a href="find-tasks.php" class="action-btn btn-primary" style="margin: 0 auto;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                Find More Tasks
                            </a>
                        <?php else: ?>
                            <h3 class="empty-title">No Applications Yet</h3>
                            <p class="empty-description">
                                You haven't applied to any tasks yet. Start by browsing available tasks and submitting your first application!
                            </p>
                            <a href="find-tasks.php" class="action-btn btn-primary" style="margin: 0 auto;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                Find Tasks to Apply
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <div class="application-card">
                            <div class="application-date">
                                Applied <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                            </div>
                            
                            <div class="application-header">
                                <span class="application-status status-<?php echo $app['status']; ?>">
                                    <?php echo ucfirst($app['status']); ?>
                                </span>
                            </div>
                            
                            <div class="task-info">
                                <div class="task-title">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14,2 14,8 20,8"/>
                                    </svg>
                                    <?php echo htmlspecialchars($app['task_title']); ?>
                                </div>
                                <div class="task-meta">
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($app['task_location']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        <span><?php echo date('M d, Y - g:i A', strtotime($app['scheduled_time'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                        </svg>
                                        <span>Task Budget: $<?php echo number_format($app['task_budget'], 2); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                        <span>Status: <?php echo ucfirst(str_replace('_', ' ', $app['task_status'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="client-section">
                                <div class="client-avatar">
                                    <?php echo strtoupper(substr($app['client_name'], 0, 1)); ?>
                                </div>
                                <div class="client-info">
                                    <div class="client-name"><?php echo htmlspecialchars($app['client_name']); ?></div>
                                    <div class="client-rating">
                                        <div class="stars">
                                            <?php 
                                            $rating = $app['client_rating'] ? floatval($app['client_rating']) : 0;
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <svg class="star" viewBox="0 0 24 24" fill="<?php echo $i <= $rating ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-text">
                                            <?php echo $rating > 0 ? number_format($rating, 1) . ' rating' : 'New client'; ?>
                                            <?php if (isset($app['total_ratings']) && $app['total_ratings'] > 0): ?>
                                                (<?php echo $app['total_ratings']; ?> reviews)
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bid-section">
                                <div class="bid-header">
                                    <div class="bid-label">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        My Proposal & Bid
                                    </div>
                                    <div class="bid-amount">$<?php echo number_format($app['bid_amount'], 2); ?></div>
                                </div>
                                <div class="proposal-text">
                                    <?php echo nl2br(htmlspecialchars($app['proposal'])); ?>
                                </div>
                            </div>
                            
                            <div class="application-actions">
                                <a href="task-details.php?id=<?php echo $app['task_id']; ?>" class="action-btn btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Task Details
                                </a>
                                
                                <?php if ($app['status'] === 'accepted'): ?>
                                    <a href="my-jobs.php" class="action-btn btn-success">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                            <line x1="8" y1="21" x2="16" y2="21"/>
                                            <line x1="12" y1="17" x2="12" y2="21"/>
                                        </svg>
                                        Go to Job
                                    </a>
                                <?php endif; ?>
                                
                                <a href="helper-messages.php?task_id=<?php echo $app['task_id']; ?>&client_id=<?php echo $app['client_id']; ?>" class="action-btn btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Message Client
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
            currentUrl.searchParams.delete('order'); // Reset order when changing status
            window.location.href = currentUrl.toString();
        }
        
        // Auto-hide any error messages
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    this.style.opacity = '0.7';
                    this.style.pointerEvents = 'none';
                });
            });
            
            // Add tooltip functionality for long proposal text
            document.querySelectorAll('.proposal-text').forEach(element => {
                if (element.scrollHeight > element.clientHeight) {
                    element.title = element.textContent;
                    element.style.cursor = 'pointer';
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus on filters
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const sortSelect = document.querySelector('select[name="sort"]');
                if (sortSelect) {
                    sortSelect.focus();
                }
            }
        });
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>