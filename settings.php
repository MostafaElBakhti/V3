<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $fullname = sanitize($_POST['fullname'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            
            // Validation
            if (empty($fullname) || strlen($fullname) < 2) {
                $errors[] = 'Full name must be at least 2 characters long.';
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            }
            
            // Check if email is already taken by another user
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $errors[] = 'This email is already in use by another account.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error occurred.';
                }
            }
            
            // Update profile if no errors
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, bio = ? WHERE id = ?");
                    if ($stmt->execute([$fullname, $email, $bio, $user_id])) {
                        $_SESSION['fullname'] = $fullname;
                        $_SESSION['email'] = $email;
                        $success = 'Profile updated successfully!';
                    } else {
                        $errors[] = 'Failed to update profile.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error occurred.';
                }
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($current_password)) {
                $errors[] = 'Please enter your current password.';
            }
            
            if (empty($new_password) || strlen($new_password) < 6) {
                $errors[] = 'New password must be at least 6 characters long.';
            }
            
            if ($new_password !== $confirm_password) {
                $errors[] = 'New password and confirmation do not match.';
            }
            
            // Verify current password
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch();
                    
                    if (!$user || !password_verify($current_password, $user['password'])) {
                        $errors[] = 'Current password is incorrect.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error occurred.';
                }
            }
            
            // Update password if no errors
            if (empty($errors)) {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $user_id])) {
                        $success = 'Password changed successfully!';
                    } else {
                        $errors[] = 'Failed to change password.';
                    }
                } catch (PDOException $e) {
                    $errors[] = 'Database error occurred.';
                }
            }
            break;
    }
}

