<?php
require_once 'config.php';

// This is a debug script to help you see what's happening with tasks
// You can run this temporarily to debug the issue

echo "<h2>Debug: Task System</h2>";

try {
    // Check total tasks in database
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tasks");
    $total_tasks = $stmt->fetch()['total'];
    echo "<p><strong>Total tasks in database:</strong> $total_tasks</p>";
    
    // Check open tasks
    $stmt = $pdo->query("SELECT COUNT(*) as open_tasks FROM tasks WHERE status = 'open'");
    $open_tasks = $stmt->fetch()['open_tasks'];
    echo "<p><strong>Open tasks:</strong> $open_tasks</p>";
    
    // Show recent tasks
    echo "<h3>Recent Tasks (Last 5):</h3>";
    $stmt = $pdo->query("
        SELECT t.id, t.title, t.status, t.budget, t.location, t.created_at, t.client_id,
               u.fullname as client_name
        FROM tasks t 
        JOIN users u ON t.client_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $recent_tasks = $stmt->fetchAll();
    
    if (empty($recent_tasks)) {
        echo "<p>No tasks found in database.</p>";
    } else {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Title</th><th>Status</th><th>Budget</th><th>Location</th><th>Client</th><th>Created</th></tr>";
        foreach ($recent_tasks as $task) {
            echo "<tr>";
            echo "<td>" . $task['id'] . "</td>";
            echo "<td>" . htmlspecialchars($task['title']) . "</td>";
            echo "<td>" . $task['status'] . "</td>";
            echo "<td>$" . number_format($task['budget'], 2) . "</td>";
            echo "<td>" . htmlspecialchars($task['location']) . "</td>";
            echo "<td>" . htmlspecialchars($task['client_name']) . "</td>";
            echo "<td>" . $task['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check users
    echo "<h3>Users in System:</h3>";
    $stmt = $pdo->query("SELECT id, fullname, email, user_type FROM users ORDER BY id");
    $users = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Type</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . $user['id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['fullname']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . $user['user_type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show what a helper would see
    if (isset($_GET['helper_id'])) {
        $helper_id = intval($_GET['helper_id']);
        echo "<h3>What Helper ID $helper_id Would See:</h3>";
        
        $stmt = $pdo->prepare("
            SELECT 
                t.*,
                u.fullname as client_name,
                COUNT(DISTINCT a.id) as total_applications
            FROM tasks t
            JOIN users u ON t.client_id = u.id
            LEFT JOIN applications a ON t.id = a.task_id
            WHERE t.status = 'open' 
            AND t.client_id != ? 
            AND NOT EXISTS (
                SELECT 1 
                FROM applications a2 
                WHERE a2.task_id = t.id 
                AND a2.helper_id = ? 
                AND a2.status != 'rejected'
            )
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$helper_id, $helper_id]);
        $helper_tasks = $stmt->fetchAll();
        
        echo "<p><strong>Available tasks for helper $helper_id:</strong> " . count($helper_tasks) . "</p>";
        
        if (!empty($helper_tasks)) {
            echo "<table border='1' cellpadding='10'>";
            echo "<tr><th>ID</th><th>Title</th><th>Budget</th><th>Client</th><th>Applications</th></tr>";
            foreach ($helper_tasks as $task) {
                echo "<tr>";
                echo "<td>" . $task['id'] . "</td>";
                echo "<td>" . htmlspecialchars($task['title']) . "</td>";
                echo "<td>$" . number_format($task['budget'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($task['client_name']) . "</td>";
                echo "<td>" . $task['total_applications'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p><a href='?helper_id=2'>Test with Helper ID 2</a> (assuming you have a helper with ID 2)</p>";
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; margin: 10px 0; }
    th { background: #f0f0f0; }
    td, th { text-align: left; }
</style>