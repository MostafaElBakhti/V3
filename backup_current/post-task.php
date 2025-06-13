<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    redirect('login.php');
}

// Get user data
$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Initialize variables
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
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
    
    if ($budget < 10) {
        $errors[] = 'Budget must be at least $10.';
    }
    
    if ($budget > 10000) {
        $errors[] = 'Budget cannot exceed $10,000.';
    }
    
    if (empty($scheduled_date)) {
        $errors[] = 'Scheduled date is required.';
    } else {
        // Check if date is not in the past
        $scheduled_datetime = DateTime::createFromFormat('Y-m-d', $scheduled_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($scheduled_datetime < $today) {
            $errors[] = 'Scheduled date cannot be in the past.';
        }
    }
    
    if (empty($scheduled_time)) {
        $errors[] = 'Scheduled time is required.';
    }
    
    // Create scheduled datetime
    if (empty($errors)) {
        $scheduled_datetime_str = $scheduled_date . ' ' . $scheduled_time;
        $scheduled_datetime_obj = DateTime::createFromFormat('Y-m-d H:i', $scheduled_datetime_str);
        
        if (!$scheduled_datetime_obj) {
            $errors[] = 'Invalid date/time format.';
        } else {
            // Check if datetime is not in the past
            $now = new DateTime();
            if ($scheduled_datetime_obj < $now) {
                $errors[] = 'Scheduled date and time cannot be in the past.';
            }
        }
    }
    
    // Create task if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (client_id, title, description, location, scheduled_time, budget, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'open')
            ");
            
            if ($stmt->execute([$user_id, $title, $description, $location, $scheduled_datetime_str, $budget])) {
                $task_id = $pdo->lastInsertId();
                $success = 'Task created successfully! Your task has been posted and helpers can now apply.';
                
                // Clear form data on success
                $title = $description = $location = $scheduled_date = $scheduled_time = '';
                $budget = 0;
                
                // Redirect to task details or dashboard after a short delay
                header("refresh:3;url=client-dashboard.php");
            } else {
                $errors[] = 'Failed to create task. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post a Task | Helpify</title>
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
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 32px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            transition: all 0.2s;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-4px);
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .form-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .form-header p {
            font-size: 16px;
            color: #666;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.2s;
            background: white;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .budget-input {
            position: relative;
        }
        
        .budget-input::before {
            content: '$';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: #666;
            z-index: 1;
        }
        
        .budget-input input {
            padding-left: 40px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            justify-content: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
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
        
        .form-tips {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }
        
        .form-tips h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1a1a1a;
        }
        
        .form-tips ul {
            list-style: none;
            padding: 0;
        }
        
        .form-tips li {
            padding: 8px 0;
            padding-left: 24px;
            position: relative;
            color: #555;
            line-height: 1.5;
        }
        
        .form-tips li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 16px;
            }
            
            .form-container {
                padding: 24px;
            }
            
            .form-header h1 {
                font-size: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="client-dashboard.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="m12 19-7-7 7-7"/>
                <path d="m19 12-7 7-7-7"/>
            </svg>
            Back to Dashboard
        </a>
        
        <div class="form-container">
            <div class="form-header">
                <h1>Post a New Task</h1>
                <p>Describe what you need help with and connect with skilled helpers in your area.</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?php echo $success; ?>
                    <br><small>Redirecting to dashboard in 3 seconds...</small>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="post-task.php">
                <div class="form-group">
                    <label for="title">Task Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title ?? ''); ?>" required 
                           placeholder="e.g., Help with garden cleanup" maxlength="255">
                    <div class="help-text">Be specific and clear about what you need help with</div>
                </div>
                
                <div class="form-group">
                    <label for="description">Task Description</label>
                    <textarea id="description" name="description" required 
                              placeholder="Provide detailed information about the task, requirements, and any special instructions..."><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    <div class="help-text">Include all important details, requirements, and expectations (minimum 20 characters)</div>
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($location ?? ''); ?>" required 
                           placeholder="e.g., Downtown, New York, NY or 123 Main Street">
                    <div class="help-text">Provide the general area or specific address where the task will be performed</div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="scheduled_date">Scheduled Date</label>
                        <input type="date" id="scheduled_date" name="scheduled_date" value="<?php echo htmlspecialchars($scheduled_date ?? ''); ?>" required 
                               min="<?php echo date('Y-m-d'); ?>">
                        <div class="help-text">When do you need this task completed?</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="scheduled_time">Scheduled Time</label>
                        <input type="time" id="scheduled_time" name="scheduled_time" value="<?php echo htmlspecialchars($scheduled_time ?? ''); ?>" required>
                        <div class="help-text">Preferred start time for the task</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="budget">Budget</label>
                    <div class="budget-input">
                        <input type="number" id="budget" name="budget" value="<?php echo htmlspecialchars($budget ?? ''); ?>" required 
                               min="10" max="10000" step="1" placeholder="100">
                    </div>
                    <div class="help-text">Set a fair budget for your task (minimum $10, maximum $10,000)</div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="16"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                    </svg>
                    Post Task
                </button>
            </form>
            
            <div class="form-tips">
                <h3>💡 Tips for a Great Task Post</h3>
                <ul>
                    <li>Be specific about what you need - the more details, the better</li>
                    <li>Set a realistic budget based on the complexity and time required</li>
                    <li>Include any tools or materials needed</li>
                    <li>Mention if you have any time constraints or preferences</li>
                    <li>Be clear about the location and any access requirements</li>
                    <li>Respond quickly to helper applications to get the best candidates</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Auto-resize textarea
        const textarea = document.getElementById('description');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const budget = parseFloat(document.getElementById('budget').value);
            
            if (title.length < 5) {
                alert('Task title must be at least 5 characters long.');
                e.preventDefault();
                return;
            }
            
            if (description.length < 20) {
                alert('Task description must be at least 20 characters long.');
                e.preventDefault();
                return;
            }
            
            if (budget < 10 || budget > 10000) {
                alert('Budget must be between $10 and $10,000.');
                e.preventDefault();
                return;
            }
        });

        // Set minimum date to today
        document.getElementById('scheduled_date').setAttribute('min', new Date().toISOString().split('T')[0]);
    </script>
</body>
</html>