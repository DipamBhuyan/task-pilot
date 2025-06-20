<?php
include('../includes/auth.php');
include('../includes/header.php');
require '../config/db.php';

// Only boss can access this page
if ($_SESSION['user_role'] !== 'boss') {
    header("Location: dashboard.php");
    exit();
}

// Handle task submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = $_POST['assigned_to'];
    $assigned_by = $_SESSION['user_id'];

    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $description, $assigned_to, $assigned_by]);

    $success = "Task assigned successfully!";
}

// Fetch employees
$employees = $pdo->query("SELECT id, name FROM users WHERE role = 'employee'")->fetchAll();

// Fetch all tasks assigned by this boss
$stmt = $pdo->prepare("SELECT t.*, u.name AS employee_name FROM tasks t JOIN users u ON t.assigned_to = u.id WHERE t.assigned_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$tasks = $stmt->fetchAll();
?>

<div class="container mt-4">
    <h3>Assign New Task</h3>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <form method="POST" class="mb-4">
        <div class="mb-2">
            <input type="text" name="title" class="form-control" placeholder="Task Title" required>
        </div>
        <div class="mb-2">
            <textarea name="description" class="form-control" rows="3" placeholder="Task Description" required></textarea>
        </div>
        <div class="mb-2">
            <select name="assigned_to" class="form-select" required>
                <option value="">Assign to...</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Assign Task</button>
    </form>

    <h4>Assigned Tasks</h4>
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Assigned To</th>
                <th>Status</th>
                <th>Assigned On</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= htmlspecialchars($task['title']) ?></td>
                    <td><?= htmlspecialchars($task['employee_name']) ?></td>
                    <td><?= $task['status'] ?></td>
                    <td><?= $task['created_at'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include('../includes/footer.php'); ?>
