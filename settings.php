<?php
// Simple Settings Page - Educational Version
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}
$user_type = $_SESSION['user_type'];

// Get user data from session
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Handle simple form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_profile') {
            $new_fullname = trim($_POST['fullname']);
            $new_email = trim($_POST['email']);
            $new_bio = trim($_POST['bio']);
            
            // Simple validation
            if (empty($new_fullname) || empty($new_email)) {
                throw new Exception('Name and email are required.');
            }
            
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }
            
            // Check if email already exists for other users
            $email_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $email_check->execute([$new_email, $user_id]);
            if ($email_check->fetch()) {
                throw new Exception('This email address is already in use.');
            }
            
            // Update profile
            $update_stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, bio = ? WHERE id = ?");
            $update_stmt->execute([$new_fullname, $new_email, $new_bio, $user_id]);
            
            // Update session
            $_SESSION['fullname'] = $new_fullname;
            
            $success_message = "Profile updated successfully!";
            
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Simple validation
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }
            
            // Check current password
            $user_stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user_data = $user_stmt->fetch();
            
            if (!password_verify($current_password, $user_data['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $user_id]);
            
            $success_message = "Password changed successfully!";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('login.php');
    }
    
} catch (PDOException $e) {
    $error_message = "Error loading user data.";
    $user = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | Helpify</title>
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
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .user-summary {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .user-avatar {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        .user-info h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        /* Settings Navigation */
        .settings-nav {
            background: #f8fafc;
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 24px;
            display: flex;
            gap: 4px;
            overflow-x: auto;
        }
        
        .nav-tab {
            padding: 12px 20px;
            border-radius: 12px;
            background: transparent;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        
        .nav-tab.active {
            background: white;
            color: #8b5cf6;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .nav-tab:hover:not(.active) {
            background: rgba(255, 255, 255, 0.5);
            color: #475569;
        }
        
        /* Settings Content */
        .settings-content {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .section-description {
            color: #64748b;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
            line-height: 1.5;
        }
        
        .form-help {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        /* Buttons */
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(139, 92, 246, 0.3);
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .main-content.collapsed {
                margin-left: 80px;
            }
            
            .user-summary {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .settings-nav {
                overflow-x: auto;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .settings-content {
                padding: 20px;
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
            <?php if ($user_type === 'client'): ?>
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
                <a href="messages.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="nav-text">Messages</span>
                </a>
                <a href="settings.php" class="nav-item active">
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
            <?php else: ?>
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
                <a href="helper-messages.php" class="nav-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="nav-text">Messages</span>
                </a>
                <a href="settings.php" class="nav-item active">
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
            <?php endif; ?>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content" id="mainContent">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-top">
                    <h1 class="page-title">
                        <div class="title-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </div>
                        Settings
                    </h1>
                    
                    <div class="user-summary">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <h3><?php echo htmlspecialchars($user['fullname']); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Settings Navigation -->
            <div class="settings-nav">
                <button class="nav-tab active" data-tab="profile">Profile</button>
                <button class="nav-tab" data-tab="security">Security</button>
            </div>
            
            <!-- Settings Content -->
            <div class="settings-content">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Section -->
                <div class="settings-section active" id="profile">
                    <h2 class="section-title">Profile Information</h2>
                    <p class="section-description">Update your profile information and how others see you on the platform.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="fullname" class="form-input" value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Bio</label>
                            <textarea name="bio" class="form-input form-textarea"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            <p class="form-help">Tell others a little bit about yourself and your experience.</p>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
                
                <!-- Security Section -->
                <div class="settings-section" id="security">
                    <h2 class="section-title">Security</h2>
                    <p class="section-description">Update your password and security settings.</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Toggle sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('collapsed');
        }
        
        // Tab switching
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and sections
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding section
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
    </script>
</body>
</html>