<?php
session_start();
include('../includes/header.php'); 
include('../includes/navbar.php');
?>
<div class="container d-flex align-items-center justify-content-center vh-100">
  <div class="card shadow p-4" style="max-width: 500px; width: 100%;">
    <h3 class="text-center mb-4">Register for TaskPilot</h3>
    <form action="../actions/register_process.php" method="POST">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="mb-3">
            <label>Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Password</label>
            <div class="input-group">
                <input type="password" name="password" class="form-control" id="password" required>
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <small class="form-text text-muted" id="passwordHelp">
                Must be 6+ characters with uppercase, lowercase, number, and symbol.
            </small>
        </div>

        <div class="mb-3">
            <label>Confirm Password</label>
            <div class="input-group">
                <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                <button type="button" class="btn btn-outline-secondary toggle-password" data-target="confirm_password">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <small class="form-text text-muted text-danger" id="confirmHelp"></small>
        </div>

        <div class="mb-3">
            <label>Role</label>
            <select name="role" class="form-control" required>
                <option value="employee">Employee</option>
                <option value="boss">Boss</option>
            </select>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-success">Register</button>
        </div>

        <div class="mt-3 text-center">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </form>
  </div>
</div>

<script>
  // Toggle password visibility
  document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
      const target = document.getElementById(button.dataset.target);
      target.type = target.type === 'password' ? 'text' : 'password';
    });
  });

  // Password strength indicator
  const password = document.getElementById('password');
  const passwordHelp = document.getElementById('passwordHelp');
  password.addEventListener('input', () => {
    const val = password.value;
    const valid = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).{6,}$/.test(val);
    passwordHelp.style.color = valid ? 'green' : 'red';
  });

  // Confirm password match check
  const confirm = document.getElementById('confirm_password');
  const confirmHelp = document.getElementById('confirmHelp');
  function checkMatch() {
    if (confirm.value !== password.value) {
      confirmHelp.textContent = "Passwords do not match!";
    } else {
      confirmHelp.textContent = "";
    }
  }
  password.addEventListener('input', checkMatch);
  confirm.addEventListener('input', checkMatch);
</script>

<?php include('../includes/footer.php'); ?>
