<?php
require_once 'config.php';

// Initialize variables
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Please enter your password.';
    }

    // Authenticate user
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, fullname, email, password, user_type FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];

                // Redirect to appropriate dashboard based on user type
                if ($user['user_type'] === 'client') {
                    redirect('client-dashboard.php');
                } else {
                    redirect('helper-dashboard.php');
                }
            } else {
                $errors[] = 'Invalid email or password.';
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
  <title>Login | Helpify</title>
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

    .login-container {
      display: flex;
      min-height: 100vh;
    }

    .login-image {
      flex: 1;
      background: linear-gradient(135deg, var(--secondary) 0%, var(--accent) 100%);
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

    .login-image .logo-img {
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

    .login-image .hero-content {
      max-width: 400px;
      z-index: 2;
    }

    .login-image .hero-content h2 {
      font-size: clamp(1.8rem, 4vw, 2.2rem);
      font-weight: 700;
      letter-spacing: 0.03em;
      margin-bottom: 15px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .login-image .hero-content p {
      font-size: clamp(0.95rem, 2vw, 1rem);
      font-weight: 500;
      opacity: 0.92;
      line-height: 1.6;
    }

    .login-form {
      flex: 1.2;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 60px 40px;
      background-color: #fff;
      min-height: 100vh;
    }

    .form-wrapper {
      width: 100%;
      max-width: 500px;
      background: #ffffff;
    }

    .form-wrapper h2 {
      font-size: clamp(2rem, 4vw, 2.5rem);
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

    .form-group input {
      width: 100%;
      padding: 12px 15px;
      font-size: clamp(0.95rem, 2vw, 1rem);
      border: 1.5px solid #c9d8f0;
      border-radius: 10px;
      outline: none;
      transition: all 0.3s ease;
      box-sizing: border-box;
      background-color: #fff;
    }

    .form-group input:focus {
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
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

    .register-link {
      margin-top: 20px;
      font-size: clamp(0.9rem, 2vw, 0.95rem);
      text-align: center;
      color: var(--text-secondary);
    }

    .register-link a {
      color: var(--secondary);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .register-link a:hover {
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

    /* Background decoration for left side */
    .login-image::before {
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
      .login-container {
        flex-direction: column;
      }

      .login-image {
        flex: none;
        min-height: 40vh;
        padding: 30px 25px;
        align-items: center;
        text-align: center;
        justify-content: center;
      }

      .login-image .hero-content {
        max-width: 100%;
        text-align: center;
      }

      .login-image .logo-img {
        margin: 0 auto 25px;
      }

      .login-form {
        flex: none;
        min-height: 60vh;
        padding: 40px 25px;
      }

      .back-home {
        top: 15px;
        left: 15px;
      }
    }

    @media (max-width: 768px) {
      .login-image {
        min-height: 35vh;
        padding: 25px 20px;
      }

      .login-form {
        min-height: 65vh;
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

      .form-group input {
        padding: 14px 15px;
      }

      .form-group button {
        padding: 16px 20px;
      }

      .back-home {
        top: 10px;
        left: 10px;
        padding: 6px 12px;
      }
    }

    @media (max-width: 480px) {
      .login-image {
        min-height: 30vh;
        padding: 20px 15px;
      }

      .login-form {
        padding: 25px 15px;
        min-height: 70vh;
      }

      .form-wrapper h2 {
        margin-bottom: 20px;
      }

      .form-group {
        margin-bottom: 16px;
      }

      .form-group input, .form-group button {
        padding: 12px 15px;
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
      .login-image {
        min-height: 25vh;
        padding: 15px 10px;
      }

      .login-form {
        padding: 20px 10px;
        min-height: 75vh;
      }

      .form-wrapper {
        padding: 0 5px;
      }

      .form-group input, .form-group button {
        padding: 12px;
      }

      .login-image .hero-content h2 {
        font-size: 1.5rem;
      }

      .login-image .hero-content p {
        font-size: 0.9rem;
      }
    }

    /* Landscape orientation fixes */
    @media (max-height: 600px) and (orientation: landscape) {
      .login-container {
        flex-direction: row;
      }

      .login-image {
        flex: 1;
        min-height: 100vh;
        padding: 20px;
      }

      .login-form {
        flex: 1.2;
        min-height: 100vh;
        padding: 20px;
      }

      .login-image .hero-content h2 {
        font-size: 1.6rem;
        margin-bottom: 10px;
      }

      .login-image .hero-content p {
        font-size: 0.9rem;
      }

      .form-wrapper h2 {
        font-size: 1.8rem;
        margin-bottom: 20px;
      }

      .form-group {
        margin-bottom: 15px;
      }
    }

    /* High DPI displays */
    @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
      .login-image .logo-img {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
      }
      
      .form-group button {
        box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
      }
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
      .login-form {
        background-color: #1a1a1a;
      }
      
      .form-wrapper {
        background: #1a1a1a;
      }
      
      .form-group input {
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
    .form-group button:focus,
    .back-home:focus,
    .register-link a:focus {
      outline: 2px solid var(--secondary);
      outline-offset: 2px;
    }

    /* Loading state for button */
    .form-group button:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
  </style>
</head>
<body>

  <div class="login-container">

    <!-- Left Side: Image & Promo Text -->
    <div class="login-image">
      <a href="index.php" class="back-home">← Back to Home</a>
      <div class="logo-img">H</div>
      <div class="hero-content">
        <h2>Welcome Back to Helpify</h2>
        <p>Sign in to continue connecting with your community. Whether you're seeking help or ready to lend a hand, your next opportunity is just a click away.</p>
      </div>
    </div>

    <!-- Right Side: Form -->
    <div class="login-form">
      <div class="form-wrapper">
        <h2>Sign In</h2>

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

        <form method="POST" action="login.php" novalidate>
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autocomplete="email" />
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password" />
          </div>

          <div class="form-group">
            <button type="submit">Sign In</button>
          </div>
        </form>

        <div class="register-link">
          Don't have an account? <a href="register.php">Create one</a>
        </div>
      </div>
    </div>

  </div>

  <script>
    // Form validation and UX improvements
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.querySelector('form');
      const emailInput = document.getElementById('email');
      const passwordInput = document.getElementById('password');
      const submitButton = document.querySelector('button[type="submit"]');

      // Add real-time validation
      emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !isValidEmail(email)) {
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
        const email = emailInput.value.trim();
        const password = passwordInput.value;

        // Basic validation
        if (!email || !password) {
          e.preventDefault();
          
          if (!email) {
            emailInput.focus();
            emailInput.style.borderColor = 'var(--error)';
          } else if (!password) {
            passwordInput.focus();
            passwordInput.style.borderColor = 'var(--error)';
          }
          return;
        }

        if (!isValidEmail(email)) {
          e.preventDefault();
          emailInput.focus();
          emailInput.style.borderColor = 'var(--error)';
          return;
        }

        // Show loading state
        submitButton.textContent = 'Signing In...';
        submitButton.disabled = true;
      });

      // Clear error styling on input
      [emailInput, passwordInput].forEach(input => {
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
      if (!emailInput.value) {
        emailInput.focus();
      } else if (!passwordInput.value) {
        passwordInput.focus();
      }
    });
  </script>

</body>
</html>