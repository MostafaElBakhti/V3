<?php
// Authentication and session helper functions

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
} 