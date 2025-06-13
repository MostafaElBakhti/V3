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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $proposal = trim($_POST['proposal'] ?? '');
        $bid_amount = floatval($_POST['bid_amount'] ?? 0);
        
        // Validation
        $errors = [];
        
        if (strlen($proposal) < 50) {
            $errors[] = 'Proposal must be at least 50 characters long.';
        }
        
        if ($bid_amount < 5) {
            $errors[] = 'Bid amount must be at least $5.';
        }
        
        // Check if task exists and is still open
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND status = 'open'");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch();
        
        if (!$task) {
            $errors[] = 'Task not found or no longer accepting applications.';
        }
        
        // Check if user already applied
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE task_id = ? AND helper_id = ?");
        $stmt->execute([$task_id, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'You have already applied to this task.';
        }
        
        // Check if user is not the task creator
        if ($task && $task['client_id'] == $user_id) {
            $errors[] = 'You cannot apply to your own task.';
        }
        
        if (empty($errors)) {
            // Insert application
            $stmt = $pdo->prepare("
                INSERT INTO applications (task_id, helper_id, proposal, bid_amount, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            if ($stmt->execute([$task_id, $user_id, $proposal, $bid_amount])) {
                $_SESSION['success_message'] = 'Application submitted successfully! The client will review your proposal.';
                redirect('helper-dashboard.php');
            } else {
                $errors[] = 'Failed to submit application. Please try again.';
            }
        }
    } catch (PDOException $e) {
        error_log("Application submission error: " . $e->getMessage());
        $errors[] = 'Database error occurred. Please try again.';
    }
}

// Get task details
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            u.fullname as client_name,
            u.rating as client_rating,
            u.total_ratings as client_total_ratings,
            COUNT(DISTINCT a.id) as total_applications
        FROM tasks t
        JOIN users u ON t.client_id = u.id
        LEFT JOIN applications a ON t.id = a.task_id
        WHERE t.id = ?
        GROUP BY t.id
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
        $_SESSION['info_message'] = 'You have already applied to this task.';
        redirect("task-details.php?id=$task_id");
    }
    
} catch (PDOException $e) {
    error_log("Task fetch error: " . $e->getMessage());
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
            transform: translateX(-4px);
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
        
        .pricing-guide {
            background: #e0f2fe;
            border: 1px solid #b3e5fc;
            border-radius: 12px;
            padding: 16px;
            margin-top: 12px;
        }
        
        .pricing-guide h4 {
            color: #0277bd;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .pricing-guide p {
            color: #01579b;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .character-count {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-top: 4px;
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
        
        .tips-section {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .tips-title {
            color: #c2410c;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .tips-list {
            color: #9a3412;
            font-size: 14px;
        }
        
        .tips-list li {
            margin-bottom: 6px;
            line-height: 1.4;
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
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <?php echo $task['total_applications']; ?> other applications
                    </div>
                </div>
                <div class="task-budget">
                    Client's Budget: $<?php echo number_format($task['budget'], 2); ?>
                </div>
            </div>
            
            <!-- Competition Info -->
            <div class="competition-info">
                <h3>🏆 Stand Out From the Competition</h3>
                <p>There are <?php echo $task['total_applications']; ?> other applications. Make your proposal compelling!</p>
            </div>
            
            <!-- Tips Section -->
            <div class="tips-section">
                <div class="tips-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    Tips for a Great Proposal
                </div>
                <ul class="tips-list">
                    <li>✓ Explain exactly how you'll complete the task</li>
                    <li>✓ Mention any relevant experience or skills</li>
                    <li>✓ Be specific about timing and availability</li>
                    <li>✓ Ask clarifying questions if needed</li>
                    <li>✓ Be professional but personable</li>
                </ul>
            </div>
            
            <!-- Application Form -->
            <form method="POST" id="applicationForm">
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

• [Explain your approach]
• [Mention relevant experience]
• [Specify timing and availability]
• [Ask any questions you have]

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
                                max="<?php echo $task['budget'] * 1.5; ?>" 
                                step="1" 
                                required
                                value="<?php echo isset($_POST['bid_amount']) ? $_POST['bid_amount'] : ''; ?>"
                                placeholder="<?php echo round($task['budget'] * 0.8); ?>"
                            >
                        </div>
                        <div class="help-text">
                            Enter your bid amount. Consider the client's budget of $<?php echo number_format($task['budget'], 2); ?> when pricing your services.
                        </div>
                        
                        <div class="pricing-guide">
                            <h4>💡 Pricing Strategy</h4>
                            <p>
                                <strong>Competitive:</strong> $<?php echo number_format($task['budget'] * 0.7, 0); ?> - $<?php echo number_format($task['budget'] * 0.85, 0); ?> 
                                <strong>• Fair:</strong> $<?php echo number_format($task['budget'] * 0.9, 0); ?> - $<?php echo number_format($task['budget'], 0); ?> 
                                <strong>• Premium:</strong> $<?php echo number_format($task['budget'] * 1.1, 0); ?> - $<?php echo number_format($task['budget'] * 1.3, 0); ?>
                            </p>
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
        // Character counter for proposal
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
        
        // Form validation
        document.getElementById('applicationForm').addEventListener('submit', function(e) {
            const proposal = proposalTextarea.value.trim();
            const bidAmount = parseFloat(document.getElementById('bid_amount').value);
            const maxBid = <?php echo $task['budget'] * 1.5; ?>;
            
            let isValid = true;
            let errors = [];
            
            if (proposal.length < 50) {
                errors.push('Proposal must be at least 50 characters long.');
                isValid = false;
            }
            
            if (bidAmount < 5) {
                errors.push('Bid amount must be at least $5.');
                isValid = false;
            }
            
            if (bidAmount > maxBid) {
                errors.push(`Bid amount cannot exceed $${maxBid.toFixed(2)}.`);
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
                return false;
            }
            
            // Confirm submission
            const confirmMessage = `Are you sure you want to submit this application?\n\nYour bid: $${bidAmount.toFixed(2)}\nClient's budget: $<?php echo number_format($task['budget'], 2); ?>`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
            
            // Disable submit button to prevent double submission
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Submitting...';
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
        
        // Smart bidding suggestions
        const bidInput = document.getElementById('bid_amount');
        const clientBudget = <?php echo $task['budget']; ?>;
        
        bidInput.addEventListener('input', function() {
            const bid = parseFloat(this.value);
            const pricingGuide = document.querySelector('.pricing-guide');
            
            if (bid > 0) {
                let category = '';
                let color = '';
                
                if (bid <= clientBudget * 0.85) {
                    category = 'Competitive pricing - likely to win!';
                    color = '#10b981';
                } else if (bid <= clientBudget) {
                    category = 'Fair pricing - good balance';
                    color = '#3b82f6';
                } else if (bid <= clientBudget * 1.3) {
                    category = 'Premium pricing - justify your value';
                    color = '#f59e0b';
                } else {
                    category = 'Very high - may be difficult to win';
                    color = '#ef4444';
                }
                
                pricingGuide.style.borderColor = color;
                pricingGuide.querySelector('h4').style.color = color;
                pricingGuide.querySelector('h4').textContent = `💡 ${category}`;
            }
        });
    </script>
</body>
</html>