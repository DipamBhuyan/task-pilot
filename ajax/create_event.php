<?php
session_start();
require '../config/db.php';

$data = json_decode(file_get_contents('php://input'), true);

$title = trim($data['title']);
$start_date = $data['start'];
$created_by = $_SESSION['user_id'];

if ($title && $start_date) {
    $stmt = $pdo->prepare("INSERT INTO events (title, user_id, start_date, created_by) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$title, $_SESSION['user_id'], $start_date, $created_by]);

    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false]);
}
