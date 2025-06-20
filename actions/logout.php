<?php
session_start();
require '../config/db.php';

if (isset($_SESSION['user_id'])) {
    // Update logout time for the current session
    $stmt = $pdo->prepare("UPDATE user_sessions SET logout_time = NOW() WHERE session_id = ?");
    $stmt->execute([session_id()]);

    // Clear remember_token and expiry in DB
    $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expiry = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Delete remember_token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true); // remove cookie
}

// Destroy session
session_unset();
session_destroy();
header("Location: ../pages/login.php");
exit();
