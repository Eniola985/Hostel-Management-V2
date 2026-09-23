<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$sid = $_SESSION['student_id'];
$student = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
$student->execute([$sid]);
$student = $student->fetch();

$application = $pdo->prepare("SELECT a.*, h.hostel_name FROM applications a JOIN hostels h ON a.hostel_id=h.hostel_id WHERE a.student_id=? ORDER BY a.applied_at DESC LIMIT 1");
$application->execute([$sid]);
$application = $application->fetch();

$allocation = $pdo->prepare("SELECT al.*, r.room_number, h.hostel_name FROM allocations al JOIN rooms r ON al.room_id=r.room_id JOIN hostels h ON r.hostel_id=h.hostel_id WHERE al.student_id=? AND al.status='Active'");
$allocation->execute([$sid]);
$allocation = $allocation->fetch();

$payment = $pdo->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY payment_date DESC LIMIT 1");
$payment->execute([$sid]);
$payment = $payment->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Report - Student</title>
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
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="apply.php">📝 Apply for Hostel</a>
        <a href="status.php">📊 My Application Status</a>
        <a href="allocation.php">🛏 My Room Allocation</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php" class="active">📄 My Report</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">My Report</div>
    <div class="page-subtitle">Student application, allocation, and payment summary</div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h3>Student Summary</h3>
            <button onclick="window.print()" class="btn btn-info btn-sm">🖨 Print</button>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Full Name</div>
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
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h3>Application & Allocation</h3></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Application Status</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($application['status'] ?? 'Not Applied') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Hostel Applied</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($application['hostel_name'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Room Allocation</div>
                    <div style="font-weight:600;"><?= $allocation ? 'Room '.htmlspecialchars($allocation['room_number']).' - '.htmlspecialchars($allocation['hostel_name']) : 'Not Allocated' ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Payment Status</div>
                    <div style="font-weight:600;"><?= $payment ? ($payment['verified'] === 'Yes' ? 'Verified' : 'Pending') : 'No payment found' ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Latest Payment</h3></div>
        <div class="card-body">
            <?php if (!$payment): ?>
                <div class="alert alert-info">No payment record has been found yet.</div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Amount</div>
                    <div style="font-weight:600;">₦<?= number_format($payment['amount'], 2) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Reference</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($payment['payment_ref'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Date Paid</div>
                    <div style="font-weight:600;"><?= date('d M Y', strtotime($payment['payment_date'])) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Verified</div>
                    <div style="font-weight:600;"><?= $payment['verified'] === 'Yes' ? 'Yes' : 'No' ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
