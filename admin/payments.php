<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$msg = '';
$studentSearch = trim($_GET['student_search'] ?? '');

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

if ($studentSearch !== '') {
    $searchLike = '%' . $studentSearch . '%';
    $paymentsStmt = $pdo->prepare("SELECT p.*, s.full_name, s.matric_no, s.form_no FROM payments p JOIN students s ON p.student_id=s.student_id WHERE s.full_name LIKE ? ORDER BY p.payment_date DESC");
    $paymentsStmt->execute([$searchLike]);
    $payments = $paymentsStmt->fetchAll();

    $studentsStmt = $pdo->prepare("SELECT * FROM students WHERE full_name LIKE ? ORDER BY full_name");
    $studentsStmt->execute([$searchLike]);
    $students = $studentsStmt->fetchAll();
} else {
    $payments = $pdo->query("SELECT p.*, s.full_name, s.matric_no, s.form_no FROM payments p JOIN students s ON p.student_id=s.student_id ORDER BY p.payment_date DESC")->fetchAll();
    $students = $pdo->query("SELECT * FROM students ORDER BY full_name")->fetchAll();
}
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

<div class="wrapper admin-layout">
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
        <h3>🔎 Search Student</h3>
        <form method="GET" action="payments.php">
            <div class="form-row">
                <div class="form-group" style="flex:1;">
                    <label>Search by Student Name</label>
                    <input type="text" name="student_search" value="<?= htmlspecialchars($studentSearch) ?>" placeholder="Enter student name, e.g. Adewale Bamidele">
                    <small style="color:#64748b;font-size:0.8rem;">Use the student's name to find their payment records and select them when recording a payment.</small>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;gap:10px;">
                    <button type="submit" class="btn btn-primary">Search Student</button>
                    <?php if ($studentSearch !== ''): ?><a href="payments.php" class="btn btn-secondary">Clear Search</a><?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="form-card" style="max-width:100%;margin-bottom:24px;">
        <h3>💰 Record New Payment</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['form_no'] ?: 'No form number') ?><?= $s['matric_no'] ? ' / ' . htmlspecialchars($s['matric_no']) : '' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($studentSearch !== '' && empty($students)): ?>
                        <small style="color:#b91c1c;font-size:0.8rem;">No student found with that name.</small>
                    <?php elseif ($studentSearch !== ''): ?>
                        <small style="color:#059669;font-size:0.8rem;"><?= count($students) ?> matching student<?= count($students) === 1 ? '' : 's' ?> found.</small>
                    <?php endif; ?>
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
        <div class="card-header"><h3><?= $studentSearch !== '' ? 'Payment Records for "' . htmlspecialchars($studentSearch) . '"' : 'Payment Records' ?> (<?= count($payments) ?>)</h3></div>
        <div class="table-wrap">
            <?php if (empty($payments)): ?>
                <div class="empty-state"><span class="empty-icon">💰</span><p><?= $studentSearch !== '' ? 'No payment records found for this student name.' : 'No payment records yet.' ?></p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Student</th><th>Form No</th><th>Matric No</th><th>Amount (₦)</th><th>Reference</th><th>Date</th><th>Verified</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $i => $p): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($p['full_name']) ?></td>
                    <td><?= htmlspecialchars($p['form_no'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['matric_no'] ?: '—') ?></td>
                    <td>₦<?= number_format($p['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($p['payment_ref']) ?></td>
                    <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                    <td><span class="badge <?= $p['verified']==='Yes'?'badge-success':'badge-warning' ?>"><?= $p['verified']==='Yes'?'Verified':'Pending' ?></span></td>
                    <td>
                        <?php if ($p['verified'] !== 'Yes'): ?>
                            <a href="?verify=<?= $p['payment_id'] ?><?= $studentSearch !== '' ? '&student_search=' . urlencode($studentSearch) : '' ?>" class="btn btn-success btn-sm">Verify</a>
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
