<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get parameters (simplified)
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$helper_id = isset($_GET['helper_id']) ? intval($_GET['helper_id']) : 0;

// Handle message sending (simplified)
$success_message = '';
$error_message = '';

if ($_POST && isset($_POST['send_message'])) {
    $message_task_id = $_POST['task_id'];
    $receiver_id = $_POST['receiver_id'];
    $message_content = trim($_POST['message']);
    
    if (!empty($message_content) && $message_task_id > 0 && $receiver_id > 0) {
        try {
            // Verify the client owns this task
            $stmt = $pdo->prepare("SELECT id, title FROM tasks WHERE id = ? AND client_id = ?");
            $stmt->execute([$message_task_id, $user_id]);
            $task = $stmt->fetch();
            
            if ($task) {
                // Insert message
                $stmt = $pdo->prepare("INSERT INTO messages (task_id, sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$message_task_id, $user_id, $receiver_id, $message_content]);
                
                $success_message = "Message sent successfully!";
            } else {
                $error_message = "You don't have permission to message about this task.";
            }
        } catch (PDOException $e) {
            $error_message = "Error sending message. Please try again.";
        }
    } else {
        $error_message = "Please enter a valid message.";
    }
}

try {
    // Get all conversations (simplified)
    $stmt = $pdo->prepare("
        SELECT 
            t.id as task_id,
            t.title as task_title,
            t.status as task_status,
            t.budget,
            t.scheduled_time,
            u.id as helper_id,
            u.fullname as helper_name,
            u.rating,
            MAX(m.created_at) as last_message_time,
            COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 END) as unread_count
        FROM tasks t
        JOIN users u ON t.helper_id = u.id
        LEFT JOIN messages m ON t.id = m.task_id
        WHERE t.client_id = ? AND t.helper_id IS NOT NULL
        AND EXISTS(SELECT 1 FROM messages WHERE task_id = t.id)
        GROUP BY t.id, u.id
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $conversations = $stmt->fetchAll();

    // Get messages for selected conversation (simplified)
    $messages = [];
    $selected_conversation = null;
    
    if ($task_id > 0 && $helper_id > 0) {
        // Get conversation details
        $stmt = $pdo->prepare("
            SELECT t.*, u.fullname as helper_name, u.rating
            FROM tasks t
            JOIN users u ON t.helper_id = u.id
            WHERE t.id = ? AND t.client_id = ? AND t.helper_id = ?
        ");
        $stmt->execute([$task_id, $user_id, $helper_id]);
        $selected_conversation = $stmt->fetch();
        
        if ($selected_conversation) {
            // Get messages
            $stmt = $pdo->prepare("
                SELECT m.*, sender.fullname as sender_name, receiver.fullname as receiver_name
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN users receiver ON m.receiver_id = receiver.id
                WHERE m.task_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$task_id, $user_id, $helper_id, $helper_id, $user_id]);
            $messages = $stmt->fetchAll();
            
            // Mark messages as read
            $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE task_id = ? AND sender_id = ? AND receiver_id = ?");
            $stmt->execute([$task_id, $helper_id, $user_id]);
        }
    }

    // Get simple statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT t.id) as total_conversations,
            COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 END) as total_unread
        FROM tasks t
        LEFT JOIN messages m ON t.id = m.task_id
        WHERE t.client_id = ? AND t.helper_id IS NOT NULL
    ");
    $stmt->execute([$user_id, $user_id]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    $conversations = [];
    $messages = [];
    $selected_conversation = null;
    $stats = ['total_conversations' => 0, 'total_unread' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/client css/messages.css" rel="stylesheet" />
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
            
            <a href="applications.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
                <span class="nav-text">Applications</span>
            </a>
            
            <a href="messages.php" class="nav-item active">
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
            <div class="messages-container">
                <!-- Conversations Sidebar -->
                <div class="conversations-sidebar">
                    <div class="conversations-header">
                        <h1 class="page-title">
                            <div class="title-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </div>
                            Messages
                        </h1>
                        
                        <div class="message-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_conversations']; ?></div>
                                <div class="stat-label">Conversations</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_unread']; ?></div>
                                <div class="stat-label">Unread Messages</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="conversations-list">
                        <?php if (empty($conversations)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <div class="empty-title">No Conversations</div>
                                <div class="empty-description">
                                    You'll see conversations here when helpers are assigned to your tasks.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <a href="?task_id=<?php echo $conv['task_id']; ?>&helper_id=<?php echo $conv['helper_id']; ?>" 
                                   class="conversation-item <?php echo ($task_id == $conv['task_id'] && $helper_id == $conv['helper_id']) ? 'active' : ''; ?>">
                                    <div class="conversation-header">
                                        <div class="helper-info">
                                            <div class="helper-name"><?php echo htmlspecialchars($conv['helper_name']); ?></div>
                                            <div class="task-title"><?php echo htmlspecialchars($conv['task_title']); ?></div>
                                            <span class="task-status status-<?php echo $conv['task_status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $conv['task_status'])); ?>
                                            </span>
                                        </div>
                                        <div class="conversation-meta">
                                            <div class="message-time">
                                                <?php 
                                                if ($conv['last_message_time']) {
                                                    $time_diff = time() - strtotime($conv['last_message_time']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . 'm ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . 'h ago';
                                                    } else {
                                                        echo date('M j', strtotime($conv['last_message_time']));
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <?php if ($conv['unread_count'] > 0): ?>
                                                <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if ($selected_conversation): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-helper-info">
                                <div class="helper-avatar">
                                    <?php echo strtoupper(substr($selected_conversation['helper_name'], 0, 1)); ?>
                                </div>
                                <div class="chat-helper-details">
                                    <h3><?php echo htmlspecialchars($selected_conversation['helper_name']); ?></h3>
                                    <div class="helper-rating">
                                        <div class="stars">
                                            <?php 
                                            $rating = $selected_conversation['rating'] ? floatval($selected_conversation['rating']) : 0;
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <svg class="star" viewBox="0 0 24 24" fill="<?php echo $i <= $rating ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                        <span><?php echo $rating > 0 ? number_format($rating, 1) . ' rating' : 'New helper'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="chat-task-info">
                                <div class="task-info-header">
                                    <div class="task-info-title"><?php echo htmlspecialchars($selected_conversation['title']); ?></div>
                                    <div class="task-budget">$<?php echo number_format($selected_conversation['budget'], 2); ?></div>
                                </div>
                                <div class="task-meta">
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                        <span><?php echo date('M j, Y \a\t g:i A', strtotime($selected_conversation['scheduled_time'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span><?php echo htmlspecialchars($selected_conversation['location']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12,6 12,12 16,14"/>
                                        </svg>
                                        <span class="task-status status-<?php echo $selected_conversation['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $selected_conversation['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Alert Messages -->
                        <?php if (!empty($success_message)): ?>
                            <div style="padding: 0 24px; padding-top: 16px;">
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div style="padding: 0 24px; padding-top: 16px;">
                                <div class="alert alert-error">
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Messages List -->
                        <div class="messages-list" id="messagesList">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                    </div>
                                    <div class="empty-title">No Messages Yet</div>
                                    <div class="empty-description">
                                        Start the conversation by sending a message to your helper.
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php 
                                $current_date = '';
                                foreach ($messages as $message): 
                                    $message_date = date('Y-m-d', strtotime($message['created_at']));
                                    if ($message_date !== $current_date):
                                        $current_date = $message_date;
                                ?>
                                    <div class="message-date">
                                        <span class="date-divider">
                                            <?php 
                                            $today = date('Y-m-d');
                                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                                            
                                            if ($message_date === $today) {
                                                echo 'Today';
                                            } elseif ($message_date === $yesterday) {
                                                echo 'Yesterday';
                                            } else {
                                                echo date('F j, Y', strtotime($message_date));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                    <div class="message-avatar">
                                        <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                    </div>
                                    <div class="message-content">
                                        <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                        <div class="message-time"><?php echo date('g:i A', strtotime($message['created_at'])); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="message-input-area">
                            <form method="POST" class="message-input-form">
                                <input type="hidden" name="task_id" value="<?php echo $selected_conversation['id']; ?>">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_conversation['helper_id']; ?>">
                                <textarea class="message-input" name="message" placeholder="Type your message..." required maxlength="1000" id="messageTextarea"></textarea>
                                <button type="submit" name="send_message" class="send-button">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22,2 15,22 11,13 2,9 22,2"/>
                                    </svg>
                                    Send
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            </div>
                            <div class="empty-title">Select a Conversation</div>
                            <div class="empty-description">
                                Choose a conversation from the sidebar to start messaging with your helper about the task.
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
        
        // Auto-resize message textarea
        const messageTextarea = document.getElementById('messageTextarea');
        if (messageTextarea) {
            messageTextarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
        
        // Auto-scroll to bottom of messages
        const messagesList = document.getElementById('messagesList');
        if (messagesList && messagesList.children.length > 0) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
        
        // Hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>