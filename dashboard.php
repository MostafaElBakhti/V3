<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Redirect to appropriate dashboard based on user type
if ($_SESSION['user_type'] === 'client') {
    redirect('client-dashboard.php');
} else {
    redirect('helper-dashboard.php');
}
?>