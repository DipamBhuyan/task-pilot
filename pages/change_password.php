<?php
include('../includes/auth.php'); 
require_once '../config/db.php';
include('../includes/header.php');
include('../includes/navbar.php');
include('../includes/auth_check.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_password, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/', $new_password)) {
        $errors[] = "Password must be at least 6 characters long and include uppercase, lowercase, digit, and special character.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New password and confirm password do not match.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        $success = "Password changed successfully.";
    }
}
?>

<?php include '../includes/header.php'; ?>
<div class="container py-4">
    <div class="card shadow-sm mt-4 p-4">
        <h3 class="mb-4 text-center">Change Password</h3>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" class="mx-auto" style="max-width: 400px;" id="passwordForm">
            <div class="mb-3 position-relative">
                <label for="current_password" class="form-label">Current Password</label>
                <div class="input-group">
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3 position-relative">
                <label for="new_password" class="form-label">New Password</label>
                <div class="input-group">
                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <small class="text-muted">Min 6 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character</small>
            </div>
            <div class="mb-3 position-relative">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <div class="input-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Password</button>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target);
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });

    // Optional: Password strength checker
    const newPasswordInput = document.getElementById("new_password");
    newPasswordInput.addEventListener("input", () => {
        const val = newPasswordInput.value;
        const hint = document.querySelector(".text-muted");
        if (/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/.test(val)) {
            hint.style.color = "green";
        } else {
            hint.style.color = "red";
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
