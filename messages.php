<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get conversation parameters
$selected_task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$selected_helper_id = isset($_GET['helper_id']) ? intval($_GET['helper_id']) : 0;

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $task_id = intval($_POST['task_id']);
    $receiver_id = intval($_POST['receiver_id']);
    $message_content = trim($_POST['message']);
    
    if (!empty($message_content) && $task_id > 0 && $receiver_id > 0) {
        try {
            // Verify the user has access to this task (must be the client who owns the task)
            $verify_stmt = $pdo->prepare("SELECT id, title FROM tasks WHERE id = ? AND client_id = ?");
            $verify_stmt->execute([$task_id, $user_id]);
            $task_info = $verify_stmt->fetch();
            
            if ($task_info) {
                // Insert message
                $insert_stmt = $pdo->prepare("
                    INSERT INTO messages (task_id, sender_id, receiver_id, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_stmt->execute([$task_id, $user_id, $receiver_id, $message_content]);
                
                // Create notification for receiver
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'message', ?, ?, NOW())
                ");
                $notification_content = "New message from " . $fullname . " regarding '" . $task_info['title'] . "'";
                $notification_stmt->execute([$receiver_id, $notification_content, $task_id]);
                
                $success_message = "Message sent successfully!";
            } else {
                $error_message = "You don't have permission to message about this task.";
            }
        } catch (PDOException $e) {
            error_log("Message send error: " . $e->getMessage());
            $error_message = "Error sending message. Please try again.";
        }
    } else {
        $error_message = "Please enter a message.";
    }
}

