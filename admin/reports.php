<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$report = $_GET['report'] ?? '';

$students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$available = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn();
$full = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Full'")->fetchColumn();
$active_allocs = $pdo->query("SELECT COUNT(*) FROM allocations WHERE status='Active'")->fetchColumn();
$pending_apps = $pdo->query("SELECT COUNT(*) FROM applications WHERE status='Pending'")->fetchColumn();

$data = [];
$title = '';
$cols = [];

if ($report === 'students') {
    $title = 'Student Registration Report';
    $cols = ['#','Full Name','Matric No','Department','Level','Gender','Phone','Registered'];
    $data = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
} elseif ($report === 'allocations') {
    $title = 'Room Allocation Report';
    $cols = ['#','Student','Matric No','Department','Hostel','Room','Date','Status'];
    $data = $pdo->query("SELECT a.*, s.full_name, s.matric_no, s.department, r.room_number, h.hostel_name FROM allocations a JOIN students s ON a.student_id=s.student_id JOIN rooms r ON a.room_id=r.room_id JOIN hostels h ON r.hostel_id=h.hostel_id ORDER BY a.allocation_date DESC")->fetchAll();
} elseif ($report === 'occupancy') {
    $title = 'Hostel Occupancy Report';
    $cols = ['#','Hostel','Type','Total Rooms','Occupied','Available','Occupancy %'];
    $data = $pdo->query("SELECT h.*, COUNT(r.room_id) as total, SUM(CASE WHEN r.status='Available' THEN 1 ELSE 0 END) as avail FROM hostels h LEFT JOIN rooms r ON h.hostel_id=r.hostel_id GROUP BY h.hostel_id")->fetchAll();
} elseif ($report === 'applications') {
    $title = 'Applications Report';
    $cols = ['#','Student','Matric No','Hostel Applied','Payment Ref','Date','Status'];
    $data = $pdo->query("SELECT a.*, s.full_name, s.matric_no, h.hostel_name FROM applications a JOIN students s ON a.student_id=s.student_id JOIN hostels h ON a.hostel_id=h.hostel_id ORDER BY a.applied_at DESC")->fetchAll();
} elseif ($report === 'payments') {
    $title = 'Payments Records Report';
    $cols = ['#','Student','Matric No','Amount (₦)','Reference','Date','Verified'];
    $data = $pdo->query("SELECT p.*, s.full_name, s.matric_no FROM payments p JOIN students s ON p.student_id=s.student_id ORDER BY p.payment_date DESC")->fetchAll();
} elseif ($report === 'payment_confirmations') {
    $title = 'Payment Confirmation Report';
    $cols = ['#','Student','Matric No','Amount (₦)','Reference','Date','Verified'];
    $data = $pdo->query("SELECT p.*, s.full_name, s.matric_no FROM payments p JOIN students s ON p.student_id=s.student_id WHERE p.verified='Yes' ORDER BY p.payment_date DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
@media print {
    .navbar,.sidebar,.no-print { display:none!important; }
    .wrapper { display:block; }
    .main { padding:0; }
    .print-header { display:block!important; }
}
.print-header { display:none; text-align:center; margin-bottom:20px; }
.print-header h2 { font-size:1.3rem; }
.print-header p { font-size:0.85rem; color:#555; }
</style>
</head>
<body>
<nav class="navbar no-print">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="hostels.php">Hostels</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="allocations.php">Allocations</a></li>
        <li><a href="reports.php" class="active">Reports</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</nav>
<div class="wrapper">
<aside class="sidebar no-print">
    <div class="user-info">
        <div class="avatar">A</div>
        <div class="name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
        <div class="role">Administrator</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="hostels.php">🏨 Manage Hostels</a>
        <a href="students.php">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php" class="active">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title no-print">Reports</div>
    <div class="page-subtitle no-print">Generate and print accommodation reports</div>

    <!-- Summary Cards -->
    <div class="stats-grid no-print" style="margin-bottom:24px;">
        <div class="stat-card"><div class="stat-icon blue">👥</div><div class="stat-info"><div class="value"><?= $students ?></div><div class="label">Students</div></div></div>
        <div class="stat-card"><div class="stat-icon green">🛏</div><div class="stat-info"><div class="value"><?= $active_allocs ?></div><div class="label">Active Allocations</div></div></div>
        <div class="stat-card"><div class="stat-icon orange">🚪</div><div class="stat-info"><div class="value"><?= $available ?></div><div class="label">Available Rooms</div></div></div>
        <div class="stat-card"><div class="stat-icon red">📋</div><div class="stat-info"><div class="value"><?= $pending_apps ?></div><div class="label">Pending Apps</div></div></div>
    </div>

    <!-- Report Buttons -->
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;">
        <a href="?report=students" class="btn btn-info">👥 Student Report</a>
        <a href="?report=allocations" class="btn btn-success">🛏 Allocation Report</a>
        <a href="?report=occupancy" class="btn btn-warning">🏨 Occupancy Report</a>
        <a href="?report=applications" class="btn btn-primary" style="background:#7c3aed;">📋 Applications Report</a>
        <a href="?report=payments" class="btn btn-primary" style="background:#059669;">💰 Payments Report</a>
        <a href="?report=payment_confirmations" class="btn btn-primary" style="background:#047857;">✅ Payment Confirmation Report</a>
    </div>

    <?php if ($report && !empty($data)): ?>
    <div class="print-header">
        <h2>The Polytechnic, Ibadan</h2>
        <p>Computerized Hostel Accommodation Management System</p>
        <h3><?= htmlspecialchars($title) ?></h3>
        <p>Generated on: <?= date('d F Y, h:i A') ?></p>
        <hr>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>📊 <?= htmlspecialchars($title) ?> (<?= count($data) ?> records)</h3>
            <div class="no-print" style="display:flex;gap:8px;">
                <button onclick="window.print()" class="btn btn-info btn-sm">🖨 Print Report</button>
                <a href="reports.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">Clear</a>
            </div>
        </div>
        <div class="table-wrap">
        <table>
            <thead><tr><?php foreach ($cols as $c): ?><th><?= $c ?></th><?php endforeach; ?></tr></thead>
            <tbody>
            <?php foreach ($data as $i => $row): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <?php if ($report === 'students'): ?>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['matric_no']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= htmlspecialchars($row['level']) ?></td>
                    <td><?= htmlspecialchars($row['gender']) ?></td>
                    <td><?= htmlspecialchars($row['phone']) ?></td>
                    <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                <?php elseif ($report === 'allocations'): ?>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['matric_no']) ?></td>
                    <td><?= htmlspecialchars($row['department']) ?></td>
                    <td><?= htmlspecialchars($row['hostel_name']) ?></td>
                    <td>Room <?= htmlspecialchars($row['room_number']) ?></td>
                    <td><?= date('d M Y', strtotime($row['allocation_date'])) ?></td>
                    <td><span class="badge <?= $row['status']==='Active'?'badge-success':'badge-secondary' ?>"><?= $row['status'] ?></span></td>
                <?php elseif ($report === 'occupancy'): ?>
                    <td><?= htmlspecialchars($row['hostel_name']) ?></td>
                    <td><?= htmlspecialchars($row['hostel_type']) ?></td>
                    <td><?= $row['total'] ?></td>
                    <td><?= $row['total'] - ($row['avail'] ?? 0) ?></td>
                    <td><?= $row['avail'] ?? 0 ?></td>
                    <td><?= $row['total'] > 0 ? round((($row['total'] - ($row['avail'] ?? 0)) / $row['total']) * 100) : 0 ?>%</td>
                <?php elseif ($report === 'applications'): ?>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['matric_no']) ?></td>
                    <td><?= htmlspecialchars($row['hostel_name']) ?></td>
                    <td><?= htmlspecialchars($row['payment_ref'] ?? 'N/A') ?></td>
                    <td><?= date('d M Y', strtotime($row['applied_at'])) ?></td>
                    <td><span class="badge <?= $row['status']==='Approved'?'badge-success':($row['status']==='Rejected'?'badge-danger':'badge-warning') ?>"><?= $row['status'] ?></span></td>
                <?php elseif ($report === 'payments'): ?>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['matric_no']) ?></td>
                    <td>₦<?= number_format($row['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['payment_ref']) ?></td>
                    <td><?= date('d M Y', strtotime($row['payment_date'])) ?></td>
                    <td><span class="badge <?= $row['verified']==='Yes'?'badge-success':'badge-warning' ?>"><?= $row['verified']==='Yes'?'Verified':'Pending' ?></span></td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php elseif ($report): ?>
        <div class="alert alert-info">No data found for this report.</div>
    <?php endif; ?>
</main>
</div>
<div class="footer no-print">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
