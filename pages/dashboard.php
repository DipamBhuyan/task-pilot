<?php
include('../includes/auth.php');  
require '../config/db.php';
include('../includes/header.php');
include('../includes/navbar.php');
include('../includes/auth_check.php');
?>

<!-- For Attendance section -->
<?php
date_default_timezone_set('Asia/Kolkata');
$now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$cutoff = new DateTime('10:00:00', new DateTimeZone('Asia/Kolkata'));
$isBefore10am = $now < $cutoff;

$employee_ip = $_SERVER['REMOTE_ADDR'];
if ($employee_ip === '::1') {
    $employee_ip = '27.60.32.21'; // for localhost testing
}

$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'office_ip'");
$office_ip = $stmt->fetchColumn();

$today = $now->format('Y-m-d');
$user_id = $_SESSION['user_id'] ?? null;

// Attendance already submitted?
$stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND DATE(created_at) = ?");
$stmt->execute([$user_id, $today]);
$already_attended = $stmt->fetchColumn() > 0;

// Handle Attendance POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_attendance'])) {
        $stmt = $pdo->prepare("INSERT INTO attendance (user_id, created_at, ip_address, status) VALUES (?, NOW(), ?, 'present')");
        $stmt->execute([$user_id, $employee_ip]);

        header("Location: dashboard.php?attendance=success");
        exit;
    }

    if (isset($_POST['special_attendance_request'])) {
        $remark = trim($_POST['remark']);
        if (!empty($remark)) {
            $stmt = $pdo->prepare("INSERT INTO attendance_requests (user_id, requested_date, reason, status, submitted_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([$user_id, $today, $remark]);

            header("Location: dashboard.php?special_attendance_request=success");
            exit;
        }
    }
}
?>

<!-- For Assign New Task Section in Boss dashboard -->
 <?php
// Handle form submission before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = $_POST['assigned_to'];
    $deadline = $_POST['deadline'] ?? null;
    $assigned_by = $_SESSION['user_id'];

    if ($title && $description && $assigned_to && $deadline) {
        $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, deadline) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $assigned_to, $assigned_by, $deadline]);
        $_SESSION['task_success'] = "Task assigned successfully!";
    } else {
        $_SESSION['task_error'] = "Please fill in all fields.";
    }

    // Prevent form resubmission on page reload
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Always fetch employee list
$employees = $pdo->query("SELECT id, name FROM users WHERE role = 'employee'")->fetchAll();
?>

<!-- Daily Work Submission -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_daily_work'])) {
    $work = trim($_POST['daily_work']);
    $employee_id = $_SESSION['user_id'];
    $upload_dir = __DIR__ . '/../uploads/proof_of_work_photos/';
    $photo_filename = null;

    // Handle photo upload
    if (!empty($_FILES['proof_photo']['name'])) {
        $tmp_name = $_FILES['proof_photo']['tmp_name'];
        $original_name = basename($_FILES['proof_photo']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $_SESSION['daily_work_msg'] = "Only JPG, JPEG or PNG files allowed.";
            header("Location: dashboard.php");
            exit;
        }

        // Generate unique filename
        $photo_filename = uniqid('proof_') . '.' . $ext;
        $target_path = $upload_dir . $photo_filename;

        // Resize and compress to under 500KB
        list($width, $height) = getimagesize($tmp_name);
        $image = ($ext === 'png') ? imagecreatefrompng($tmp_name) : imagecreatefromjpeg($tmp_name);

        $new_width = $width;
        $new_height = $height;

        // Resize only if file > 500KB
        if (filesize($tmp_name) > 500 * 1024) {
            $scale = sqrt((500 * 1024) / filesize($tmp_name)); // rough scale factor
            $new_width = $width * $scale;
            $new_height = $height * $scale;
        }

        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

        if ($ext === 'png') {
            imagepng($resized, $target_path, 9);
        } else {
            imagejpeg($resized, $target_path, 75); // Adjust quality if needed
        }

        imagedestroy($image);
        imagedestroy($resized);
    }

    if ($work) {
        $stmt = $pdo->prepare("INSERT INTO daily_work_logs (employee_id, log_text, submitted_at, photo_filename) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([$employee_id, $work, $photo_filename]);
        $_SESSION['daily_work_msg'] = "Your daily work has been submitted!";
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['daily_work_msg'] = "Please enter your work description.";
    }
}
?>


