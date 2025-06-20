<?php
session_start();
require '../config/db.php'; // Ensure $pdo is properly initialized here

header('Content-Type: application/json');

// Check for valid session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$taskId = $input['task_id'] ?? null;
$newStatus = $input['status'] ?? '';

// Validate input
$allowedStatuses = ['in_progress', 'completed'];
if (!$taskId || !in_array($newStatus, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Check if the task belongs to the logged-in employee
$stmt = $pdo->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
$stmt->execute([$taskId, $_SESSION['user_id']]);
$task = $stmt->fetch();

if (!$task) {
    echo json_encode(['success' => false, 'message' => 'Task not found or unauthorized.']);
    exit;
}

// Update the status
$updateStmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ?");
if ($updateStmt->execute([$newStatus, $taskId])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
