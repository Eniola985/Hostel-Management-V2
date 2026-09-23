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

$payment = $pdo->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY payment_date DESC LIMIT 1");
$payment->execute([$sid]);
$payment = $payment->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Application Status</title>
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
        <a href="status.php" class="active">📊 My Application Status</a>
        <a href="allocation.php">🛏 My Room Allocation</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📄 My Report</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">My Application Status</div>
    <div class="page-subtitle">Track the progress of your hostel accommodation application</div>

    <?php if (!$application): ?>
    <div class="alert alert-info">
        You have not submitted a hostel application yet. <a href="apply.php"><strong>Apply Now &rarr;</strong></a>
    </div>
    <?php else: ?>

    <!-- Status Timeline -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h3>Application Progress</h3></div>
        <div class="card-body">
            <?php
            $steps = ['Submitted','Payment Verified','Approved','Room Allocated'];
            $current = 0;
            if ($application['status'] === 'Pending') $current = 1;
            if ($payment && $payment['verified'] === 'Yes') $current = 2;
            if ($application['status'] === 'Approved') $current = 2;
            if ($application['status'] === 'Allocated') $current = 4;
            if ($application['status'] === 'Rejected') $current = -1;
            ?>
            <?php if ($application['status'] === 'Rejected'): ?>
            <div class="alert alert-error">
                ❌ Your application has been <strong>rejected</strong>.
                <?php if (!empty($application['rejection_reason'])): ?>
                    <div style="margin-top:8px;"><strong>Reason:</strong> <?= htmlspecialchars($application['rejection_reason']) ?></div>
                <?php else: ?>
                    Please visit the hostel management office for more information.
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="display:flex;align-items:center;gap:0;margin-bottom:24px;overflow-x:auto;padding:10px 0;">
                <?php foreach ($steps as $i => $step): ?>
                <div style="display:flex;align-items:center;flex:1;min-width:120px;">
                    <div style="display:flex;flex-direction:column;align-items:center;flex:1;">
                        <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:0.9rem;
                            background:<?= ($i+1)<=$current ? '#059669' : '#e2e8f0' ?>;
                            color:<?= ($i+1)<=$current ? 'white' : '#94a3b8' ?>;">
                            <?= ($i+1)<=$current ? '✓' : ($i+1) ?>
                        </div>
                        <div style="font-size:0.75rem;margin-top:6px;text-align:center;color:<?= ($i+1)<=$current ? '#059669' : '#94a3b8' ?>;font-weight:<?= ($i+1)<=$current ? '600' : '400' ?>;">
                            <?= $step ?>
                        </div>
                    </div>
                    <?php if ($i < count($steps)-1): ?>
                    <div style="height:3px;flex:1;background:<?= ($i+2)<=$current ? '#059669' : '#e2e8f0' ?>;margin-bottom:20px;"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Application Details -->
    <div class="card">
        <div class="card-header">
            <h3>Application Details</h3>
            <span class="badge <?= match($application['status']) {
                'Approved','Allocated' => 'badge-success',
                'Rejected' => 'badge-danger',
                default => 'badge-warning'
            } ?>" style="font-size:0.9rem;padding:6px 14px;"><?= $application['status'] ?></span>
        </div>
        <div class="card-body">
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
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Hostel Applied</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($application['hostel_name']) ?> (<?= $application['hostel_type'] ?>)</div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Payment Reference</div>
                    <div style="font-weight:600;"><?= htmlspecialchars($application['payment_ref'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Date Applied</div>
                    <div style="font-weight:600;"><?= date('d F Y, h:i A', strtotime($application['applied_at'])) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Payment Status</div>
                    <div>
                        <span class="badge <?= ($payment && $payment['verified']==='Yes') ? 'badge-success' : 'badge-warning' ?>">
                            <?= ($payment && $payment['verified']==='Yes') ? '✓ Verified' : 'Awaiting Verification' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
