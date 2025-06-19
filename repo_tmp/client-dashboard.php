<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Fetch client statistics - Using simple separate queries
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

    // Calculate total spent on completed tasks
    $stmt = $pdo->prepare("SELECT SUM(budget) as total_spent FROM tasks WHERE client_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetch()['total_spent'] ?? 0;

    // Calculate pending budget for open tasks
    $stmt = $pdo->prepare("SELECT SUM(budget) as pending_budget FROM tasks WHERE client_id = ? AND status = 'open'");
    $stmt->execute([$user_id]);
    $pending_budget = $stmt->fetch()['pending_budget'] ?? 0;

    // Get week spending (last 7 days) - simplified
    $stmt = $pdo->prepare("SELECT SUM(budget) as week_spending FROM tasks WHERE client_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$user_id]);
    $week_spending = $stmt->fetch()['week_spending'] ?? 0;

    // Count new helpers (simplified - count applications in last 7 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) as new_helpers FROM applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $new_helpers = $stmt->fetch()['new_helpers'];

    // Count pending applications for this client's tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_applications FROM applications a JOIN tasks t ON a.task_id = t.id WHERE t.client_id = ? AND a.status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_apps = $stmt->fetch()['pending_applications'];

    // Get most recent task (simplified)
    $stmt = $pdo->prepare("SELECT id, title, description, status, budget, created_at, scheduled_time FROM tasks WHERE client_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $recent_task = $stmt->fetch();

} catch (PDOException $e) {
    // Set default values if database error
    $total_tasks = 0;
    $open_tasks = 0;
    $active_tasks = 0;
    $completed_tasks = 0;
    $total_spent = 0;
    $pending_budget = 0;
    $week_spending = 0;
    $new_helpers = 0;
    $pending_apps = 0;
    $recent_task = null;
}

// Handle new task creation if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $scheduled_date = $_POST['scheduled_date'];
    $scheduled_time = $_POST['scheduled_time'];
    $budget = $_POST['budget'];

    // Basic validation
    if (empty($title) || empty($description) || empty($location) || empty($scheduled_date) || empty($scheduled_time) || empty($budget)) {
        $error_message = "All fields are required.";
    } elseif (strlen($title) < 5) {
        $error_message = "Task title must be at least 5 characters.";
    } elseif (strlen($description) < 20) {
        $error_message = "Description must be at least 20 characters.";
    } elseif ($budget < 10 || $budget > 10000) {
        $error_message = "Budget must be between $10 and $10,000.";
    } else {
        try {
            // Combine date and time
            $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time;

            // Insert new task
            $stmt = $pdo->prepare("INSERT INTO tasks (client_id, title, description, location, scheduled_time, budget, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
            $stmt->execute([$user_id, $title, $description, $location, $scheduled_datetime, $budget]);

            $success_message = "Task created successfully!";
        } catch (PDOException $e) {
            $error_message = "Error creating task. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/client css/client-dashbord.css" rel="stylesheet" />
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
            
            <a href="client-dashboard.php" class="nav-item active">
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
            <!-- Header -->
            <div class="header">
                <h1 class="greeting">Good morning, <?php echo explode(' ', $fullname)[0]; ?>!</h1>
                <div class="header-actions">
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
                    </div>
                    <div class="stat-value" style="color: white; font-size: 42px;">$<?php echo number_format($pending_budget); ?></div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.9);">Active task budget</div>
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
                    </div>
                    <div class="stat-value">$<?php echo number_format($week_spending, 0); ?></div>
                    <div class="stat-label">This week's spending</div>
                </div>
            </div>
            
            <!-- Priority Action Banner -->
            <?php if ($pending_apps > 0): ?>
            <div style="background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 20px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 12px;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 8v4m0 4h.01"/>
                        </svg>
                        <?php echo $pending_apps; ?> Applications Need Your Review!
                    </h2>
                    <p style="font-size: 16px; opacity: 0.9;">Helpers are waiting for your response. Review applications to get your tasks started.</p>
                </div>
                <a href="applications.php" style="background: white; color: #ef4444; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 16px;">
                    Review Now →
                </a>
            </div>
            <?php endif; ?>
            
            <!-- Attractive Action Cards -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px;">
                <!-- Add Task Card -->
                <div style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); border-radius: 20px; padding: 32px; color: white; cursor: pointer; transition: all 0.3s ease; position: relative; overflow: hidden;" 
                     onclick="window.location.href='post-task.php'" 
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
            
            <!-- Recent Task Section -->
            <div class="tasks-section">
                <div class="tasks-header">
                    <h2 class="tasks-title">
                        <div class="title-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                            </svg>
                        </div>
                        Your Recent Task
                    </h2>
                    <a href="my-tasks.php" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                        View All Tasks
                    </a>
                </div>
                
                <div class="task-list">
                    <?php if (!$recent_task): ?>
                        <div style="text-align: center; padding: 80px 20px; background: linear-gradient(135deg, #f8fafc, #e2e8f0); border-radius: 16px; border: 2px dashed #cbd5e1;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #e2e8f0, #cbd5e1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14,2 14,8 20,8"/>
                                </svg>
                            </div>
                            <h3 style="color: #333; margin-bottom: 12px; font-size: 24px; font-weight: 600;">No Tasks Yet</h3>
                            <p style="color: #666; margin-bottom: 24px; font-size: 16px; line-height: 1.5;">
                                Start by creating your first task using the "Add New Task" card above.
                            </p>
                            <button onclick="window.location.href='post-task.php'" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 14px 28px; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 16px;">
                                Create Your First Task
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="task-card">
                            <div class="task-header">
                                <h3 class="task-title"><?php echo htmlspecialchars($recent_task['title']); ?></h3>
                                <span class="task-status status-<?php echo $recent_task['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $recent_task['status'])); ?>
                                </span>
                            </div>
                            
                            <p class="task-description"><?php echo htmlspecialchars(substr($recent_task['description'], 0, 150)) . '...'; ?></p>
                            
                            <div class="task-details">
                                <div class="task-detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <?php echo date('M d, Y', strtotime($recent_task['scheduled_time'])); ?>
                                </div>
                                <div class="task-detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    $<?php echo number_format($recent_task['budget'], 2); ?>
                                </div>
                                <div class="task-detail">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 9l2 2 4-4" />
                                    </svg>
                                    Created <?php echo date('M d', strtotime($recent_task['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="task-actions">
                                <button class="task-btn task-btn-primary" onclick="viewTask(<?php echo $recent_task['id']; ?>)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                    View Details
                                </button>
                                <a href="my-tasks.php" class="task-btn task-btn-secondary">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14,2 14,8 20,8"/>
                                    </svg>
                                    View All Tasks
                                </a>
                            </div>
                        </div>
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
            
            // Update toggle icon
            const toggleBtn = sidebar.querySelector('.sidebar-toggle svg');
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.innerHTML = '<path d="m9 18 6-6-6-6"/>';
            } else {
                toggleBtn.innerHTML = '<path d="m15 18-6-6 6-6"/>';
            }
        }
        
        function viewTask(taskId) {
            window.location.href = `task-details.php?id=${taskId}`;
        }
    </script>
</body>
</html>