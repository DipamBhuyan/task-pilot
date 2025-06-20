<?php
include('../includes/auth.php');  
require '../config/db.php';
include('../includes/header.php');
include('../includes/navbar.php');
include('../includes/auth_check.php');
?>

<?php

// Access control
if (!isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Fetch today's date
$todayDate = date('Y-m-d');

// Fetch today's special attendance requests
$stmt = $pdo->prepare("
    SELECT r.id, r.user_id, r.reason, r.submitted_at, r.status, u.name
    FROM attendance_requests r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.submitted_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle Approval/Rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];

    if (in_array($action, ['approved', 'rejected'])) {
        // Get request details
        $getStmt = $pdo->prepare("SELECT user_id, reason, submitted_at FROM attendance_requests WHERE id = ?");
        $getStmt->execute([$request_id]);
        $request = $getStmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            $user_id = $request['user_id'];
            $reason = $request['reason'];
            $submitted_at = $request['submitted_at'];

            // Check if attendance already exists for that user on the same date
            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM attendance 
                WHERE user_id = ? AND DATE(created_at) = DATE(?)
            ");
            $checkStmt->execute([$user_id, $submitted_at]);
            $alreadyExists = $checkStmt->fetchColumn() > 0;

            if (!$alreadyExists) {
                // Insert attendance
                $insertStmt = $pdo->prepare("
                    INSERT INTO attendance (user_id, created_at, ip_address, status, remark, approved_by, approved_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $attendanceStatus = $action === 'approved' ? 'present' : 'denied';
                $ipAddress = 'Special Request';
                $insertStmt->execute([
                    $user_id,
                    $submitted_at,
                    $ipAddress,
                    $attendanceStatus,
                    $reason,
                    $_SESSION['user_id']
                ]);
            }

            // Update request status regardless
            $reviewStmt = $pdo->prepare("
                UPDATE attendance_requests
                SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $reviewStmt->execute([$action, $_SESSION['user_id'], $request_id]);

            header("Location: handle_requests.php");
            exit;
        }
    }
}

// Code for search, filter and pagination
date_default_timezone_set('Asia/Kolkata');

// --- Capture Filters ---
$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchName = $_GET['name'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// --- Build WHERE clause dynamically ---
$where = [];
$params = [];

if (!empty($filterDate)) {
    $where[] = "DATE(r.submitted_at) = ?";
    $params[] = $filterDate;
}

if (!empty($filterStatus)) {
    $where[] = "r.status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchName)) {
    $where[] = "u.name LIKE ?";
    $params[] = '%' . $searchName . '%';
}

$whereClause = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// --- Count Total Records for Pagination ---
$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM attendance_requests r
    JOIN users u ON r.user_id = u.id
    $whereClause
");
$countStmt->execute($params);
$totalRequests = $countStmt->fetchColumn();
$totalPages = ceil($totalRequests / $limit);

// --- Fetch Requests with Pagination ---
$dataStmt = $pdo->prepare("
    SELECT r.id, r.user_id, r.reason, r.submitted_at, r.status, u.name
    FROM attendance_requests r
    JOIN users u ON r.user_id = u.id
    $whereClause
    ORDER BY r.submitted_at DESC
    LIMIT $limit OFFSET $offset
");
$dataStmt->execute($params);
$requests = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
// Access control
if (!isset($_SESSION['user_role'])) {
    header("Location: login.php");
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// EMPLOYEE VIEW: Attendance History
if ($_SESSION['user_role'] === 'employee') {
    $userId = $_SESSION['user_id'];

    $where = ["user_id = ?"];
    $params = [$userId];

    if (!empty($filterDate)) {
        $where[] = "DATE(created_at) = ?";
        $params[] = $filterDate;
    }

    if (!empty($filterStatus)) {
        $where[] = "status = ?";
        $params[] = $filterStatus;
    }

    $whereClause = count($where) ? "WHERE " . implode(" AND ", $where) : "";

    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance $whereClause");
    $countStmt->execute($params);
    $totalRows = $countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $limit);

    // Fetch data
    $stmt = $pdo->prepare("SELECT * FROM attendance $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php 
// --- LEAVE APPLICATION MODULE ---

// Capture filters
$leaveDate = $_GET['leave_date'] ?? '';
$leaveStatus = $_GET['leave_status'] ?? '';
$leaveName = $_GET['leave_name'] ?? '';
$leavePage = isset($_GET['leave_page']) ? max(1, intval($_GET['leave_page'])) : 1;
$leaveLimit = 10;
$leaveOffset = ($leavePage - 1) * $leaveLimit;

// Build WHERE clause dynamically
$leaveWhere = [];
$leaveParams = [];

if (!empty($leaveDate)) {
    $leaveWhere[] = "DATE(l.leave_date) = ?";
    $leaveParams[] = $leaveDate;
}
if (!empty($leaveStatus)) {
    $leaveWhere[] = "l.status = ?";
    $leaveParams[] = $leaveStatus;
}
if (!empty($leaveName)) {
    $leaveWhere[] = "u.name LIKE ?";
    $leaveParams[] = "%$leaveName%";
}

$leaveWhereClause = count($leaveWhere) ? "WHERE " . implode(" AND ", $leaveWhere) : "";

// Total records
$leaveCountStmt = $pdo->prepare("
    SELECT COUNT(*) FROM leave_applications l
    JOIN users u ON l.user_id = u.id
    $leaveWhereClause
");
$leaveCountStmt->execute($leaveParams);
$totalLeaves = $leaveCountStmt->fetchColumn();
$totalLeavePages = ceil($totalLeaves / $leaveLimit);

// Fetch Leave Applications
$leaveStmt = $pdo->prepare("
    SELECT l.id, l.user_id, l.leave_date, l.reason, l.status, l.reviewed_by, l.reviewed_at, u.name
    FROM leave_applications l
    JOIN users u ON l.user_id = u.id
    $leaveWhereClause
    ORDER BY l.leave_date DESC
    LIMIT $leaveLimit OFFSET $leaveOffset
");
$leaveStmt->execute($leaveParams);
$leaveApplications = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (isset($_POST['leave_id'], $_POST['leave_action'])) {
        $leaveId = $_POST['leave_id'];
        $leaveAction = $_POST['leave_action'];

        if (in_array($leaveAction, ['approved', 'rejected'])) {
            $leaveUpdateStmt = $pdo->prepare("
                UPDATE leave_applications
                SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $leaveUpdateStmt->execute([$leaveAction, $_SESSION['user_id'], $leaveId]);
            header("Location: handle_requests.php");
            exit;
        }
    }
}
?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
        <a href="../actions/logout.php" class="btn btn-outline-danger">Logout</a>
    </div>

    <?php if ($_SESSION['user_role'] === 'boss'): ?>
        <div class="alert alert-info">You are logged in as <strong>Boss</strong>. You can assign approve or reject special attendance requests and leave application requests.</div>
            
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm p-3 border-primary">
                    <h4 class="mb-4">📋 Special Attendance Requests</h4>

                    <form method="get" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>" placeholder="Filter by Date">
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?= $filterStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $filterStatus == 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $filterStatus == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="name" class="form-control" placeholder="Search by name" value="<?= htmlspecialchars($searchName) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>

                    <?php if (count($requests) === 0): ?>
                        <div class="alert alert-info">No special attendance requests submitted today.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Submitted At</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $req): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($req['name']) ?></td>
                                            <td><?= date('d M Y, h:i A', strtotime($req['submitted_at'])) ?></td>
                                            <td><?= nl2br(htmlspecialchars($req['reason'])) ?></td>
                                            <td>
                                                <?php
                                                    if ($req['status'] === 'pending') {
                                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    } elseif ($req['status'] === 'approved') {
                                                        echo '<span class="badge bg-success">Approved</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Rejected</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                        <button name="action" value="approved" class="btn btn-sm btn-success">Approve</button>
                                                        <button name="action" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <em class="text-muted">Reviewed</em>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                    <a href="dashboard.php" class="btn btn-secondary mt-3">← Back to Dashboard</a>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card shadow-sm p-3 border-success">
                    <h4 class="mb-4">📩 Leave Applications</h4>

                    <form method="get" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="date" name="leave_date" class="form-control" value="<?= htmlspecialchars($leaveDate) ?>" placeholder="Leave Date">
                        </div>
                        <div class="col-md-3">
                            <select name="leave_status" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?= $leaveStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $leaveStatus == 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $leaveStatus == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="leave_name" class="form-control" placeholder="Search by name" value="<?= htmlspecialchars($leaveName) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-success w-100">Filter</button>
                        </div>
                    </form>

                    <?php if (count($leaveApplications) === 0): ?>
                        <div class="alert alert-info">No leave applications found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Employee</th>
                                        <th>Leave Date</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveApplications as $leave): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($leave['name']) ?></td>
                                            <td><?= date('d M Y', strtotime($leave['leave_date'])) ?></td>
                                            <td><?= nl2br(htmlspecialchars($leave['reason'])) ?></td>
                                            <td>
                                                <?php if ($leave['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php elseif ($leave['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($leave['status'] === 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="leave_id" value="<?= $leave['id'] ?>">
                                                        <button name="leave_action" value="approved" class="btn btn-sm btn-success">Approve</button>
                                                        <button name="leave_action" value="rejected" class="btn btn-sm btn-danger">Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <em class="text-muted">Reviewed</em>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalLeavePages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php for ($lp = 1; $lp <= $totalLeavePages; $lp++): ?>
                                    <li class="page-item <?= $lp == $leavePage ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['leave_page' => $lp])) ?>"><?= $lp ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    
    <?php elseif ($_SESSION['user_role'] === 'employee'): ?>
        <div class="alert alert-warning">You are logged in as <strong>Employee</strong>. Below is your attendance history.</div>
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm p-3 border-info">
                    <h4 class="mb-4">My Attendance History</h4>

                    <form method="get" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="present" <?= $filterStatus == 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="requested" <?= $filterStatus == 'requested' ? 'selected' : '' ?>>Requested</option>
                                <option value="denied" <?= $filterStatus == 'denied' ? 'selected' : '' ?>>Denied</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>

                    <?php if (count($attendances) === 0): ?>
                        <div class="alert alert-info">No attendance records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Remark</th>
                                        <th>Approved By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendances as $att): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($att['created_at'])) ?></td>
                                            <td>
                                                <?php
                                                    if ($att['status'] === 'present') echo '<span class="badge bg-success">Present</span>';
                                                    elseif ($att['status'] === 'denied') echo '<span class="badge bg-danger">Denied</span>';
                                                    else echo '<span class="badge bg-warning text-dark">Requested</span>';
                                                ?>
                                            </td>
                                            <td><?= htmlspecialchars($att['remark'] ?? '-') ?></td>
                                            <td>
                                                <?php
                                                    if ($att['approved_by']) {
                                                        $u = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                                                        $u->execute([$att['approved_by']]);
                                                        echo htmlspecialchars($u->fetchColumn());
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <a href="dashboard.php" class="btn btn-secondary mt-3">← Back to Dashboard</a>

                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-success p-3">
                    <h4 class="mb-3 text-success">📆 Apply for Leave</h4>

                    <?php if (isset($_GET['leave_status']) && $_GET['leave_status'] == 'success'): ?>
                        <div class="alert alert-success">Leave application submitted successfully!</div>
                    <?php elseif (isset($_GET['leave_status']) && $_GET['leave_status'] == 'duplicate'): ?>
                        <div class="alert alert-warning">You have already submitted a leave request for this date.</div>
                    <?php elseif (isset($_GET['leave_status']) && $_GET['leave_status'] == 'error'): ?>
                        <div class="alert alert-danger">Something went wrong. Please try again.</div>
                    <?php endif; ?>

                    <form method="POST" action="submit_leave.php" class="row g-3">
                        <div class="col-md-4">
                            <label for="leave_date" class="form-label">Leave Date</label>
                            <input type="date" id="leave_date" name="leave_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-8">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea id="reason" name="reason" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-success">Apply for Leave</button>
                        </div>
                    </form>
                </div>

                <?php
                // Leave Application History Logic (for employees only)
                $userId = $_SESSION['user_id'];

                // Filters
                $leaveDate = $_GET['leave_date'] ?? '';
                $leaveStatus = $_GET['leave_status_filter'] ?? '';
                $reasonSearch = $_GET['reason'] ?? '';
                $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                $limit = 10;
                $offset = ($page - 1) * $limit;

                // Build WHERE clause
                $where = ['user_id = ?'];
                $params = [$userId];

                if (!empty($leaveDate)) {
                    $where[] = 'leave_date = ?';
                    $params[] = $leaveDate;
                }
                if (!empty($leaveStatus)) {
                    $where[] = 'status = ?';
                    $params[] = $leaveStatus;
                }
                if (!empty($reasonSearch)) {
                    $where[] = 'reason LIKE ?';
                    $params[] = "%$reasonSearch%";
                }

                $whereSQL = implode(' AND ', $where);

                // Count total for pagination
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE $whereSQL");
                $countStmt->execute($params);
                $totalRecords = $countStmt->fetchColumn();
                $totalPages = ceil($totalRecords / $limit);

                // Fetch data
                $dataStmt = $pdo->prepare("
                    SELECT leave_date, reason, status, reviewed_at 
                    FROM leave_applications 
                    WHERE $whereSQL 
                    ORDER BY leave_date DESC 
                    LIMIT $limit OFFSET $offset
                ");
                $dataStmt->execute($params);
                $leaveApps = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div class="card mt-3 shadow-sm border-info p-3">
                    <h4 class="mb-3 text-info">🗂️ Leave Application History</h4>

                    <form method="GET" class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="date" name="leave_date" class="form-control" value="<?= htmlspecialchars($leaveDate) ?>" placeholder="Date">
                        </div>
                        <div class="col-md-3">
                            <select name="leave_status_filter" class="form-control">
                                <option value="">All Status</option>
                                <option value="pending" <?= $leaveStatus == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $leaveStatus == 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $leaveStatus == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="reason" class="form-control" placeholder="Search Reason" value="<?= htmlspecialchars($reasonSearch) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-outline-info w-100">Filter</button>
                        </div>
                    </form>

                    <?php if (empty($leaveApps)): ?>
                        <div class="alert alert-warning">No leave records found.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Leave Date</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Reviewed At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaveApps as $leave): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($leave['leave_date'])) ?></td>
                                            <td><?= nl2br(htmlspecialchars($leave['reason'])) ?></td>
                                            <td>
                                                <?php
                                                    if ($leave['status'] === 'pending') {
                                                        echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                    } elseif ($leave['status'] === 'approved') {
                                                        echo '<span class="badge bg-success">Approved</span>';
                                                    } else {
                                                        echo '<span class="badge bg-danger">Rejected</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td><?= $leave['reviewed_at'] ? date('d M Y, h:i A', strtotime($leave['reviewed_at'])) : '<em class="text-muted">Not Reviewed</em>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include('../includes/footer.php'); ?>