<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
    <a href="../actions/logout.php" class="btn btn-outline-danger">Logout</a>
  </div>

  <?php if ($_SESSION['user_role'] === 'boss'): ?>
    <div class="alert alert-info">You are logged in as <strong>Boss</strong>. You can assign tasks, view updates, and manage everything.</div>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm p-3 border-primary">
                <h5 class="text-dark">📊 Sales & Purchase Summary</h5>

                <?php
                require '../config/db.php';

                $today = date('Y-m-d');

                // Today's totals
                $todayStmt = $pdo->query("
                    SELECT 
                        SUM(sales) AS today_sales, 
                        SUM(purchases) AS today_purchases 
                    FROM sales_purchases 
                    WHERE entry_date = '$today'
                ");
                $todayTotals = $todayStmt->fetch();

                // Aggregate totals
                $aggStmt = $pdo->query("
                    SELECT 
                        SUM(sales) AS total_sales, 
                        SUM(purchases) AS total_purchases 
                    FROM sales_purchases
                ");
                $aggTotals = $aggStmt->fetch();
                ?>

                <!-- Today's Totals -->
                <div class="mb-3 bg-light border-start border-4 border-info p-2 rounded">
                    <h6 class="text-dark fw-bold">🗓️ Today (<?= date('d M Y') ?>)</h6>
                    <p class="mb-1 text-success">Sales: <strong>₹<?= number_format($todayTotals['today_sales'] ?? 0, 2) ?></strong></p>
                    <p class="mb-0 text-danger">Purchases: <strong>₹<?= number_format($todayTotals['today_purchases'] ?? 0, 2) ?></strong></p>
                </div>

                <!-- Divider -->
                <hr class="my-2">

                <!-- Aggregate Totals -->
                <div class="bg-light border-start border-4 border-primary p-2 rounded">
                    <h6 class="text-primary fw-bold">📈 Aggregate</h6>
                    <p class="mb-1 text-success">Total Sales: <strong>₹<?= number_format($aggTotals['total_sales'] ?? 0, 2) ?></strong></p>
                    <p class="mb-0 text-danger">Total Purchases: <strong>₹<?= number_format($aggTotals['total_purchases'] ?? 0, 2) ?></strong></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-3">
            <?php
            // Set timezone to Asia/Kolkata globally for this script
            date_default_timezone_set('Asia/Kolkata');

            // Current date and time in Asia/Kolkata
            $now = date('Y-m-d H:i:s');
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd   = date('Y-m-d 23:59:59');

            // Task: Overdue Count
            $overdueStmt = $pdo->prepare("
                SELECT COUNT(*) FROM tasks
                WHERE assigned_by = ? AND status != 'completed' AND deadline < ?
            ");
            $overdueStmt->execute([$_SESSION['user_id'], $now]);
            $overdueCount = $overdueStmt->fetchColumn();

            // Task: Pending/In-progress Count
            $pendingStmt = $pdo->prepare("
                SELECT COUNT(*) FROM tasks
                WHERE assigned_by = ? AND status IN ('pending', 'in_progress')
            ");
            $pendingStmt->execute([$_SESSION['user_id']]);
            $pendingCount = $pendingStmt->fetchColumn();

            // Events: Today’s Events (using fixed time zone)
            $eventStmt = $pdo->prepare("
                SELECT title, start_date, start_time FROM events
                WHERE CONCAT(start_date, ' ', start_time) >= ? AND CONCAT(start_date, ' ', start_time) <= ?
                ORDER BY start_time ASC
            ");
            $eventStmt->execute([$todayStart, $todayEnd]);
            $todayEvents = $eventStmt->fetchAll();

            // Fetch today's pending special attendance requests
            $todayDate = date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM attendance_requests
                WHERE status = 'pending' AND DATE(submitted_at) = ?
            ");
            $stmt->execute([$todayDate]);
            $pendingRequestCount = $stmt->fetchColumn();

            // Count pending leave applications
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'");
            $stmt->execute();
            $pendingLeaveCount = $stmt->fetchColumn();
            ?>

            <?php if ($overdueCount > 0 || $pendingCount > 0 || count($todayEvents) > 0): ?>
                <div class="card border-warning shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-2">🔔 Notifications</h6>

                        <?php if ($overdueCount > 0): ?>
                            <div><strong><?= $overdueCount ?></strong> task<?= $overdueCount > 1 ? 's are' : ' is' ?> <span class="text-danger">overdue</span>.</div>
                        <?php endif; ?>

                        <?php if ($pendingCount > 0): ?>
                            <div><strong><?= $pendingCount ?></strong> task<?= $pendingCount > 1 ? 's are' : ' is' ?> <span class="text-primary">pending/in-progress</span>.</div>
                        <?php endif; ?>

                        <?php if ($pendingRequestCount > 0): ?>
                            <div>
                                <strong><?= $pendingRequestCount ?></strong> special attendance request<?= $pendingRequestCount > 1 ? 's are' : ' is' ?> pending.
                                <a href="handle_requests.php" class="btn btn-sm btn-outline-primary ms-2">Review</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($pendingLeaveCount > 0): ?>
                            <div>
                                <strong><?= $pendingLeaveCount ?></strong> leave application<?= $pendingLeaveCount > 1 ? 's are' : ' is' ?> pending.
                                <a href="handle_requests.php#leave-section" class="btn btn-sm btn-outline-primary ms-2">Review</a>
                            </div>
                        <?php endif; ?>

                        <?php if (count($todayEvents) > 0): ?>
                            <div class="mt-2">
                                <strong class="text-success">📅 Today's Events:</strong>
                                <ul class="mb-0 small ps-3">
                                    <?php foreach ($todayEvents as $event): ?>
                                        <li>
                                            <strong><?= htmlspecialchars($event['title']) ?></strong> at
                                            <?= date('h:i A', strtotime($event['start_time'])) ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mb-4">
        <?php if ($_SESSION['user_role'] === 'boss'): ?>
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm p-3">
                    <h5>Assign New Task</h5>

                    <?php if (!empty($_SESSION['task_success'])): ?>
                        <div class="alert alert-success"><?= $_SESSION['task_success'] ?></div>
                        <?php unset($_SESSION['task_success']); ?>
                    <?php elseif (!empty($_SESSION['task_error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['task_error'] ?></div>
                        <?php unset($_SESSION['task_error']); ?>
                    <?php endif; ?>

                    <form method="POST" class="mt-2">
                        <div class="mb-2">
                            <input type="text" name="title" class="form-control" placeholder="Task Title" required>
                        </div>
                        <div class="mb-2">
                            <textarea name="description" class="form-control" placeholder="Task Description" rows="2" required></textarea>
                        </div>
                        <div class="mb-2">
                            <select name="assigned_to" class="form-select" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Deadline</label>
                            <input type="datetime-local" name="deadline" class="form-control" required>
                        </div>
                        <input type="hidden" name="assign_task" value="1">
                        <button type="submit" class="btn btn-primary btn-sm">Assign Task</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-md-6 mb-3">
            <div class="card shadow-sm p-3">
            <h5>Recently Assigned Tasks</h5>

            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-4">
                <input type="text" id="search" class="form-control" placeholder="Search by task title or employee...">
                </div>
                <div class="col-md-3">
                <select id="status" class="form-select">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
                </div>
                <div class="col-md-3">
                    <input type="date" id="date_filter" class="form-control">
                </div>
                <div class="col-md-2">
                <button class="btn btn-primary w-100" onclick="fetchTasks(1)">Search</button>
                </div>
            </div>

            <!-- Toast for alert message -->
            <?php if (isset($_SESSION['toast'])): ?>
                <div class="alert alert-<?= $_SESSION['toast']['type'] ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['toast']['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['toast']); ?>
            <?php endif; ?>

            <!-- Task Results -->
            <div id="task-results"></div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm p-3">
                <h5>📅 Calendar & Events</h5>
                <div id="calendar"></div>
                <!-- Event Modal -->
                <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form id="eventForm" class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Event Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="event_id" id="event_id">
                            <div class="mb-3">
                            <label>Title</label>
                            <input type="text" name="title" id="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                            <label>Start Date & Time</label>
                            <input type="datetime-local" name="start" id="start" class="form-control" required>
                            </div>
                            <div class="mb-3">
                            <label>End Date & Time</label>
                            <input type="datetime-local" name="end" id="end" class="form-control">
                            </div>
                            <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" id="description" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button type="button" id="deleteBtn" class="btn btn-danger d-none">Delete</button>
                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

  <?php elseif ($_SESSION['user_role'] === 'employee'): ?>
    <div class="alert alert-warning">You are logged in as <strong>Employee</strong>. Please check your assigned tasks and submit updates.</div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card shadow-sm p-3">
                <h5>My Tasks</h5>

                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="my-search" class="form-control" placeholder="Search tasks...">
                    </div>
                    <div class="col-md-3">
                        <select id="my-status" class="form-select">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="my-date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" onclick="fetchMyTasks(1)">Search</button>
                    </div>
                </div>

                <!-- AJAX Task Results -->
                <div id="my-task-results"></div>
                <!-- Toast for alert message -->
                <?php if (isset($_SESSION['toast'])): ?>
                    <div class="alert alert-<?= $_SESSION['toast']['type'] ?> alert-dismissible fade show" role="alert">
                        <?= $_SESSION['toast']['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['toast']); ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="card border-warning shadow-sm">
                <div class="card-body">
                    <?php
                    // Fetch today's approved/rejected special attendance requests
                    $todayDate = date('Y-m-d');
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM attendance_requests
                        WHERE user_id = ? AND status = 'approved' AND DATE(submitted_at) = ?
                    ");
                    $stmt->execute([$user_id, $todayDate]);
                    $approvedRequestCount = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM attendance_requests
                        WHERE user_id = ? AND status = 'rejected' AND DATE(submitted_at) = ?
                    ");
                    $stmt->execute([$user_id, $todayDate]);
                    $rejectedRequestCount = $stmt->fetchColumn();

                    // Check if any leave application is approved or rejected (and not already seen)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE user_id = ? AND status IN ('approved', 'rejected')");
                    $stmt->execute([$user_id]);
                    $respondedLeaveCount = $stmt->fetchColumn();
                    ?>

                    <h6 class="card-title mb-2">🔔 Notifications</h6>

                    <?php if ($approvedRequestCount > 0): ?>
                        <div>
                            <strong><?= $approvedRequestCount ?></strong> special attendance request<?= $approvedRequestCount > 1 ? 's are' : ' is' ?> approved.
                            <a href="handle_requests.php" class="btn btn-sm btn-outline-primary ms-2">Review</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($rejectedRequestCount > 0): ?>
                        <div>
                            <strong><?= $rejectedRequestCount ?></strong> special attendance request<?= $rejectedRequestCount > 1 ? 's are' : ' is' ?> rejected.
                            <a href="handle_requests.php" class="btn btn-sm btn-outline-primary ms-2">Review</a>
                        </div>
                    <?php endif; ?>

                    <?php if ($respondedLeaveCount > 0): ?>
                        <div>
                            <strong><?= $respondedLeaveCount ?></strong> of your leave application<?= $respondedLeaveCount > 1 ? 's have' : ' has' ?> been responded to.
                            <a href="handle_requests.php" class="btn btn-sm btn-outline-secondary ms-2">View</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Code for attendance -->
            <div class="card shadow-sm p-3 mt-3">
                <?php
                if (isset($_GET['attendance']) && $_GET['attendance'] === 'success') {
                    echo '<div class="alert alert-success">Attendance marked successfully.</div>';
                }

                if (isset($_GET['special_attendance_request']) && $_GET['special_attendance_request'] === 'success') {
                    echo '<div class="alert alert-info">Special attendance request sent to boss.</div>';
                }

                if ($already_attended) {
                    echo '<div class="alert alert-info">You have already submitted attendance today.</div>';
                } elseif ($isBefore10am && $employee_ip === $office_ip) {
                    ?>
                    <form method="POST">
                        <input type="hidden" name="mark_attendance" value="1">
                        <button type="submit" class="btn btn-success">Mark Attendance</button>
                    </form>
                    <?php
                } else {
                    ?>
                    <div class="alert alert-warning">
                        Attendance can't be submitted directly (After 10:00 AM or Not in Office).<br>
                        Please request special attendance with a remark.
                    </div>
                    <form method="POST">
                        <div class="mb-2">
                            <textarea name="remark" class="form-control" placeholder="Enter reason for late attendance... or why are you not in office..." required></textarea>
                        </div>
                        <input type="hidden" name="special_attendance_request" value="1">
                        <button type="submit" class="btn btn-primary">Request Special Attendance</button>
                    </form>
                    <?php
                }
                ?>
            </div>

            <?php if ($_SESSION['user_role'] === 'employee'): ?>
                <div class="card mt-3 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Submit Daily Work</h5>
                        <?php if (!empty($_SESSION['daily_work_msg'])): ?>
                            <div class="alert alert-info"><?= $_SESSION['daily_work_msg']; unset($_SESSION['daily_work_msg']); ?></div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <textarea name="daily_work" class="form-control" rows="3" placeholder="Describe what you worked on today..." required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="proof_photo" class="form-label">Upload Photo (Proof of Work, optional)</label>
                                <input type="file" name="proof_photo" id="proof_photo" accept="image/*" class="form-control">
                            </div>
                            <button type="submit" name="submit_daily_work" class="btn btn-success">Submit</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['user_role'] === 'employee' && $_SESSION['user_id'] === 2): ?>
                <div class="card shadow-sm p-3 mt-3">
                    <h5>Enter Daily Sales & Purchase</h5>

                    <?php
                    require '../config/db.php';
                    $employee_id = $_SESSION['user_id'];

                    // Handle form submission
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sales_purchases'])) {
                        $sales = floatval($_POST['sales']);
                        $purchases = floatval($_POST['purchases']);
                        $entry_date = $_POST['entry_date'] ?? date('Y-m-d');

                        // Check for existing entry
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_purchases WHERE employee_id = ? AND entry_date = ?");
                        $checkStmt->execute([$employee_id, $entry_date]);
                        $exists = $checkStmt->fetchColumn();

                        if ($exists) {
                            echo '<div class="alert alert-warning">You have already submitted values for this date.</div>';
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO sales_purchases (employee_id, sales, purchases, entry_date) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$employee_id, $sales, $purchases, $entry_date]);
                            echo '<div class="alert alert-success">Sales and purchase values submitted successfully.</div>';
                        }
                    }
                    ?>

                    <form method="POST">
                        <div class="mb-2">
                            <label for="sales" class="form-label">Sales Amount</label>
                            <input type="number" step="0.01" name="sales" id="sales" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="purchases" class="form-label">Purchase Amount</label>
                            <input type="number" step="0.01" name="purchases" id="purchases" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label for="entry_date" class="form-label">Entry Date</label>
                            <input type="date" name="entry_date" id="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <button type="submit" name="submit_sales_purchases" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Code for Telecaller account -->
            <?php if ($_SESSION['user_role'] === 'employee' && $_SESSION['user_id'] === 4): ?>
                <div class="card shadow-sm p-3 mt-3">
                    <h5>📞 Follow Up Events Entry for Boss</h5>
                    <form id="telecallerEventForm">
                        <div class="mb-2">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" required>
                        </div>
                        <div class="mb-2">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Start Time</label>
                                <input type="time" name="start_time" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>End Time</label>
                                <input type="time" name="end_time" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-2">Add Follow-Up Event</button>
                        <div id="eventStatus" class="mt-2 text-success" style="display: none;"></div>
                    </form>
                </div>

                <script>
                $('#telecallerEventForm').on('submit', function(e) {
                    e.preventDefault();
                    $.post('../ajax/add_event.php', $(this).serialize(), function(res) {
                        $('#eventStatus').text(res.message).show();
                        $('#telecallerEventForm')[0].reset();
                    }, 'json').fail(() => {
                        $('#eventStatus').text("❌ Failed to add event.").css('color', 'red').show();
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>
  <?php endif; ?>
  
</div>

<?php include('../includes/footer.php'); ?>

