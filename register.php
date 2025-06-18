<?php
require_once 'config.php';

// Initialize variables
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $user_type = sanitize($_POST['user_type'] ?? '');
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);

    // Validation
    if (empty($user_type) || !in_array($user_type, ['client', 'helper'])) {
        $errors[] = 'Please select whether you are a client or helper.';
    }

    if (empty($fullname) || strlen($fullname) < 2) {
        $errors[] = 'Full name must be at least 2 characters long.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$terms) {
        $errors[] = 'You must agree to the terms and conditions.';
    }

    // Check if email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with this email already exists.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred. Please try again.';
        }
    }

    // Create account if no errors
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (fullname, email, password, user_type) 
                VALUES (?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$fullname, $email, $hashed_password, $user_type])) {
                $user_id = $pdo->lastInsertId();
                
                // Log the user in automatically
                $_SESSION['user_id'] = $user_id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['email'] = $email;
                $_SESSION['user_type'] = $user_type;
                
                // Redirect to dashboard
                redirect('dashboard.php');
            } else {
                $errors[] = 'Failed to create account. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred. Please try again.';
        }
    }
}

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register | Helpify</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #0F172A;
      --primary-light: #1E293B;
      --secondary: #0EA5E9;
      --accent: #06B6D4;
      --background: #F8FAFC;
      --text-primary: #334155;
      --text-secondary: #64748B;
      --error: #EF4444;
      --success: #10B981;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--background);
      color: var(--text-primary);
      line-height: 1.6;
      overflow-x: hidden;
    }

    .register-container {
      display: flex;
      min-height: 100vh;
    }

    .register-image {
      flex: 1;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      display: flex;
      flex-direction: column;
      padding: 40px 30px;
      justify-content: center;
      align-items: flex-start;
      text-align: left;
      position: relative;
      min-height: 100vh;
    }

    .register-image .logo-img {
      width: 80px;
      height: 80px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
      color: var(--secondary);
      margin-bottom: 30px;
      align-self: flex-start;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .register-image .hero-content {
      max-width: 400px;
      z-index: 2;
    }

    .register-image .hero-content h2 {
      font-size: clamp(1.8rem, 4vw, 2.2rem);
      font-weight: 700;
      letter-spacing: 0.03em;
      margin-bottom: 15px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .register-image .hero-content p {
      font-size: clamp(0.95rem, 2vw, 1rem);
      font-weight: 500;
      opacity: 0.92;
      line-height: 1.6;
    }

    .register-form {
      flex: 1.2;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 60px 40px;
      background-color: #fff;
      min-height: 100vh;
      overflow-y: auto;
    }

    .form-wrapper {
      width: 100%;
      max-width: 500px;
      background: #ffffff;
    }

    .form-wrapper h2 {
      font-size: clamp(1.8rem, 4vw, 2.5rem);
      font-weight: 800;
      margin-bottom: 30px;
      color: var(--primary);
      text-align: center;
    }

    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
      font-size: 14px;
    }

    .alert-error {
      background-color: #fef2f2;
      border: 1px solid #fecaca;
      color: var(--error);
    }

    .alert-success {
      background-color: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: var(--success);
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      font-size: clamp(0.95rem, 2vw, 1rem);
      color: var(--text-primary);
    }

    .form-group input, .form-group select {
      width: 100%;
      padding: 12px 15px;
      font-size: clamp(0.95rem, 2vw, 1rem);
      border: 1.5px solid #c9d8f0;
      border-radius: 10px;
      outline: none;
      transition: all 0.3s ease;
      box-sizing: border-box;
      background-color: #fff;
      font-family: 'Poppins', sans-serif;
    }

    .form-group select {
      cursor: pointer;
    }

    .form-group input:focus, .form-group select:focus {
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .form-group.terms {
      display: flex;
      align-items: flex-start;
      font-size: clamp(0.9rem, 2vw, 0.95rem);
      gap: 12px;
      margin-bottom: 25px;
    }

    .form-group.terms input {
      width: auto;
      margin: 0;
      flex-shrink: 0;
      margin-top: 3px;
      transform: scale(1.2);
    }

    .form-group.terms label {
      margin: 0;
      flex: 1;
      font-weight: 500;
      line-height: 1.5;
    }

    .form-group.terms a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .form-group.terms a:hover {
      color: var(--accent);
      text-decoration: underline;
    }

    .form-group button {
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      color: white;
      border: none;
      padding: 14px 20px;
      font-size: clamp(1rem, 2vw, 1.1rem);
      font-weight: 600;
      border-radius: 12px;
      cursor: pointer;
      width: 100%;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
    }

    .form-group button:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
    }

    .form-group button:active {
      transform: translateY(0);
    }

    .form-group button:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .login-link {
      margin-top: 20px;
      font-size: clamp(0.9rem, 2vw, 0.95rem);
      text-align: center;
      color: var(--text-secondary);
    }

    .login-link a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .login-link a:hover {
      color: var(--accent);
      text-decoration: underline;
    }

    .back-home {
      position: absolute;
      top: 20px;
      left: 20px;
      background: rgba(255, 255, 255, 0.1);
      color: white;
      padding: 8px 16px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      font-size: clamp(0.85rem, 2vw, 0.95rem);
      backdrop-filter: blur(10px);
      z-index: 10;
    }

    .back-home:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-1px);
    }

    /* Password strength indicator */
    .password-strength {
      margin-top: 5px;
      height: 4px;
      background-color: #e2e8f0;
      border-radius: 2px;
      overflow: hidden;
      display: none;
    }

    .password-strength.show {
      display: block;
    }

    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }

    .password-strength.weak .password-strength-bar {
      width: 33%;
      background-color: var(--error);
    }

    .password-strength.medium .password-strength-bar {
      width: 66%;
      background-color: var(--warning, #F59E0B);
    }

    .password-strength.strong .password-strength-bar {
      width: 100%;
      background-color: var(--success);
    }

    /* Background decoration for left side */
    .register-image::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1.5" fill="rgba(255,255,255,0.08)"/><circle cx="40" cy="80" r="1" fill="rgba(255,255,255,0.06)"/><circle cx="90" cy="90" r="1.2" fill="rgba(255,255,255,0.07)"/><circle cx="10" cy="60" r="0.8" fill="rgba(255,255,255,0.05)"/></svg>') repeat;
      pointer-events: none;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .register-container {
        flex-direction: column;
      }

      .register-image {
        flex: none;
        min-height: 35vh;
        padding: 30px 25px;
        align-items: center;
        text-align: center;
        justify-content: center;
      }

      .register-image .hero-content {
        max-width: 100%;
        text-align: center;
      }

      .register-image .logo-img {
        margin: 0 auto 25px;
      }

      .register-form {
        flex: none;
        min-height: 65vh;
        padding: 40px 25px;
      }

      .back-home {
        top: 15px;
        left: 15px;
      }
    }

    @media (max-width: 768px) {
      .register-image {
        min-height: 30vh;
        padding: 25px 20px;
      }

      .register-form {
        min-height: 70vh;
        padding: 30px 20px;
      }

      .form-wrapper {
        max-width: 100%;
      }

      .form-wrapper h2 {
        margin-bottom: 25px;
      }

      .form-group {
        margin-bottom: 18px;
      }

      .form-group input, .form-group select {
        padding: 14px 15px;
      }

      .form-group button {
        padding: 16px 20px;
      }

      .form-group.terms {
        margin-bottom: 20px;
        gap: 10px;
      }

      .back-home {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
      }
    }

    @media (max-width: 480px) {
      .register-image {
        min-height: 25vh;
        padding: 20px 15px;
      }

      .register-form {
        padding: 25px 15px;
        min-height: 75vh;
      }

      .form-wrapper h2 {
        margin-bottom: 20px;
      }

      .form-group {
        margin-bottom: 16px;
      }

      .form-group input, .form-group select, .form-group button {
        padding: 12px 15px;
      }

      .form-group.terms {
        margin-bottom: 18px;
        gap: 8px;
      }

      .alert {
        padding: 10px 14px;
        font-size: 13px;
      }

      .back-home {
        position: fixed;
        top: 10px;
        left: 10px;
        padding: 8px 12px;
        font-size: 0.8rem;
      }
    }

    @media (max-width: 360px) {
      .register-image {
        min-height: 20vh;
        padding: 15px 10px;
      }

      .register-form {
        padding: 20px 10px;
        min-height: 80vh;
      }

      .form-wrapper {
        padding: 0 5px;
      }

      .form-group input, .form-group select, .form-group button {
        padding: 12px;
      }

      .form-group.terms {
        gap: 6px;
      }

      .register-image .hero-content h2 {
        font-size: 1.5rem;
      }

      .register-image .hero-content p {
        font-size: 0.9rem;
      }
    }

    /* Landscape orientation fixes */
    @media (max-height: 600px) and (orientation: landscape) {
      .register-container {
        flex-direction: row;
      }

      .register-image {
        flex: 1;
        min-height: 100vh;
        padding: 20px;
      }

      .register-form {
        flex: 1.2;
        min-height: 100vh;
        padding: 20px;
      }

      .register-image .hero-content h2 {
        font-size: 1.6rem;
        margin-bottom: 10px;
      }

      .register-image .hero-content p {
        font-size: 0.9rem;
      }

      .form-wrapper h2 {
        font-size: 1.8rem;
        margin-bottom: 20px;
      }

      .form-group {
        margin-bottom: 12px;
      }

      .form-group.terms {
        margin-bottom: 15px;
      }
    }

    /* High DPI displays */
    @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
      .register-image .logo-img {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      }
      
      .form-group button {
        box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
      }
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
      .register-form {
        background-color: #1a1a1a;
      }
      
      .form-wrapper {
        background: #1a1a1a;
      }
      
      .form-group input, .form-group select {
        background-color: #2a2a2a;
        border-color: #444;
        color: #fff;
      }
      
      .form-wrapper h2 {
        color: #fff;
      }
      
      .form-group label {
        color: #ddd;
      }
    }

    /* Accessibility improvements */
    @media (prefers-reduced-motion: reduce) {
      * {
        transition: none !important;
        animation: none !important;
      }
    }

    /* Focus indicators for keyboard navigation */
    .form-group input:focus,
    .form-group select:focus,
    .form-group button:focus,
    .back-home:focus,
    .login-link a:focus,
    .form-group.terms a:focus {
      outline: 2px solid var(--secondary);
      outline-offset: 2px;
    }
  </style>
