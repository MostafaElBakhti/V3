<?php
// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
?>

<header class="header">
    <nav class="nav-container">
        <a href="index.php" class="logo">TaskGo</a>
        <ul class="nav-links">
            <li><a href="#how-it-works">How it Works</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
        <div class="auth-buttons">
            <?php if ($isLoggedIn): ?>
                <a href="dashboard.php" class="btn btn-login">Dashboard</a>
                <a href="logout.php" class="btn btn-signup">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-login">Login</a>
                <a href="register.php" class="btn btn-signup">Sign Up</a>
            <?php endif; ?>
        </div>
    </nav>
</header> 