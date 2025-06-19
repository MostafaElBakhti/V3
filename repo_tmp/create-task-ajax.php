<?php
require_once 'config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a client
if (!isLoggedIn() || $_SESSION['user_type'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

try {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    $budget = floatval($_POST['budget'] ?? 0);
    
    // Validation
    $errors = [];
    
    if (strlen($title) < 5) {
        $errors[] = 'Task title must be at least 5 characters long.';
    }
    
    if (strlen($description) < 20) {
        $errors[] = 'Task description must be at least 20 characters long.';
    }
    
    if (empty($location)) {
        $errors[] = 'Location is required.';
    }
    
    if (empty($scheduled_date) || empty($scheduled_time)) {
        $errors[] = 'Please select both date and time.';
    }
    
    if ($budget < 10 || $budget > 10000) {
        $errors[] = 'Budget must be between $10 and $10,000.';
    }
    
    // Check if scheduled datetime is not in the past
    if ($scheduled_date && $scheduled_time) {
        $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time . ':00';
        $current_datetime = date('Y-m-d H:i:s');
        
        if ($scheduled_datetime < $current_datetime) {
            $errors[] = 'Scheduled date and time cannot be in the past.';
        }
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        echo json_encode([
            'success' => false, 
            'message' => implode(' ', $errors)
        ]);
        exit();
    }
    
    // Prepare scheduled datetime
    $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time . ':00';
    
    // Insert task into database
    $stmt = $pdo->prepare("
        INSERT INTO tasks (client_id, title, description, location, scheduled_time, budget, status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
    ");
    
    $result = $stmt->execute([
        $_SESSION['user_id'],
        $title,
        $description,
        $location,
        $scheduled_datetime,
        $budget
    ]);
    
    if ($result) {
        $task_id = $pdo->lastInsertId();
        
        // Create notification for helpers (optional - you can implement this later)
        // This would notify helpers about new tasks in their area
        
        echo json_encode([
            'success' => true,
            'message' => 'Task created successfully! It will be visible to helpers immediately.',
            'task_id' => $task_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create task. Please try again.'
        ]);
    }
    
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Task creation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("General error in task creation: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.'
    ]);
}
?>