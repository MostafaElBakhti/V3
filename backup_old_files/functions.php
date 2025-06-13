<?php
require_once 'config.php';

/**
 * Authenticate user login
 * @param string $email
 * @param string $password
 * @param bool $remember
 * @return array
 */
function authenticateUser($email, $password, $remember = false) {
    global $pdo;
    
    try {
        // Check for too many login attempts
        if (checkLoginAttempts($email)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }

        // Get user by email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fullname'] = $user['fullname'];

            // Update last login time
            $stmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Handle remember me
            if ($remember) {
                createRememberToken($user['id']);
            }

            // Clear login attempts
            clearLoginAttempts($email);

            return ['success' => true, 'user' => $user];
        } else {
            // Login failed
            recordLoginAttempt($email);
            return ['success' => false, 'message' => 'Invalid email or password'];
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during login'];
    }
}

/**
 * Record failed login attempt
 * @param string $email
 */
function recordLoginAttempt($email) {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)");
    $stmt->execute([$email, $ip]);
}

/**
 * Check if too many login attempts
 * @param string $email
 * @return bool
 */
function checkLoginAttempts($email) {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $timeframe = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts 
                          WHERE (email = ? OR ip_address = ?) 
                          AND attempted_at > ?");
    $stmt->execute([$email, $ip, $timeframe]);
    
    return $stmt->fetchColumn() >= 5; // Limit to 5 attempts per 15 minutes
}

/**
 * Clear login attempts
 * @param string $email
 */
function clearLoginAttempts($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE email = ?");
    $stmt->execute([$email]);
}

/**
 * Create remember me token
 * @param int $user_id
 */
function createRememberToken($user_id) {
    global $pdo;
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) 
                          VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $token, $expires]);
    
    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
}

/**
 * Verify remember me token
 * @return bool
 */
function verifyRememberToken() {
    global $pdo;
    
    if (!isset($_COOKIE['remember_token'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    
    $stmt = $pdo->prepare("SELECT u.* FROM users u 
                          JOIN remember_tokens rt ON u.id = rt.user_id 
                          WHERE rt.token = ? AND rt.expires_at > CURRENT_TIMESTAMP 
                          AND u.is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'];
        return true;
    }
    
    return false;
}

/**
 * Logout user
 */
function logoutUser() {
    // Clear session
    session_unset();
    session_destroy();
    
    // Clear remember me cookie and token
    if (isset($_COOKIE['remember_token'])) {
        global $pdo;
        $token = $_COOKIE['remember_token'];
        
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
        $stmt->execute([$token]);
        
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

/**
 * Check if email exists
 * @param string $email
 * @return bool
 */
function emailExists($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Create new user
 * @param string $email
 * @param string $password
 * @param string $fullname
 * @return array
 */
function createUser($email, $password, $fullname) {
    global $pdo;
    
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (email, password, fullname) 
                              VALUES (?, ?, ?)");
        $stmt->execute([$email, $hashedPassword, $fullname]);
        
        return ['success' => true, 'user_id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        error_log("User creation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred during registration'];
    }
}

/**
 * Generate password reset token
 * @param string $email
 * @return array
 */
function generateResetToken($email) {
    global $pdo;
    
    try {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? 
                              WHERE email = ?");
        $stmt->execute([$token, $expires, $email]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'token' => $token];
        }
        return ['success' => false, 'message' => 'Email not found'];
    } catch (Exception $e) {
        error_log("Reset token generation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred'];
    }
}

/**
 * Verify password reset token
 * @param string $token
 * @return bool
 */
function verifyResetToken($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users 
                          WHERE reset_token = ? 
                          AND reset_token_expires > CURRENT_TIMESTAMP");
    $stmt->execute([$token]);
    return $stmt->fetch() !== false;
}

/**
 * Reset password
 * @param string $token
 * @param string $newPassword
 * @return bool
 */
function resetPassword($token, $newPassword) {
    global $pdo;
    
    try {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users 
                              SET password = ?, reset_token = NULL, reset_token_expires = NULL 
                              WHERE reset_token = ? 
                              AND reset_token_expires > CURRENT_TIMESTAMP");
        $stmt->execute([$hashedPassword, $token]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        return false;
    }
} 