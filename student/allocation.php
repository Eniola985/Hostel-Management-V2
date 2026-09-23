<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$sid = $_SESSION['student_id'];
$student = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
$student->execute([$sid]);
$student = $student->fetch();

$allocation = $pdo->prepare("SELECT al.*, r.room_number, r.capacity, r.occupied, h.hostel_name, h.hostel_type FROM allocations al JOIN rooms r ON al.room_id=r.room_id JOIN hostels h ON r.hostel_id=h.hostel_id WHERE al.student_id=? AND al.status='Active'");
$allocation->execute([$sid]);
$allocation = $allocation->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Room Allocation</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
@media print {
    .navbar,.sidebar,.no-print { display:none!important; }
    .wrapper { display:block; }
    .main { padding:0; }
    .print-header { display:block!important; }
}
.print-header { display:none; text-align:center; margin-bottom:20px; border-bottom:2px solid #1e3a5f; padding-bottom:16px; }
</style>
</head>
<body>
<div class="wrapper">
<aside class="sidebar no-print">
    <div class="user-info">
        <div class="avatar"><?= strtoupper(substr($student['full_name'],0,1)) ?></div>
        <div class="name"><?= htmlspecialchars(explode(' ',$student['full_name'])[0]) ?></div>
        <div class="role"><?= htmlspecialchars($student['matric_no']) ?></div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="apply.php">📝 Apply for Hostel</a>
        <a href="status.php">📊 My Application Status</a>
        <a href="allocation.php" class="active">🛏 My Room Allocation</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📄 My Report</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title no-print">My Room Allocation</div>
    <div class="page-subtitle no-print">Your hostel room assignment details</div>

    <?php if (!$allocation): ?>
    <div class="alert alert-info">
        You have not been allocated a room yet. <?php if (!$pdo->prepare("SELECT * FROM applications WHERE student_id=?")->execute([$sid])): ?>
        <a href="apply.php"><strong>Apply for hostel accommodation &rarr;</strong></a>
        <?php else: ?>
        Please check back later or contact the hostel management office.
        <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- Print Header -->
    <div class="print-header">
        <h2>THE POLYTECHNIC, IBADAN</h2>
        <p>Computerized Hostel Accommodation Management System</p>
        <h3>ROOM ALLOCATION NOTICE</h3>
        <p>Session: <?= date('Y') ?>/<?= date('Y')+1 ?></p>
    </div>

    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()" class="btn btn-info">🖨 Print Allocation Letter</button>
    </div>

    <!-- Allocation Card -->
    <div class="card" style="border:2px solid #059669;max-width:640px;">
        <div class="card-header" style="background:linear-gradient(135deg,#1e3a5f,#2d6a4f);color:white;">
            <h3 style="color:white;">🎉 Room Allocation Confirmed</h3>
            <span class="badge badge-success" style="background:rgba(255,255,255,0.2);color:white;">Active</span>
        </div>
        <div class="card-body">
            <div style="text-align:center;padding:24px 0;border-bottom:1px solid #f1f5f9;margin-bottom:24px;">
                <div style="font-size:4rem;margin-bottom:8px;">🛏</div>
                <div style="font-size:2.5rem;font-weight:700;color:#1e3a5f;">Room <?= htmlspecialchars($allocation['room_number']) ?></div>
                <div style="font-size:1.1rem;color:#059669;font-weight:600;"><?= htmlspecialchars($allocation['hostel_name']) ?></div>
                <div style="font-size:0.875rem;color:#64748b;"><?= htmlspecialchars($allocation['hostel_type']) ?> Hostel<?= $allocation['bunk_number'] ? ', Bunk ' . htmlspecialchars($allocation['bunk_number']) : '' ?></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Student Name</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($student['full_name']) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Matric Number</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($student['matric_no']) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Department</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($student['department']) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Level</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($student['level']) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Room Capacity</div>
                    <div style="font-weight:600;"><?= $allocation['capacity'] ?> students</div>
                </div>
                <?php if ($allocation['bunk_number']): ?>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Bunk</div>
                    <div style="font-weight:600;">Bunk <?= htmlspecialchars($allocation['bunk_number']) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Current Occupants</div>
                    <div style="font-weight:600;"><?= $allocation['occupied'] ?> of <?= $allocation['capacity'] ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Date Allocated</div>
                    <div style="font-weight:600;"><?= date('d F Y', strtotime($allocation['allocation_date'])) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Academic Session</div>
                    <div style="font-weight:600;"><?= date('Y') ?>/<?= date('Y')+1 ?></div>
                </div>
            </div>

            <div style="margin-top:24px;padding:14px;background:#f0fdf4;border-radius:8px;font-size:0.875rem;color:#065f46;border:1px solid #bbf7d0;">
                <strong>Note:</strong> Please report to the hostel management office with this allocation notice, your student ID card, and your payment receipt to collect your room key and complete the check-in process.
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
</div>
<div class="footer no-print">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
