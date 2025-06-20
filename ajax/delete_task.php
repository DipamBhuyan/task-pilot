<?php
session_start();
require '../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    if ($_SESSION['user_role'] !== 'boss') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $task_id = $_POST['task_id'];

    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    if ($stmt->execute([$task_id])) {
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
