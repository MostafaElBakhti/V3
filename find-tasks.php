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
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$location_filter = isset($_GET['location']) ? trim($_GET['location']) : '';
$min_budget = isset($_GET['min_budget']) ? floatval($_GET['min_budget']) : 0;
$max_budget = isset($_GET['max_budget']) ? floatval($_GET['max_budget']) : 10000;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Validate sort parameters
$allowed_sorts = ['created_at', 'scheduled_time', 'budget', 'title'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}

// Build the query
$where_conditions = [
    't.status = ?',
    't.client_id != ?',
    't.id NOT IN (SELECT DISTINCT task_id FROM applications WHERE helper_id = ? AND status IN (?, ?))'
];
$params = ['open', $user_id, $user_id, 'pending', 'accepted'];

// Add search filter
if (!empty($search_query)) {
    $where_conditions[] = '(t.title LIKE ? OR t.description LIKE ?)';
    $search_param = '%' . $search_query . '%';
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add location filter
if (!empty($location_filter)) {
    $where_conditions[] = 't.location LIKE ?';
    $params[] = '%' . $location_filter . '%';
}

// Add budget filters
if ($min_budget > 0) {
    $where_conditions[] = 't.budget >= ?';
    $params[] = $min_budget;
}

if ($max_budget < 10000) {
    $where_conditions[] = 't.budget <= ?';
    $params[] = $max_budget;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get tasks with client details and application counts
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            (SELECT COUNT(*) FROM applications WHERE task_id = t.id) as total_applications,
            (SELECT COUNT(*) FROM applications WHERE task_id = t.id AND status = 'pending') as pending_applications
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        $where_clause
        ORDER BY t.$sort_by $sort_order
        LIMIT 50
    ");
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    // Get task statistics for filters
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_available,
            MIN(budget) as min_budget_available,
            MAX(budget) as max_budget_available,
            AVG(budget) as avg_budget
        FROM tasks t
        WHERE t.status = 'open' 
        AND t.client_id != ?
        AND t.id NOT IN (
            SELECT DISTINCT task_id 
            FROM applications 
            WHERE helper_id = ? 
            AND status IN ('pending', 'accepted')
        )
    ");
    $stats_stmt->execute([$user_id, $user_id]);
    $task_stats = $stats_stmt->fetch();

    // Get popular locations
    $locations_stmt = $pdo->prepare("
        SELECT t.location, COUNT(*) as task_count
        FROM tasks t
        WHERE t.status = 'open' 
        AND t.client_id != ?
        AND t.id NOT IN (
            SELECT DISTINCT task_id 
            FROM applications 
            WHERE helper_id = ? 
            AND status IN ('pending', 'accepted')
        )
        GROUP BY t.location
        ORDER BY task_count DESC
        LIMIT 10
    ");
    $locations_stmt->execute([$user_id, $user_id]);
    $popular_locations = $locations_stmt->fetchAll();

} catch (PDOException $e) {
    $tasks = [];
    $task_stats = ['total_available' => 0, 'min_budget_available' => 0, 'max_budget_available' => 0, 'avg_budget' => 0];
    $popular_locations = [];
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
        
        .task-stats-banner {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            text-align: center;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #10b981;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Search & Filters */
        .search-filters {
            background: #f8fafc;
            border-radius: 16px;
            padding: 24px;
        }
        
        .search-section {
            margin-bottom: 20px;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 16px;
        }
        
        .search-input {
            width: 100%;
            padding: 16px 16px 16px 56px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 16px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .filter-input,
        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            background: white;
            transition: all 0.2s;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .budget-range {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 8px;
            align-items: center;
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        
        .filter-btn {
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .filter-btn:hover {
            transform: translateY(-1px);
        }
        
        /* Popular Locations */
        .popular-locations {
            margin-top: 16px;
        }
        
        .locations-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .location-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .location-tag {
            padding: 6px 12px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            font-size: 12px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .location-tag:hover {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }
        
        /* Tasks Grid */
        .tasks-container {
            margin-top: 32px;
        }
        
        .tasks-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .tasks-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .tasks-count {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
        }
        
        .sort-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .sort-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-right: 8px;
        }
        
        .sort-select {
            padding: 8px 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
        }
        
        .sort-toggle {
            padding: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .sort-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .tasks-grid {
            display: grid;
            gap: 24px;
        }
        
        .task-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
            position: relative;
            overflow: hidden;
        }
        
        .task-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .task-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.3;
            flex: 1;
            margin-right: 16px;
        }
        
        .task-budget {
            font-size: 28px;
            font-weight: 700;
            color: #10b981;
            text-align: right;
        }
        
        .budget-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .task-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
            font-size: 16px;
        }
        
        .task-meta {
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
        
        .task-badges {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .task-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-urgent {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-new {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .badge-popular {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .task-actions {
            display: flex;
            gap: 12px;
        }
        
        .task-btn {
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .task-btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .task-btn-secondary {
            background: linear-gradient(135deg, #4f46e5, #3730a3);
            color: white;
        }
        
        .task-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .applications-indicator {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
            .task-stats-banner {
                grid-template-columns: repeat(2, 1fr);
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
            
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .filter-actions {
                justify-content: stretch;
            }
            
            .filter-btn {
                flex: 1;
                justify-content: center;
            }
            
            .task-stats-banner {
                grid-template-columns: 1fr;
            }
            
            .task-meta {
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
            
            <a href="find-tasks.php" class="nav-item active">
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

            <!-- Page Header -->
            <div class="page-header">
                <div class="header-top">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="m21 21-4.35-4.35"/>
                            </svg>
                        </div>
                        Find Tasks
                    </h1>
                </div>
                
                <!-- Task Statistics -->
                <div class="task-stats-banner">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $task_stats['total_available']; ?></div>
                        <div class="stat-label">Available Tasks</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($task_stats['avg_budget'], 0); ?></div>
                        <div class="stat-label">Average Budget</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($task_stats['min_budget_available']); ?></div>
                        <div class="stat-label">Min Budget</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">$<?php echo number_format($task_stats['max_budget_available']); ?></div>
                        <div class="stat-label">Max Budget</div>
                    </div>
                </div>
                
                <!-- Search & Filters -->
                <div class="search-filters">
                    <form method="GET" action="" id="searchForm">
                        <div class="search-section">
                            <div class="search-box">
                                <svg class="search-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                <input type="text" class="search-input" name="search" 
                                       placeholder="Search tasks by title, description, or keywords..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            
                            <div class="filters-grid">
                                <div class="filter-group">
                                    <label class="filter-label">Location</label>
                                    <input type="text" class="filter-input" name="location" 
                                           placeholder="Enter city or area..." 
                                           value="<?php echo htmlspecialchars($location_filter); ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <label class="filter-label">Budget Range</label>
                                    <div class="budget-range">
                                        <input type="number" class="filter-input" name="min_budget" 
                                               placeholder="Min" min="0" max="10000" 
                                               value="<?php echo $min_budget > 0 ? $min_budget : ''; ?>">
                                        <span style="color: #64748b;">to</span>
                                        <input type="number" class="filter-input" name="max_budget" 
                                               placeholder="Max" min="0" max="10000" 
                                               value="<?php echo $max_budget < 10000 ? $max_budget : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="filter-btn btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="11" cy="11" r="8"/>
                                            <path d="m21 21-4.35-4.35"/>
                                        </svg>
                                        Search
                                    </button>
                                </div>
                                
                                <div class="filter-actions">
                                    <a href="find-tasks.php" class="filter-btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M3 6h18"/>
                                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        Clear
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Popular Locations -->
                        <?php if (!empty($popular_locations)): ?>
                        <div class="popular-locations">
                            <div class="locations-label">Popular Locations:</div>
                            <div class="location-tags">
                                <?php foreach ($popular_locations as $location): ?>
                                    <div class="location-tag" onclick="selectLocation('<?php echo htmlspecialchars($location['location']); ?>')">
                                        <?php echo htmlspecialchars($location['location']); ?> 
                                        (<?php echo $location['task_count']; ?>)
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Tasks Container -->
            <div class="tasks-container">
                <div class="tasks-header">
                    <div>
                        <h2 class="tasks-title">Available Opportunities</h2>
                        <p class="tasks-count"><?php echo count($tasks); ?> tasks found</p>
                    </div>
                    
                    <div class="sort-controls">
                        <span class="sort-label">Sort by:</span>
                        <select class="sort-select" onchange="updateSort(this.value)">
                            <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Date Posted</option>
                            <option value="budget" <?php echo $sort_by === 'budget' ? 'selected' : ''; ?>>Budget</option>
                            <option value="scheduled_time" <?php echo $sort_by === 'scheduled_time' ? 'selected' : ''; ?>>Scheduled Date</option>
                            <option value="title" <?php echo $sort_by === 'title' ? 'selected' : ''; ?>>Title</option>
                        </select>
                        <button class="sort-toggle" onclick="toggleSortOrder()">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <?php if ($sort_order === 'DESC'): ?>
                                    <path d="M7 13l3 3 3-3"/>
                                    <path d="M7 6l3 3 3-3"/>
                                <?php else: ?>
                                    <path d="M7 17l3-3 3 3"/>
                                    <path d="M7 7l3 3 3-3"/>
                                <?php endif; ?>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="tasks-grid">
                    <?php if (empty($tasks)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                    <line x1="9" y1="9" x2="15" y2="15"/>
                                    <line x1="15" y1="9" x2="9" y2="15"/>
                                </svg>
                            </div>
                            <h3 class="empty-title">No Tasks Found</h3>
                            <p class="empty-description">
                                <?php if (!empty($search_query) || !empty($location_filter) || $min_budget > 0 || $max_budget < 10000): ?>
                                    No tasks match your current search criteria. Try adjusting your filters or search terms.
                                <?php else: ?>
                                    No tasks are currently available. Check back later for new opportunities!
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($search_query) || !empty($location_filter) || $min_budget > 0 || $max_budget < 10000): ?>
                                <a href="find-tasks.php" class="task-btn task-btn-primary" style="max-width: 200px; margin: 0 auto;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 6h18"/>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Clear All Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-card">
                                <?php if ($task['pending_applications'] > 3): ?>
                                    <div class="applications-indicator">
                                        🔥 <?php echo $task['total_applications']; ?> applications
                                    </div>
                                <?php endif; ?>
                                
                                <div class="task-badges">
                                    <?php 
                                    $created_hours_ago = (time() - strtotime($task['created_at'])) / 3600;
                                    ?>
                                    
                                    <?php if ($created_hours_ago < 24): ?>
                                        <span class="task-badge badge-new">New</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['total_applications'] > 5): ?>
                                        <span class="task-badge badge-popular">Popular</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="task-header">
                                    <h3 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h3>
                                    <span class="task-status">Open</span>
                                </div>
                                
                                <p class="task-description">
                                    <?php echo htmlspecialchars(substr($task['description'], 0, 200)) . (strlen($task['description']) > 200 ? '...' : ''); ?>
                                </p>
                                
                                <div class="task-meta">
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        <span><?php echo date('M j, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($task['location']); ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                        <span><?php echo $task['total_applications']; ?> application<?php echo $task['total_applications'] != 1 ? 's' : ''; ?></span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                        <span>Posted <?php 
                                        $time_ago = time() - strtotime($task['created_at']);
                                        if ($time_ago < 3600) {
                                            echo floor($time_ago / 60) . ' min ago';
                                        } elseif ($time_ago < 86400) {
                                            echo floor($time_ago / 3600) . ' hrs ago';
                                        } else {
                                            echo floor($time_ago / 86400) . ' days ago';
                                        }
                                        ?></span>
                                    </div>
                                </div>
                                
                                <div class="client-section">
                                    <div class="client-avatar">
                                        <?php echo strtoupper(substr($task['client_name'], 0, 1)); ?>
                                    </div>
                                    <div class="client-info">
                                        <div class="client-name"><?php echo htmlspecialchars($task['client_name']); ?></div>
                                        <div class="client-rating">
                                            <div class="stars">
                                                <?php 
                                                $rating = $task['client_rating'] ? floatval($task['client_rating']) : 0;
                                                for ($i = 1; $i <= 5; $i++): 
                                                ?>
                                                    <svg class="star" viewBox="0 0 24 24" fill="<?php echo $i <= $rating ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                        <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                    </svg>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="rating-text">
                                                <?php echo $rating > 0 ? number_format($rating, 1) . ' rating' : 'New client'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="task-actions">
                                    <a href="task-details.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        View Details
                                    </a>
                                    <a href="apply-task.php?id=<?php echo $task['id']; ?>" class="task-btn task-btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
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
            
            // Update toggle icon
            const toggleBtn = sidebar.querySelector('.sidebar-toggle svg');
            if (sidebar.classList.contains('collapsed')) {
                toggleBtn.innerHTML = '<path d="m9 18 6-6-6-6"/>';
            } else {
                toggleBtn.innerHTML = '<path d="m15 18-6-6 6-6"/>';
            }
        }
        
        function selectLocation(location) {
            document.querySelector('input[name="location"]').value = location;
            document.getElementById('searchForm').submit();
        }
        
        function updateSort(sortBy) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('sort', sortBy);
            window.location.href = currentUrl.toString();
        }
        
        function toggleSortOrder() {
            const currentUrl = new URL(window.location);
            const currentOrder = new URLSearchParams(window.location.search).get('order') || 'desc';
            const newOrder = currentOrder === 'desc' ? 'asc' : 'desc';
            currentUrl.searchParams.set('order', newOrder);
            window.location.href = currentUrl.toString();
        }
        
        // Auto-submit search on input (debounced)
        let searchTimeout;
        document.querySelector('.search-input').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    document.getElementById('searchForm').submit();
                }
            }, 1000);
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape' && document.activeElement === document.querySelector('.search-input')) {
                document.querySelector('.search-input').value = '';
                document.getElementById('searchForm').submit();
            }
        });
        
        // Auto-refresh every 5 minutes for new tasks
        setInterval(() => {
            if (!document.querySelector('.search-input').value) {
                location.reload();
            }
        }, 300000);
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>