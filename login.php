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
  <link href="./css/login.css" rel="stylesheet" />


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