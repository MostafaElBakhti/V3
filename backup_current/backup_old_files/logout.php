<?php
require_once 'functions.php';

// Log out the user
logoutUser();

// Redirect to login page
header("Location: login.php");
exit();
?> 