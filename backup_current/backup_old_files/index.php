<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Helpify - Get Help Anytime</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet" />
  <style>
    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9f9f9;
      color: #333;
    }
    header {
      display: flex; justify-content: space-between; align-items: center;
      padding: 20px 60px; background-color: #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      position: fixed; width: 100%; top: 0; z-index: 1000;
    }
    .logo {
      font-size: 28px; font-weight: 700; color: #2c3e50;
    }
    .nav-links {
      display: flex; gap: 35px;
    }
    .nav-links a {
      text-decoration: none; color: #333; font-weight: 500;
      transition: color 0.3s;
    }
    .nav-links a:hover {
      color: #007bff;
    }
    .auth-buttons {
      display: flex; gap: 15px;
    }
    .auth-buttons a {
      text-decoration: none; padding: 10px 18px;
      border-radius: 8px; font-weight: 500;
      transition: all 0.3s ease-in-out;
    }
    .sign-in {
      border: 2px solid #007bff; color: #007bff;
    }
    .sign-in:hover {
      background-color: #007bff; color: #fff;
    }
    .sign-up {
      background: linear-gradient(to right, #007bff, #00b4d8);
      color: white; border: none;
    }
    .sign-up:hover {
      opacity: 0.9;
    }
    .hero {
      height: 100vh;
      background:
        linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(0,0,0,0.3)),
        url('te.png') center/cover no-repeat;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      text-align: center; padding: 0 20px; padding-top: 80px; color: #fff;
    }
    .hero h1 {
      font-size: 48px; max-width: 800px; margin-bottom: 20px; font-weight: 700;
      text-shadow: 2px 2px 10px rgba(0,0,0,0.4);
    }
    .hero p {
      font-size: 18px; max-width: 650px; margin-bottom: 35px; line-height: 1.6;
      text-shadow: 1px 1px 8px rgba(0,0,0,0.5);
    }
    .hero-buttons {
      display: flex; gap: 20px; flex-wrap: wrap;
    }
    .hero-buttons a {
      text-decoration: none; padding: 14px 30px; font-size: 16px; font-weight: 600;
      border-radius: 8px; transition: all 0.3s ease;
    }
    .post-task {
      background: linear-gradient(to right, #00b4d8, #007bff); color: white;
    }
    .post-task:hover {
      transform: translateY(-2px); box-shadow: 0 6px 12px rgba(0,123,255,0.4);
    }
    .find-work {
      background-color: white; color: #007bff; border: 2px solid #007bff;
    }
    .find-work:hover {
      background-color: #007bff; color: white;
    }
    .user-menu {
      position: relative;
      display: inline-block;
    }
    .user-dropdown {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background: white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border-radius: 8px;
      min-width: 200px;
      z-index: 1000;
    }
    .user-dropdown a {
      display: block;
      padding: 12px 16px;
      text-decoration: none;
      color: #333;
      border-bottom: 1px solid #eee;
    }
    .user-dropdown a:hover {
      background-color: #f8f9fa;
    }
    .user-dropdown a:last-child {
      border-bottom: none;
    }
    .user-menu:hover .user-dropdown {
      display: block;
    }
    @media (max-width: 768px) {
      header {
        padding: 15px 30px; flex-direction: column; gap: 10px;
      }
      .nav-links {
        gap: 20px;
      }
      .hero h1 {
        font-size: 36px;
      }
      .hero p {
        font-size: 16px;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="logo">Helpify</div>
    <nav class="nav-links">
      <a href="index.php">Home</a>
      <a href="#how-it-works">How It Works</a>
      <?php if (isLoggedIn()): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="tasks.php">Tasks</a>
      <?php endif; ?>
    </nav>
    <div class="auth-buttons">
      <?php if (isLoggedIn()): ?>
        <div class="user-menu">
          <a href="#" class="sign-in">Welcome, <?php echo sanitize($_SESSION['fullname']); ?></a>
          <div class="user-dropdown">
            <a href="profile.php">My Profile</a>
            <a href="my-tasks.php">My Tasks</a>
            <a href="messages.php">Messages</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="sign-in">Sign In</a>
        <a href="register.php" class="sign-up">Sign Up</a>
      <?php endif; ?>
    </div>
  </header>

  <section class="hero">
    <h1>Get help with any task in your daily life</h1>
    <p>Post your task and connect with skilled helpers in your area. From home repairs to personal assistance – find help for anything!</p>
    <div class="hero-buttons">
      <?php if (isLoggedIn()): ?>
        <a href="post-task.php" class="post-task">Post a Task</a>
        <a href="find-tasks.php" class="find-work">Find Work</a>
      <?php else: ?>
        <a href="register.php" class="post-task">Post a Task</a>
        <a href="register.php" class="find-work">Find Work</a>
      <?php endif; ?>
    </div>
  </section>

  <section id="how-it-works" class="how-it-works">
    <div class="container">
      <div class="header">
        <h1>How it works</h1>
        <p>
          Get help with your tasks in three simple steps. Whether you need help with home repairs, 
          moving, or any other task, we make it easy to connect with reliable helpers.
        </p>
      </div>
  
      <div class="steps">
        <!-- Step 1 -->
        <div class="step">
          <div class="step-number">01</div>
          <div class="step-visual">
            <div class="account-form">
              <h4>Create Account</h4>
              <div class="form-field"></div>
              <div class="form-field"></div>
              <div class="form-field"></div>
              <button class="signup-btn">Sign up</button>
            </div>
          </div>
          <div class="step-content">
            <h3>Create Your Account</h3>
            <p>
              Sign up in minutes with your email or social media account. Choose whether you want to 
              post tasks or offer your services as a helper. Your profile helps build trust in our community.
            </p>
          </div>
        </div>
  
        <!-- Step 2 -->
        <div class="step">
          <div class="step-number">02</div>
          <div class="step-visual">
            <div class="search-container">
              <div class="search-box">
                <div class="search-input"></div>
                <div class="search-icon">&#128269;</div>
              </div>
            </div>
          </div>
          <div class="step-content">
            <h3>Post or Find Tasks</h3>
            <p>
              Need help? Post your task with details and your budget. Looking for work? Browse available 
              tasks in your area and apply to help. Our smart matching system connects you with the right people.
            </p>
          </div>
        </div>
  
        <!-- Step 3 -->
        <div class="step">
          <div class="step-number">03</div>
          <div class="step-visual">
            <div class="emoji-container">
              <div class="emoji">😊</div>
              <div class="emoji">😟</div>
              <div class="emoji">❤️</div>
            </div>
          </div>
          <div class="step-content">
            <h3>Get Things Done</h3>
            <p>
              Connect with your chosen helper, agree on details, and get your task completed. 
              Our secure payment system and rating system ensure a smooth experience for everyone.
            </p>
          </div>
        </div>
      </div>
    </div>
  
    <style>
      /* Container & base */
      .how-it-works {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #e0f7ff, #d2eaff);
        padding: 60px 20px;
        color: #1a1a1a;
        margin: 0 auto;
      }
      .container {
        max-width: 900px;
        margin: 0 auto;
      }
  
      /* Header */
      .how-it-works .header h1 {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 10px;
        letter-spacing: 0.07em;
        color: #0077cc;
        text-transform: uppercase;
      }
      .how-it-works .header p {
        font-size: 1.25rem;
        color: #004d80cc;
        max-width: 620px;
        margin: 0 auto 50px;
        font-weight: 600;
        line-height: 1.6;
        font-style: italic;
      }
  
      /* Steps container */
      .steps {
        display: flex;
        justify-content: space-between;
        gap: 40px;
        flex-wrap: wrap;
      }
  
      /* Each Step */
      .step {
        background: #ffffffcc;
        border-radius: 20px;
        box-shadow: 0 12px 30px rgba(0, 119, 204, 0.2);
        padding: 30px 35px;
        flex: 1 1 300px;
        display: flex;
        flex-direction: column;
        align-items: center;
        transition: box-shadow 0.35s ease, transform 0.35s ease;
        cursor: default;
      }
      .step:hover {
        box-shadow: 0 18px 40px rgba(0, 119, 204, 0.35);
        transform: translateY(-6px);
      }
  
      /* Step Number */
      .step-number {
        font-size: 3.5rem;
        font-weight: 900;
        color: #0077cc;
        opacity: 0.12;
        align-self: flex-start;
        margin-bottom: 20px;
        font-family: 'Courier New', Courier, monospace;
        user-select: none;
      }
  
      /* Step Visual */
      .step-visual {
        margin-bottom: 25px;
        width: 100%;
        display: flex;
        justify-content: center;
      }
  
      /* Account Form mockup */
      .account-form {
        width: 100%;
        max-width: 220px;
        background: #e6f2ff;
        border-radius: 12px;
        padding: 20px 25px;
        box-shadow: inset 0 0 10px #99c2ff;
        user-select: none;
      }
      .account-form h4 {
        margin-bottom: 15px;
        font-weight: 800;
        color: #0077cc;
        font-size: 1.15rem;
      }
      .form-field {
        height: 12px;
        background: #add8ff;
        border-radius: 6px;
        margin: 10px 0;
        box-shadow: inset 0 1px 3px #7abaff;
      }
      .signup-btn {
        margin-top: 18px;
        background: #0077cc;
        border: none;
        color: white;
        font-weight: 700;
        padding: 10px 0;
        border-radius: 8px;
        width: 100%;
        cursor: pointer;
        transition: background-color 0.3s ease;
        font-size: 1rem;
      }
      .signup-btn:hover {
        background: #005fa3;
      }
  
      /* Search Box mockup */
      .search-container {
        width: 100%;
        max-width: 220px;
      }
      .search-box {
        display: flex;
        align-items: center;
        background: #e6f2ff;
        padding: 10px 15px;
        border-radius: 12px;
        box-shadow: inset 0 0 10px #99c2ff;
        user-select: none;
      }
      .search-input {
        flex-grow: 1;
        height: 16px;
        background: #add8ff;
        border-radius: 8px;
        margin-right: 12px;
        box-shadow: inset 0 1px 4px #7abaff;
      }
      .search-icon {
        font-size: 1.5rem;
        color: #0077cc;
        user-select: none;
      }
  
      /* Emoji container */
      .emoji-container {
        display: flex;
        gap: 18px;
        font-size: 2.5rem;
        user-select: none;
        justify-content: center;
        width: 100%;
        max-width: 220px;
      }
  
      /* Step Content */
      .step-content h3 {
        font-weight: 800;
        font-size: 1.4rem;
        margin-bottom: 15px;
        color: #004d80;
        text-align: center;
      }
      .step-content p {
        color: #004d80cc;
        font-size: 1.05rem;
        line-height: 1.6;
        text-align: center;
        font-weight: 600;
      }
  
      /* Responsive */
      @media (max-width: 900px) {
        .steps {
          flex-direction: column;
          gap: 50px;
        }
        .step {
          max-width: 100%;
        }
      }
    </style>
  </section>

  <section id="features" class="features-floating">
    <div class="container">
      <h2 class="features-title">Why Helpify Stands Out</h2>
      <div class="features-floating-wrapper">
        <div class="feature-card floating-card card-1">
          <div class="feature-icon">🚀</div>
          <h3>Create in Minutes</h3>
          <p>Sign up quickly and start posting or finding tasks in no time.</p>
        </div>
        <div class="feature-card floating-card card-2">
          <div class="feature-icon">🤝</div>
          <h3>Trusted Community</h3>
          <p>Connect with helpers and posters who are verified and rated.</p>
        </div>
        <div class="feature-card floating-card card-3">
          <div class="feature-icon">🔒</div>
          <h3>Secure Payments</h3>
          <p>Our platform protects your transactions with advanced security.</p>
        </div>
        <div class="feature-card floating-card card-4">
          <div class="feature-icon">💬</div>
          <h3>24/7 Support</h3>
          <p>We're always here to help you with any questions or issues.</p>
        </div>
      </div>
    </div>
  
    <style>
      .features-floating {
        background: #fff;
        font-family: 'Poppins', sans-serif;
        padding: 80px 20px 120px;
        margin: 0 auto;
        position: relative;
        overflow: visible;
        color: #004a99;
        text-align: center;
      }
      .features-floating .features-title {
        font-size: 3rem;
        font-weight: 900;
        margin-bottom: 60px;
        letter-spacing: 0.07em;
        position: relative;
        z-index: 10;
        color: #003366;
      }
      .features-floating-wrapper {
        position: relative;
        width: 100%;
        height: 420px;
        max-width: 900px;
        margin: 0 auto;
      }
      .floating-card {
        position: absolute;
        background: #f9faff;
        border-radius: 18px;
        padding: 30px 25px;
        width: 260px;
        box-shadow: 0 20px 40px rgba(0, 123, 255, 0.15);
        transition: transform 0.4s ease, box-shadow 0.3s ease;
        cursor: default;
        user-select: none;
      }
      .floating-card:hover {
        transform: translateY(-15px) scale(1.05);
        box-shadow: 0 30px 60px rgba(0, 123, 255, 0.3);
        z-index: 20;
      }
      .feature-icon {
        font-size: 3.8rem;
        margin-bottom: 20px;
        color: #007bff;
        filter: drop-shadow(0 1px 2px rgba(0, 123, 255, 0.3));
      }
      .floating-card h3 {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 15px;
        color: #0056b3;
      }
      .floating-card p {
        font-weight: 500;
        font-size: 1.05rem;
        line-height: 1.5;
        color: #0066ccaa;
      }
  
      /* Position cards in a staggered floating layout */
      .card-1 {
        top: 0;
        left: 5%;
        box-shadow: 0 20px 50px rgba(0, 123, 255, 0.15);
      }
      .card-2 {
        top: 80px;
        left: 33%;
        box-shadow: 0 20px 50px rgba(0, 123, 255, 0.12);
      }
      .card-3 {
        top: 50px;
        left: 62%;
        box-shadow: 0 20px 50px rgba(0, 123, 255, 0.1);
      }
      .card-4 {
        top: 180px;
        left: 85%;
        box-shadow: 0 20px 50px rgba(0, 123, 255, 0.08);
      }
  
      /* Responsive */
      @media (max-width: 960px) {
        .features-floating-wrapper {
          height: auto;
          display: flex;
          flex-wrap: wrap;
          gap: 30px;
          justify-content: center;
        }
        .floating-card {
          position: relative !important;
          width: 90%;
          max-width: 320px;
          box-shadow: 0 10px 25px rgba(0, 123, 255, 0.1);
          transform: none !important;
        }
      }
    </style>
  </section>

  <footer class="site-footer">
    <div class="footer-container">
      <div class="footer-brand">Helpify</div>
  
      <div class="footer-links">
        <a href="#features">Features</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
      </div>
  
      <div class="footer-social">
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-facebook-f"></i></a>
      </div>
    </div>
  
    <div class="footer-bottom">© 2025 Helpify. All rights reserved.</div>
  
    <style>
      .site-footer {
        background: #f9fbfe;
        padding: 40px 20px 20px;
        font-family: 'Poppins', sans-serif;
        color: #003366;
        border-top: 1px solid #e0e8f0;
        text-align: center;
      }
  
      .footer-container {
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
      }
  
      .footer-brand {
        font-size: 1.8rem;
        font-weight: 800;
        color: #004a99;
      }
  
      .footer-links {
        display: flex;
        gap: 25px;
      }
  
      .footer-links a {
        text-decoration: none;
        color: #0066cc;
        font-weight: 500;
        transition: color 0.3s ease;
      }
  
      .footer-links a:hover {
        color: #003366;
      }
  
      .footer-social a {
        color: #007bff;
        margin: 0 10px;
        font-size: 1.4rem;
        transition: color 0.3s ease;
      }
  
      .footer-social a:hover {
        color: #0056b3;
      }
  
      .footer-bottom {
        margin-top: 30px;
        font-size: 0.95rem;
        color: #666;
      }
  
      @media (max-width: 600px) {
        .footer-container {
          flex-direction: column;
          align-items: center;
          text-align: center;
        }
        .footer-links {
          flex-wrap: wrap;
          justify-content: center;
        }
      }
    </style>
  
    <!-- Add Font Awesome for icons -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  </footer>
</body>
</html>