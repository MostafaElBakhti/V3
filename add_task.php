<?php
session_start();
require 'db_connection.php'; // replace with your actual DB connector

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_SESSION['user_id']; // ensure user is logged in
    $title = $_POST['title'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $scheduled_time = $_POST['scheduled_time'];
    $budget = $_POST['budget'];

    try {
        $stmt = $pdo->prepare("INSERT INTO tasks (client_id, title, description, location, scheduled_time, budget) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $title, $description, $location, $scheduled_time, $budget]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
