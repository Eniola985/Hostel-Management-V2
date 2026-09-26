<?php
require_once '../includes/admin_auth.php';

$hostel = current_admin_hostel($pdo);

$studentSearch = trim($_GET['student_search'] ?? '');

$sql = "SELECT
            p.payment_id,
            p.student_id,
            p.amount,
            p.payment_ref,
            p.payment_date,
            p.verified,
            p.verification_source,
            p.verified_at,
            s.full_name,
            s.matric_no,
            s.form_no
        FROM payments p
        JOIN students s ON s.student_id = p.student_id
        JOIN applications a ON a.student_id = p.student_id
        WHERE a.hostel_id = ?";

$params = [$admin_hostel_id];

if ($studentSearch !== '') {
    $sql .= " AND (
        s.full_name LIKE ?
        OR s.matric_no LIKE ?
        OR s.form_no LIKE ?
    )";

    $searchLike = "%{$studentSearch}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " ORDER BY p.payment_date DESC, p.payment_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - <?= htmlspecialchars($hostel['hostel_name']) ?></title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper admin-layout">

<aside class="sidebar">
    <div class="user-info">
        <div class="avatar">
            <?= htmlspecialchars(strtoupper(substr($hostel['hostel_name'], 0, 1))) ?>
        </div>
        <div class="name"><?= htmlspecialchars($hostel['hostel_name']) ?></div>
        <div class="role">Hostel Admin</div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="students.php">Students</a>
        <a href="applications.php">Applications</a>
        <a href="allocations.php">Allocations</a>
        <a href="payments.php" class="active">Payments</a>
        <a href="rooms.php">Rooms</a>
        <a href="reports.php">Reports</a>
        <a href="logout.php">Logout</a>
    </nav>
</aside>

<main class="main">
    <div class="page-title"><?= htmlspecialchars($hostel['hostel_name']) ?> Payments</div>
    <div class="page-subtitle">
        Verified Remita payment records for students associated with this hostel.
    </div>

    <div class="alert alert-info">
        Payments are verified through Remita during the student's hostel application.
        This page is read-only for hostel administrators.
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Payment Records (<?= count($payments) ?>)</h3>
        </div>

        <form method="GET" style="margin-bottom:20px;">
            <div class="form-row">
                <div class="form-group">
                    <label for="student_search">Search Student</label>
                    <input
                        type="text"
                        id="student_search"
                        name="student_search"
                        value="<?= htmlspecialchars($studentSearch) ?>"
                        placeholder="Name, matric number or form number"
                    >
                </div>

                <div class="form-group" style="display:flex;align-items:flex-end;gap:10px;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($studentSearch !== ''): ?>
                        <a href="payments.php" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="table-wrap">
            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <span class="empty-icon">Payments</span>
                    <p>No payment records found for this hostel.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Matric / Form No.</th>
                    <th>Amount</th>
                    <th>Reference (RRR)</th>
                    <th>Date</th>
                    <th>Verification</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $i => $payment): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($payment['full_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($payment['matric_no'] ?: ($payment['form_no'] ?: 'N/A')) ?>
                    </td>
                    <td>?<?= number_format((float)$payment['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($payment['payment_ref']) ?></td>
                    <td>
                        <?= htmlspecialchars(date('d M Y H:i', strtotime($payment['payment_date']))) ?>
                    </td>
                    <td>
                        <?php if ($payment['verified'] === 'Yes'): ?>
                            <span class="badge badge-success">Verified</span>
                            <?php if (!empty($payment['verification_source'])): ?>
                                <div style="font-size:12px;margin-top:4px;">
                                    <?= htmlspecialchars($payment['verification_source']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-warning">Pending</span>
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
