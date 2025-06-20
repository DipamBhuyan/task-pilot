<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-warning" href="dashboard.php">TaskPilot</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : '' ?>" href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'handle_requests.php' ? ' active' : '' ?>" href="handle_requests.php">Requests</a>
        </li>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'boss'): ?>
          <li class="nav-item">
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'task_updates.php' ? ' active' : '' ?>" href="task_updates.php">Task Updates</a>
          </li>
          <li>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'boss_attendance.php' ? ' active' : '' ?>" href="boss_attendance.php">Attendance</a>
          </li>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'employee'): ?>
          <li>
            <a class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'task_updates.php' ? ' active' : '' ?>" href="task_updates.php">Daily Work Logs</a>
          </li>
        <?php endif;?>
      </ul>

      <ul class="navbar-nav mb-2 mb-lg-0">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i> <?= $_SESSION['username'] ?? 'User' ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
            <li><a class="dropdown-item" href="change_password.php">Change Password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a href="../actions/logout.php" class="dropdown-item text-danger">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
