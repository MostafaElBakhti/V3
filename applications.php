<?php
require_once 'config.php';


// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$task_filter = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Handle application actions (accept/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $application_id = intval($_POST['application_id']);
    $action = $_POST['action'];
    
    try {
        // Verify the application belongs to user's task
        $verify_stmt = $pdo->prepare("
            SELECT a.*, t.title as task_title 
            FROM applications a 
            JOIN tasks t ON a.task_id = t.id 
            WHERE a.id = ? AND t.client_id = ?
        ");
        $verify_stmt->execute([$application_id, $user_id]);
        $application = $verify_stmt->fetch();
        
        if ($application) {
            if ($action === 'accept') {
                // Accept application
                $update_stmt = $pdo->prepare("UPDATE applications SET status = 'accepted', updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$application_id]);
                
                // Update task to assign helper and change status to in_progress
                $task_update_stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET helper_id = (SELECT helper_id FROM applications WHERE id = ?), 
                        status = 'in_progress',
                        updated_at = NOW()
                    WHERE id = ? AND status = 'open'
                ");
                $task_update_stmt->execute([$application_id, $application['task_id']]);
                
                // Reject all other pending applications for this task
                $reject_others_stmt = $pdo->prepare("
                    UPDATE applications 
                    SET status = 'rejected', updated_at = NOW() 
                    WHERE task_id = ? AND id != ? AND status = 'pending'
                ");
                $reject_others_stmt->execute([$application['task_id'], $application_id]);
                
                // Create notification for accepted helper
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'application', ?, ?, NOW())
                ");
                $notification_content = "Your application for '" . $application['task_title'] . "' has been accepted!";
                $notification_stmt->execute([
                    $application['helper_id'], 
                    $notification_content, 
                    $application['task_id']
                ]);
                
                $success_message = "Application accepted successfully! The helper has been notified and assigned to your task.";
                
            } elseif ($action === 'reject') {
                // Reject application
                $update_stmt = $pdo->prepare("UPDATE applications SET status = 'rejected', updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$application_id]);
                
                // Create notification for rejected helper
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'application', ?, ?, NOW())
                ");
                $notification_content = "Your application for '" . $application['task_title'] . "' has been declined.";
                $notification_stmt->execute([
                    $application['helper_id'], 
                    $notification_content, 
                    $application['task_id']
                ]);
                
                $success_message = "Application rejected. The helper has been notified.";
            }
        }
    } catch (PDOException $e) {
        $error_message = "Error updating application status. Please try again.";
    }
}

// Build the query for applications
$where_conditions = ['t.client_id = ?'];
$params = [$user_id];

// Add status filter
if ($status_filter !== 'all') {
    $where_conditions[] = 'a.status = ?';
    $params[] = $status_filter;
}

// Add task filter
if ($task_filter > 0) {
    $where_conditions[] = 'a.task_id = ?';
    $params[] = $task_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get applications with task and helper details
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            u.fullname as helper_name,
            u.email as helper_email,
            u.profile_image,
            u.rating,
            u.total_ratings,
            (SELECT COUNT(*) FROM tasks t2 WHERE t2.helper_id = u.id AND t2.status = 'completed') as completed_tasks,
            t.title as task_title,
            t.budget as task_budget,
            t.location as task_location,
            t.scheduled_time,
            t.status as task_status
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        JOIN users u ON a.helper_id = u.id
        $where_clause
        ORDER BY a.$sort_by $sort_order
    ");
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    // Get application statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) as accepted_applications,
            SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_applications
        FROM applications a
        JOIN tasks t ON a.task_id = t.id
        WHERE t.client_id = ?
    ");
    $stats_stmt->execute([$user_id]);
    $stats = $stats_stmt->fetch();
    
    // Get user's tasks for filter dropdown
    $tasks_stmt = $pdo->prepare("
        SELECT id, title, status
        FROM tasks 
        WHERE client_id = ? 
        ORDER BY created_at DESC
    ");
    $tasks_stmt->execute([$user_id]);
    $user_tasks = $tasks_stmt->fetchAll();
    
} catch (PDOException $e) {
    $applications = [];
    $stats = ['total_applications' => 0, 'pending_applications' => 0, 'accepted_applications' => 0, 'rejected_applications' => 0];
    $user_tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/client css/applications.css" rel="stylesheet" />


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
            
            <a href="client-dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <a href="my-tasks.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
                <span class="nav-text">My Tasks</span>
            </a>
            
            <a href="post-task.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="16"/>
                    <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
                <span class="nav-text">Post Task</span>
            </a>
            
            <a href="applications.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
                <span class="nav-text">Applications</span>
            </a>
            
            <a href="messages.php" class="nav-item">
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-top">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                        </div>
                        Applications
                    </h1>
                    <div class="header-actions">
                        <a href="post-task.php" class="header-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="16"/>
                                <line x1="8" y1="12" x2="16" y2="12"/>
                            </svg>
                            Post New Task
                        </a>
                        <a href="my-tasks.php" class="header-btn" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                            View My Tasks
                        </a>
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
                        <div class="stat-label">Pending Review</div>
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
                            <select class="filter-select" name="task_id" onchange="document.getElementById('filtersForm').submit()">
                                <option value="0">All Tasks</option>
                                <?php foreach ($user_tasks as $task): ?>
                                    <option value="<?php echo $task['id']; ?>" <?php echo ($task_filter === $task['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(substr($task['title'], 0, 50)) . (strlen($task['title']) > 50 ? '...' : ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
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
            
            <!-- Applications Grid -->
            <div class="applications-grid">
                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <?php if ($status_filter !== 'all' || $task_filter > 0): ?>
                            <h2 class="empty-title">No Applications Found</h2>
                            <p class="empty-description">
                                No applications match your current filters. Try adjusting your filters or check back later for new applications.
                            </p>
                            <button onclick="clearFilters()" class="header-btn" style="margin: 0 auto;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 6h18"/>
                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                                Clear Filters
                            </button>
                        <?php else: ?>
                            <h2 class="empty-title">No Applications Yet</h2>
                            <p class="empty-description">
                                You haven't received any applications yet. Make sure your tasks are detailed and attractively priced to get more applications.
                            </p>
                            <a href="post-task.php" class="header-btn" style="margin: 0 auto;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="16"/>
                                    <line x1="8" y1="12" x2="16" y2="12"/>
                                </svg>
                                Post a New Task
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
                                <div class="helper-info">
                                    <div class="helper-avatar">
                                        <?php echo strtoupper(substr($app['helper_name'], 0, 1)); ?>
                                    </div>
                                    <div class="helper-details">
                                        <h3><?php echo htmlspecialchars($app['helper_name']); ?></h3>
                                        <div class="helper-rating">
                                            <div class="stars">
                                                <?php 
                                                $rating = $app['rating'] ? floatval($app['rating']) : 0;
                                                for ($i = 1; $i <= 5; $i++): 
                                                ?>
                                                    <svg class="star" viewBox="0 0 24 24" fill="<?php echo $i <= $rating ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                    </svg>
                                                <?php endfor; ?>
                                            </div>
                                            <span><?php echo $rating > 0 ? number_format($rating, 1) : 'New'; ?></span>
                                        </div>
                                        <div class="helper-stats">
                                            <?php 
                                            $completed = $app['completed_tasks'] ? $app['completed_tasks'] : 0;
                                            $total_ratings = $app['total_ratings'] ? $app['total_ratings'] : 0;
                                            echo $completed . ' task' . ($completed != 1 ? 's' : '') . ' completed';
                                            if ($total_ratings > 0) {
                                                echo ' • ' . $total_ratings . ' review' . ($total_ratings != 1 ? 's' : '');
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
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
                                        <span>Budget: $<?php echo number_format($app['task_budget'], 2); ?></span>
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
                            
                            <div class="proposal-section">
                                <div class="proposal-header">
                                    <div class="proposal-label">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                        Proposal
                                    </div>
                                    <div class="bid-amount">$<?php echo number_format($app['bid_amount'], 2); ?></div>
                                </div>
                                <div class="proposal-text">
                                    <?php echo nl2br(htmlspecialchars($app['proposal'])); ?>
                                </div>
                            </div>
                            
                            <div class="application-actions">
                                <?php if ($app['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('accept')">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="action-btn btn-accept">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20,6 9,17 4,12"/>
                                            </svg>
                                            Accept Application
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('reject')">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn btn-reject">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"/>
                                                <line x1="6" y1="6" x2="18" y2="18"/>
                                            </svg>
                                            Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="helper-profile.php?id=<?php echo $app['helper_id']; ?>" class="action-btn btn-view">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Profile
                                </a>
                                
                                <a href="messages.php?task_id=<?php echo $app['task_id']; ?>&helper_id=<?php echo $app['helper_id']; ?>" class="action-btn btn-contact">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                    Send Message
                                </a>
                                
                                <a href="task-details.php?id=<?php echo $app['task_id']; ?>" class="action-btn btn-view">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14,2 14,8 20,8"/>
                                    </svg>
                                    View Task
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
        
        function clearFilters() {
            window.location.href = 'applications.php';
        }
        
        function confirmAction(action) {
            const actionText = action === 'accept' ? 'accept' : 'reject';
            const confirmText = action === 'accept' 
                ? 'Are you sure you want to accept this application? This will assign the helper to your task.'
                : 'Are you sure you want to reject this application? This action cannot be undone.';
            
            return confirm(confirmText);
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
        
        // Add spinning animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>