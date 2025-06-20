<?php
session_start();
require '../config/db.php';

// Allow only boss access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'boss') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$search = trim($_POST['search'] ?? '');
$date = $_POST['date'] ?? date('Y-m-d');
$page = max(1, intval($_POST['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$params = [];
$conditions = [];

// Search filter
if ($search !== '') {
    $conditions[] = "u.name LIKE ?";
    $params[] = "%$search%";
}

// Date filter
if ($date !== '') {
    $conditions[] = "DATE(a.created_at) = ?";
    $params[] = $date;
}

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN users u ON a.user_id = u.id $whereSQL");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Fetch paginated data
$stmt = $pdo->prepare("
    SELECT a.*, u.name AS employee_name, boss.name AS approved_by_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN users boss ON a.approved_by = boss.id
    $whereSQL
    ORDER BY a.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'records' => $records,
    'totalPages' => $totalPages
]);
