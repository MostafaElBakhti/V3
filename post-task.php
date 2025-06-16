<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Handle form submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    
    // Validation
    if (empty($title) || strlen($title) < 5) {
        $errors[] = 'Task title must be at least 5 characters long.';
    }
    
    if (empty($description) || strlen($description) < 20) {
        $errors[] = 'Task description must be at least 20 characters long.';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required.';
    }
    
    if ($budget < 10 || $budget > 10000) {
        $errors[] = 'Budget must be between $10 and $10,000.';
    }
    
    if (empty($scheduled_date) || empty($scheduled_time)) {
        $errors[] = 'Please select both date and time.';
    }
    
    if (!empty($scheduled_date) && !empty($scheduled_time)) {
        $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time;
        $scheduled_timestamp = strtotime($scheduled_datetime);
        
        if ($scheduled_timestamp < time()) {
            $errors[] = 'Scheduled date and time cannot be in the past.';
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (
                    client_id, title, description, location, budget, 
                    scheduled_time, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW(), NOW())
            ");
            
            $stmt->execute([
                $user_id, $title, $description, $location, $budget, $scheduled_datetime
            ]);
            
            $task_id = $pdo->lastInsertId();
            $success_message = 'Task posted successfully! Your task is now live and helpers can start applying.';
            
            // Redirect to task details page after 2 seconds
            header("refresh:2;url=task-details.php?id=$task_id");
            
        } catch (PDOException $e) {
            $errors[] = 'Database error: Unable to save task. Please try again.';
        }
    }
}

// Remove categories and other fields that don't exist in the database schema
// Keep only: title, description, location, budget, scheduled_time, status

