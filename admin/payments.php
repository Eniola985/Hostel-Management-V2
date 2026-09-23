<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$msg = '';

if (isset($_POST['add_payment'])) {
    $sid = (int)$_POST['student_id'];
    $amount = (float)$_POST['amount'];
    $ref = trim($_POST['payment_ref']);
    $pdo->prepare("INSERT INTO payments (student_id, amount, payment_ref, verified) VALUES (?,?,?,'No')")->execute([$sid,$amount,$ref]);
    $msg = 'Payment recorded as pending verification.';
}

if (isset($_GET['verify'])) {
    $pdo->prepare("UPDATE payments SET verified='Yes' WHERE payment_id=?")->execute([$_GET['verify']]);
    $msg = 'Payment verified.';
}

$payments = $pdo->query("SELECT p.*, s.full_name, s.matric_no FROM payments p JOIN students s ON p.student_id=s.student_id ORDER BY p.payment_date DESC")->fetchAll();
$students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="hostels.php">Hostels</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="allocations.php">Allocations</a></li>
        <li><a href="payments.php" class="active">Payments</a></li>
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
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="hostels.php">🏨 Manage Hostels</a>
        <a href="students.php">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php">🛏 Allocations</a>
        <a href="payments.php" class="active">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Payments Management</div>
    <div class="page-subtitle">Record and verify hostel fee payments</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="form-card" style="max-width:100%;margin-bottom:24px;">
        <h3>💰 Record New Payment</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['matric_no']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (₦)</label>
                    <input type="number" name="amount" step="0.01" min="1" placeholder="e.g. 25000" required>
                </div>
            </div>
            <div class="form-group">
                <label>Payment Reference / Teller Receipt Number</label>
                <input type="text" name="payment_ref" placeholder="e.g. TRF202400001" required>
                <small style="color:#64748b;font-size:0.8rem;">Enter the bank teller receipt or transaction reference printed by the bank.</small>
            </div>
            <button type="submit" name="add_payment" class="btn btn-success">Record Payment</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header"><h3>Payment Records (<?= count($payments) ?>)</h3></div>
        <div class="table-wrap">
            <?php if (empty($payments)): ?>
                <div class="empty-state"><span class="empty-icon">💰</span><p>No payment records yet.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Student</th><th>Matric No</th><th>Amount (₦)</th><th>Reference</th><th>Date</th><th>Verified</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                    <td><?= htmlspecialchars($p['matric_no']) ?></td>
                    <td>₦<?= number_format($p['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['payment_ref']) ?></td>
                    <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                    <td><span class="badge <?= $p['verified']==='Yes'?'badge-success':'badge-warning' ?>"><?= $p['verified']==='Yes'?'Verified':'Pending' ?></span></td>
                    <td>
                        <?php if ($p['verified'] !== 'Yes'): ?>
                            <a href="?verify=<?= $p['payment_id'] ?>" class="btn btn-success btn-sm">Verify</a>
                        <?php else: ?>
                            <span style="color:#059669;font-size:0.8rem;">✓ Done</span>
                        <?php endif; ?>
                    </td>
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
