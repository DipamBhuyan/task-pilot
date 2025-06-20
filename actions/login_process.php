<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // Check if the user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Login successful — store user info in session
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            // Optional: success message (if you want to show one after login)
            $_SESSION['success'] = "Login successful!";

            // Log session details to database
            $sessionId  = session_id();
            $ipAddress  = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
            $userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';

            $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $sessionId, $ipAddress, $userAgent]);

            // Handle "Remember Me"
            if (!empty($_POST['remember'])) {
                $token = bin2hex(random_bytes(32));  // Secure random token
                $expiry = date('Y-m-d H:i:s', strtotime('+30 days')); // 30 days validity

                // Save to database
                $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expiry = ? WHERE id = ?");
                $stmt->execute([$token, $expiry, $user['id']]);

                // Set cookie (expires in 30 days)
                setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true); // HTTP-only
            }

            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            // Login failed
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: ../pages/login.php");
            exit();
        }

    } catch (Exception $e) {
        // Handle unexpected errors (e.g., DB down)
        $_SESSION['error'] = "An unexpected error occurred. Please try again.";
        error_log("Login error: " . $e->getMessage());
        header("Location: ../pages/login.php");
        exit();
    }
} else {
    // Direct access (not via POST)
    $_SESSION['error'] = "Invalid access method.";
    header("Location: ../pages/login.php");
    exit();
}
