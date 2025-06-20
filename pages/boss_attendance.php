<?php
// File: boss_attendance.php
include('../includes/auth.php');
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'boss') {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

include('../includes/header.php');
include('../includes/navbar.php');
?>

<div class="container mt-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
    <a href="../actions/logout.php" class="btn btn-outline-danger">Logout</a>
  </div>
  <div class="alert alert-info">You are logged in as <strong>Boss</strong>. You can check the attendance of employees here.</div>

  <div class="card shadow border border-primary mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h4 class="mb-0">Employee Attendance Sheet</h4>
      <form action="../ajax/download_attendance.php" method="get" target="_blank" class="d-inline">
        <input type="hidden" name="search" value="" id="downloadSearch">
        <input type="hidden" name="date" value="" id="downloadDate">
        <button class="btn btn-light btn-sm">Download Attendance Sheet</button>
      </form>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <div class="col-md-6">
          <input id="search" type="text" class="form-control" placeholder="Search by employee name">
        </div>
        <div class="col-md-4">
          <input id="dateFilter" type="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" onclick="loadAttendance(1)">Search</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-bordered table-sm">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Employee</th>
              <th>Status</th>
              <th>Remark</th>
              <th>IP Address</th>
              <th>Submitted At</th>
              <th>Approved By</th>
              <th>Approved At</th>
            </tr>
          </thead>
          <tbody id="attendanceTable">
            <tr><td colspan="8" class="text-center">Loading…</td></tr>
          </tbody>
        </table>
      </div>
      <nav><ul id="pagination" class="pagination justify-content-center"></ul></nav>
    </div>
  </div>
</div>

<script>
function loadAttendance(page = 1) {
  $.post('../ajax/fetch_attendance.php', {
    search: $('#search').val(),
    date: $('#dateFilter').val(),
    page: page
  }).done(res => {
    const tbody = $('#attendanceTable').empty();
    $('#pagination').empty();

    if (!res.records || !res.records.length) {
      tbody.append('<tr><td colspan="8" class="text-center">No attendance found.</td></tr>');
      return;
    }

    res.records.forEach((row, idx) => {
      const num = (page - 1) * 20 + idx + 1;
      tbody.append(`
        <tr>
          <td>${num}</td>
          <td>${row.employee_name}</td>
          <td>${row.status}</td>
          <td>${row.remark || '-'}</td>
          <td>${row.ip_address}</td>
          <td>${formatDate(row.created_at)}</td>
          <td>${row.approved_by_name || '-'}</td>
          <td>${formatDate(row.approved_at)}</td>
        </tr>`);
    });

    for (let i = 1; i <= res.totalPages; i++) {
      $('#pagination').append(`
        <li class="page-item ${i === page ? 'active' : ''}">
          <a class="page-link" href="#" onclick="loadAttendance(${i}); return false;">${i}</a>
        </li>`);
    }
  });
}

function formatDate(dateTime) {
  if (!dateTime) return '-';
  const d = new Date(dateTime);
  return d.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
}

$(document).ready(() => {
  loadAttendance();
});
</script>

<?php include('../includes/footer.php'); ?>
