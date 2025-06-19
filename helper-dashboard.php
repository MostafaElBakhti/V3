<?php
// Simple Helper Dashboard - Educational Version
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get user data from session
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Initialize default values
$total_applications = 0;
$pending_applications = 0;
$accepted_applications = 0;
$total_earned = 0;
$available_tasks = [];
$recent_applications = [];

// Get helper statistics - Simple queries
try {
    // 1. Count total applications by this helper
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ?");
    $stmt->execute([$user_id]);
    $total_applications = $stmt->fetch()['count'];

    // 2. Count pending applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_applications = $stmt->fetch()['count'];

    // 3. Count accepted applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ? AND status = 'accepted'");
    $stmt->execute([$user_id]);
    $accepted_applications = $stmt->fetch()['count'];

    // 4. Calculate total earnings from completed tasks
    $stmt = $pdo->prepare("SELECT SUM(budget) as total FROM tasks WHERE helper_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $total_earned = $result['total'] ? $result['total'] : 0;

    // 5. Get available tasks (simple version)
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.description, t.location, t.budget, t.scheduled_time, 
               u.fullname as client_name
        FROM tasks t 
        JOIN users u ON t.client_id = u.id
        WHERE t.status = 'open' 
        AND t.client_id != ?
        ORDER BY t.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $available_tasks = $stmt->fetchAll();

    // 6. Get recent applications
    $stmt = $pdo->prepare("
        SELECT a.status, a.bid_amount, a.created_at, t.title 
        FROM applications a 
        JOIN tasks t ON a.task_id = t.id 
        WHERE a.helper_id = ? 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_applications = $stmt->fetchAll();

} catch (PDOException $e) {
    // If error, keep default values
    error_log("Dashboard error: " . $e->getMessage());
}

// Calculate success rate (simple math)
if ($total_applications > 0) {
    $success_rate = round(($accepted_applications / $total_applications) * 100);
} else {
    $success_rate = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helper Dashboard | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/helper css/helper-dashboard.css" rel="stylesheet" />
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
            
            <a href="helper-dashboard.php" class="nav-item active">
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
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color: white;">$<?php echo number_format($total_earned); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">Total earnings</div>
                </div>
                
                <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white;">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: rgba(255,255,255,0.2);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value" style="color: white;"><?php echo count($available_tasks); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">Available tasks</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $success_rate; ?>%</div>
                    <div class="stat-label">Success rate</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo $pending_applications; ?></div>
                    <div class="stat-label">Applications pending</div>
                </div>
            </div>
            
            <!-- Available Tasks Section -->
            <div class="tasks-section">
                <div class="tasks-header">
                    <h2 class="tasks-title">
                        <div class="title-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="12" cy="12" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                        </div>
                        Available Tasks
                    </h2>
                    <a href="find-tasks.php" class="refresh-btn">View All</a>
                </div>
                
                <div class="task-list">
                    <?php if (empty($available_tasks)): ?>
                        <div class="no-tasks">
                            <div class="no-tasks-icon">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <circle cx="12" cy="12" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                            </div>
                            <h3>No Available Tasks</h3>
                            <p>Check back later for new opportunities!</p>
                            <a href="find-tasks.php" class="task-btn task-btn-primary">Browse All Tasks</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($available_tasks as $task): ?>
                            <div class="task-card">
                                <div class="task-header">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <span class="task-status">Open</span>
                                </div>
                                
                                <p class="task-description">
                                    <?php echo htmlspecialchars(substr($task['description'], 0, 150)) . '...'; ?>
                                </p>
                                
                                <div class="task-details">
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <?php echo date('M d, Y', strtotime($task['scheduled_time'])); ?>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <strong style="color: #10b981;">$<?php echo number_format($task['budget'], 2); ?></strong>
                                    </div>
                                    <div class="task-detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <?php echo htmlspecialchars($task['location']); ?>
                                    </div>
                                </div>
                                
                                <div class="client-info">
                                    <div class="client-avatar">
                                        <?php echo strtoupper(substr($task['client_name'], 0, 1)); ?>
                                    </div>
                                    <div class="client-name">
                                        Posted by <?php echo htmlspecialchars($task['client_name']); ?>
                                    </div>
                                </div>
                                
                                <div class="task-actions">
                                    <a href="apply-task.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-primary">
                                        Apply Now
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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
        }
    </script>
</body>
</html>