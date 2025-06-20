<?php
session_start();
require '../config/db.php';

$empId = $_SESSION['user_id'];
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$date = $_GET['date'] ?? '';
$limit = 5;
$offset = ($page - 1) * $limit;

$where = "WHERE assigned_to = ?";
$params = [$empId];

// Add search condition
if ($search !== '') {
    $where .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Add status filter
if ($status !== 'all') {
    $where .= " AND status = ?";
    $params[] = $status;
}

// Add date filter
if (!empty($date)) {
    $where .= " AND DATE(created_at) = ?";
    $params[] = $date;
}

// Count total tasks
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Get paginated results
$sql = "SELECT * FROM tasks $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

if ($tasks): ?>
<ul class="list-group list-group-flush">
    <?php foreach ($tasks as $task): ?>
        <li class="list-group-item">
            <div class="d-flex justify-content-between align-items-start">
                <div class="me-3">
                    <div class="fw-bold"><?= htmlspecialchars($task['title']) ?></div>
                    <small><?= htmlspecialchars($task['description']) ?></small>
                </div>
                <div class="text-end">
                    <span class="badge bg-<?= 
                        $task['status'] === 'completed' ? 'success' : 
                        ($task['status'] === 'in_progress' ? 'warning' : 'secondary')
                    ?> rounded-pill mb-1"><?= $task['status'] ?></span>

                    <?php if ($task['status'] !== 'completed'): ?>
                        <div class="btn-group">
                            <?php if ($task['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-outline-warning" onclick="updateStatus(<?= $task['id'] ?>, 'in_progress')">Start</button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?= $task['id'] ?>, 'completed')">Complete</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            // Fetch last update time for this task by this user
            $lastUpdateStmt = $pdo->prepare("SELECT submitted_at FROM task_updates WHERE task_id = ? AND employee_id = ? ORDER BY submitted_at DESC LIMIT 1");
            $lastUpdateStmt->execute([$task['id'], $_SESSION['user_id']]);
            $lastUpdate = $lastUpdateStmt->fetchColumn();
            $canSubmit = true;

            if ($lastUpdate) {
                $lastTime = strtotime($lastUpdate);
                $currentTime = time();
                $canSubmit = ($currentTime - $lastTime) >= 10800; // 3 hours = 10800 seconds
            }
            ?>

            <!-- Submit Update Form -->
            <?php if ($task['status'] !== 'completed'): ?>
                <?php if ($canSubmit): ?>
                    <form method="POST" action="submit_update.php" class="mt-2">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <textarea name="update_text" class="form-control mb-2" rows="2" placeholder="Write your update..."></textarea>
                        <button type="submit" name="submit_update" class="btn btn-sm btn-primary">Submit Update</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning mt-2 mb-0 p-2 small">
                        You can submit your next update after <?= date("h:i A", $lastTime + 10800) ?>.
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Last updates accordion -->
            <?php
            $updates = $pdo->prepare("SELECT update_text, submitted_at FROM task_updates WHERE task_id = ? ORDER BY submitted_at DESC LIMIT 5");
            $updates->execute([$task['id']]);
            $recent = $updates->fetchAll();
            ?>

            <?php if ($recent): ?>
                <div class="accordion mt-2" id="accordion<?= $task['id'] ?>">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?= $task['id'] ?>">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $task['id'] ?>" aria-expanded="false" aria-controls="collapse<?= $task['id'] ?>">
                                Last Updates
                            </button>
                        </h2>
                        <div id="collapse<?= $task['id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $task['id'] ?>" data-bs-parent="#accordion<?= $task['id'] ?>">
                            <div class="accordion-body">
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent as $u): ?>
                                        <li class="list-group-item">
                                            <?= nl2br(htmlspecialchars($u['update_text'])) ?><br>
                                            <small class="text-muted"><?= date("d M Y, h:i A", strtotime($u['submitted_at'])) ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>

<!-- Pagination -->
<nav class="mt-2">
  <ul class="pagination pagination-sm">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i == $page ? 'active' : '' ?>">
        <button class="page-link" onclick="fetchMyTasks(<?= $i ?>)"><?= $i ?></button>
      </li>
    <?php endfor; ?>
  </ul>
</nav>

<?php else: ?>
<p class="text-muted">No tasks found.</p>
<?php endif; ?>