try {
    // Get all conversations for this client
    // We need to find all tasks owned by this client that have messages
    $conversations_stmt = $pdo->prepare("
        SELECT DISTINCT
            m.task_id,
            t.title as task_title,
            t.status as task_status,
            t.budget,
            t.location,
            t.scheduled_time,
            CASE 
                WHEN m.sender_id = ? THEN m.receiver_id 
                ELSE m.sender_id 
            END as other_user_id,
            u.fullname as other_user_name,
            u.email as other_user_email,
            u.rating as other_user_rating,
            u.profile_image as other_user_image,
            (SELECT message FROM messages m2 
             WHERE m2.task_id = m.task_id 
             AND ((m2.sender_id = ? AND m2.receiver_id = other_user_id) 
                  OR (m2.receiver_id = ? AND m2.sender_id = other_user_id))
             ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM messages m2 
             WHERE m2.task_id = m.task_id 
             AND ((m2.sender_id = ? AND m2.receiver_id = other_user_id) 
                  OR (m2.receiver_id = ? AND m2.sender_id = other_user_id))
             ORDER BY m2.created_at DESC LIMIT 1) as last_message_time,
            (SELECT COUNT(*) FROM messages m2 
             WHERE m2.task_id = m.task_id 
             AND m2.receiver_id = ? AND m2.sender_id = other_user_id 
             AND m2.is_read = FALSE) as unread_count
        FROM messages m
        JOIN tasks t ON m.task_id = t.id
        JOIN users u ON u.id = CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END
        WHERE t.client_id = ?
        ORDER BY last_message_time DESC
    ");
    $conversations_stmt->execute([
        $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id
    ]);
    $conversations = $conversations_stmt->fetchAll();
    
    // Get messages for selected conversation
    $messages = [];
    $selected_conversation = null;
    
    if ($selected_task_id > 0 && $selected_helper_id > 0) {
        // Verify access to this conversation - client must own the task
        $conv_stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.title as task_title,
                t.status as task_status,
                t.budget,
                t.location,
                t.scheduled_time,
                t.description,
                u.fullname as helper_name,
                u.email as helper_email,
                u.rating as helper_rating,
                u.profile_image as helper_image,
                u.total_ratings
            FROM tasks t
            JOIN users u ON u.id = ?
            WHERE t.id = ? AND t.client_id = ?
        ");
        $conv_stmt->execute([$selected_helper_id, $selected_task_id, $user_id]);
        $selected_conversation = $conv_stmt->fetch();
        
        if ($selected_conversation) {
            // Get messages for this conversation
            $messages_stmt = $pdo->prepare("
                SELECT 
                    m.*,
                    sender.fullname as sender_name,
                    receiver.fullname as receiver_name
                FROM messages m
                JOIN users sender ON m.sender_id = sender.id
                JOIN users receiver ON m.receiver_id = receiver.id
                WHERE m.task_id = ? 
                AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
            ");
            $messages_stmt->execute([
                $selected_task_id, $user_id, $selected_helper_id, $selected_helper_id, $user_id
            ]);
            $messages = $messages_stmt->fetchAll();
            
            // Mark messages as read
            $mark_read_stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = TRUE 
                WHERE task_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = FALSE
            ");
            $mark_read_stmt->execute([$selected_task_id, $user_id, $selected_helper_id]);
        }
    }
    
    // Get total unread count
    $unread_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_unread
        FROM messages m
        JOIN tasks t ON m.task_id = t.id
        WHERE m.receiver_id = ? AND m.is_read = FALSE AND t.client_id = ?
    ");
    $unread_stmt->execute([$user_id, $user_id]);
    $total_unread = $unread_stmt->fetch()['total_unread'];
    
} catch (PDOException $e) {
    error_log("Messages query error: " . $e->getMessage());
    $conversations = [];
    $messages = [];
    $selected_conversation = null;
    $total_unread = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Helpify</title>
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
        
        .unread-badge {
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            display: flex;
            transition: margin-left 0.3s ease;
            height: 100vh;
        }
        
        .main-content.collapsed {
            margin-left: 80px;
        }
        
        /* Messages Layout */
        .messages-container {
            display: flex;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin: 20px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        /* Conversations Sidebar */
        .conversations-sidebar {
            width: 380px;
            border-right: 2px solid #f1f5f9;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .conversations-header {
            padding: 24px;
            border-bottom: 2px solid #f1f5f9;
            background: #f8fafc;
        }
        
        .conversations-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .title-icon {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .conversations-subtitle {
            color: #64748b;
            font-size: 14px;
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 12px 0;
        }
        
        .conversation-item {
            padding: 16px 24px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 4px solid transparent;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #f8fafc;
        }
        
        .conversation-item.active {
            background: #eff6ff;
            border-left-color: #3b82f6;
        }
        
        .conversation-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        
        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-name {
            font-weight: 600;
            color: #1a1a1a;
            font-size: 16px;
            margin-bottom: 2px;
        }
        
        .conversation-task {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .conversation-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
        }
        
        .conversation-time {
            font-size: 12px;
            color: #64748b;
        }
        
        .conversation-unread {
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .conversation-preview {
            color: #64748b;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 24px;
            border-bottom: 2px solid #f1f5f9;
            background: #f8fafc;
        }
        
        .chat-header-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .chat-avatar {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        .chat-info {
            flex: 1;
        }
        
        .chat-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .chat-task-title {
            font-size: 16px;
            color: #3b82f6;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .chat-task-details {
            display: flex;
            gap: 16px;
            font-size: 14px;
            color: #64748b;
        }
        
        .chat-actions {
            display: flex;
            gap: 8px;
        }
        
        .chat-action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .chat-action-btn:hover {
            transform: translateY(-1px);
        }
        
        /* Messages Area */
        .messages-area {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: #f8fafc;
        }
        
        .message {
            display: flex;
            margin-bottom: 16px;
            gap: 12px;
        }
        
        .message.own {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #10b981, #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }
        
        .message.own .message-avatar {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .message-content {
            max-width: 60%;
            position: relative;
        }
        
        .message-bubble {
            background: white;
            padding: 16px 20px;
            border-radius: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .message.own .message-bubble {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .message-text {
            line-height: 1.5;
            word-wrap: break-word;
        }
        
        .message-time {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
            text-align: right;
        }
        
        .message.own .message-time {
            color: rgba(255, 255, 255, 0.8);
            text-align: left;
        }
        
        /* Message Input */
        .message-input-area {
            padding: 24px;
            border-top: 2px solid #f1f5f9;
            background: white;
        }
        
        .message-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            min-height: 48px;
            max-height: 120px;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            resize: none;
            transition: all 0.2s;
        }
        
        .message-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .send-btn {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            border-radius: 12px;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Empty States */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            padding: 60px 40px;
            text-align: center;
        }
        
        .empty-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 32px;
        }
        
        .empty-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .empty-description {
            font-size: 16px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        
        .empty-action {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .empty-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        /* Date dividers */
        .message-date {
            text-align: center;
            margin: 24px 0 16px;
        }
        
        .date-divider {
            background: #e2e8f0;
            color: #64748b;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .main-content.collapsed {
                margin-left: 80px;
            }
            
            .messages-container {
                margin: 10px;
                flex-direction: column;
                height: calc(100vh - 20px);
            }
            
            .conversations-sidebar {
                width: 100%;
                height: 200px;
                border-right: none;
                border-bottom: 2px solid #f1f5f9;
            }
            
            .chat-area {
                height: calc(100% - 200px);
            }
            
            .message-content {
                max-width: 80%;
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
                <?php if ($total_unread > 0): ?>
                    <span class="unread-badge"><?php echo $total_unread; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="settings.php" class="nav-item" style="margin-top: auto;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <span class="nav-text">Settings</span>
            </a>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
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
            
            <div class="messages-container">
                <!-- Conversations Sidebar -->
                <div class="conversations-sidebar">
                    <div class="conversations-header">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <h2 class="conversations-title">
                                    <div class="title-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        </svg>
                                    </div>
                                    Messages
                                </h2>
                                <p class="conversations-subtitle">
                                    <?php echo count($conversations); ?> conversation<?php echo count($conversations) != 1 ? 's' : ''; ?>
                                </p>
                            </div>
                            <?php if ($total_unread > 0): ?>
                                <div class="unread-badge"><?php echo $total_unread; ?> unread</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="conversations-list">
                        <?php if (empty($conversations)): ?>
                            <div class="empty-state" style="padding: 40px 20px;">
                                <div class="empty-icon" style="width: 80px; height: 80px; margin-bottom: 20px;">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    </svg>
                                </div>
                                <h3 class="empty-title" style="font-size: 18px;">No Messages Yet</h3>
                                <p class="empty-description" style="font-size: 14px;">
                                    Start conversations by posting tasks and receiving applications.
                                </p>
                                <a href="post-task.php" class="empty-action" style="font-size: 14px; padding: 8px 16px;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="12" y1="8" x2="12" y2="16"/>
                                        <line x1="8" y1="12" x2="16" y2="12"/>
                                    </svg>
                                    Post a Task
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <div class="conversation-item <?php echo ($selected_task_id == $conv['task_id'] && $selected_helper_id == $conv['other_user_id']) ? 'active' : ''; ?>" 
                                     onclick="selectConversation(<?php echo $conv['task_id']; ?>, <?php echo $conv['other_user_id']; ?>)">
                                    <div class="conversation-header">
                                        <div class="conversation-avatar">
                                            <?php echo strtoupper(substr($conv['other_user_name'], 0, 1)); ?>
                                        </div>
                                        <div class="conversation-info">
                                            <div class="conversation-meta">
                                                <div class="conversation-name"><?php echo htmlspecialchars($conv['other_user_name']); ?></div>
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <div class="conversation-unread"><?php echo $conv['unread_count']; ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="conversation-task">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                </svg>
                                                <?php echo htmlspecialchars(substr($conv['task_title'], 0, 30)) . (strlen($conv['task_title']) > 30 ? '...' : ''); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="conversation-meta">
                                        <div class="conversation-preview">
                                            <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)) . (strlen($conv['last_message']) > 50 ? '...' : ''); ?>
                                        </div>
                                        <div class="conversation-time">
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
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if ($selected_conversation): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-header-content">
                                <div class="chat-avatar">
                                    <?php echo strtoupper(substr($selected_conversation['helper_name'], 0, 1)); ?>
                                </div>
                                <div class="chat-info">
                                    <div class="chat-name"><?php echo htmlspecialchars($selected_conversation['helper_name']); ?></div>
                                    <div class="chat-task-title"><?php echo htmlspecialchars($selected_conversation['task_title']); ?></div>
                                    <div class="chat-task-details">
                                        <span>💰 $<?php echo number_format($selected_conversation['budget'], 2); ?></span>
                                        <span>📍 <?php echo htmlspecialchars($selected_conversation['location']); ?></span>
                                        <span>📅 <?php echo date('M j, Y', strtotime($selected_conversation['scheduled_time'])); ?></span>
                                    </div>
                                </div>
                                <div class="chat-actions">
                                    <a href="task-details.php?id=<?php echo $selected_task_id; ?>" class="chat-action-btn btn-primary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        View Task
                                    </a>
                                    <a href="helper-profile.php?id=<?php echo $selected_helper_id; ?>" class="chat-action-btn btn-secondary">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                            <circle cx="12" cy="7" r="4"/>
                                        </svg>
                                        Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Area -->
                        <div class="messages-area" id="messagesArea">
                            <?php if (empty($messages)): ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                            <circle cx="9" cy="10" r="1"/>
                                            <circle cx="15" cy="10" r="1"/>
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                                        </svg>
                                    </div>
                                    <h3 class="empty-title">Start the Conversation</h3>
                                    <p class="empty-description">
                                        Send a message to <?php echo htmlspecialchars($selected_conversation['helper_name']); ?> about your task.
                                    </p>
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
                                
                                <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'own' : ''; ?>">
                                    <div class="message-avatar">
                                        <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                    </div>
                                    <div class="message-content">
                                        <div class="message-bubble">
                                            <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                        </div>
                                        <div class="message-time">
                                            <?php echo date('M j, g:i A', strtotime($message['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Input Area -->
                        <div class="message-input-area">
                            <form class="message-form" method="POST" id="messageForm">
                                <input type="hidden" name="task_id" value="<?php echo $selected_task_id; ?>">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_helper_id; ?>">
                                <textarea class="message-input" name="message" placeholder="Type your message..." 
                                          rows="1" id="messageInput" required></textarea>
                                <button type="submit" name="send_message" class="send-btn" id="sendBtn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="22" y1="2" x2="11" y2="13"/>
                                        <polygon points="22,2 15,22 11,13 2,9 22,2"/>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="empty-state">
                            <div class="empty-icon">
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    <path d="M13 8l3 3-3 3"/>
                                    <path d="M5 12h14"/>
                                </svg>
                            </div>
                            <h3 class="empty-title">Select a Conversation</h3>
                            <p class="empty-description">
                                Choose a conversation from the sidebar to start messaging with helpers about your tasks.
                            </p>
                            <?php if (empty($conversations)): ?>
                                <a href="post-task.php" class="empty-action">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10"/>
                                        <line x1="12" y1="8" x2="12" y2="16"/>
                                        <line x1="8" y1="12" x2="16" y2="12"/>
                                    </svg>
                                    Post Your First Task
                                </a>
                            <?php endif; ?>
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
        
        function selectConversation(taskId, helperId) {
            window.location.href = `messages.php?task_id=${taskId}&helper_id=${helperId}`;
        }
        
        // Auto-resize message input
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Send message on Enter (but allow Shift+Enter for new lines)
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    document.getElementById('messageForm').submit();
                }
            });
        }
        
        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            if (messagesArea) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }
        
        // Scroll to bottom on page load
        document.addEventListener('DOMContentLoaded', scrollToBottom);
        
        // Handle form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                const sendBtn = document.getElementById('sendBtn');
                const messageInput = document.getElementById('messageInput');
                
                if (!messageInput.value.trim()) {
                    e.preventDefault();
                    return;
                }
                
                // Disable send button and show loading
                sendBtn.disabled = true;
                sendBtn.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <path d="M21 12a9 9 0 11-6.219-8.56"/>
                    </svg>
                `;
                
                // Re-enable after 3 seconds in case of errors
                setTimeout(() => {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = `
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22,2 15,22 11,13 2,9 22,2"/>
                        </svg>
                    `;
                }, 3000);
            });
        }
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 4000);
            });
        });
        
        // Add spinning animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        
        // Refresh page every 30 seconds to check for new messages (simple polling)
        if (window.location.search.includes('task_id') && window.location.search.includes('helper_id')) {
            setInterval(() => {
                // Only refresh if user hasn't typed anything recently
                const messageInput = document.getElementById('messageInput');
                if (!messageInput || !messageInput.value.trim()) {
                    window.location.reload();
                }
            }, 30000);
        }
    </script>
</body>
</html>