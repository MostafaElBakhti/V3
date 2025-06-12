<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Please log in as a client.']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];

// Get and sanitize form data
$title = sanitize($_POST['title'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$location = sanitize($_POST['location'] ?? '');
$budget = floatval($_POST['budget'] ?? 0);
$scheduled_date = $_POST['scheduled_date'] ?? '';
$scheduled_time = $_POST['scheduled_time'] ?? '';

// Initialize errors array
$errors = [];

// Validation
if (empty($title) || strlen($title) < 5) {
    $errors[] = 'Task title must be at least 5 characters long.';
}

if (empty($description) || strlen($description) < 20) {
    $errors[] = 'Task description must be at least 20 characters long.';
}

if (empty($location)) {
    $errors[] = 'Location is required.';
}

if ($budget < 10) {
    $errors[] = 'Budget must be at least $10.';
}

if ($budget > 10000) {
    $errors[] = 'Budget cannot exceed $10,000.';
}

if (empty($scheduled_date)) {
    $errors[] = 'Scheduled date is required.';
} else {
    // Check if date is not in the past
    $scheduled_datetime = DateTime::createFromFormat('Y-m-d', $scheduled_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($scheduled_datetime < $today) {
        $errors[] = 'Scheduled date cannot be in the past.';
    }
}

if (empty($scheduled_time)) {
    $errors[] = 'Scheduled time is required.';
}

// Create scheduled datetime
if (empty($errors)) {
    $scheduled_datetime_str = $scheduled_date . ' ' . $scheduled_time;
    $scheduled_datetime_obj = DateTime::createFromFormat('Y-m-d H:i', $scheduled_datetime_str);
    
    if (!$scheduled_datetime_obj) {
        $errors[] = 'Invalid date/time format.';
    } else {
        // Check if datetime is not in the past
        $now = new DateTime();
        if ($scheduled_datetime_obj < $now) {
            $errors[] = 'Scheduled date and time cannot be in the past.';
        }
    }
}

// Return validation errors if any
if (!empty($errors)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Validation failed: ' . implode(' ', $errors)
    ]);
    exit;
}

// Create task if no errors
try {
    $stmt = $pdo->prepare("
        INSERT INTO tasks (client_id, title, description, location, scheduled_time, budget, status) 
        VALUES (?, ?, ?, ?, ?, ?, 'open')
    ");
    
    if ($stmt->execute([$user_id, $title, $description, $location, $scheduled_datetime_str, $budget])) {
        $task_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully! Your task has been posted and helpers can now apply.',
            'task_id' => $task_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create task. Please try again.'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again.'
    ]);
}
?>