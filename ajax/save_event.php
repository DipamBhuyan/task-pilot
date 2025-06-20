<?php
session_start();
require '../config/db.php';

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $start = $_POST['start'];
    $end = $_POST['end'] ?? null;
    $description = trim($_POST['description']);
    $created_by = $_SESSION['user_id'];
    $event_id = $_POST['event_id'] ?? '';

    // Extract date and time separately
    $start_date = date('Y-m-d', strtotime($start));
    $start_time = date('H:i:s', strtotime($start));
    $end_date = $end ? date('Y-m-d', strtotime($end)) : $start_date;
    $end_time = $end ? date('H:i:s', strtotime($end)) : $start_time;

    if ($event_id === '') {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO events (title, start_date, start_time, end_date, end_time, description, created_by)
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$title, $start_date, $start_time, $end_date, $end_time, $description, $created_by]);
    } else {
        // Update existing
        $stmt = $pdo->prepare("UPDATE events SET title=?, start_date=?, start_time=?, end_date=?, end_time=?, description=?
                               WHERE id=? AND created_by=?");
        $result = $stmt->execute([$title, $start_date, $start_time, $end_date, $end_time, $description, $event_id, $created_by]);
    }

    $response['success'] = $result;
}

header('Content-Type: application/json');
echo json_encode($response);
