<?php
include('../includes/auth.php'); 
require_once '../config/db.php';
include('../includes/header.php');
include('../includes/navbar.php');
include('../includes/auth_check.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../pages/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$profile_success = $profile_error = $password_success = $password_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Profile info update
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $designation = trim($_POST['designation']);

    if (!empty($name) && !empty($email)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, designation = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $email, $designation, $user_id]);
        $_SESSION['username'] = $name;
        $profile_success = "Profile updated successfully.";
    } else {
        $profile_error = "Name and email cannot be empty.";
    }

    // Password change
    if (!empty($_POST['new_password']) || !empty($_POST['current_password']) || !empty($_POST['confirm_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            $password_error = "Current password is incorrect.";
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/', $new_password)) {
            $password_error = "Password must be at least 6 characters long and include uppercase, lowercase, digit, and special character.";
        } elseif ($new_password !== $confirm_password) {
            $password_error = "New password and confirm password do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $password_success = "Password changed successfully.";
        }
    }

    // Profile picture upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $targetDir = '../uploads/profile_pics/';
        $filename = $user_id . '_' . basename($_FILES['profile_image']['name']);
        $targetFile = $targetDir . $filename;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFile)) {
            $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $stmt->execute([$filename, $user_id]);
        }
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT name, email, role, designation, profile_image FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Completion logic
$completion = 0;
$completion += !empty($user['name']) ? 20 : 0;
$completion += !empty($user['email']) ? 20 : 0;
$completion += !empty($user['designation']) ? 20 : 0;
$completion += !empty($user['profile_image']) ? 20 : 0;
$completion += 20; // Assume password already set
?>

<div class="container mt-4">
    <div class="row justify-content-center mb-4">
        <?php if (!empty($user['profile_image'])): ?>
            <div class="text-center">
                <img src="../uploads/profile_pics/<?= htmlspecialchars($user['profile_image']) ?>"
                    class="rounded-circle shadow-lg border border-3 border-warning"
                    style="width: 130px; height: 130px; object-fit: cover; transition: transform 0.3s;"
                    onmouseover="this.style.transform='scale(1.05)'"
                    onmouseout="this.style.transform='scale(1)'">
            </div>
        <?php endif; ?>
    </div>

    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-dark text-white text-center">
                    <h5 class="mb-0">My Profile</h5>
                </div>
                <div class="card-body">

                    <!-- PROFILE ALERTS -->
                    <?php if (!empty($profile_success)): ?>
                        <div class="alert alert-success"><?= $profile_success ?></div>
                    <?php elseif (!empty($profile_error)): ?>
                        <div class="alert alert-danger"><?= $profile_error ?></div>
                    <?php endif; ?>

                    <!-- PASSWORD ALERTS -->
                    <?php if (!empty($password_success)): ?>
                        <div class="alert alert-success"><?= $password_success ?></div>
                    <?php elseif (!empty($password_error)): ?>
                        <div class="alert alert-danger"><?= $password_error ?></div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Profile Completion: <?= $completion ?>%</label>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $completion ?>%"></div>
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <!-- NAME -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>

                        <!-- EMAIL -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <!-- DESIGNATION -->
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" name="designation" value="<?= htmlspecialchars($user['designation']) ?>">
                        </div>

                        <!-- PROFILE IMAGE -->
                        <div class="mb-3">
                            <label class="form-label">Upload Profile Picture</label>
                            <input type="file" class="form-control" name="profile_image">
                        </div>

                        <hr>

                        <!-- CURRENT PASSWORD -->
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="current_password" id="current_password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="current_password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>

                        <!-- NEW PASSWORD -->
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="new_password" id="new_password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="new_password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                            <small class="form-text text-muted" id="passwordHelp">
                                Must be 6+ characters with uppercase, lowercase, digit, and special character.
                            </small>
                        </div>

                        <!-- CONFIRM PASSWORD -->
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>

                        <!-- ACTION BUTTONS -->
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                        <a href="dashboard.php" class="btn btn-secondary ms-2">Back</a>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS SCRIPT SECTION -->
<script>
    // Toggle visibility of password fields
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (target) {
                target.type = target.type === 'password' ? 'text' : 'password';
            }
        });
    });

    // Password strength color feedback
    const newPass = document.getElementById('new_password');
    const helpText = document.getElementById('passwordHelp');
    if (newPass && helpText) {
        newPass.addEventListener('input', () => {
            const val = newPass.value;
            const valid = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/.test(val);
            helpText.style.color = valid ? 'green' : 'red';
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
