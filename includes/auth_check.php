<?php
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? AND remember_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
    } else {
        // Expired or invalid — clear cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
?>