// Get current user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('logout.php');
    }
} catch (PDOException $e) {
    redirect('logout.php');
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
        
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .settings-title {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .settings-subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 32px;
            margin: 0 auto 16px;
        }
        
        .alert {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
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
        
        .settings-section {
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .section-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 16px;
        }
        
        .section-description {
            color: #666;
            margin-bottom: 24px;
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
            min-height: 100px;
            line-height: 1.5;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .btn {
            padding: 16px 32px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }
        
        .user-type-badge {
            display: inline-block;
            background: #f0f9ff;
            color: #0369a1;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-item {
            text-align: center;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .danger-zone {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 16px;
            padding: 24px;
        }
        
        .danger-zone h3 {
            color: #dc2626;
            margin-bottom: 12px;
        }
        
        .danger-zone p {
            color: #7f1d1d;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to Dashboard
        </a>
        
        <div class="settings-card">
            <div class="settings-header">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                </div>
                <h1 class="settings-title">Account Settings</h1>
                <p class="settings-subtitle">Manage your account preferences and security</p>
                <div class="user-type-badge">
                    <?php echo ucfirst($user['user_type']); ?> Account
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Information -->
            <div class="settings-section">
                <h2 class="section-title">Profile Information</h2>
                <p class="section-description">
                    Update your account's profile information and email address.
                </p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label for="fullname">Full Name</label>
                        <input type="text" id="fullname" name="fullname" 
                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                        <div class="help-text">Your full name as it appears to other users</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="help-text">Used for login and notifications</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Bio (Optional)</label>
                        <textarea id="bio" name="bio" placeholder="Tell others about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        <div class="help-text">Visible to other users when you apply for tasks or post tasks</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                        Save Profile
                    </button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="settings-section">
                <h2 class="section-title">Change Password</h2>
                <p class="section-description">
                    Ensure your account is using a long, random password to stay secure.
                </p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                            <div class="help-text">Minimum 6 characters</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <div class="help-text">Must match new password</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <circle cx="12" cy="16" r="1"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Change Password
                    </button>
                </form>
            </div>
            
            <!-- Account Statistics -->
            <div class="settings-section">
                <h2 class="section-title">Account Statistics</h2>
                <p class="section-description">
                    Overview of your activity on Helpify.
                </p>
                
                <div class="account-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo number_format($user['rating'], 1); ?></div>
                        <div class="stat-label">Rating</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user['total_ratings']; ?></div>
                        <div class="stat-label">Reviews</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo date('M Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Member Since</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php 
                            $days_active = floor((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                            echo $days_active; 
                            ?>
                        </div>
                        <div class="stat-label">Days Active</div>
                    </div>
                </div>
            </div>
            
            <!-- Danger Zone -->
            <div class="settings-section">
                <div class="danger-zone">
                    <h3>⚠️ Danger Zone</h3>
                    <p>
                        Once you delete your account, there is no going back. Please be certain.
                        All your tasks, applications, and messages will be permanently deleted.
                    </p>
                    <button type="button" class="btn btn-danger" onclick="confirmAccountDeletion()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3,6 5,6 21,6"/>
                            <path d="m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"/>
                        </svg>
                        Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function confirmAccountDeletion() {
            const confirmed = confirm(
                "⚠️ WARNING: This action cannot be undone!\n\n" +
                "Are you absolutely sure you want to delete your account?\n" +
                "• All your tasks will be cancelled\n" +
                "• All your applications will be withdrawn\n" +
                "• All your messages will be deleted\n" +
                "• Your profile and reviews will be permanently removed\n\n" +
                "Type 'DELETE' in the next prompt to confirm."
            );
            
            if (confirmed) {
                const confirmation = prompt("Please type 'DELETE' to confirm account deletion:");
                if (confirmation === 'DELETE') {
                    // Create a form to submit the deletion request
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_account';
                    
                    const confirmInput = document.createElement('input');
                    confirmInput.type = 'hidden';
                    confirmInput.name = 'confirm_delete';
                    confirmInput.value = 'DELETE';
                    
                    form.appendChild(actionInput);
                    form.appendChild(confirmInput);
                    document.body.appendChild(form);
                    
                    // You could submit this form to handle account deletion
                    // form.submit();
                    
                    alert("Account deletion feature would be implemented here in production.");
                } else {
                    alert("Account deletion cancelled - confirmation text didn't match.");
                }
            }
        }
        
        // Auto-resize bio textarea
        const bioTextarea = document.getElementById('bio');
        if (bioTextarea) {
            bioTextarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.max(100, this.scrollHeight) + 'px';
            });
            
            // Set initial height
            bioTextarea.style.height = Math.max(100, bioTextarea.scrollHeight) + 'px';
        }
        
        // Password strength indicator
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const helpText = this.parentNode.querySelector('.help-text');
                
                if (password.length === 0) {
                    helpText.textContent = 'Minimum 6 characters';
                    helpText.style.color = '#666';
                } else if (password.length < 6) {
                    helpText.textContent = 'Too short - minimum 6 characters';
                    helpText.style.color = '#ef4444';
                } else if (password.length < 8) {
                    helpText.textContent = 'Weak - consider using 8+ characters';
                    helpText.style.color = '#f59e0b';
                } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password)) {
                    helpText.textContent = 'Good - add numbers and mixed case for stronger security';
                    helpText.style.color = '#3b82f6';
                } else {
                    helpText.textContent = 'Strong password!';
                    helpText.style.color = '#10b981';
                }
            });
        }
        
        // Confirm password matching
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput && newPasswordInput) {
            function checkPasswordMatch() {
                const helpText = confirmPasswordInput.parentNode.querySelector('.help-text');
                
                if (confirmPasswordInput.value === '') {
                    helpText.textContent = 'Must match new password';
                    helpText.style.color = '#666';
                } else if (newPasswordInput.value === confirmPasswordInput.value) {
                    helpText.textContent = 'Passwords match!';
                    helpText.style.color = '#10b981';
                } else {
                    helpText.textContent = 'Passwords do not match';
                    helpText.style.color = '#ef4444';
                }
            }
            
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            newPasswordInput.addEventListener('input', checkPasswordMatch);
        }
    </script>
</body>
</html>