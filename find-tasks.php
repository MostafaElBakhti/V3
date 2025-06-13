<?php
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$location_filter = trim($_GET['location'] ?? '');
$min_budget = floatval($_GET['min_budget'] ?? 0);
$max_budget = floatval($_GET['max_budget'] ?? 10000);
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Build query conditions
$where_conditions = [
    "t.status = 'open'",
    "t.client_id != ?",
    "t.id NOT IN (SELECT DISTINCT task_id FROM applications WHERE helper_id = ? AND status IN ('pending', 'accepted'))"
];
$params = [$user_id, $user_id];

if ($search) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($location_filter) {
    $where_conditions[] = "t.location LIKE ?";
    $params[] = "%$location_filter%";
}

if ($min_budget > 0) {
    $where_conditions[] = "t.budget >= ?";
    $params[] = $min_budget;
}

if ($max_budget < 10000) {
    $where_conditions[] = "t.budget <= ?";
    $params[] = $max_budget;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get available tasks
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            u.total_ratings as client_total_ratings,
            COUNT(DISTINCT a.id) as application_count,
            COUNT(DISTINCT CASE WHEN a.status = 'pending' THEN a.id END) as pending_applications
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        LEFT JOIN applications a ON t.id = a.task_id
        $where_clause
        GROUP BY t.id, u.id
        ORDER BY t.$sort_by $sort_order
        LIMIT 50
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
    
    // Get popular locations for filter suggestions
    $stmt = $pdo->prepare("
        SELECT location, COUNT(*) as task_count
        FROM tasks 
        WHERE status = 'open' AND client_id != ?
        GROUP BY location 
        ORDER BY task_count DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $popular_locations = $stmt->fetchAll();
    
    // Get search statistics
    $total_tasks = count($tasks);
    $avg_budget = $total_tasks > 0 ? array_sum(array_column($tasks, 'budget')) / $total_tasks : 0;
    $competition_level = $total_tasks > 0 ? array_sum(array_column($tasks, 'application_count')) / $total_tasks : 0;
    
} catch (PDOException $e) {
    error_log("Find tasks query error: " . $e->getMessage());
    $tasks = [];
    $popular_locations = [];
    $total_tasks = 0;
    $avg_budget = 0;
    $competition_level = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Tasks | Helpify</title>
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
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            color: white;
            font-size: 32px;
            font-weight: 700;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
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
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .btn-small {
            padding: 8px 16px;
            font-size: 12px;
        }
        
        /* Search and Filters */
        .search-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 32px;
        }
        
        .search-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .search-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .search-subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 16px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .budget-range {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .budget-range input {
            width: 80px;
        }
        
        .popular-locations {
            margin-top: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .location-tag {
            background: #f3f4f6;
            color: #4b5563;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .location-tag:hover {
            background: #667eea;
            color: white;
        }
        
        /* Stats */
        .search-stats {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 32px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }
        
        /* Task Results */
        .results-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 24px;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .results-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sort-controls select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }
        
        .task-grid {
            display: grid;
            gap: 24px;
        }
        
        .task-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
        }
        
        .task-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .task-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .client-info {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .client-avatar {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .client-name {
            font-weight: 500;
            color: #4b5563;
        }
        
        .client-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: 8px;
        }
        
        .stars {
            display: flex;
            gap: 1px;
        }
        
        .star {
            color: #fbbf24;
            font-size: 12px;
        }
        
        .budget-section {
            text-align: right;
        }
        
        .budget-amount {
            font-size: 28px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 4px;
        }
        
        .budget-label {
            font-size: 12px;
            color: #666;
        }
        
        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .meta-item svg {
            width: 16px;
            height: 16px;
        }
        
        .task-description {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .task-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .tag {
            background: #f0f9ff;
            color: #0369a1;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .competition-indicator {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 8px 12px;
            margin-bottom: 16px;
            font-size: 12px;
            color: #92400e;
        }
        
        .competition-high {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        
        .competition-low {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }
        
        .task-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }
        
        .urgency-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #ef4444;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .sidebar {
                display: none;
            }
            
            .search-form {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .search-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .task-header {
                flex-direction: column;
                gap: 16px;
            }
            
            .task-meta {
                grid-template-columns: 1fr;
            }
            
            .task-actions {
                justify-content: stretch;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">H</div>
            
            <a href="helper-dashboard.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
            </a>
            
            <a href="find-tasks.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </a>
            
            <a href="my-applications.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
            </a>
            
            <a href="my-jobs.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
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
                <h1 class="page-title">Find Tasks</h1>
                <div class="header-actions">
                    <a href="my-applications.php" class="btn btn-secondary">
                        My Applications
                    </a>
                    <a href="helper-dashboard.php" class="btn btn-secondary">
                        Dashboard
                    </a>
                </div>
            </div>
            
            <!-- Search and Filters -->
            <div class="search-container">
                <div class="search-header">
                    <h2 class="search-title">Find Your Next Opportunity</h2>
                    <p class="search-subtitle">Discover tasks that match your skills and schedule</p>
                </div>
                
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="search">Search Tasks</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Enter keywords, skills, or task type...">
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location_filter); ?>" 
                               placeholder="City, area, or zip code">
                    </div>
                    
                    <div class="form-group">
                        <label>Budget Range</label>
                        <div class="budget-range">
                            <input type="number" name="min_budget" value="<?php echo $min_budget; ?>" placeholder="Min" min="0">
                            <span>-</span>
                            <input type="number" name="max_budget" value="<?php echo $max_budget; ?>" placeholder="Max" max="10000">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="sort">Sort By</label>
                        <select id="sort" name="sort">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Latest First</option>
                            <option value="budget" <?php echo $sort_by === 'budget' ? 'selected' : ''; ?>>Highest Budget</option>
                            <option value="scheduled_time" <?php echo $sort_by === 'scheduled_time' ? 'selected' : ''; ?>>Earliest Date</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        Search
                    </button>
                </form>
                
                <?php if (!empty($popular_locations)): ?>
                <div class="popular-locations">
                    <strong style="color: #374151; margin-right: 12px;">Popular locations:</strong>
                    <?php foreach ($popular_locations as $loc): ?>
                        <span class="location-tag" onclick="setLocation('<?php echo htmlspecialchars($loc['location']); ?>')">
                            <?php echo htmlspecialchars($loc['location']); ?> (<?php echo $loc['task_count']; ?>)
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Search Stats -->
            <div class="search-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $total_tasks; ?></div>
                    <div class="stat-label">Tasks Found</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">$<?php echo number_format($avg_budget, 0); ?></div>
                    <div class="stat-label">Average Budget</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo round($competition_level, 1); ?></div>
                    <div class="stat-label">Avg Applications</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php 
                        if ($competition_level < 3) echo "Low";
                        elseif ($competition_level < 6) echo "Medium";
                        else echo "High";
                        ?>
                    </div>
                    <div class="stat-label">Competition Level</div>
                </div>
            </div>
            
            <!-- Results -->
            <div class="results-container">
                <div class="results-header">
                    <h3 class="results-title">Available Tasks</h3>
                </div>
                
                <?php if (empty($tasks)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <h3>No tasks found</h3>
                        <p>
                            <?php if ($search || $location_filter || $min_budget > 0 || $max_budget < 10000): ?>
                                Try adjusting your search filters to find more opportunities.
                            <?php else: ?>
                                There are no available tasks at the moment. Check back later!
                            <?php endif; ?>
                        </p>
                        <?php if ($search || $location_filter || $min_budget > 0 || $max_budget < 10000): ?>
                            <a href="find-tasks.php" class="btn btn-primary" style="margin-top: 16px;">
                                Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="task-grid">
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-card">
                                <?php
                                // Check if task is urgent (within 24 hours)
                                $time_until = strtotime($task['scheduled_time']) - time();
                                $is_urgent = $time_until > 0 && $time_until < 86400;
                                ?>
                                
                                <?php if ($is_urgent): ?>
                                    <div class="urgency-badge">Urgent</div>
                                <?php endif; ?>
                                
                                <div class="task-header">
                                    <div>
                                        <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                        <div class="client-info">
                                            <div class="client-avatar">
                                                <?php echo strtoupper(substr($task['client_name'], 0, 1)); ?>
                                            </div>
                                            <span class="client-name"><?php echo htmlspecialchars($task['client_name']); ?></span>
                                            <div class="client-rating">
                                                <div class="stars">
                                                    <?php
                                                    $rating = round($task['client_rating']);
                                                    for ($i = 1; $i <= 5; $i++) {
                                                        echo '<span class="star">' . ($i <= $rating ? '★' : '☆') . '</span>';
                                                    }
                                                    ?>
                                                </div>
                                                <span style="font-size: 12px; color: #666;">
                                                    (<?php echo $task['client_total_ratings']; ?>)
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="budget-section">
                                        <div class="budget-amount">$<?php echo number_format($task['budget'], 2); ?></div>
                                        <div class="budget-label">Fixed Price</div>
                                    </div>
                                </div>
                                
                                <div class="task-meta">
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        <?php echo htmlspecialchars($task['location']); ?>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                        <?php echo date('M j, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        Posted <?php echo date('M j', strtotime($task['created_at'])); ?>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                        </svg>
                                        <?php echo $task['application_count']; ?> Applications
                                    </div>
                                </div>
                                
                                <div class="task-description">
                                    <?php echo htmlspecialchars(substr($task['description'], 0, 200)); ?>
                                    <?php if (strlen($task['description']) > 200): ?>...<?php endif; ?>
                                </div>
                                
                                <?php
                                // Competition indicator
                                $competition_class = '';
                                $competition_text = '';
                                if ($task['application_count'] < 3) {
                                    $competition_class = 'competition-low';
                                    $competition_text = '🟢 Low competition - Great chance to win!';
                                } elseif ($task['application_count'] < 6) {
                                    $competition_class = '';
                                    $competition_text = '🟡 Medium competition - ' . $task['application_count'] . ' other applicants';
                                } else {
                                    $competition_class = 'competition-high';
                                    $competition_text = '🔴 High competition - ' . $task['application_count'] . ' applicants, stand out!';
                                }
                                ?>
                                
                                <div class="competition-indicator <?php echo $competition_class; ?>">
                                    <?php echo $competition_text; ?>
                                </div>
                                
                                <div class="task-actions">
                                    <a href="task-details.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary btn-small">
                                        View Details
                                    </a>
                                    <a href="apply-task.php?id=<?php echo $task['id']; ?>" class="btn btn-primary btn-small">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        Apply Now
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <script>
        function setLocation(location) {
            document.getElementById('location').value = location;
            document.querySelector('.search-form').submit();
        }
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            if (!document.hidden) {
                location.reload();
            }
        }, 60000);
        
        // Enhanced task card interactions
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.task-card');
            
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-6px)';
                    this.style.boxShadow = '0 16px 40px rgba(0, 0, 0, 0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = '0 12px 30px rgba(0, 0, 0, 0.15)';
                });
            });
        });
        
        // Smart search suggestions
        const searchInput = document.getElementById('search');
        const suggestions = [
            'house cleaning', 'garden work', 'moving help', 'furniture assembly',
            'pet sitting', 'grocery shopping', 'handyman', 'tutoring',
            'event planning', 'photography', 'painting', 'delivery'
        ];
        
        searchInput.addEventListener('focus', function() {
            if (!this.value) {
                this.placeholder = suggestions[Math.floor(Math.random() * suggestions.length)] + '...';
            }
        });
    </script>
</body>
</html>