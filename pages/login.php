<?php session_start(); ?>
<?php 
include('../includes/header.php'); 
include('../includes/navbar.php');
?>
<div class="container d-flex align-items-center justify-content-center vh-100">
  <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
    <h3 class="text-center mb-4">Login to TaskPilot</h3>

    <form action="../actions/login_process.php" method="POST">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Email -->
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <!-- Password with toggle -->
        <div class="mb-3">
            <label>Password</label>
            <div class="input-group">
                <input type="password" name="password" class="form-control" id="login_password" required>
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="login_password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <small class="form-text text-muted" id="loginPasswordHelp">
                Must be 6+ characters with uppercase, lowercase, digit, and special character.
            </small>
        </div>

        <!-- Remember Me Checkbox-->
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">Remember Me</label>
        </div>

        <!-- Submit -->
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Login</button>
        </div>

        <!-- Register Link -->
        <div class="mt-3 text-center">
            <a href="register.php">Don't have an account? Register</a>
        </div>
    </form>
  </div>
</div>

<!-- JS for password toggle and validation -->
<script>
    // Toggle visibility of password
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            if (target) {
                target.type = target.type === 'password' ? 'text' : 'password';
            }
        });
    });

    // Password strength validation
    const loginPass = document.getElementById('login_password');
    const helpText = document.getElementById('loginPasswordHelp');
    if (loginPass && helpText) {
        loginPass.addEventListener('input', () => {
            const val = loginPass.value;
            const valid = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/.test(val);
            helpText.style.color = valid ? 'green' : 'red';
        });
    }
</script>

<?php include('../includes/footer.php'); ?>
