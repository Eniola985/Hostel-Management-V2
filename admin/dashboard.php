<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$hostels = $pdo->query("SELECT COUNT(*) FROM hostels")->fetchColumn();
$rooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$available = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status='Available'")->fetchColumn();
$allocations = $pdo->query("SELECT COUNT(*) FROM allocations WHERE status='Active'")->fetchColumn();
$pending = $pdo->query("SELECT COUNT(*) FROM applications WHERE status='Pending'")->fetchColumn();

$recent = $pdo->query("SELECT a.*, s.full_name, s.matric_no, r.room_number, h.hostel_name 
    FROM allocations a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN rooms r ON a.room_id = r.room_id 
    JOIN hostels h ON r.hostel_id = h.hostel_id 
    ORDER BY a.allocation_date DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Hostel Management</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php" class="active">Dashboard</a></li>
        <li><a href="hostels.php">Hostels</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="allocations.php">Allocations</a></li>
        <li><a href="reports.php">Reports</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</nav>
<div class="wrapper">
    <aside class="sidebar">
        <div class="user-info">
            <div class="avatar">A</div>
            <div class="name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
            <div class="role">Administrator</div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">Main Menu</div>
            <a href="dashboard.php" class="active">🏠 Dashboard</a>
            <a href="hostels.php">🏨 Manage Hostels</a>
            <a href="students.php">👥 Students</a>
            <div class="nav-section">Operations</div>
            <a href="applications.php">📋 Applications <?php if($pending > 0): ?><span class="badge badge-warning"><?= $pending ?></span><?php endif; ?></a>
            <a href="allocations.php">🛏 Allocations</a>
            <a href="payments.php">💰 Payments</a>
            <div class="nav-section">Reports</div>
            <a href="reports.php">📊 Generate Reports</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </aside>
    <main class="main">
        <div class="page-title">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?> 👋</div>
        <div class="page-subtitle">Here is a summary of hostel accommodation activities</div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-info">
                    <div class="value"><?= $students ?></div>
                    <div class="label">Total Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🏨</div>
                <div class="stat-info">
                    <div class="value"><?= $hostels ?></div>
                    <div class="label">Hostels</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">🚪</div>
                <div class="stat-info">
                    <div class="value"><?= $available ?></div>
                    <div class="label">Available Rooms</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🛏</div>
                <div class="stat-info">
                    <div class="value"><?= $allocations ?></div>
                    <div class="label">Active Allocations</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">📋</div>
                <div class="stat-info">
                    <div class="value"><?= $pending ?></div>
                    <div class="label">Pending Applications</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">🏠</div>
                <div class="stat-info">
                    <div class="value"><?= $rooms ?></div>
                    <div class="label">Total Rooms</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Recent Allocations</h3>
                <a href="allocations.php" class="btn btn-info btn-sm">View All</a>
            </div>
            <div class="table-wrap">
                <?php if (empty($recent)): ?>
                    <div class="empty-state"><span class="empty-icon">📭</span><p>No allocations yet.</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>Student</th><th>Matric No</th><th>Hostel</th><th>Room</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['full_name']) ?></td>
                            <td><?= htmlspecialchars($r['matric_no']) ?></td>
                            <td><?= htmlspecialchars($r['hostel_name']) ?></td>
                            <td>Room <?= htmlspecialchars($r['room_number']) ?></td>
                            <td><?= date('d M Y', strtotime($r['allocation_date'])) ?></td>
                            <td><span class="badge badge-success"><?= $r['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <a href="applications.php" class="btn btn-warning" style="padding:16px;font-size:1rem;">📋 Review Applications (<?= $pending ?> Pending)</a>
            <a href="allocations.php" class="btn btn-success" style="padding:16px;font-size:1rem;">🛏 Allocate Rooms</a>
            <a href="payments.php" class="btn btn-primary" style="padding:16px;font-size:1rem;">💰 Payments</a>
            <a href="reports.php" class="btn btn-info" style="padding:16px;font-size:1rem;">📊 Reports</a>
        </div>
    </main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan Hostel Management System</div>
</body>
</html>