// Remove the categories array and all skill-related arrays since they're not in the database
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Task | Helpify</title>
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
            text-align: center;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 12px;
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
        
        .page-subtitle {
            font-size: 18px;
            color: #64748b;
            line-height: 1.6;
        }
        
        /* Form Container */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-icon {
            width: 24px;
            height: 24px;
            color: #3b82f6;
        }
        
        .section-description {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 24px;
            line-height: 1.5;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .required {
            color: #ef4444;
        }
        
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
            font-family: inherit;
        }
        
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
            line-height: 1.4;
        }
        
        .budget-input-wrapper {
            position: relative;
        }
        
        .budget-input-wrapper::before {
            content: '$';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: #666;
            z-index: 1;
        }
        
        .budget-input {
            padding-left: 40px;
        }
        
        /* Skills Selection */
        .skills-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .skill-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .skill-item:hover {
            background: #e2e8f0;
        }
        
        .skill-item.selected {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .skill-checkbox {
            margin-right: 8px;
        }
        
        .skill-label {
            font-size: 14px;
            font-weight: 500;
            text-transform: capitalize;
            cursor: pointer;
        }
        
        /* Radio Group */
        .radio-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .radio-item:hover {
            border-color: #3b82f6;
            background: #f1f5f9;
        }
        
        .radio-item.selected {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.1);
        }
        
        .radio-input {
            margin-right: 8px;
        }
        
        .radio-label {
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        
        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
        }
        
        .alert-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }
        
        /* Buttons */
        .btn-group {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid #f1f5f9;
        }
        
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            min-width: 200px;
            justify-content: center;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            border-color: #cbd5e1;
        }
        
        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
            gap: 16px;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f8fafc;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
        }
        
        .step.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .step-number {
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
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
            
            .form-container {
                padding: 24px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .skills-container {
                grid-template-columns: 1fr;
            }
            
            .radio-group {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .progress-steps {
                flex-wrap: wrap;
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
            
            <a href="post-task.php" class="nav-item active">
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
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <div class="title-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                    </div>
                    Post a New Task
                </h1>
                <p class="page-subtitle">
                    Describe your task in detail and connect with skilled helpers in your area. 
                    The more information you provide, the better matches you'll receive.
                </p>
            </div>
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <span>Task Details</span>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <span>Requirements</span>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <span>Review & Post</span>
                </div>
            </div>
            
            <!-- Form Container -->
            <div class="form-container">
                <!-- Alert Messages -->
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong>Please fix the following errors:</strong>
                            <ul style="margin: 8px 0 0 20px; list-style: disc;">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <svg class="alert-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
                            <br><small>Redirecting to task details...</small>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Form -->
                <form method="POST" action="" id="taskForm">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Basic Information
                        </h2>
                        <p class="section-description">
                            Start with the essential details about your task. A clear title and detailed description will help attract the right helpers.
                        </p>
                        
                        <div class="form-group full-width">
                            <label for="title" class="form-label">Task Title <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-input" 
                                   placeholder="e.g., Help me move furniture to new apartment" 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                   maxlength="255" required>
                            <div class="help-text">Be specific and descriptive. This is what helpers will see first.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location" class="form-label">Location <span class="required">*</span></label>
                            <input type="text" id="location" name="location" class="form-input" 
                                   placeholder="e.g., Downtown Seattle, WA" 
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required>
                            <div class="help-text">Where will the task take place?</div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description" class="form-label">Detailed Description <span class="required">*</span></label>
                            <textarea id="description" name="description" class="form-textarea" 
                                      placeholder="Provide a detailed description of your task. Include what needs to be done, any special requirements, tools needed, and what you expect from the helper..." 
                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="help-text">The more details you provide, the better. Include materials needed, difficulty level, and any special instructions.</div>
                        </div>
                    </div>
                    
                    <!-- Scheduling & Budget -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Scheduling & Budget
                        </h2>
                        <p class="section-description">
                            Set when you need the task completed and how much you're willing to pay.
                        </p>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="scheduled_date" class="form-label">Preferred Date <span class="required">*</span></label>
                                <input type="date" id="scheduled_date" name="scheduled_date" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['scheduled_date'] ?? ''); ?>" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                                <div class="help-text">When would you like this task to be completed?</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="scheduled_time" class="form-label">Preferred Time <span class="required">*</span></label>
                                <input type="time" id="scheduled_time" name="scheduled_time" class="form-input" 
                                       value="<?php echo htmlspecialchars($_POST['scheduled_time'] ?? ''); ?>" required>
                                <div class="help-text">What time would work best for you?</div>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="budget" class="form-label">Budget <span class="required">*</span></label>
                                <div class="budget-input-wrapper">
                                    <input type="number" id="budget" name="budget" class="form-input budget-input" 
                                           min="10" max="10000" step="1" 
                                           placeholder="100" 
                                           value="<?php echo htmlspecialchars($_POST['budget'] ?? ''); ?>" required>
                                </div>
                                <div class="help-text">Set a fair budget ($10 - $10,000). You can negotiate the final price with helpers.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="btn-group">
                        <a href="my-tasks.php" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 12H5"/>
                                <polyline points="12,19 5,12 12,5"/>
                            </svg>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22,4 12,14.01 9,11.01"/>
                            </svg>
                            Post Task
                        </button>
                    </div>="contact_preference" value="any" id="contact_any" class="radio-input"
                                           <?php echo (($_POST['contact_preference'] ?? 'platform') === 'any') ? 'checked' : ''; ?>>
                                    <label for="contact_any" class="radio-label">Any contact method</label>
                                </div>
                            </div>
                            <div class="help-text">How would you prefer helpers to contact you?</div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="form-section">
                        <h2 class="section-title">
                            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                            Additional Information
                        </h2>
                        <p class="section-description">
                            Any extra details that would help helpers understand your requirements better.
                        </p>
                        
                        <div class="form-group full-width">
                            <label for="additional_notes" class="form-label">Additional Notes (Optional)</label>
                            <textarea id="additional_notes" name="additional_notes" class="form-textarea" 
                                      placeholder="Any additional information, special requirements, or questions for potential helpers..."><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                            <div class="help-text">Include parking information, access instructions, tools/materials provided, or any other relevant details.</div>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="btn-group">
                        <a href="my-tasks.php" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 12H5"/>
                                <polyline points="12,19 5,12 12,5"/>
                            </svg>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22,4 12,14.01 9,11.01"/>
                            </svg>
                            Post Task
                        </button>
                    </div>
                </form>
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
        
        // Form validation and submission
        document.getElementById('taskForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;">
                    <path d="M21 12a9 9 0 11-6.219-8.56"/>
                </svg>
                Posting Task...
            `;
            
            // Re-enable button after 3 seconds (in case of errors)
            setTimeout(() => {
                if (submitBtn.disabled) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            }, 3000);
        });
        
        // Character counter for description
        const descriptionTextarea = document.getElementById('description');
        const titleInput = document.getElementById('title');
        
        function addCharacterCounter(element, minLength) {
            const helpText = element.nextElementSibling;
            const originalText = helpText.textContent;
            
            element.addEventListener('input', function() {
                const currentLength = this.value.length;
                const remaining = Math.max(0, minLength - currentLength);
                
                if (remaining > 0) {
                    helpText.textContent = `${originalText} (${remaining} more characters needed)`;
                    helpText.style.color = '#ef4444';
                } else {
                    helpText.textContent = originalText;
                    helpText.style.color = '#666';
                }
            });
        }
        
        addCharacterCounter(descriptionTextarea, 20);
        addCharacterCounter(titleInput, 5);
        
        // Auto-resize textarea
        descriptionTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        
        // Budget validation
        document.getElementById('budget').addEventListener('input', function() {
            const value = parseFloat(this.value);
            const helpText = this.parentNode.nextElementSibling;
            
            if (value < 10) {
                helpText.textContent = 'Budget must be at least $10';
                helpText.style.color = '#ef4444';
            } else if (value > 10000) {
                helpText.textContent = 'Budget cannot exceed $10,000';
                helpText.style.color = '#ef4444';
            } else {
                helpText.textContent = 'Set a fair budget ($10 - $10,000). You can negotiate the final price with helpers.';
                helpText.style.color = '#666';
            }
        });
        
        // Add spinning animation to loading icon
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>