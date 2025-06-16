<?php
require_once 'config.php';


// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Get query parameters
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0;
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_task_id = intval($_POST['task_id']);
    $receiver_id = intval($_POST['receiver_id']);
    $message_content = trim($_POST['message']);
    
    if (!empty($message_content) && $message_task_id > 0 && $receiver_id > 0) {
        try {
            // Verify the helper has access to this task (either applied or assigned)
            $verify_stmt = $pdo->prepare("
                SELECT t.id, t.client_id, t.title
                FROM tasks t
                LEFT JOIN applications a ON t.id = a.task_id AND a.helper_id = ?
                WHERE t.id = ? AND (t.helper_id = ? OR a.id IS NOT NULL)
            ");
            $verify_stmt->execute([$user_id, $message_task_id, $user_id]);
            $task_access = $verify_stmt->fetch();
            
            if ($task_access && $receiver_id == $task_access['client_id']) {
                // Insert message
                $insert_stmt = $pdo->prepare("
                    INSERT INTO messages (task_id, sender_id, receiver_id, message, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_stmt->execute([$message_task_id, $user_id, $receiver_id, $message_content]);
                
                // Create notification for receiver
                $notification_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, content, related_id, created_at) 
                    VALUES (?, 'message', ?, ?, NOW())
                ");
                $notification_content = "New message from " . $fullname . " regarding '" . $task_access['title'] . "'";
                $notification_stmt->execute([$receiver_id, $notification_content, $message_task_id]);
                
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

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $conversation_task_id = intval($_POST['conversation_task_id']);
    $conversation_client_id = intval($_POST['conversation_client_id']);
    
    try {
        $mark_read_stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = TRUE 
            WHERE task_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = FALSE
        ");
        $mark_read_stmt->execute([$conversation_task_id, $conversation_client_id, $user_id]);
    } catch (PDOException $e) {
        // Ignore errors for mark as read
    }
}

try {
    // Get all conversations (grouped by task and client)
    $conversations_query = "
        SELECT 
            t.id as task_id,
            t.title as task_title,
            t.status as task_status,
            t.budget,
            t.scheduled_time,
            u.id as client_id,
            u.fullname as client_name,
            u.email as client_email,
            u.rating as client_rating,
            MAX(m.created_at) as last_message_time,
            COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 END) as unread_count,
            (SELECT message FROM messages WHERE task_id = t.id AND (sender_id = ? OR receiver_id = ?) ORDER BY created_at DESC LIMIT 1) as last_message
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        LEFT JOIN messages m ON t.id = m.task_id AND (m.sender_id = ? OR m.receiver_id = ?)
        WHERE (t.helper_id = ? OR EXISTS(
            SELECT 1 FROM applications a 
            WHERE a.task_id = t.id AND a.helper_id = ? AND a.status IN ('pending', 'accepted')
        ))
        AND EXISTS(
            SELECT 1 FROM messages msg 
            WHERE msg.task_id = t.id AND (msg.sender_id = ? OR msg.receiver_id = ?)
        )
    ";
    
    $params = [$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id];
    
    // Add search filter
    if (!empty($search_query)) {
        $conversations_query .= " AND (t.title LIKE ? OR u.fullname LIKE ?)";
        $search_param = '%' . $search_query . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $conversations_query .= "
        GROUP BY t.id, u.id
        ORDER BY last_message_time DESC
    ";
    
    $conversations_stmt = $pdo->prepare($conversations_query);
    $conversations_stmt->execute($params);
    $conversations = $conversations_stmt->fetchAll();
    
    // Get messages for selected conversation
    $messages = [];
    $selected_conversation = null;
    
    if ($task_id > 0 && $client_id > 0) {
        // Verify access to this conversation
        $verify_conv_stmt = $pdo->prepare("
            SELECT t.*, u.fullname as client_name, u.email as client_email, u.rating as client_rating
            FROM tasks t
            JOIN users u ON t.client_id = u.id
            LEFT JOIN applications a ON t.id = a.task_id AND a.helper_id = ?
            WHERE t.id = ? AND t.client_id = ? AND (t.helper_id = ? OR a.id IS NOT NULL)
        ");
        $verify_conv_stmt->execute([$user_id, $task_id, $client_id, $user_id]);
        $selected_conversation = $verify_conv_stmt->fetch();
        
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
                WHERE m.task_id = ? AND ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
                ORDER BY m.created_at ASC
            ");
            $messages_stmt->execute([$task_id, $user_id, $client_id, $client_id, $user_id]);
            $messages = $messages_stmt->fetchAll();
            
            // Mark messages as read
            $mark_read_stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = TRUE 
                WHERE task_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = FALSE
            ");
            $mark_read_stmt->execute([$task_id, $client_id, $user_id]);
        }
    }
    
    // Get message statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN CONCAT(m.task_id, '-', m.sender_id) END) as unread_conversations,
            COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = FALSE THEN 1 END) as total_unread,
            COUNT(DISTINCT CONCAT(m.task_id, '-', CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END)) as total_conversations
        FROM messages m
        JOIN tasks t ON m.task_id = t.id
        WHERE (m.sender_id = ? OR m.receiver_id = ?)
        AND (t.helper_id = ? OR EXISTS(
            SELECT 1 FROM applications a 
            WHERE a.task_id = t.id AND a.helper_id = ? AND a.status IN ('pending', 'accepted')
        ))
    ");
    $stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    $conversations = [];
    $messages = [];
    $selected_conversation = null;
    $stats = ['unread_conversations' => 0, 'total_unread' => 0, 'total_conversations' => 0];
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
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 240px;
            overflow: hidden;
            transition: margin-left 0.3s ease;
            height: 100vh;
        }
        
        .main-content.collapsed {
            margin-left: 80px;
        }
        
        .messages-container {
            display: flex;
            height: 100vh;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
        
        /* Left Sidebar - Conversations */
        .conversations-sidebar {
            width: 400px;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .conversations-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        .message-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .stat-item {
            text-align: center;
            padding: 12px;
            background: white;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #3b82f6;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 16px 24px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .conversation-item:hover {
            background: #f8fafc;
        }
        
        .conversation-item.active {
            background: #eff6ff;
            border-right: 3px solid #3b82f6;
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .client-info {
            flex: 1;
        }
        
        .client-name {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 2px;
        }
        
        .task-title {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .conversation-meta {
            text-align: right;
            flex-shrink: 0;
            margin-left: 12px;
        }
        
        .message-time {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }
        
        .unread-badge {
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }
        
        .last-message {
            font-size: 14px;
            color: #64748b;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .task-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        
        .status-open { background: #dbeafe; color: #1d4ed8; }
        .status-in_progress { background: #fef3c7; color: #d97706; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-pending { background: #ede9fe; color: #7c3aed; }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .chat-header {
            padding: 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .chat-client-info {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }
        
        .client-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }
        
        .chat-client-details h3 {
            font-size: 18px;
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
        
        .chat-task-info {
            background: white;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e2e8f0;
        }
        
        .task-info-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .task-info-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            flex: 1;
        }
        
        .task-budget {
            font-size: 18px;
            font-weight: 700;
            color: #10b981;
        }
        
        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 14px;
        }
        
        .meta-item svg {
            width: 16px;
            height: 16px;
            color: #3b82f6;
            flex-shrink: 0;
        }
        
        .messages-list {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: #f8fafc;
        }
        
        .message-group {
            margin-bottom: 24px;
        }
        
        .message-date {
            text-align: center;
            margin-bottom: 16px;
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
        
        .message {
            display: flex;
            margin-bottom: 12px;
            align-items: flex-start;
            gap: 12px;
        }
        
        .message.sent {
            flex-direction: row-reverse;
        }
        
        .message-avatar {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #64748b, #475569);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }
        
        .message.sent .message-avatar {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }
        
        .message-content {
            max-width: 70%;
            background: white;
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .message.sent .message-content {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .message-text {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 4px;
        }
        
        .message-time {
            font-size: 11px;
            opacity: 0.7;
        }
        
        .message.sent .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Message Input */
        .message-input-area {
            padding: 24px;
            border-top: 1px solid #e2e8f0;
            background: white;
        }
        
        .message-input-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            resize: vertical;
            min-height: 44px;
            max-height: 120px;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        .message-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .send-button {
            padding: 12px 16px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .send-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Empty States */
        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }
        
        .empty-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        
        .empty-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .empty-description {
            font-size: 14px;
            color: #64748b;
            line-height: 1.5;
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            font-weight: 500;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .main-content.collapsed {
                margin-left: 80px;
            }
            
            .conversations-sidebar {
                width: 100%;
                position: absolute;
                z-index: 100;
                height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .conversations-sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .chat-area {
                width: 100%;
            }
            
            .message-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .task-meta {
                grid-template-columns: 1fr;
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
            
            <a href="my-jobs.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
                <span class="nav-text">My Jobs</span>
            </a>
            
            <a href="helper-messages.php" class="nav-item active">
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
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Header -->
            <div class="header">
    <h1 class="greeting">Good morning, <?php echo explode(' ', $fullname)[0]; ?>!</h1>
    <div class="header-actions">
        <button class="header-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </button>
        
        <!-- Notification Bell -->
        <div class="header-btn notification-bell" style="position: relative;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <!-- Notification badge will be added by JavaScript -->
        </div>
        
        <div class="user-avatar">
            <?php echo strtoupper(substr($fullname, 0, 1)); ?>
        </div>
    </div>
</div>

            <div class="messages-container">
                <!-- Conversations Sidebar -->
                <div class="conversations-sidebar" id="conversationsSidebar">
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
                                <div class="stat-number"><?php echo $stats['unread_conversations']; ?></div>
                                <div class="stat-label">Unread</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_unread']; ?></div>
                                <div class="stat-label">New Messages</div>
                            </div>
                        </div>
                        
                        <form method="GET" action="">
                            <div class="search-box">
                                <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"/>
                                    <path d="m21 21-4.35-4.35"/>
                                </svg>
                                <input type="text" class="search-input" name="search" 
                                       placeholder="Search conversations..." 
                                       value="<?php echo htmlspecialchars($search_query); ?>"
                                       onchange="this.form.submit()">
                                <?php if ($task_id > 0): ?>
                                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                                <?php endif; ?>
                                <?php if ($client_id > 0): ?>
                                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <div class="conversations-list">
                        <?php if (empty($conversations)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                        <path d="M13 17h6m-9-4h9m-9-4h6"/>
                                    </svg>
                                </div>
                                <div class="empty-title">No Conversations</div>
                                <div class="empty-description">
                                    Start communicating with clients by applying to tasks and sending messages.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <a href="?task_id=<?php echo $conv['task_id']; ?>&client_id=<?php echo $conv['client_id']; ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?>" 
                                   class="conversation-item <?php echo ($task_id == $conv['task_id'] && $client_id == $conv['client_id']) ? 'active' : ''; ?>">
                                    <div class="conversation-header">
                                        <div class="client-info">
                                            <div class="client-name"><?php echo htmlspecialchars($conv['client_name']); ?></div>
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
                                    <?php if ($conv['last_message']): ?>
                                        <div class="last-message">
                                            <?php echo htmlspecialchars(substr($conv['last_message'], 0, 80)) . (strlen($conv['last_message']) > 80 ? '...' : ''); ?>
                                        </div>
                                    <?php endif; ?>
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
                            <div class="chat-client-info">
                                <div class="client-avatar">
                                    <?php echo strtoupper(substr($selected_conversation['client_name'], 0, 1)); ?>
                                </div>
                                <div class="chat-client-details">
                                    <h3><?php echo htmlspecialchars($selected_conversation['client_name']); ?></h3>
                                    <div class="client-rating">
                                        <div class="stars">
                                            <?php 
                                            $rating = $selected_conversation['client_rating'] ? floatval($selected_conversation['client_rating']) : 0;
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <svg class="star" viewBox="0 0 24 24" fill="<?php echo $i <= $rating ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                                                    <polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>
                                        <span><?php echo $rating > 0 ? number_format($rating, 1) . ' rating' : 'New client'; ?></span>
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
                        <?php if (isset($success_message)): ?>
                            <div style="padding: 0 24px; padding-top: 16px;">
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
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
                                        Start the conversation by sending a message about this task.
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
                            <form method="POST" class="message-input-form" id="messageForm">
                                <input type="hidden" name="task_id" value="<?php echo $selected_conversation['id']; ?>">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_conversation['client_id']; ?>">
                                <textarea class="message-input" name="message" placeholder="Type your message..." required maxlength="1000" id="messageTextarea"></textarea>
                                <button type="submit" name="send_message" class="send-button" id="sendButton">
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
                                    <path d="M13 17h6m-9-4h9m-9-4h6"/>
                                </svg>
                            </div>
                            <div class="empty-title">Select a Conversation</div>
                            <div class="empty-description">
                                Choose a conversation from the sidebar to start messaging with a client about their task.
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
            
            // Send message on Ctrl/Cmd + Enter
            messageTextarea.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('messageForm').dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }
        
        // Handle form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                const textarea = document.getElementById('messageTextarea');
                const sendButton = document.getElementById('sendButton');
                
                if (!textarea.value.trim()) {
                    e.preventDefault();
                    return;
                }
                
                // Disable send button to prevent double sending
                sendButton.disabled = true;
                sendButton.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                        <path d="M21 12a9 9 0 11-6.219-8.56"/>
                    </svg>
                    Sending...
                `;
            });
        }
        
        // Auto-scroll to bottom of messages
        const messagesList = document.getElementById('messagesList');
        if (messagesList && messagesList.children.length > 0) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
        
        // Auto-refresh messages every 30 seconds
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const taskId = urlParams.get('task_id');
            const clientId = urlParams.get('client_id');
            
            if (taskId && clientId) {
                // Only refresh if we're not currently typing
                const textarea = document.getElementById('messageTextarea');
                if (!textarea || !textarea.matches(':focus')) {
                    location.reload();
                }
            }
        }, 30000);
        
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
        
        // Mobile responsive - toggle conversations sidebar
        function toggleConversations() {
            const sidebar = document.getElementById('conversationsSidebar');
            sidebar.classList.toggle('mobile-open');
        }
        
        // Close conversations sidebar when clicking on chat area (mobile)
        const chatArea = document.querySelector('.chat-area');
        if (chatArea) {
            chatArea.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    const sidebar = document.getElementById('conversationsSidebar');
                    sidebar.classList.remove('mobile-open');
                }
            });
        }
        
        // Add spinning animation for loading states
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        
        // Add mobile back button for small screens
        if (window.innerWidth <= 768 && window.location.search.includes('task_id')) {
            const chatHeader = document.querySelector('.chat-header');
            if (chatHeader) {
                const backButton = document.createElement('button');
                backButton.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m15 18-6-6 6-6"/>
                    </svg>
                `;
                backButton.style.cssText = `
                    position: absolute;
                    top: 24px;
                    left: 24px;
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 8px;
                    cursor: pointer;
                    z-index: 10;
                `;
                backButton.onclick = function() {
                    window.location.href = 'messages.php';
                };
                chatHeader.appendChild(backButton);
            }
        }
    </script>
    <script src="js/notifications.js"></script>
</body>
</html>