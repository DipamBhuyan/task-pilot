<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'boss') {
    echo '<div class="alert alert-danger">Unauthorized.</div>';
    exit;
}

$bossId = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$tasksPerPage = 5;
$offset = ($page - 1) * $tasksPerPage;

$searchParam = '%' . $search . '%';

// Base SQL
$sql = "
    SELECT t.*, u.name AS employee_name
    FROM tasks t
    JOIN users u ON t.assigned_to = u.id
    WHERE t.assigned_by = ?
";

$params = [$bossId];

// Search filter
if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR u.name LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Status filter
if ($statusFilter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
}

// Date filter
if (!empty($dateFilter)) {
    $sql .= " AND DATE(t.created_at) = ?";
    $params[] = $dateFilter;
}

// Total count
$countSql = "SELECT COUNT(*) FROM ($sql) AS total";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalTasks = $countStmt->fetchColumn();
$totalPages = ceil($totalTasks / $tasksPerPage);

// Add limit & offset for pagination
$sql .= " ORDER BY t.created_at DESC LIMIT $tasksPerPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>

<!-- Render table -->
<?php if ($tasks): ?>
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Title</th>
          <th>Employee</th>
          <th>Status</th>
          <th>Created At</th>
          <th>Deadline</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $task): ?>
          <tr id="task-row-<?= $task['id'] ?>">
            <td><?= htmlspecialchars($task['title']) ?></td>
            <td><?= htmlspecialchars($task['employee_name']) ?></td>
            <td>
              <span class="badge bg-<?= 
                $task['status'] === 'completed' ? 'success' : 
                ($task['status'] === 'in_progress' ? 'warning' : 'secondary')
              ?>">
                <?= htmlspecialchars($task['status']) ?>
              </span>
            </td>
            <td><?= date('d M Y, h:i A', strtotime($task['created_at'])) ?></td>
            <td><?= date('d M Y, h:i A', strtotime($task['deadline'])) ?></td>
            <td>
              <?php if ($_SESSION['user_role'] === 'boss'): ?>
                <button class="btn btn-sm btn-danger" onclick="deleteTask(<?= $task['id'] ?>)">Delete</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <nav>
    <ul class="pagination justify-content-end">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $page == $i ? 'active' : '' ?>">
          <a href="#" class="page-link" onclick="fetchTasks(<?= $i ?>); return false;"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
<?php else: ?>
  <p class="text-muted">No tasks found.</p>
<?php endif; ?>
