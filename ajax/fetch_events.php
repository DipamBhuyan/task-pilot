<?php
session_start();
require '../config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch events that belong to the logged-in user (i.e., their calendar)
$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ?");
$stmt->execute([$user_id]);

$events = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $events[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'start' => $row['start_date'] . 'T' . $row['start_time'],
        'end' => $row['end_date'] . 'T' . $row['end_time'],
        'description' => $row['description']
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
