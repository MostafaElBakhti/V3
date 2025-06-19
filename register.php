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
  <link href="./css/register.css" rel="stylesheet" />

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