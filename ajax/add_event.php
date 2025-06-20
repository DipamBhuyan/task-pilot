<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee' || $_SESSION['user_id'] !== 4) {
    http_response_code(403);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

$title = $_POST['title'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$description = $_POST['description'] ?? '';

// 🔐 Validate inputs (basic)
if (!$title || !$start_date || !$start_time || !$end_date || !$end_time) {
    http_response_code(400);
    echo json_encode(['message' => 'Missing fields']);
    exit;
}

// ✅ Insert into events table for the BOSS (e.g., user_id = 1)
$stmt = $pdo->prepare("INSERT INTO events (user_id, title, start_date, end_date, start_time, end_time, description, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$success = $stmt->execute([
    1, // 👈 Boss's user_id (replace with dynamic if needed)
    $_POST['title'],
    $_POST['start_date'],
    $_POST['end_date'],
    $_POST['start_time'],
    $_POST['end_time'],
    $_POST['description'],
    $_SESSION['user_id'] // Telecaller’s ID
]);


if ($success) {
    echo json_encode(['message' => '✅ Event added to boss’s calendar']);
} else {
    http_response_code(500);
    echo json_encode(['message' => 'Database error']);
}
