<?php
require_once 'config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background-color: #f5f8ff;
      color: #1b2b5f;
    }

    .login-container {
      display: flex;
      min-height: 100vh;
    }

    .login-image {
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
      color: #007bff;
      margin-bottom: 30px;
      align-self: flex-start;
    }

    .login-image .hero-content {
      max-width: 340px;
    }

    .login-image .hero-content h2 {
      font-size: 2.2rem;
      font-weight: 700;
      letter-spacing: 0.03em;
      margin-bottom: 15px;
    }

    .login-image .hero-content p {
      font-size: 1rem;
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

    .form-group input {
      width: 100%;
      padding: 12px 15px;
      font-size: 1rem;
      border: 1.5px solid #c9d8f0;
      border-radius: 10px;
      outline: none;
      transition: border 0.3s;
      box-sizing: border-box;
    }

    .form-group input:focus {
      border-color: #007bff;
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

    .register-link {
      margin-top: 15px;
      font-size: 0.95rem;
      text-align: center;
    }

    .register-link a {
      color: #007bff;
      text-decoration: none;
      font-weight: 600;
    }

    .register-link a:hover {
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
      .login-container {
        flex-direction: column;
      }

      .login-image, .login-form {
        flex: none;
        width: 100%;
      }

      .login-image {
        padding: 40px 25px;
        align-items: center;
        text-align: center;
        position: relative;
      }

      .login-image .hero-content {
        align-items: center;
      }

      .login-image .logo-img {
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
      <form method="POST" action="login.php">
        <h2>Sign In</h2>

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
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" value="<?php echo sanitize($_POST['email'] ?? ''); ?>" required />
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required />
        </div>

        <div class="form-group">
          <button type="submit">Sign In</button>
        </div>

        <div class="register-link">
          Don't have an account? <a href="register.php">Create one</a>
        </div>
      </form>
    </div>

  </div>

</body>
</html>