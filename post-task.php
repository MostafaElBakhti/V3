<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Handle form submission (simplified)
$errors = [];
$success_message = '';

if ($_POST) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $budget = $_POST['budget'];
    $scheduled_date = $_POST['scheduled_date'];
    $scheduled_time = $_POST['scheduled_time'];
    
    // Simple validation
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
    
    // Check if date is not in the past (simplified)
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
            $stmt = $pdo->prepare("INSERT INTO tasks (client_id, title, description, location, budget, scheduled_time, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())");
            $stmt->execute([$user_id, $title, $description, $location, $budget, $scheduled_datetime]);
            
            $success_message = 'Task posted successfully! Your task is now live and helpers can start applying.';
            
            // Clear form data on success
            $_POST = [];
            
        } catch (PDOException $e) {
            $errors[] = 'Error saving task. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post New Task | Helpify</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="./css/client css/post-task.css" rel="stylesheet" />
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
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                   maxlength="255" required>
                            <div class="help-text">Be specific and descriptive. This is what helpers will see first.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="location" class="form-label">Location <span class="required">*</span></label>
                            <input type="text" id="location" name="location" class="form-input" 
                                   placeholder="e.g., tangier " 
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
                        <button type="submit" class="btn btn-primary">
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
        
        // Auto-resize textarea
        const descriptionTextarea = document.getElementById('description');
        descriptionTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>