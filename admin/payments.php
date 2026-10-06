<?php
require_once '../includes/admin_auth.php';

$hostel = current_admin_hostel($pdo);

$studentSearch = trim($_GET['student_search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 20, 30, 40, 50], true)) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql_base = "FROM payments p
             JOIN students s ON s.student_id = p.student_id
             JOIN applications a ON a.student_id = p.student_id
             WHERE a.hostel_id = ?";

$params = [$admin_hostel_id];

if ($studentSearch !== '') {
    $sql_base .= " AND (
        s.full_name LIKE ?
        OR s.form_no LIKE ?
        OR p.payment_ref LIKE ?
    )";

    $searchLike = "%{$studentSearch}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($status_filter === 'Verified') {
    $sql_base .= " AND p.verified = 'Yes'";
} elseif ($status_filter === 'Pending') {
    $sql_base .= " AND p.verified != 'Yes'";
}

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT p.payment_id) " . $sql_base);
$count_stmt->execute($params);
$total_payments_count = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_payments_count / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch slice
$sql = "SELECT DISTINCT
            p.payment_id,
            p.student_id,
            p.amount,
            p.payment_ref,
            p.payment_date,
            p.verified,
            p.verification_source,
            p.verified_at,
            s.full_name,
            s.form_no "
     . $sql_base
     . " ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

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
<link rel="stylesheet" href="../css/style.css?v=7">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper admin-layout">
<?php $current_page = 'payments'; require_once '../includes/admin_sidebar.php'; ?>
<main class="main">
    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
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
            <h3>Payment Records (<?= $total_payments_count ?>)</h3>

            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input
                    type="text"
                    name="student_search"
                    value="<?= htmlspecialchars($studentSearch) ?>"
                    placeholder="Search student or RRR..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:220px;"
                >

                <select
                    name="status"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="">All Verifications</option>
                    <option value="Verified" <?= $status_filter === 'Verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending Verification</option>
                </select>

                <select
                    name="limit"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10 per page</option>
                    <option value="20" <?= $limit === 20 ? 'selected' : '' ?>>20 per page</option>
                    <option value="30" <?= $limit === 30 ? 'selected' : '' ?>>30 per page</option>
                    <option value="40" <?= $limit === 40 ? 'selected' : '' ?>>40 per page</option>
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 per page</option>
                </select>

                <button type="submit" class="btn btn-info btn-sm">Filter</button>

                <?php if ($studentSearch || $status_filter || $limit !== 10): ?>
                    <a href="payments.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">
            <?php if (empty($payments)): ?>
                <div class="empty-state">
                    <span class="empty-icon">💳</span>
                    <p>No payment records found matching current filters.</p>
                </div>
            <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Form No.</th>
                    <th>Amount</th>
                    <th>Reference (RRR)</th>
                    <th>Date</th>
                    <th>Verification</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $i => $payment): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($payment['full_name']) ?></strong></td>
                    <td>
                        <?= htmlspecialchars($payment['form_no'] ?: 'N/A') ?>
                    </td>
                    <td>₦<?= number_format((float)$payment['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($payment['payment_ref']) ?></td>
                    <td>
                        <?= htmlspecialchars(date('d M Y H:i', strtotime($payment['payment_date']))) ?>
                    </td>
                    <td>
                        <?php if ($payment['verified'] === 'Yes'): ?>
                            <span class="badge badge-success">Verified</span>
                            <?php if (!empty($payment['verification_source'])): ?>
                                <div style="font-size:12px;margin-top:4px;color:#047857;">
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

            <?php if ($total_pages > 1 || $total_payments_count > 0): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;font-size:0.85rem;color:#64748b;">
                    <div>
                        Showing <?= min($total_payments_count, $offset + 1) ?> to <?= min($total_payments_count, $offset + $limit) ?> of <?= $total_payments_count ?> payments
                    </div>

                    <div style="display:flex;gap:4px;align-items:center;">
                        <?php
                        $queryParams = $_GET;
                        function adminPaymentsPageUrl($p, $params) {
                            $params['page'] = $p;
                            return 'payments.php?' . http_build_query($params);
                        }
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="<?= adminPaymentsPageUrl($page - 1, $queryParams) ?>" class="btn btn-sm" style="background:#f1f5f9;color:#334155;">&laquo; Prev</a>
                        <?php else: ?>
                            <span class="btn btn-sm" style="background:#f8fafc;color:#cbd5e1;cursor:not-allowed;">&laquo; Prev</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p == $page): ?>
                                <span class="btn btn-sm" style="background:#075985;color:white;font-weight:700;"><?= $p ?></span>
                            <?php elseif ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                                <a href="<?= adminPaymentsPageUrl($p, $queryParams) ?>" class="btn btn-sm" style="background:#f1f5f9;color:#334155;"><?= $p ?></a>
                            <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                                <span style="padding:0 4px;">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= adminPaymentsPageUrl($page + 1, $queryParams) ?>" class="btn btn-sm" style="background:#f1f5f9;color:#334155;">Next &raquo;</a>
                        <?php else: ?>
                            <span class="btn btn-sm" style="background:#f8fafc;color:#cbd5e1;cursor:not-allowed;">Next &raquo;</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
