<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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
      height: 80px;
      background: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
      color: #007bff;
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

    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .alert-error {
      background-color: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
    }

    .alert-success {
      background-color: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #16a34a;
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
      align-items: flex-start;
      font-size: 0.95rem;
      gap: 10px;
    }

    .form-group.terms input {
      width: auto;
      margin: 0;
      flex-shrink: 0;
      margin-top: 3px;
    }

    .form-group.terms label {
      margin: 0;
      flex: 1;
    }

    .form-group.terms a {
      color: #007bff;
      text-decoration: none;
    }

    .form-group.terms a:hover {
      text-decoration: underline;
    }

    .form-group button {
      background-color: #007bff;
      color: white;
      border: none;
      padding: 14px 20px;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 12px;
      cursor: pointer;
      width: 100%;
      transition: background-color 0.3s ease;
    }

    .form-group button:hover {
      background-color: #0056cc;
    }

    .form-group button:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }

    .login-link {
      margin-top: 15px;
      font-size: 0.95rem;
      text-align: center;
    }

    .login-link a {
      color: #007bff;
      text-decoration: none;
      font-weight: 600;
    }

    .login-link a:hover {
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
      transition: background 0.3s;
    }

    .back-home:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    @media (max-width: 960px) {
      .register-container {
        flex-direction: column;
      }

      .register-image, .register-form {
        flex: none;
        width: 100%;
      }

      .register-image {
        padding: 40px 25px;
        align-items: center;
        text-align: center;
        position: relative;
      }

      .register-image .hero-content {
        align-items: center;
      }

      .register-image .logo-img {
        margin: 0 auto 25px;
      }

      .back-home {
        position: absolute;
        top: 10px;
        left: 10px;
      }
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
      <form method="POST" action="register.php">
        <h2>Create Your Account</h2>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
              <div><?php echo $error; ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
          <div class="alert alert-success">
            <?php echo $success; ?>
          </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="user_type">I am a:</label>
          <select id="user_type" name="user_type" required>
            <option value="">Select...</option>
            <option value="client" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'client') ? 'selected' : ''; ?>>Client (I need help)</option>
            <option value="helper" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'helper') ? 'selected' : ''; ?>>Helper (I provide help)</option>
          </select>
        </div>

        <div class="form-group">
          <label for="fullname">Full Name</label>
          <input type="text" id="fullname" name="fullname" value="<?php echo sanitize($_POST['fullname'] ?? ''); ?>" required />
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required />
        </div>

        <div class="form-group">
          <label for="confirm_password">Repeat Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required />
        </div>

        <div class="form-group terms">
          <input type="checkbox" id="terms" name="terms" required />
          <label for="terms">I agree to the <a href="#" target="_blank">terms and conditions</a></label>
        </div>

        <div class="form-group">
          <button type="submit">Create Account</button>
        </div>

        <div class="login-link">
          Already have an account? <a href="login.php">Login</a>
        </div>
      </form>
    </div>

  </div>

</body>
</html>