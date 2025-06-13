<?php
session_start();

// If user is already logged in, redirect to index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'taskhelper';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = '';

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    
    // Validate email and password
    if (empty($email) || empty($password)) {
        $error = "Both email and password are required";
    } else {
        // Query to check user credentials
        $sql = "SELECT id, email, password FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Password is correct, create session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                
                // If remember me is checked, set cookie
                if (isset($_POST['remember']) && $_POST['remember'] == 'on') {
                    setcookie("user_login", $email, time() + (30 * 24 * 60 * 60), "/");
                }
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        .error-message {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .remember-me input {
            margin-right: 10px;
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

        .forgot-password {
            text-align: center;
            margin-top: 15px;
            font-size: 0.95rem;
        }

        .forgot-password a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link {
            margin-top: 15px;
            font-size: 0.95rem;
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .register-link a {
            color: #007bff;
            text-decoration: none;
            font-weight: 600;
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
            }

            .login-image .hero-content {
                align-items: center;
            }

            .login-image .logo-img {
                margin: 0 auto 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Left Side: Image & Promo Text -->
        <div class="login-image">
            <img src="logo.png" alt="Helpify Logo" class="logo-img" />
            <div class="hero-content">
                <h2>Welcome Back to Helpify</h2>
                <p>Log in to your account to connect with our community, find solutions, or provide help to those who need it.</p>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="login-form">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <h2>Login</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="error-message" style="margin-bottom: 20px;"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="username">Email Address</label>
                    <input type="email" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>

                <div class="form-group">
                    <button type="submit">Login</button>
                </div>

                <div class="forgot-password">
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>

                <div class="register-link">
                    Don't have an account? <a href="register.php">Register here</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 