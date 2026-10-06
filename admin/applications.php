<?php
require_once '../includes/admin_auth.php';
$hostel = current_admin_hostel($pdo);

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if ($app_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
        $err = 'That application action is not valid. Please try again.';
    } elseif ($action === 'reject' && $rejection_reason === '') {
        $err = 'Please provide a reason before rejecting an application.';
    } else {
        try {
            if ($action === 'approve') {
                $statement = $pdo->prepare("UPDATE applications SET status='Approved', rejection_reason=NULL WHERE app_id=? AND hostel_id=? AND status='Pending'");
                $statement->execute([$app_id, $admin_hostel_id]);
                $msg = $statement->rowCount() ? 'Application approved successfully.' : 'This application is no longer pending.';
            } else {
                $statement = $pdo->prepare("UPDATE applications SET status='Rejected', rejection_reason=? WHERE app_id=? AND hostel_id=? AND status='Pending'");
                $statement->execute([$rejection_reason, $app_id, $admin_hostel_id]);
                $msg = $statement->rowCount() ? 'Application rejected and the reason was saved.' : 'This application is no longer pending.';
            }
        } catch (PDOException $e) {
            $err = 'The application could not be updated. Please try again.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 20, 30, 40, 50], true)) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql_base = "FROM applications a 
             JOIN students s ON a.student_id = s.student_id 
             JOIN hostels h ON a.hostel_id = h.hostel_id 
             WHERE a.hostel_id = ?";

$params = [$admin_hostel_id];

if ($search !== '') {
    $sql_base .= " AND (
        s.full_name LIKE ?
        OR s.form_no LIKE ?
        OR s.department LIKE ?
        OR a.payment_ref LIKE ?
    )";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter === 'Accepted') {
    $sql_base .= " AND a.status IN ('Approved', 'Allocated')";
} elseif ($status_filter !== '') {
    $sql_base .= " AND a.status = ?";
    $params[] = $status_filter;
}

// Total count
$count_stmt = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$count_stmt->execute($params);
$total_apps_count = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_apps_count / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch paginated slice
$sql = "SELECT a.*, s.full_name, s.form_no, s.department, s.level, s.gender, h.hostel_name, h.hostel_type "
     . $sql_base
     . " ORDER BY a.applied_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Applications - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=7">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="wrapper admin-layout">
<?php $current_page = 'applications'; require_once '../includes/admin_sidebar.php'; ?>
<main class="main">
    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
    <div class="page-title"><?= htmlspecialchars($hostel['hostel_name']) ?> Applications</div>
    <div class="page-subtitle">Review and process student accommodation applications</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Applications (<?= $total_apps_count ?>)</h3>

            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search name, form number, ref..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:220px;"
                >

                <select
                    name="status"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="">All Statuses</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="Accepted" <?= $status_filter === 'Accepted' ? 'selected' : '' ?>>Accepted (Approved / Allocated)</option>
                    <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Allocated" <?= $status_filter === 'Allocated' ? 'selected' : '' ?>>Allocated</option>
                    <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
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

                <?php if ($search || $status_filter || $limit !== 10): ?>
                    <a href="applications.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <?php if (empty($apps)): ?>
                <div class="empty-state"><span class="empty-icon">📋</span><p>No applications match your criteria.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Student</th><th>Form No</th><th>Dept / Level</th><th>Gender</th><th>Hostel Applied</th><th>Payment Ref</th><th>Date Applied</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($apps as $i => $a): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($a['full_name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['form_no']) ?></td>
                    <td><?= htmlspecialchars($a['department']) ?> / <?= htmlspecialchars($a['level']) ?></td>
                    <td><span class="badge <?= $a['gender']==='Male'?'badge-info':'badge-warning' ?>"><?= $a['gender'] ?></span></td>
                    <td><?= htmlspecialchars($a['hostel_name']) ?> <small>(<?= $a['hostel_type'] ?>)</small></td>
                    <td><?= htmlspecialchars($a['payment_ref'] ?? 'N/A') ?></td>
                    <td><?= date('d M Y', strtotime($a['applied_at'])) ?></td>
                    <td>
                        <?php
                        $badge = match($a['status']) {
                            'Approved', 'Allocated' => 'badge-success',
                            'Rejected' => 'badge-danger',
                            default => 'badge-warning'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= $a['status'] ?></span>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if ($a['status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="app_id" value="<?= $a['app_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <details style="display:inline-block;vertical-align:middle;">
                                <summary class="btn btn-danger btn-sm" style="cursor:pointer;list-style:none;">Reject</summary>
                                <form method="POST" style="position:absolute;z-index:2;background:white;border:1px solid #e2e8f0;border-radius:8px;padding:12px;width:260px;box-shadow:0 8px 20px rgba(15,23,42,0.15);">
                                    <input type="hidden" name="app_id" value="<?= $a['app_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <label for="reason-<?= $a['app_id'] ?>" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:6px;">Reason for rejection</label>
                                    <textarea id="reason-<?= $a['app_id'] ?>" name="rejection_reason" rows="3" required maxlength="1000" style="width:100%;margin-bottom:8px;" placeholder="Explain what the student needs to correct"></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm">Confirm rejection</button>
                                </form>
                            </details>
                        <?php elseif ($a['status'] === 'Approved'): ?>
                            <a href="allocations.php?student_id=<?= $a['student_id'] ?>" class="btn btn-info btn-sm">Allocate Room</a>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:0.8rem;">No action</span>
                        <?php endif; ?>
                        <?php if ($a['status'] === 'Rejected' && !empty($a['rejection_reason'])): ?>
                            <div style="margin-top:6px;color:#991b1b;font-size:0.8rem;white-space:normal;max-width:220px;"><strong>Reason:</strong> <?= htmlspecialchars($a['rejection_reason']) ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1 || $total_apps_count > 0): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;font-size:0.85rem;color:#64748b;">
                    <div>
                        Showing <?= min($total_apps_count, $offset + 1) ?> to <?= min($total_apps_count, $offset + $limit) ?> of <?= $total_apps_count ?> applications
                    </div>

                    <div class="pagination">
                        <?php
                        $queryParams = $_GET;
                        function adminAppsPageUrl($p, $params) {
                            $params['page'] = $p;
                            return 'applications.php?' . http_build_query($params);
                        }
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="<?= adminAppsPageUrl($page - 1, $queryParams) ?>" class="prev-btn">&larr; Previous</a>
                        <?php else: ?>
                            <span class="page-btn disabled prev-btn">&larr; Previous</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p == $page): ?>
                                <span class="page-btn active"><?= $p ?></span>
                            <?php elseif ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                                <a href="<?= adminAppsPageUrl($p, $queryParams) ?>"><?= $p ?></a>
                            <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                                <span style="padding:0 4px;color:#94a3b8;">&hellip;</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= adminAppsPageUrl($page + 1, $queryParams) ?>" class="next-btn">Next &rarr;</a>
                        <?php else: ?>
                            <span class="page-btn disabled next-btn">Next &rarr;</span>
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
