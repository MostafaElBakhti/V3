<?php
require_once 'config.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = sanitize($_POST['role'] ?? '');
    $fullname = sanitize($_POST['fullname'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm-password'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($role) || empty($fullname) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!in_array($role, ['client', 'helper'])) {
        $error = 'Please select a valid role.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (!$terms) {
        $error = 'You must agree to the terms and conditions.';
    } else {
        // Check if email already exists
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = 'An account with this email already exists.';
            } else {
                // Hash password and insert user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, password, user_type) VALUES (?, ?, ?, ?)");
                $stmt->execute([$fullname, $email, $hashed_password, $role]);
                
                $success = 'Account created successfully! You can now login.';
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
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
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background-color: #f5f8ff;
      color: #1b2b5f;
    }

    .register-container {
      display: flex;
      min-height: 100vh;
    }

    .register-image {
      flex: 1;
      background: linear-gradient(to bottom right, #007bff, #00b4d8);
      color: white;
      display: flex;
      flex-direction: column;
      padding: 40px 30px;
      justify-content: center;
      align-items: flex-start;
      text-align: left;
    }

    .register-image .logo-img {
      width: 80px;
      margin-bottom: 30px;
      align-self: flex-start;
    }

    .register-image .hero-content {
      max-width: 340px;
    }

    .register-image .hero-content h2 {
      font-size: 2.2rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      margin-bottom: 15px;
    }

    .register-image .hero-content p {
      font-size: 1rem;
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
    }

    form {
      width: 100%;
      max-width: 500px;
      background: #ffffff;
    }

    form h2 {
      font-size: 2.5rem;
      font-weight: 800;
      margin-bottom: 30px;
      color: #1b2b5f;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-weight: 600;
      margin-bottom: 8px;
      font-size: 1rem;
    }

    .form-group input, .form-group select {
      width: 100%;
      padding: 12px 15px;
      font-size: 1rem;
      border: 1.5px solid #c9d8f0;
      border-radius: 10px;
      outline: none;
      transition: border 0.3s;
      box-sizing: border-box;
    }

    .form-group input:focus, .form-group select:focus {
      border-color: #007bff;
    }

    .form-group.terms {
      display: flex;
      align-items: center;
      font-size: 0.95rem;
    }

    .form-group.terms input {
      margin-right: 10px;
      width: auto;
    }

    .form-group button {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 14px 20px;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      width: 100%;
    }

    .form-group button:hover {
      background-color: #0056b3;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 123, 255, 0.2);
    }

    .error-message {
      color: #dc3545;
      background-color: #f8d7da;
      border: 1px solid #f5c6cb;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .success-message {
      color: #28a745;
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 20px;
      font-size: 0.9rem;
    }

    .login-link {
      text-align: center;
      margin-top: 20px;
      font-size: 0.95rem;
    }

    .login-link a {
      color: #007bff;
      text-decoration: none;
      font-weight: 600;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .register-container {
        flex-direction: column;
      }

      .register-image {
        padding: 30px 20px;
        text-align: center;
        align-items: center;
      }

      .register-image .hero-content {
        max-width: 100%;
      }

      .register-form {
        padding: 30px 20px;
      }
    }
  </style>
</head>
<body>
  <div class="register-container">
    <div class="register-image">
      <img src="assets/images/logo.png" alt="Helpify Logo" class="logo-img">
      <div class="hero-content">
        <h2>Join Our Community</h2>
        <p>Create an account to start managing your tasks efficiently. Connect with reliable helpers or offer your services to those in need.</p>
      </div>
    </div>
    <div class="register-form">
      <form method="POST" action="">
        <h2>Create Account</h2>
        
        <?php if ($error): ?>
          <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
          <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="form-group">
          <label for="role">I want to</label>
          <select name="role" id="role" required>
            <option value="">Select your role</option>
            <option value="client" <?php echo (isset($_POST['role']) && $_POST['role'] === 'client') ? 'selected' : ''; ?>>Post Tasks</option>
            <option value="helper" <?php echo (isset($_POST['role']) && $_POST['role'] === 'helper') ? 'selected' : ''; ?>>Help Others</option>
          </select>
        </div>

        <div class="form-group">
          <label for="fullname">Full Name</label>
          <input type="text" id="fullname" name="fullname" value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" required>
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
          <label for="confirm-password">Confirm Password</label>
          <input type="password" id="confirm-password" name="confirm-password" required>
        </div>

        <div class="form-group terms">
          <input type="checkbox" id="terms" name="terms" required>
          <label for="terms">I agree to the <a href="terms.php">Terms and Conditions</a> and <a href="privacy.php">Privacy Policy</a></label>
        </div>

        <div class="form-group">
          <button type="submit">Create Account</button>
        </div>

        <div class="login-link">
          Already have an account? <a href="login.php">Login here</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>