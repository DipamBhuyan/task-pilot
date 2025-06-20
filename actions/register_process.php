<?php
session_start();
require '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name             = trim($_POST['name']);
    $email            = trim($_POST['email']);
    $password         = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role             = $_POST['role'];

    // Email format check
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Please enter a valid email address.";
        header("Location: ../pages/register.php");
        exit();
    }

    // Email exists check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = "Email already exists!";
        header("Location: ../pages/register.php");
        exit();
    }

    // Password strength check
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/', $password)) {
        $_SESSION['error'] = "Password must be at least 6 characters long and include uppercase, lowercase, digit, and special character.";
        header("Location: ../pages/register.php");
        exit();
    }

    // Confirm password check
    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match!";
        header("Location: ../pages/register.php");
        exit();
    }

    // Hash and insert
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $hashedPassword, $role]);

    $_SESSION['success'] = "Registration successful. Please login.";
    header("Location: ../pages/login.php");
    exit();
}
?>
