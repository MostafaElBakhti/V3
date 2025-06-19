<?php
// Simple My Jobs Page - Educational Version
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
$jobs = [];
$total_jobs = 0;
$active_jobs = 0;
$completed_jobs = 0;
$total_earned = 0;

// Handle simple job actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $task_id = intval($_POST['task_id']);
    $action = $_POST['action'];
    
    try {
        // Check if task belongs to this helper
        $verify_stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND helper_id = ?");
        $verify_stmt->execute([$task_id, $user_id]);
        $task = $verify_stmt->fetch();
        
        if ($task) {
            if ($action === 'complete_job' && $task['status'] === 'in_progress') {
                // Mark task as completed
                $update_stmt = $pdo->prepare("UPDATE tasks SET status = 'completed' WHERE id = ? AND helper_id = ?");
                $update_stmt->execute([$task_id, $user_id]);
                $success_message = "Job marked as completed!";
            }
        } else {
            $error_message = "Task not found.";
        }
    } catch (PDOException $e) {
        $error_message = "Error updating job status.";
    }
}

try {
    // 1. Get job statistics (simple separate queries)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE helper_id = ?");
    $stmt->execute([$user_id]);
    $total_jobs = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE helper_id = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $active_jobs = $stmt->fetch()['count'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE helper_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_jobs = $stmt->fetch()['count'];

    // Calculate total earnings
    $stmt = $pdo->prepare("SELECT SUM(budget) as total FROM tasks WHERE helper_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $total_earned = $result['total'] ? $result['total'] : 0;

    // 2. Build simple query for jobs
    $sql = "SELECT t.*, u.fullname as client_name
            FROM tasks t 
            JOIN users u ON t.client_id = u.id 
            WHERE t.helper_id = ?";
    $params = [$user_id];

    // Add status filter if not 'all'
    if ($status_filter !== 'all') {
        $sql .= " AND t.status = ?";
        $params[] = $status_filter;
    }

    // Order by newest first
    $sql .= " ORDER BY t.created_at DESC";

    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobs = $stmt->fetchAll();

} catch (PDOException $e) {
    // If error, keep default values
    error_log("My Jobs error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/helper css/my-jobs.css" rel="stylesheet" />
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
                    </div>
                </div>
                
                <!-- Job Statistics -->
                <div class="job-stats">
                    <div class="stat-item <?php echo $status_filter === 'all' ? 'active' : ''; ?>" onclick="filterByStatus('all')">
                        <div class="stat-number"><?php echo $total_jobs; ?></div>
                        <div class="stat-label">Total Jobs</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'in_progress' ? 'active' : ''; ?>" onclick="filterByStatus('in_progress')">
                        <div class="stat-number"><?php echo $active_jobs; ?></div>
                        <div class="stat-label">Active Jobs</div>
                    </div>
                    <div class="stat-item <?php echo $status_filter === 'completed' ? 'active' : ''; ?>" onclick="filterByStatus('completed')">
                        <div class="stat-number"><?php echo $completed_jobs; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($total_earned); ?></div>
                        <div class="stat-label">Total Earned</div>
                    </div>
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
                            <a href="?status=all" class="header-btn" style="margin: 0 auto;">View All Jobs</a>
                        <?php else: ?>
                            <h2 class="empty-title">No Jobs Yet</h2>
                            <p class="empty-description">
                                You haven't been assigned to any jobs yet. Start by applying to tasks!
                            </p>
                            <a href="find-tasks.php" class="header-btn" style="margin: 0 auto;">Find Tasks to Apply</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <div class="job-card">
                            <div class="job-header">
                                <h3 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h3>
                                <span class="job-status status-<?php echo $job['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                                </span>
                            </div>
                            
                            <p class="job-description">
                                <?php 
                                $description = htmlspecialchars($job['description']);
                                echo strlen($description) > 200 ? substr($description, 0, 200) . '...' : $description;
                                ?>
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
                                    <span>Added <?php echo date('M d, Y', strtotime($job['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="client-section">
                                <div class="client-avatar">
                                    <?php echo strtoupper(substr($job['client_name'], 0, 1)); ?>
                                </div>
                                <div class="client-info">
                                    <div class="client-name"><?php echo htmlspecialchars($job['client_name']); ?></div>
                                </div>
                            </div>
                            
                            <div class="job-actions">
                                <?php if ($job['status'] === 'in_progress'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to mark this job as completed?')">
                                        <input type="hidden" name="task_id" value="<?php echo $job['id']; ?>">
                                        <input type="hidden" name="action" value="complete_job">
                                        <button type="submit" class="job-btn btn-primary">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20,6 9,17 4,12"/>
                                            </svg>
                                            Mark as Completed
                                        </button>
                                    </form>
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
        }
        
        function filterByStatus(status) {
            // Simple way to change URL with status filter
            window.location.href = 'my-jobs.php?status=' + status;
        }
    </script>
</body>
</html>