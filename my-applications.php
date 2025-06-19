<?php
// Simple My Applications Page - Educational Version
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get user data from session
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get simple filter from URL
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Initialize default values
$applications = [];
$total_applications = 0;
$pending_applications = 0;
$accepted_applications = 0;
$rejected_applications = 0;

try {
    // 1. Get application statistics (simple separate queries)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ?");
    $stmt->execute([$user_id]);
    $total_applications = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_applications = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ? AND status = 'accepted'");
    $stmt->execute([$user_id]);
    $accepted_applications = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM applications WHERE helper_id = ? AND status = 'rejected'");
    $stmt->execute([$user_id]);
    $rejected_applications = $stmt->fetch()['count'];

    // 2. Build simple query for applications
    $sql = "SELECT a.*, t.title as task_title, t.budget as task_budget, t.location as task_location, 
                   t.scheduled_time, t.status as task_status, t.description as task_description,
                   u.fullname as client_name
            FROM applications a 
            JOIN tasks t ON a.task_id = t.id 
            JOIN users u ON t.client_id = u.id 
            WHERE a.helper_id = ?";
    $params = [$user_id];

    // Add status filter if not 'all'
    if ($status_filter !== 'all') {
        $sql .= " AND a.status = ?";
        $params[] = $status_filter;
    }

    // Order by newest first
    $sql .= " ORDER BY a.created_at DESC";

    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();

} catch (PDOException $e) {
    // If error, keep default values
    error_log("My Applications error: " . $e->getMessage());
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
    <title>My Applications | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/helper css/my-applications.css" rel="stylesheet" />
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
                        <div class="stat-number"><?php echo $total_applications; ?></div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" onclick="filterByStatus('pending')">
                        <div class="stat-number"><?php echo $pending_applications; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>" onclick="filterByStatus('accepted')">
                        <div class="stat-number"><?php echo $accepted_applications; ?></div>
                        <div class="stat-label">Accepted</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" onclick="filterByStatus('rejected')">
                        <div class="stat-number"><?php echo $rejected_applications; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
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
                            </svg>
                        </div>
                        <?php if ($status_filter !== 'all'): ?>
                            <h3 class="empty-title">No <?php echo ucfirst($status_filter); ?> Applications</h3>
                            <p class="empty-description">
                                You don't have any <?php echo $status_filter; ?> applications yet.
                            </p>
                            <a href="find-tasks.php" class="action-btn btn-primary" style="margin: 0 auto;">Find More Tasks</a>
                        <?php else: ?>
                            <h3 class="empty-title">No Applications Yet</h3>
                            <p class="empty-description">
                                You haven't applied to any tasks yet. Start by browsing available tasks!
                            </p>
                            <a href="find-tasks.php" class="action-btn btn-primary" style="margin: 0 auto;">Find Tasks to Apply</a>
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
                                        <span><?php echo date('M d, Y', strtotime($app['scheduled_time'])); ?></span>
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
                                    <?php 
                                    $proposal = htmlspecialchars($app['proposal']);
                                    echo strlen($proposal) > 200 ? substr($proposal, 0, 200) . '...' : $proposal;
                                    ?>
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
        }
        
        function filterByStatus(status) {
            // Simple way to change URL with status filter
            window.location.href = 'my-applications.php?status=' + status;
        }
    </script>
</body>
</html>