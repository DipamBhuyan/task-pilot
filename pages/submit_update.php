<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_update'])) {
    $task_id = $_POST['task_id'];
    $employee_id = $_SESSION['user_id'];
    $update_text = trim($_POST['update_text']);

    if ($update_text === '') {
        $_SESSION['toast'] = [
            'type' => 'danger',
            'message' => 'Update cannot be empty.'
        ];
    } else {
        // Check for last update time
        $checkStmt = $pdo->prepare("SELECT submitted_at FROM task_updates WHERE task_id = ? AND employee_id = ? ORDER BY submitted_at DESC LIMIT 1");
        $checkStmt->execute([$task_id, $employee_id]);
        $lastUpdate = $checkStmt->fetch();

        if ($lastUpdate) {
            $lastTime = strtotime($lastUpdate['submitted_at']);
            $now = time();
            $diffHours = ($now - $lastTime) / 3600;

            if ($diffHours < 3) {
                $remaining = round(3 - $diffHours, 1);
                $_SESSION['toast'] = [
                    'type' => 'warning',
                    'message' => "You can submit your next update after {$remaining} hour(s)."
                ];
                header("Location: ../pages/dashboard.php");
                exit;
            }
        }

        // Submit the update
        $stmt = $pdo->prepare("INSERT INTO task_updates (task_id, employee_id, update_text) VALUES (?, ?, ?)");
        if ($stmt->execute([$task_id, $employee_id, $update_text])) {
            $_SESSION['toast'] = [
                'type' => 'success',
                'message' => 'Update submitted successfully!'
            ];
        } else {
            $_SESSION['toast'] = [
                'type' => 'danger',
                'message' => 'Failed to submit the update.'
            ];
        }
    }
}

// Redirect to dashboard (where toast will be shown)
header("Location: ../pages/dashboard.php");
exit;