</head>
<body>

  <div class="register-container">

    <!-- Left Side: Image & Promo Text -->
    <div class="register-image">
      <a href="index.php" class="back-home">← Back to Home</a>
      <div class="logo-img">H</div>
      <div class="hero-content">
        <h2>Be Part of the Helpify Community</h2>
        <p>Whether you need a hand or you're ready to help, Helpify connects real people with real solutions. Join a growing community built on trust, speed, and support.</p>
      </div>
    </div>

    <!-- Right Side: Form -->
    <div class="register-form">
      <div class="form-wrapper">
        <h2>Create Your Account</h2>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
              <div><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="register.php" novalidate>
          <div class="form-group">
            <label for="user_type">I am a:</label>
            <select id="user_type" name="user_type" required>
              <option value="">Select your role...</option>
              <option value="client" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'client') ? 'selected' : ''; ?>>Client (I need help)</option>
              <option value="helper" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'helper') ? 'selected' : ''; ?>>Helper (I provide help)</option>
            </select>
          </div>

          <div class="form-group">
            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required autocomplete="name" />
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email" />
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password" />
            <div class="password-strength" id="passwordStrength">
              <div class="password-strength-bar"></div>
            </div>
          </div>

          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" />
          </div>

          <div class="form-group terms">
            <input type="checkbox" id="terms" name="terms" required />
            <label for="terms">I agree to the <a href="#" target="_blank">terms and conditions</a> and <a href="#" target="_blank">privacy policy</a></label>
          </div>

          <div class="form-group">
            <button type="submit">Create Account</button>
          </div>
        </form>

        <div class="login-link">
          Already have an account? <a href="login.php">Sign In</a>
        </div>
      </div>
    </div>

  </div>

  <script>
    // Form validation and UX improvements
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form');
      const userTypeSelect = document.getElementById('user_type');
      const fullnameInput = document.getElementById('fullname');
      const emailInput = document.getElementById('email');
      const passwordInput = document.getElementById('password');
      const confirmPasswordInput = document.getElementById('confirm_password');
      const termsCheckbox = document.getElementById('terms');
      const submitButton = document.querySelector('button[type="submit"]');
      const passwordStrength = document.getElementById('passwordStrength');

      // Password strength checker
      function checkPasswordStrength(password) {
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;

        return strength;
      }

      // Update password strength indicator
      passwordInput.addEventListener('input', function() {
        const password = this.value;
        const strength = checkPasswordStrength(password);
        
        if (password.length === 0) {
          passwordStrength.classList.remove('show', 'weak', 'medium', 'strong');
          return;
        }

        passwordStrength.classList.add('show');
        passwordStrength.classList.remove('weak', 'medium', 'strong');

        if (strength <= 2) {
          passwordStrength.classList.add('weak');
        } else if (strength <= 4) {
          passwordStrength.classList.add('medium');
        } else {
          passwordStrength.classList.add('strong');
        }
      });

      // Real-time validation
      emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !isValidEmail(email)) {
          this.style.borderColor = 'var(--error)';
        } else {
          this.style.borderColor = '';
        }
      });

      fullnameInput.addEventListener('blur', function() {
        const name = this.value.trim();
        if (name && name.length < 2) {
          this.style.borderColor = 'var(--error)';
        } else {
          this.style.borderColor = '';
        }
      });

      passwordInput.addEventListener('blur', function() {
        const password = this.value;
        if (password && password.length < 6) {
          this.style.borderColor = 'var(--error)';
        } else {
          this.style.borderColor = '';
        }
      });

      confirmPasswordInput.addEventListener('blur', function() {
        const password = passwordInput.value;
        const confirmPassword = this.value;
        if (confirmPassword && password !== confirmPassword) {
          this.style.borderColor = 'var(--error)';
        } else {
          this.style.borderColor = '';
        }
      });

      // Email validation function
      function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
      }

      // Form submission handling
      form.addEventListener('submit', function(e) {
        const userType = userTypeSelect.value;
        const fullname = fullnameInput.value.trim();
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        const terms = termsCheckbox.checked;

        let hasErrors = false;

        // Reset all error styles
        [userTypeSelect, fullnameInput, emailInput, passwordInput, confirmPasswordInput].forEach(input => {
          input.style.borderColor = '';
        });

        // Validate all fields
        if (!userType) {
          userTypeSelect.style.borderColor = 'var(--error)';
          userTypeSelect.focus();
          hasErrors = true;
        }

        if (!fullname || fullname.length < 2) {
          fullnameInput.style.borderColor = 'var(--error)';
          if (!hasErrors) fullnameInput.focus();
          hasErrors = true;
        }

        if (!email || !isValidEmail(email)) {
          emailInput.style.borderColor = 'var(--error)';
          if (!hasErrors) emailInput.focus();
          hasErrors = true;
        }

        if (!password || password.length < 6) {
          passwordInput.style.borderColor = 'var(--error)';
          if (!hasErrors) passwordInput.focus();
          hasErrors = true;
        }

        if (password !== confirmPassword) {
          confirmPasswordInput.style.borderColor = 'var(--error)';
          if (!hasErrors) confirmPasswordInput.focus();
          hasErrors = true;
        }

        if (!terms) {
          termsCheckbox.focus();
          hasErrors = true;
        }

        if (hasErrors) {
          e.preventDefault();
          return;
        }

        // Show loading state
        submitButton.textContent = 'Creating Account...';
        submitButton.disabled = true;
      });

      // Clear error styling on input
      [userTypeSelect, fullnameInput, emailInput, passwordInput, confirmPasswordInput].forEach(input => {
        input.addEventListener('input', function() {
          this.style.borderColor = '';
        });
      });

      // Handle window resize for mobile layout
      function handleResize() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
      }

      window.addEventListener('resize', handleResize);
      handleResize();

      // Auto-focus first empty input
      if (!userTypeSelect.value) {
        userTypeSelect.focus();
      } else if (!fullnameInput.value) {
        fullnameInput.focus();
      } else if (!emailInput.value) {
        emailInput.focus();
      }

      // Password match indicator
      confirmPasswordInput.addEventListener('input', function() {
        const password = passwordInput.value;
        const confirmPassword = this.value;
        
        if (confirmPassword && password !== confirmPassword) {
          this.style.borderColor = 'var(--error)';
        } else if (confirmPassword && password === confirmPassword) {
          this.style.borderColor = 'var(--success)';
        } else {
          this.style.borderColor = '';
        }
      });
    });
  </script>

</body>
</html>