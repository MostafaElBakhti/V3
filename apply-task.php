<?php
require_once 'config.php';

// Check if user is logged in and is a helper
if (!isLoggedIn() || $_SESSION['user_type'] !== 'helper') {
    redirect('login.php');
}

// Get task ID from URL
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$task_id) {
    redirect('helper-dashboard.php');
}

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'];

// Handle form submission (simplified)
$errors = [];
$success_message = '';

if ($_POST) {
    $proposal = trim($_POST['proposal']);
    $bid_amount = $_POST['bid_amount'];
    
    // Simple validation
    if (strlen($proposal) < 50) {
        $errors[] = 'Proposal must be at least 50 characters long.';
    }
    
    if ($bid_amount < 5) {
        $errors[] = 'Bid amount must be at least $5.';
    }
    
    if (empty($errors)) {
        try {
            // Check if task exists and is open
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND status = 'open'");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            
            if (!$task) {
                $errors[] = 'Task not found or no longer accepting applications.';
            } else {
                // Check if already applied
                $stmt = $pdo->prepare("SELECT id FROM applications WHERE task_id = ? AND helper_id = ?");
                $stmt->execute([$task_id, $user_id]);
                if ($stmt->fetch()) {
                    $errors[] = 'You have already applied to this task.';
                } else {
                    // Insert application
                    $stmt = $pdo->prepare("INSERT INTO applications (task_id, helper_id, proposal, bid_amount, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                    $stmt->execute([$task_id, $user_id, $proposal, $bid_amount]);
                    
                    $success_message = 'Application submitted successfully!';
                    // Redirect after 2 seconds
                    header("refresh:2;url=helper-dashboard.php");
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Error submitting application. Please try again.';
        }
    }
}

// Get task details (simplified)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, u.fullname as client_name, u.rating as client_rating
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();
    
    if (!$task) {
        redirect('helper-dashboard.php');
    }
    
    // Check if already applied
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE task_id = ? AND helper_id = ?");
    $stmt->execute([$task_id, $user_id]);
    $existing_application = $stmt->fetch();
    
    if ($existing_application) {
        redirect("task-details.php?id=$task_id");
    }
    
    // Count total applications
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM applications WHERE task_id = ?");
    $stmt->execute([$task_id]);
    $total_applications = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    redirect('helper-dashboard.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Task | Helpify</title>
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
            padding: 32px 16px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 500;
            margin-bottom: 32px;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .task-summary {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            border-left: 4px solid #667eea;
        }
        
        .task-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 12px;
        }
        
        .task-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            font-size: 14px;
        }
        
        .task-budget {
            font-size: 20px;
            font-weight: 700;
            color: #10b981;
            text-align: center;
            padding: 16px;
            background: white;
            border-radius: 12px;
            margin-top: 16px;
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .section-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .form-group input,
        .form-group textarea {
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
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.5;
        }
        
        .bid-input {
            position: relative;
        }
        
        .bid-input::before {
            content: '$';
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-weight: 600;
            color: #666;
            z-index: 1;
        }
        
        .bid-input input {
            padding-left: 40px;
        }
        
        .help-text {
            font-size: 14px;
            color: #666;
            margin-top: 6px;
            line-height: 1.4;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .alert-error ul {
            margin: 8px 0 0 20px;
        }
        
        .form-footer {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }
        
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .competition-info {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .competition-info h3 {
            margin-bottom: 8px;
            font-size: 18px;
        }
        
        .competition-info p {
            opacity: 0.9;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .form-footer {
                flex-direction: column;
            }
            
            .task-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="task-details.php?id=<?php echo $task['id']; ?>" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to Task Details
        </a>
        
        <div class="main-card">
            <div class="header">
                <h1>Apply for Task</h1>
                <p>Submit your proposal to get this job</p>
            </div>
            
            <!-- Task Summary -->
            <div class="task-summary">
                <h2 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h2>
                <div class="task-meta">
                    <div class="meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        <?php echo htmlspecialchars($task['location']); ?>
                    </div>
                    <div class="meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12,6 12,12 16,14"/>
                        </svg>
                        <?php echo date('M j, Y \a\t g:i A', strtotime($task['scheduled_time'])); ?>
                    </div>
                    <div class="meta-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        <?php echo $total_applications; ?> other applications
                    </div>
                </div>
                <div class="task-budget">
                    Client's Budget: $<?php echo number_format($task['budget'], 2); ?>
                </div>
            </div>
            
            <!-- Competition Info -->
            <div class="competition-info">
                <h3>🏆 Stand Out From the Competition</h3>
                <p>There are <?php echo $total_applications; ?> other applications. Make your proposal compelling!</p>
            </div>
            
            <!-- Application Form -->
            <form method="POST">
                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <!-- Success Message -->
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?php echo htmlspecialchars($success_message); ?>
                    <br><small>Redirecting to dashboard...</small>
                </div>
                <?php endif; ?>
                
                <div class="form-section">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14,2 14,8 20,8"/>
                        </svg>
                        Your Proposal
                    </h2>
                    
                    <div class="form-group">
                        <label for="proposal">Describe how you'll complete this task *</label>
                        <textarea 
                            id="proposal" 
                            name="proposal" 
                            required 
                            placeholder="Hi! I'm interested in helping with your task. Here's how I would approach it...

• Explain your approach
• Mention relevant experience
• Specify timing and availability
• Ask any questions you have

I'm confident I can deliver quality work and would love to discuss this further with you."
                        ><?php echo isset($_POST['proposal']) ? htmlspecialchars($_POST['proposal']) : ''; ?></textarea>
                        <div class="character-count">
                            <span id="charCount">0</span> / 50 characters minimum
                        </div>
                        <div class="help-text">
                            Write a detailed proposal explaining how you'll complete the task. Be specific and professional.
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h2 class="section-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Your Bid
                    </h2>
                    
                    <div class="form-group">
                        <label for="bid_amount">How much do you want to charge? *</label>
                        <div class="bid-input">
                            <input 
                                type="number" 
                                id="bid_amount" 
                                name="bid_amount" 
                                min="5" 
                                max="<?php echo $task['budget'] * 2; ?>" 
                                step="1" 
                                required
                                value="<?php echo isset($_POST['bid_amount']) ? $_POST['bid_amount'] : ''; ?>"
                                placeholder="<?php echo round($task['budget'] * 0.8); ?>"
                            >
                        </div>
                        <div class="help-text">
                            Enter your bid amount. Consider the client's budget of $<?php echo number_format($task['budget'], 2); ?> when pricing your services.
                        </div>
                    </div>
                </div>
                
                <div class="form-footer">
                    <a href="task-details.php?id=<?php echo $task['id']; ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Simple character counter for proposal
        const proposalTextarea = document.getElementById('proposal');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            const length = proposalTextarea.value.length;
            charCount.textContent = length;
            
            if (length < 50) {
                charCount.style.color = '#ef4444';
            } else {
                charCount.style.color = '#10b981';
            }
        }
        
        proposalTextarea.addEventListener('input', updateCharCount);
        updateCharCount(); // Initial count
        
        // Auto-resize textarea
        proposalTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.max(120, this.scrollHeight) + 'px';
        });
    </script>
</body>
</html>