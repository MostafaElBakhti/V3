<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$fullname = $_SESSION['fullname'];

// Get conversation parameters
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$conversation_with = isset($_GET['with']) ? intval($_GET['with']) : 0;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'send_message':
                $task_id = intval($_POST['task_id']);
                $receiver_id = intval($_POST['receiver_id']);
                $message = trim($_POST['message']);
                
                if (empty($message) || strlen($message) > 2000) {
                    echo json_encode(['success' => false, 'message' => 'Message must be between 1 and 2000 characters.']);
                    exit();
                }
                
                // Verify task relationship
                $stmt = $pdo->prepare("
                    SELECT client_id, helper_id FROM tasks 
                    WHERE id = ? AND (client_id = ? OR helper_id = ? OR 
                          EXISTS(SELECT 1 FROM applications WHERE task_id = ? AND helper_id = ? AND status = 'accepted'))
                ");
                $stmt->execute([$task_id, $user_id, $user_id, $task_id, $user_id]);
                $task = $stmt->fetch();
                
                if (!$task) {
                    echo json_encode(['success' => false, 'message' => 'Not authorized to message on this task.']);
                    exit();
                }
                
                // Insert message
                $stmt = $pdo->prepare("
                    INSERT INTO messages (task_id, sender_id, receiver_id, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$task_id, $user_id, $receiver_id, $message]);
                
                echo json_encode(['success' => true, 'message' => 'Message sent successfully.']);
                break;
                
            case 'get_messages':
                $task_id = intval($_POST['task_id']);
                $other_user_id = intval($_POST['other_user_id']);
                $last_message_id = intval($_POST['last_message_id'] ?? 0);
                
                // Get messages
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                           s.fullname as sender_name,
                           r.fullname as receiver_name
                    FROM messages m
                    JOIN users s ON m.sender_id = s.id
                    JOIN users r ON m.receiver_id = r.id
                    WHERE m.task_id = ? 
                    AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                    AND m.id > ?
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([$task_id, $user_id, $other_user_id, $other_user_id, $user_id, $last_message_id]);
                $messages = $stmt->fetchAll();
                
                // Mark messages as read
                $stmt = $pdo->prepare("
                    UPDATE messages SET is_read = 1 
                    WHERE task_id = ? AND receiver_id = ? AND sender_id = ? AND is_read = 0
                ");
                $stmt->execute([$task_id, $user_id, $other_user_id]);
                
                echo json_encode(['success' => true, 'messages' => $messages]);
                break;
                
            case 'mark_read':
                $task_id = intval($_POST['task_id']);
                $sender_id = intval($_POST['sender_id']);
                
                $stmt = $pdo->prepare("
                    UPDATE messages SET is_read = 1 
                    WHERE task_id = ? AND receiver_id = ? AND sender_id = ?
                ");
                $stmt->execute([$task_id, $user_id, $sender_id]);
                
                echo json_encode(['success' => true]);
                break;
        }
    } catch (PDOException $e) {
        error_log("Messages error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
    exit();
}

try {
    // Get conversations list
    $conversations = [];
    
    if ($user_type === 'client') {
        // Get conversations from tasks where user is client
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                t.id as task_id,
                t.title as task_title,
                t.status as task_status,
                u.id as other_user_id,
                u.fullname as other_user_name,
                u.email as other_user_email,
                (SELECT COUNT(*) FROM messages WHERE task_id = t.id AND receiver_id = ? AND is_read = 0) as unread_count,
                (SELECT message FROM messages WHERE task_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages WHERE task_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM tasks t
            LEFT JOIN applications a ON t.id = a.task_id AND a.status = 'accepted'
            LEFT JOIN users u ON a.helper_id = u.id
            WHERE t.client_id = ? 
            AND EXISTS(SELECT 1 FROM messages WHERE task_id = t.id)
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([$user_id, $user_id]);
        $conversations = $stmt->fetchAll();
    } else {
        // Get conversations from tasks where user is helper
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                t.id as task_id,
                t.title as task_title,
                t.status as task_status,
                u.id as other_user_id,
                u.fullname as other_user_name,
                u.email as other_user_email,
                (SELECT COUNT(*) FROM messages WHERE task_id = t.id AND receiver_id = ? AND is_read = 0) as unread_count,
                (SELECT message FROM messages WHERE task_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages WHERE task_id = t.id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM tasks t
            JOIN users u ON t.client_id = u.id
            WHERE (t.helper_id = ? OR EXISTS(SELECT 1 FROM applications WHERE task_id = t.id AND helper_id = ? AND status = 'accepted'))
            AND EXISTS(SELECT 1 FROM messages WHERE task_id = t.id)
            ORDER BY last_message_time DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $conversations = $stmt->fetchAll();
    }
    
    // Get current conversation details
    $current_conversation = null;
    $current_messages = [];
    $other_user = null;
    
    if ($task_id && $conversation_with) {
        // Verify access to this conversation
        $stmt = $pdo->prepare("
            SELECT t.*, u.fullname as other_user_name, u.email as other_user_email
            FROM tasks t
            JOIN users u ON u.id = ?
            WHERE t.id = ? 
            AND (t.client_id = ? OR t.helper_id = ? OR 
                 EXISTS(SELECT 1 FROM applications WHERE task_id = t.id AND helper_id = ? AND status = 'accepted'))
        ");
        $stmt->execute([$conversation_with, $task_id, $user_id, $user_id, $user_id]);
        $current_conversation = $stmt->fetch();
        
        if ($current_conversation) {
            // Get messages for this conversation
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       s.fullname as sender_name,
                       r.fullname as receiver_name
                FROM messages m
                JOIN users s ON m.sender_id = s.id
                JOIN users r ON m.receiver_id = r.id
                WHERE m.task_id = ? 
                AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$task_id, $user_id, $conversation_with, $conversation_with, $user_id]);
            $current_messages = $stmt->fetchAll();
            
            // Mark messages as read
            $stmt = $pdo->prepare("
                UPDATE messages SET is_read = 1 
                WHERE task_id = ? AND receiver_id = ? AND sender_id = ?
            ");
            $stmt->execute([$task_id, $user_id, $conversation_with]);
            
            // Get other user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$conversation_with]);
            $other_user = $stmt->fetch();
        }
    }
    
} catch (PDOException $e) {
    error_log("Messages page error: " . $e->getMessage());
    $conversations = [];
    $current_messages = [];
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
            display: flex;
            height: 100vh;
        }
        
        /* Conversations List */
        .conversations-sidebar {
            width: 350px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            flex-direction: column;
        }
        
        .conversations-header {
            padding: 24px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .conversations-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .conversations-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 16px 0;
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid transparent;
            position: relative;
        }
        
        .conversation-item:hover {
            background: #f8f9fa;
        }
        
        .conversation-item.active {
            background: #dbeafe;
            border-left-color: #3b82f6;
        }
        
        .conversation-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-name {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-task {
            font-size: 12px;
            color: #667eea;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .conversation-preview {
            font-size: 14px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }
        
        .conversation-time {
            font-size: 12px;
            color: #999;
        }
        
        .unread-badge {
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        
        /* Chat Area */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        .chat-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            background: white;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .chat-user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .chat-user-info h3 {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        
        .chat-task-title {
            font-size: 14px;
            color: #667eea;
            font-weight: 500;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f8f9fa;
        }
        
        .message {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            max-width: 70%;
        }
        
        .message.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }
        
        .message.sent .message-avatar {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .message.sent .message-content {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .message-text {
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }
        
        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .chat-input-container {
            padding: 20px 24px;
            background: white;
            border-top: 1px solid #e5e7eb;
        }
        
        .chat-input-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .chat-input {
            flex: 1;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 14px;
            resize: vertical;
            min-height: 44px;
            max-height: 120px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .chat-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 12px 16px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            height: 44px;
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .send-btn:disabled {
            background: #e5e7eb;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            background: #f1f5f9;
            border-radius: 16px;
            margin-bottom: 16px;
            max-width: 120px;
        }
        
        .typing-dots {
            display: flex;
            gap: 4px;
        }
        
        .typing-dot {
            width: 6px;
            height: 6px;
            background: #667eea;
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }
        
        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }
        
        @keyframes typing {
            0%, 60%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            30% {
                transform: translateY(-10px);
                opacity: 1;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                flex-direction: column;
            }
            
            .sidebar {
                display: none;
            }
            
            .conversations-sidebar {
                width: 100%;
                height: 300px;
            }
            
            .chat-container {
                height: calc(100vh - 300px);
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">H</div>
            
            <a href="<?php echo $user_type === 'helper' ? 'helper-dashboard.php' : 'client-dashboard.php'; ?>" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <path d="m9 9 5 12 1.774-5.226L21 14 9 9z"/>
                </svg>
            </a>
            
            <a href="<?php echo $user_type === 'helper' ? 'find-tasks.php' : 'my-tasks.php'; ?>" class="nav-item">
                <?php if ($user_type === 'helper'): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
                <?php else: ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14,2 14,8 20,8"/>
                </svg>
                <?php endif; ?>
            </a>
            
            <a href="<?php echo $user_type === 'helper' ? 'my-applications.php' : 'applications.php'; ?>" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>
            </a>
            
            <a href="messages.php" class="nav-item active">
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
        <div class="main-content">
            <!-- Conversations List -->
            <div class="conversations-sidebar">
                <div class="conversations-header">
                    <h2 class="conversations-title">Messages</h2>
                    <p class="conversations-subtitle">
                        <?php echo count($conversations); ?> conversation<?php echo count($conversations) !== 1 ? 's' : ''; ?>
                    </p>
                </div>
                
                <div class="conversations-list">
                    <?php if (empty($conversations)): ?>
                        <div style="padding: 40px 20px; text-align: center; color: #666;">
                            <div style="font-size: 48px; margin-bottom: 16px;">💬</div>
                            <h3 style="margin-bottom: 8px;">No conversations yet</h3>
                            <p style="font-size: 14px;">Start messaging when you apply for tasks or receive applications.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item <?php echo ($task_id == $conv['task_id'] && $conversation_with == $conv['other_user_id']) ? 'active' : ''; ?>" 
                                 onclick="openConversation(<?php echo $conv['task_id']; ?>, <?php echo $conv['other_user_id']; ?>)">
                                <div class="conversation-avatar">
                                    <?php echo strtoupper(substr($conv['other_user_name'], 0, 1)); ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="conversation-name"><?php echo htmlspecialchars($conv['other_user_name']); ?></div>
                                    <div class="conversation-task"><?php echo htmlspecialchars($conv['task_title']); ?></div>
                                    <div class="conversation-preview">
                                        <?php 
                                        if ($conv['last_message']) {
                                            echo htmlspecialchars(substr($conv['last_message'], 0, 50));
                                            if (strlen($conv['last_message']) > 50) echo '...';
                                        } else {
                                            echo 'No messages yet';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="conversation-meta">
                                    <?php if ($conv['last_message_time']): ?>
                                        <div class="conversation-time">
                                            <?php
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
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-container">
                <?php if ($current_conversation && $other_user): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-user-avatar">
                            <?php echo strtoupper(substr($other_user['fullname'], 0, 1)); ?>
                        </div>
                        <div class="chat-user-info">
                            <h3><?php echo htmlspecialchars($other_user['fullname']); ?></h3>
                            <div class="chat-task-title"><?php echo htmlspecialchars($current_conversation['title']); ?></div>
                        </div>
                        <div style="margin-left: auto;">
                            <a href="task-details.php?id=<?php echo $task_id; ?>" 
                               style="background: #f3f4f6; color: #4b5563; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600;">
                                View Task
                            </a>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="typing-indicator" id="typingIndicator">
                            <div class="typing-dots">
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                                <div class="typing-dot"></div>
                            </div>
                            <span style="font-size: 12px; color: #666;">typing...</span>
                        </div>
                        
                        <?php foreach ($current_messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>" data-message-id="<?php echo $message['id']; ?>">
                                <div class="message-avatar">
                                    <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                </div>
                                <div class="message-content">
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    <div class="message-time">
                                        <?php 
                                        $msg_time = strtotime($message['created_at']);
                                        $time_diff = time() - $msg_time;
                                        
                                        if ($time_diff < 60) {
                                            echo 'Just now';
                                        } elseif ($time_diff < 3600) {
                                            echo floor($time_diff / 60) . ' minutes ago';
                                        } elseif ($time_diff < 86400) {
                                            echo floor($time_diff / 3600) . ' hours ago';
                                        } elseif ($time_diff < 604800) {
                                            echo floor($time_diff / 86400) . ' days ago';
                                        } else {
                                            echo date('M j, Y \a\t g:i A', $msg_time);
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="chat-input-container">
                        <form class="chat-input-form" id="messageForm">
                            <textarea 
                                class="chat-input" 
                                id="messageInput" 
                                placeholder="Type your message here..." 
                                rows="1" 
                                maxlength="2000"
                                required
                            ></textarea>
                            <button type="submit" class="send-btn" id="sendBtn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"/>
                                    <polygon points="22,2 15,22 11,13 2,9 22,2"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <div class="empty-icon">💬</div>
                        <h3>Select a conversation</h3>
                        <p>Choose a conversation from the sidebar to start messaging</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        let currentTaskId = <?php echo $task_id ?: 0; ?>;
        let currentOtherUserId = <?php echo $conversation_with ?: 0; ?>;
        let lastMessageId = <?php echo !empty($current_messages) ? end($current_messages)['id'] : 0; ?>;
        let messageCheckInterval;
        let typingTimer;
        
        function openConversation(taskId, otherUserId) {
            window.location.href = `messages.php?task_id=${taskId}&with=${otherUserId}`;
        }
        
        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
            
            // Handle Enter key (send message)
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });
        }
        
        // Send message function
        async function sendMessage() {
            if (!currentTaskId || !currentOtherUserId) return;
            
            const messageText = messageInput.value.trim();
            if (!messageText) return;
            
            const sendBtn = document.getElementById('sendBtn');
            sendBtn.disabled = true;
            
            try {
                const response = await fetch('messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'send_message',
                        task_id: currentTaskId,
                        receiver_id: currentOtherUserId,
                        message: messageText
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    
                    // Add message to UI immediately
                    addMessageToUI({
                        id: Date.now(), // Temporary ID
                        sender_id: <?php echo $user_id; ?>,
                        sender_name: '<?php echo addslashes($fullname); ?>',
                        message: messageText,
                        created_at: new Date().toISOString()
                    });
                    
                    scrollToBottom();
                } else {
                    alert('Failed to send message: ' + result.message);
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Failed to send message. Please try again.');
            } finally {
                sendBtn.disabled = false;
            }
        }
        
        // Handle form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                sendMessage();
            });
        }
        
        // Add message to UI
        function addMessageToUI(message) {
            const chatMessages = document.getElementById('chatMessages');
            if (!chatMessages) return;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.sender_id == <?php echo $user_id; ?> ? 'sent' : 'received'}`;
            messageDiv.setAttribute('data-message-id', message.id);
            
            const currentUserId = <?php echo $user_id; ?>;
            const isSent = message.sender_id == currentUserId;
            
            messageDiv.innerHTML = `
                <div class="message-avatar">
                    ${message.sender_name.charAt(0).toUpperCase()}
                </div>
                <div class="message-content">
                    <div class="message-text">${message.message.replace(/\n/g, '<br>')}</div>
                    <div class="message-time">Just now</div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }
        
        // Check for new messages
        async function checkForNewMessages() {
            if (!currentTaskId || !currentOtherUserId) return;
            
            try {
                const response = await fetch('messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'get_messages',
                        task_id: currentTaskId,
                        other_user_id: currentOtherUserId,
                        last_message_id: lastMessageId
                    })
                });
                
                const result = await response.json();
                
                if (result.success && result.messages.length > 0) {
                    result.messages.forEach(message => {
                        addMessageToUI(message);
                        lastMessageId = Math.max(lastMessageId, message.id);
                    });
                }
            } catch (error) {
                console.error('Error checking for new messages:', error);
            }
        }
        
        // Scroll to bottom of messages
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Initialize if we have a conversation
        if (currentTaskId && currentOtherUserId) {
            // Scroll to bottom on load
            setTimeout(scrollToBottom, 100);
            
            // Check for new messages every 3 seconds
            messageCheckInterval = setInterval(checkForNewMessages, 3000);
            
            // Mark messages as read when page loads
            fetch('messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mark_read',
                    task_id: currentTaskId,
                    sender_id: currentOtherUserId
                })
            });
        }
        
        // Clean up interval on page unload
        window.addEventListener('beforeunload', function() {
            if (messageCheckInterval) {
                clearInterval(messageCheckInterval);
            }
        });
        
        // Focus on message input
        if (messageInput) {
            messageInput.focus();
        }
        
        // Show typing indicator (basic implementation)
        let isTyping = false;
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                if (!isTyping && this.value.trim()) {
                    isTyping = true;
                    // You could send a typing indicator to the server here
                }
                
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    isTyping = false;
                    // Stop typing indicator
                }, 1000);
            });
        }
        
        // Handle page visibility changes
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                // Page is hidden, stop checking for messages as frequently
                if (messageCheckInterval) {
                    clearInterval(messageCheckInterval);
                }
            } else {
                // Page is visible, resume checking for messages
                if (currentTaskId && currentOtherUserId) {
                    messageCheckInterval = setInterval(checkForNewMessages, 3000);
                    checkForNewMessages(); // Check immediately
                }
            }
        });
        
        // Add some polish - smooth scrolling and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate messages on load
            const messages = document.querySelectorAll('.message');
            messages.forEach((message, index) => {
                message.style.opacity = '0';
                message.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    message.style.transition = 'all 0.3s ease';
                    message.style.opacity = '1';
                    message.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
        
        // Auto-update conversation list every 30 seconds
        setInterval(function() {
            if (!document.hidden) {
                // Refresh unread counts in sidebar
                // This could be implemented with AJAX to update the sidebar
            }
        }, 30000);
    </script>
</body>
</html>