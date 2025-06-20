<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_date'], $_POST['reason'])) {
    $userId = $_SESSION['user_id'];
    $leaveDate = $_POST['leave_date'];
    $reason = trim($_POST['reason']);

    // Prevent duplicate for same date
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE user_id = ? AND leave_date = ?");
    $checkStmt->execute([$userId, $leaveDate]);
    $alreadyExists = $checkStmt->fetchColumn() > 0;

    if ($alreadyExists) {
        header("Location: dashboard.php?leave_status=duplicate");
        exit;
    }

    // Insert into DB
    $stmt = $pdo->prepare("INSERT INTO leave_applications (user_id, leave_date, reason, status) VALUES (?, ?, ?, 'pending')");
    if ($stmt->execute([$userId, $leaveDate, $reason])) {
        header("Location: dashboard.php?leave_status=success");
        exit;
    } else {
        header("Location: dashboard.php?leave_status=error");
        exit;
    }
} else {
    header("Location: dashboard.php");
    exit;
}
