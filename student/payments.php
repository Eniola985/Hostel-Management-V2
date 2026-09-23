<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$sid = $_SESSION['student_id'];
$student = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
$student->execute([$sid]);
$student = $student->fetch();

$payments = $pdo->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY payment_date DESC");
$payments->execute([$sid]);
$payments = $payments->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - Student</title>
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
        <a href="payments.php" class="active">💰 Payments</a>
        <a href="reports.php">📄 My Report</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Payment History</div>
    <div class="page-subtitle">All recorded hostel payments in Naira</div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h3>Payment Records (<?= count($payments) ?>)</h3>
            <button onclick="window.print()" class="btn btn-info btn-sm">🖨 Print</button>
        </div>
        <div class="table-wrap">
            <?php if (empty($payments)): ?>
                <div class="empty-state"><span class="empty-icon">💳</span><p>No payment records found.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Amount (₦)</th><th>Reference</th><th>Date</th><th>Verified</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td>₦<?= number_format($p['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['payment_ref'] ?? 'N/A') ?></td>
                    <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                    <td><span class="badge <?= $p['verified'] === 'Yes' ? 'badge-success' : 'badge-warning' ?>"><?= $p['verified'] === 'Yes' ? 'Verified' : 'Pending' ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
