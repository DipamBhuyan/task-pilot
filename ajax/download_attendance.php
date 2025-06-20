<?php
session_start();
require '../config/db.php';

// Only boss can download
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'boss') {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

// Get filters
$search = trim($_GET['search'] ?? '');
$date = $_GET['date'] ?? '';

// Build query
$params = [];
$conditions = [];

if ($search !== '') {
    $conditions[] = "u.name LIKE ?";
    $params[] = "%$search%";
}
if ($date !== '') {
    $conditions[] = "DATE(a.created_at) = ?";
    $params[] = $date;
}

$whereSQL = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Fetch data
$stmt = $pdo->prepare("
    SELECT a.*, u.name AS employee_name, boss.name AS approved_by_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN users boss ON a.approved_by = boss.id
    $whereSQL
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Send headers for CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_sheet.csv"');

// Open output buffer
$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['Employee Name', 'IP Address', 'Status', 'Remark', 'Submitted At', 'Approved By', 'Approved At']);

// Write rows
foreach ($rows as $row) {
    fputcsv($output, [
        $row['employee_name'],
        $row['ip_address'],
        ucfirst($row['status']),
        $row['remark'],
        date('Y-m-d H:i', strtotime($row['created_at'])),
        $row['approved_by_name'] ?? 'Pending',
        $row['approved_at'] ? date('Y-m-d H:i', strtotime($row['approved_at'])) : 'Pending'
    ]);
}

fclose($output);
exit;
