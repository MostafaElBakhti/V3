<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get filter parameter (simplified)
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Fetch client statistics using simple separate queries
try {
    // Count total tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tasks WHERE client_id = ?");
    $stmt->execute([$user_id]);
    $total_tasks = $stmt->fetch()['total'];

    // Count open tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as open FROM tasks WHERE client_id = ? AND status = 'open'");
    $stmt->execute([$user_id]);
    $open_tasks = $stmt->fetch()['open'];

    // Count active tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM tasks WHERE client_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $active_tasks = $stmt->fetch()['active'];

    // Count completed tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM tasks WHERE client_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_tasks = $stmt->fetch()['completed'];

    // Count cancelled tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as cancelled FROM tasks WHERE client_id = ? AND status = 'cancelled'");
    $stmt->execute([$user_id]);
    $cancelled_tasks = $stmt->fetch()['cancelled'];

    // Get tasks based on filter (simplified query)
    if ($status_filter === 'all') {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE client_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE client_id = ? AND status = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id, $status_filter]);
    }
    $tasks = $stmt->fetchAll();

    // For each task, get application count (simplified)
    foreach ($tasks as &$task) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as app_count FROM applications WHERE task_id = ?");
        $stmt->execute([$task['id']]);
        $task['application_count'] = $stmt->fetch()['app_count'];

        // Get pending applications count
        $stmt = $pdo->prepare("SELECT COUNT(*) as pending_count FROM applications WHERE task_id = ? AND status = 'pending'");
        $stmt->execute([$task['id']]);
        $task['pending_applications'] = $stmt->fetch()['pending_count'];
    }

} catch (PDOException $e) {
    // Set default values if database error
    $total_tasks = 0;
    $open_tasks = 0;
    $active_tasks = 0;
    $completed_tasks = 0;
    $cancelled_tasks = 0;
    $tasks = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tasks | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/client css/my-tasks.css" rel="stylesheet" />
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
            
            <a href="my-tasks.php" class="nav-item active">
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
            
            <a href="applications.php" class="nav-item">
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
            
            <a href="settings.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <span class="nav-text">Settings</span>
            </a>
            
            <a href="logout.php" class="nav-item" style="margin-top: auto; color: #ef4444;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16,17 21,12 16,7"/>
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
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                        My Tasks
                    </h1>
                    <button class="create-task-btn" onclick="window.location.href='post-task.php'">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Create New Task
                    </button>
                </div>
                
                <!-- Task Statistics -->
                <div class="task-stats">
                    <div class="stat-item <?php echo $status_filter === 'all' ? 'active' : ''; ?>" onclick="filterByStatus('all')">
                        <div class="stat-number"><?php echo $total_tasks; ?></div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'open' ? 'active' : ''; ?>" onclick="filterByStatus('open')">
                        <div class="stat-number"><?php echo $open_tasks; ?></div>
                        <div class="stat-label">Open</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>" onclick="filterByStatus('in_progress')">
                        <div class="stat-number"><?php echo $active_tasks; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" onclick="filterByStatus('completed')">
                        <div class="stat-number"><?php echo $completed_tasks; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>" onclick="filterByStatus('cancelled')">
                        <div class="stat-number"><?php echo $cancelled_tasks; ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>
            </div>
            
            <!-- Tasks Grid -->
            <div class="tasks-grid">
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                        </div>
                        <?php if ($status_filter !== 'all'): ?>
                            <h2 class="empty-title">No <?php echo ucfirst(str_replace('_', ' ', $status_filter)); ?> Tasks</h2>
                            <p class="empty-description">
                                You don't have any <?php echo str_replace('_', ' ', $status_filter); ?> tasks yet.
                            </p>
                            <button onclick="showAllTasks()" class="create-task-btn" style="margin: 0 auto;">
                                Show All Tasks
                            </button>
                        <?php else: ?>
                            <h2 class="empty-title">No Tasks Yet</h2>
                            <p class="empty-description">
                                You haven't created any tasks yet. Start by posting your first task and connect with skilled helpers in your area.
                            </p>
                            <button onclick="window.location.href='post-task.php'" class="create-task-btn" style="margin: 0 auto;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="16"/>
                                    <line x1="8" y1="12" x2="16" y2="12"/>
                                </svg>
                                Create Your First Task
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card">
                            <?php if ($task['pending_applications'] > 0): ?>
                                <div class="applications-badge">
                                    <?php echo $task['pending_applications']; ?> New Application<?php echo $task['pending_applications'] > 1 ? 's' : ''; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="task-card-header">
                                <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                <span class="task-status status-<?php echo $task['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                            </div>
                            
                            <p class="task-description">
                                <?php echo htmlspecialchars(substr($task['description'], 0, 200)) . (strlen($task['description']) > 200 ? '...' : ''); ?>
                            </p>
                            
                            <div class="task-meta">
                                <div class="meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span><?php echo htmlspecialchars($task['location']); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span><?php echo date('M d, Y - g:i A', strtotime($task['scheduled_time'])); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span class="meta-value">$<?php echo number_format($task['budget'], 2); ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span><?php echo $task['application_count']; ?> Application<?php echo $task['application_count'] != 1 ? 's' : ''; ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0h8m-6 0v9m6-9v9m-6 0H5a2 2 0 01-2-2V5a2 2 0 012-2h3m0 0V1" />
                                    </svg>
                                    <span>Created <?php echo date('M d, Y', strtotime($task['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="task-actions">
                                <a href="task-details.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-primary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Details
                                </a>
                                
                                <?php if ($task['application_count'] > 0): ?>
                                    <a href="applications.php?task_id=<?php echo $task['id']; ?>" class="task-btn task-btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        </svg>
                                        Applications (<?php echo $task['application_count']; ?>)
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($task['status'] === 'open'): ?>
                                    <a href="edit-task.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        Edit
                                    </a>
                                <?php endif; ?>
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
            window.location.href = 'my-tasks.php?status=' + status;
        }
        
        function showAllTasks() {
            window.location.href = 'my-tasks.php';
        }
    </script>
</body>
</html>