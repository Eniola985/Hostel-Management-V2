<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$sid = $_SESSION['student_id'];
$student = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
$student->execute([$sid]);
$student = $student->fetch();

$application = $pdo->prepare("SELECT a.*, h.hostel_name, h.hostel_type FROM applications a JOIN hostels h ON a.hostel_id=h.hostel_id WHERE a.student_id=? ORDER BY a.applied_at DESC LIMIT 1");
$application->execute([$sid]);
$application = $application->fetch();

$allocation = $pdo->prepare("SELECT al.*, r.room_number, r.capacity, r.occupied, h.hostel_name, h.hostel_type FROM allocations al JOIN rooms r ON al.room_id=r.room_id JOIN hostels h ON r.hostel_id=h.hostel_id WHERE al.student_id=? AND al.status='Active'");
$allocation->execute([$sid]);
$allocation = $allocation->fetch();

$payment = $pdo->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY payment_date DESC LIMIT 1");
$payment->execute([$sid]);
$payment = $payment->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard - Hostel Management</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
<aside class="sidebar">
    <div class="user-info">
        <div class="avatar"><?= strtoupper(substr($student['full_name'],0,1)) ?></div>
        <div class="name"><?= htmlspecialchars(explode(' ',$student['full_name'])[0]) ?></div>
        <div class="role"><?= htmlspecialchars($student['matric_no']) ?></div>
        <div class="hostel-badge">
            <?php if ($allocation): ?>
                🏨 <?= htmlspecialchars($allocation['hostel_name']) ?>
            <?php else: ?>
                🏨 Hostel not assigned
            <?php endif; ?>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Student Menu</div>
        <a href="dashboard.php" class="active">🏠 Dashboard</a>
        <a href="apply.php">📝 Apply for Hostel</a>
        <a href="status.php">📊 My Application Status</a>
        <a href="allocation.php">🛏 My Room Allocation</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📄 My Report</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Welcome, <?= htmlspecialchars(explode(' ',$student['full_name'])[0]) ?>! 👋</div>
    <div class="page-subtitle"><?= htmlspecialchars($student['department']) ?>, <?= htmlspecialchars($student['level']) ?></div>

    <!-- Status Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📋</div>
            <div class="stat-info">
                <div class="value"><?= $application ? $application['status'] : 'None' ?></div>
                <div class="label">Application Status</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">🛏</div>
            <div class="stat-info">
                <div class="value"><?= $allocation ? 'Room '.$allocation['room_number'] : 'Not Yet' ?></div>
                <div class="label">Room Allocation</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">🏨</div>
            <div class="stat-info">
                <div class="value"><?= $allocation ? $allocation['hostel_name'] : 'Pending' ?></div>
                <div class="label">Assigned Hostel</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon <?= ($payment && $payment['verified']==='Yes') ? 'green' : 'red' ?>">💰</div>
            <div class="stat-info">
                <div class="value"><?= ($payment && $payment['verified']==='Yes') ? 'Verified' : 'Not Verified' ?></div>
                <div class="label">Payment Status</div>
            </div>
        </div>
    </div>

    <!-- Allocation Banner -->
    <?php if ($allocation): ?>
    <div class="alert alert-success" style="padding:20px;border-radius:12px;font-size:1rem;">
        🎉 <strong>Room Allocated!</strong> You have been assigned to <strong>Room <?= htmlspecialchars($allocation['room_number']) ?></strong> in <strong><?= htmlspecialchars($allocation['hostel_name']) ?></strong> (<?= htmlspecialchars($allocation['hostel_type']) ?> Hostel).
        Allocated on <?= date('d F Y', strtotime($allocation['allocation_date'])) ?>.
    </div>
    <?php elseif ($application && $application['status'] === 'Approved'): ?>
    <div class="alert alert-info" style="padding:20px;border-radius:12px;">
        ✅ Your application has been <strong>approved</strong>. Awaiting room allocation by the hostel administrator.
    </div>
    <?php elseif ($application && $application['status'] === 'Pending'): ?>
    <div class="alert alert-warning" style="padding:20px;border-radius:12px;">
        ⏳ Your application is <strong>pending review</strong>. The hostel administrator will process it shortly.
    </div>
    <?php elseif ($application && $application['status'] === 'Rejected'): ?>
    <div class="alert alert-error" style="padding:20px;border-radius:12px;">
        ❌ Your application was <strong>rejected</strong>. Please contact the hostel management office for more information.
    </div>
    <?php else: ?>
    <div class="alert alert-info" style="padding:20px;border-radius:12px;">
        📝 You have not applied for hostel accommodation yet. <a href="apply.php" style="color:#1e3a5f;font-weight:700;">Apply Now &rarr;</a>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header"><h3>Quick Actions</h3></div>
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;">
            <?php if (!$application): ?>
            <a href="apply.php" class="btn btn-primary">📝 Apply for Hostel</a>
            <?php endif; ?>
            <a href="status.php" class="btn btn-info">📊 Check Application Status</a>
            <a href="allocation.php" class="btn btn-success">🛏 View Room Allocation</a>
            <a href="payments.php" class="btn btn-success">💰 View Payments</a>
            <a href="reports.php" class="btn btn-info">📄 View My Report</a>
            <a href="profile.php" class="btn btn-warning">👤 Update Profile</a>
        </div>
    </div>

    <!-- Student Info -->
    <div class="card">
        <div class="card-header"><h3>My Information</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div><strong>Full Name:</strong><br><?= htmlspecialchars($student['full_name']) ?></div>
                <div><strong>Matric Number:</strong><br><?= htmlspecialchars($student['matric_no']) ?></div>
                <div><strong>Department:</strong><br><?= htmlspecialchars($student['department']) ?></div>
                <div><strong>Level:</strong><br><?= htmlspecialchars($student['level']) ?></div>
                <div><strong>Gender:</strong><br><?= htmlspecialchars($student['gender']) ?></div>
                <div><strong>Phone:</strong><br><?= htmlspecialchars($student['phone']) ?></div>
                <div><strong>Email:</strong><br><?= htmlspecialchars($student['email'] ?? 'N/A') ?></div>
                <div><strong>Registered:</strong><br><?= date('d F Y', strtotime($student['created_at'])) ?></div>
            </div>
        </div>
    </div>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan Hostel Management System</div>
</body>
</html>
