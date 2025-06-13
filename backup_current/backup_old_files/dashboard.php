<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Get active tasks count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE client_id = ? AND status IN ('open', 'in_progress')");
    $stmt->execute([$_SESSION['user_id']]);
    $activeTasks = $stmt->fetchColumn();

    // Get pending payments
    $stmt = $pdo->prepare("SELECT SUM(budget) FROM tasks WHERE client_id = ? AND status = 'completed'");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingPayments = $stmt->fetchColumn() ?: 0;

    // Get unread messages count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadMessages = $stmt->fetchColumn();

    // Get recent tasks
    $stmt = $pdo->prepare("
        SELECT t.*, u.fullname as helper_name 
        FROM tasks t 
        LEFT JOIN users u ON t.helper_id = u.id 
        WHERE t.client_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 4
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recentTasks = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Client Dashboard - Helpify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      background: #f5f8ff;
      color: #1a1a2e;
    }
    header {
      background: #007bff;
      color: white;
      padding: 20px 40px;
      font-weight: 600;
      font-size: 1.5rem;
      letter-spacing: 0.05em;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    header nav a {
      color: white;
      text-decoration: none;
      margin-left: 25px;
      font-weight: 500;
      transition: color 0.3s ease;
    }
    header nav a:hover {
      color: #cce0ff;
    }

    .dashboard-container {
      display: flex;
      height: calc(100vh - 72px);
    }
    /* Sidebar */
    .sidebar {
      width: 260px;
      background: #0b3d91;
      color: white;
      display: flex;
      flex-direction: column;
      padding: 30px 20px;
      box-shadow: 3px 0 10px rgba(0,0,0,0.1);
    }
    .sidebar h2 {
      margin-bottom: 40px;
      font-weight: 700;
      font-size: 1.6rem;
      letter-spacing: 0.1em;
    }
    .sidebar a {
      color: white;
      text-decoration: none;
      margin-bottom: 20px;
      font-weight: 500;
      padding: 12px 15px;
      border-radius: 8px;
      transition: background 0.3s ease;
    }
    .sidebar a.active,
    .sidebar a:hover {
      background: #0056b3;
    }

    /* Main content */
    .main-content {
      flex: 1;
      padding: 30px 40px;
      overflow-y: auto;
    }
    .welcome-msg {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 25px;
      color: #007bff;
    }

    /* Cards */
    .cards {
      display: flex;
      gap: 30px;
      flex-wrap: wrap;
      margin-bottom: 40px;
    }
    .card {
      background: white;
      box-shadow: 0 15px 25px rgba(0,123,255,0.15);
      border-radius: 14px;
      padding: 25px 30px;
      flex: 1 1 250px;
      min-width: 250px;
      transition: transform 0.3s ease;
      cursor: default;
    }
    .card:hover {
      transform: translateY(-8px);
      box-shadow: 0 25px 35px rgba(0,123,255,0.25);
    }
    .card h3 {
      font-weight: 600;
      margin-bottom: 10px;
      color: #0b3d91;
    }
    .card p {
      font-size: 1.1rem;
      color: #33475b;
    }

    /* Table */
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 15px 25px rgba(0,123,255,0.15);
    }
    th, td {
      padding: 15px 20px;
      text-align: left;
      border-bottom: 1px solid #eaeaea;
    }
    th {
      background: #007bff;
      color: white;
      font-weight: 600;
      letter-spacing: 0.05em;
    }
    tr:last-child td {
      border-bottom: none;
    }
    tbody tr:hover {
      background: #f0f6ff;
    }

    /* Responsive */
    @media (max-width: 900px) {
      .dashboard-container {
        flex-direction: column;
      }
      .sidebar {
        width: 100%;
        flex-direction: row;
        overflow-x: auto;
        padding: 15px;
      }
      .sidebar h2 {
        display: none;
      }
      .sidebar a {
        margin-bottom: 0;
        margin-right: 20px;
        padding: 10px 15px;
        white-space: nowrap;
      }
      .main-content {
        padding: 20px;
      }
      .cards {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>

  <header>
    Helpify <?php echo ucfirst($user['user_type']); ?> Dashboard
    <nav>
      <a href="profile.php">Profile</a>
      <a href="tasks.php">Tasks</a>
      <a href="messages.php">Messages</a>
      <a href="settings.php">Settings</a>
      <a href="logout.php" style="font-weight: 700;">Logout</a>
    </nav>
  </header>

  <div class="dashboard-container">
    <aside class="sidebar">
      <h2>Menu</h2>
      <a href="dashboard.php" class="active">Overview</a>
      <a href="tasks.php">My Tasks</a>
      <a href="payments.php">Payments</a>
      <a href="support.php">Support</a>
    </aside>

    <main class="main-content">
      <div class="welcome-msg">Welcome back, <?php echo htmlspecialchars($user['fullname']); ?>!</div>

      <div class="cards">
        <div class="card">
          <h3>Active Tasks</h3>
          <p><?php echo $activeTasks; ?> tasks currently in progress.</p>
        </div>
        <div class="card">
          <h3>Pending Payments</h3>
          <p>$<?php echo number_format($pendingPayments, 2); ?> waiting for approval.</p>
        </div>
        <div class="card">
          <h3>Messages</h3>
          <p><?php echo $unreadMessages; ?> unread messages.</p>
        </div>
      </div>

      <h3>Recent Tasks</h3>
      <table>
        <thead>
          <tr>
            <th>Task</th>
            <th>Status</th>
            <th>Date Posted</th>
            <th>Helper</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentTasks as $task): ?>
          <tr>
            <td><?php echo htmlspecialchars($task['title']); ?></td>
            <td><?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?></td>
            <td><?php echo date('M j, Y', strtotime($task['created_at'])); ?></td>
            <td><?php echo $task['helper_name'] ? htmlspecialchars($task['helper_name']) : '-'; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

    </main>
  </div>

</body>
</html> 