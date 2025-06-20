<?php
include('../includes/auth.php');
require '../config/db.php';

// 🚫 Unauthorized access check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ✅ AJAX handler: must run before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'task';
    $search = trim($_POST['search'] ?? '');
    $dateFilter = $_POST['dateFilter'] ?? '';
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $conditions = [];
    $params = [];

    if ($type === 'task') {
        $conditions[] = "t.status != 'completed'";
        if ($search !== '') {
            $conditions[] = "(u.name LIKE ? OR t.title LIKE ? OR tu.update_text LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($dateFilter !== '') {
            $conditions[] = "DATE(tu.submitted_at) = ?";
            $params[] = $dateFilter;
        }
        $whereSQL = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM task_updates tu JOIN tasks t ON tu.task_id = t.id JOIN users u ON tu.employee_id = u.id $whereSQL");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalRows / $limit);

        $stmt = $pdo->prepare("SELECT tu.update_text, tu.submitted_at, t.title AS task_title, t.created_at AS assigned_at, t.deadline, u.name AS employee_name FROM task_updates tu JOIN tasks t ON tu.task_id = t.id JOIN users u ON tu.employee_id = u.id $whereSQL ORDER BY tu.submitted_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode(['updates' => $updates, 'totalPages' => $totalPages]);
        exit;
    } elseif ($type === 'daily') {
        if ($search !== '') {
            $conditions[] = "(u.name LIKE ? OR dw.log_text LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($dateFilter !== '') {
            $conditions[] = "DATE(dw.submitted_at) = ?";
            $params[] = $dateFilter;
        }
        $whereSQL = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_work_logs dw JOIN users u ON dw.employee_id = u.id $whereSQL");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalRows / $limit);

        $stmt = $pdo->prepare("SELECT dw.log_text, dw.photo_filename, dw.submitted_at, u.name AS employee_name FROM daily_work_logs dw JOIN users u ON dw.employee_id = u.id $whereSQL ORDER BY dw.submitted_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode(['logs' => $logs, 'totalPages' => $totalPages]);
        exit;
    } elseif ($type === 'my_daily' && $_SESSION['user_role'] === 'employee') {
        $conditions[] = "dw.employee_id = ?";
        $params[] = $_SESSION['user_id'];

        if ($dateFilter !== '') {
            $conditions[] = "DATE(dw.submitted_at) = ?";
            $params[] = $dateFilter;
        }

        $whereSQL = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_work_logs dw $whereSQL");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalRows / $limit);

        $stmt = $pdo->prepare("SELECT log_text, submitted_at, photo_filename FROM daily_work_logs dw $whereSQL ORDER BY submitted_at DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode(['logs' => $logs, 'totalPages' => $totalPages]);
        exit;
    }
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<?php if ($_SESSION['user_role'] === 'boss'): ?>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <a href="../actions/logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
    <div class="alert alert-info">You are logged in as <strong>Boss</strong>. You can check the task updates and daily work logs of employees.</div>

    <div class="card shadow border border-primary mb-4">
      <div class="card-header bg-primary text-white">
        <h4>Assigned Task Updates</h4>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6"><input id="search" type="text" class="form-control" placeholder="Search by employee, task, update..."></div>
          <div class="col-md-4"><input id="dateFilter" type="date" class="form-control"></div>
          <div class="col-md-2"><button class="btn btn-primary w-100" onclick="loadUpdates(1)">Search</button></div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead class="table-dark">
              <tr><th>#</th><th>Employee</th><th>Task</th><th>Assigned</th><th>Deadline</th><th>Update</th><th>Submitted At</th></tr>
            </thead>
            <tbody id="updatesTable"><tr><td colspan="7" class="text-center">Loading…</td></tr></tbody>
          </table>
        </div>
        <nav><ul id="pagination" class="pagination justify-content-center"></ul></nav>
      </div>
    </div>

    <div class="card shadow border border-secondary">
      <div class="card-header bg-secondary text-white">
        <h4>Daily Work Logs</h4>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6"><input id="dailySearch" type="text" class="form-control" placeholder="Search by employee or work..."></div>
          <div class="col-md-4"><input id="dailyDateFilter" type="date" class="form-control"></div>
          <div class="col-md-2"><button class="btn btn-dark w-100" onclick="loadDailyLogs(1)">Search</button></div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead class="table-dark">
              <tr><th>#</th><th>Employee</th><th>Work Done</th><th>Proof</th><th>Submitted At</th></tr>
            </thead>
            <tbody id="dailyLogsTable"><tr><td colspan="4" class="text-center">Loading…</td></tr></tbody>
          </table>
        </div>
        <nav><ul id="dailyPagination" class="pagination justify-content-center"></ul></nav>
      </div>
    </div>
  </div>
<?php endif;?>

<?php if ($_SESSION['user_role'] === 'employee'): ?>
  <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <a href="../actions/logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
    <div class="alert alert-warning">You are logged in as <strong>Employee</strong>. You can check your daily work logs.</div>

    <div class="card shadow border border-primary">
      <div class="card-header bg-primary text-white">
        <h4>My Daily Work Logs</h4>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-3"><input id="empDateFilter" type="date" class="form-control"></div>
          <div class="col-md-7"></div>
          <div class="col-md-2"><button class="btn btn-primary w-100" onclick="loadMyLogs(1)">Check</button></div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead class="table-dark">
              <tr><th>#</th><th>Work Done</th><th>Proof</th><th>Submitted At</th></tr>
            </thead>
            <tbody id="myLogsTable"><tr><td colspan="4" class="text-center">Loading…</td></tr></tbody>
          </table>
        </div>
        <nav><ul id="myPagination" class="pagination justify-content-center"></ul></nav>
      </div>
    </div>
  </div>
<?php endif; ?>

<script>
  function loadUpdates(page = 1) {
    $.post('task_updates.php', {
      type: 'task',
      search: $('#search').val(),
      dateFilter: $('#dateFilter').val(),
      page: page
    }).done(res => {
      const tbody = $('#updatesTable').empty();
      $('#pagination').empty();

      if (!res.updates || !res.updates.length) {
        tbody.append('<tr><td colspan="7" class="text-center">No updates found.</td></tr>');
        return;
      }

      res.updates.forEach((row, idx) => {
        const num = (page - 1) * 20 + idx + 1;
        tbody.append(`
          <tr>
            <td>${num}</td>
            <td>${row.employee_name}</td>
            <td>${row.task_title}</td>
            <td>${formatDate(row.assigned_at)}</td>
            <td>${formatDate(row.deadline)}</td>
            <td>${row.update_text.replace(/\n/g, '<br>')}</td>
            <td>${formatDate(row.submitted_at)}</td>
          </tr>`);
      });

      for (let i = 1; i <= res.totalPages; i++) {
        $('#pagination').append(`
          <li class="page-item ${i===page?'active':''}"><a class="page-link" href="#" onclick="loadUpdates(${i}); return false;">${i}</a></li>`);
      }
    }).fail(() => alert('Error fetching updates.'));
  }

  function loadDailyLogs(page = 1) {
    $.post('task_updates.php', {
      type: 'daily',
      search: $('#dailySearch').val(),
      dateFilter: $('#dailyDateFilter').val(),
      page: page
    }).done(res => {
      const tbody = $('#dailyLogsTable').empty();
      $('#dailyPagination').empty();

      if (!res.logs || !res.logs.length) {
        tbody.append('<tr><td colspan="4" class="text-center">No logs found.</td></tr>');
        return;
      }

      res.logs.forEach((row, idx) => {
        const num = (page - 1) * 20 + idx + 1;
        const proof = row.photo_filename ? `<a href="../uploads/proof_of_work_photos/${row.photo_filename}" target="_blank"><img src="../uploads/proof_of_work_photos/${row.photo_filename}" alt="Proof" style="max-width: 100px; max-height: 80px;"></a>` : 'N/A';
        tbody.append(`
          <tr>
            <td>${num}</td>
            <td>${row.employee_name}</td>
            <td>${row.log_text.replace(/\n/g, '<br>')}</td>
            <td>${proof}</td>
            <td>${formatDate(row.submitted_at)}</td>
          </tr>`);
      });

      for (let i = 1; i <= res.totalPages; i++) {
        $('#dailyPagination').append(`
          <li class="page-item ${i===page?'active':''}"><a class="page-link" href="#" onclick="loadDailyLogs(${i}); return false;">${i}</a></li>`);
      }
    }).fail(() => alert('Error fetching logs.'));
  }

  function formatDate(dtStr) {
    const d = new Date(dtStr);
    return d.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
  }

  // If the user is an employee, load their own daily logs
  function loadMyLogs(page = 1) {
    $.post('task_updates.php', {
      type: 'my_daily',
      dateFilter: $('#empDateFilter').val(),
      page: page
    }).done(res => {
      const tbody = $('#myLogsTable').empty();
      $('#myPagination').empty();

      if (!res.logs || !res.logs.length) {
        tbody.append('<tr><td colspan="4" class="text-center">No logs found.</td></tr>');
        return;
      }

      res.logs.forEach((row, idx) => {
        const num = (page - 1) * 20 + idx + 1;
        const proof = row.photo_filename ? `<a href="../uploads/proof_of_work_photos/${row.photo_filename}" target="_blank"><img src="../uploads/proof_of_work_photos/${row.photo_filename}" alt="Proof" style="max-width: 100px; max-height: 80px;"></a>` : 'N/A';
        tbody.append(`
          <tr>
            <td>${num}</td>
            <td>${row.log_text.replace(/\n/g, '<br>')}</td>
            <td>${proof}</td>
            <td>${formatDate(row.submitted_at)}</td>
          </tr>`);
      });

      for (let i = 1; i <= res.totalPages; i++) {
        $('#myPagination').append(`
          <li class="page-item ${i===page?'active':''}">
            <a class="page-link" href="#" onclick="loadMyLogs(${i}); return false;">${i}</a>
          </li>`);
      }
    }).fail(() => alert('Error loading your daily logs.'));
  }

  $(document).ready(() => {
    loadUpdates();
    loadDailyLogs();
    <?php if ($_SESSION['user_role'] === 'employee'): ?>
      loadMyLogs();
    <?php endif; ?>
  });

</script>

<?php include('../includes/footer.php'); ?>
