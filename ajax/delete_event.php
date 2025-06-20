<?php
session_start();
require '../config/db.php';

$response = ['success' => false];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    // Only allow creator to delete
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND user_id = ? AND created_by = ?");
    $result = $stmt->execute([$id, $user_id, $user_id]);

    $response['success'] = $result;
}

header('Content-Type: application/json');
echo json_encode($response);
