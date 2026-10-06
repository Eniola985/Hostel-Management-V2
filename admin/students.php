<?php
require_once '../includes/admin_auth.php';
$hostel = current_admin_hostel($pdo);

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 20, 30, 40, 50], true)) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql_base = "FROM students s
             JOIN applications a ON a.student_id = s.student_id AND a.hostel_id = ?
             LEFT JOIN allocations al ON al.student_id = s.student_id AND al.status = 'Active'
             WHERE 1=1";

$params = [$admin_hostel_id];

if ($search !== '') {
    $sql_base .= " AND (
        s.full_name LIKE ?
        OR s.form_no LIKE ?
        OR s.department LIKE ?
        OR s.phone LIKE ?
    )";

    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter === 'Accepted') {
    $sql_base .= " AND (a.status IN ('Approved', 'Allocated') OR al.allocation_id IS NOT NULL)";
} elseif ($status_filter === 'Allocated') {
    $sql_base .= " AND (a.status = 'Allocated' OR al.allocation_id IS NOT NULL)";
} elseif ($status_filter === 'Approved') {
    $sql_base .= " AND a.status = 'Approved' AND al.allocation_id IS NULL";
} elseif ($status_filter === 'Pending') {
    $sql_base .= " AND a.status = 'Pending'";
} elseif ($status_filter === 'Rejected') {
    $sql_base .= " AND a.status = 'Rejected'";
}

// Count total matching records.
$count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.student_id) " . $sql_base);
$count_stmt->execute($params);
$total_students_count = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_students_count / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch paginated slice.
$sql = "SELECT DISTINCT
            s.*,
            a.status AS app_status,
            a.rejection_reason,
            al.bunk_number,
            al.allocation_id
        " . $sql_base . "
        ORDER BY s.full_name
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=7">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="wrapper admin-layout">
<?php $current_page = 'students'; require_once '../includes/admin_sidebar.php'; ?>
<main class="main">
    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
    <div class="page-title"><?= htmlspecialchars($hostel['hostel_name']) ?> Students</div>
    <div class="page-subtitle">
        Students associated with <?= htmlspecialchars($hostel['hostel_name']) ?> (<?= htmlspecialchars($hostel['hostel_type']) ?> Hostel)
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Student Records (<?= $total_students_count ?>)</h3>

            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search name, form number, dept..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:220px;"
                >

                <select
                    name="status"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="">All Statuses</option>
                    <option value="Accepted" <?= $status_filter === 'Accepted' ? 'selected' : '' ?>>Accepted (Approved / Allocated)</option>
                    <option value="Allocated" <?= $status_filter === 'Allocated' ? 'selected' : '' ?>>Allocated to Room</option>
                    <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved (Awaiting Room)</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending Review</option>
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
                    <a href="students.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <span class="empty-icon">👥</span>
                    <p>No students found matching current filters.</p>
                </div>
            <?php else: ?>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Full Name</th>
                        <th>Form No</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Phone</th>
                        <th>Registered</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>

                        <td>
                            <strong><?= htmlspecialchars($s['full_name']) ?></strong>
                        </td>

                        <td><?= htmlspecialchars($s['form_no'] ?? '') ?></td>

                        <td><?= htmlspecialchars($s['department']) ?></td>

                        <td><?= htmlspecialchars($s['level']) ?></td>

                        <td>
                            <span class="badge <?= $s['gender'] === 'Male' ? 'badge-info' : 'badge-warning' ?>">
                                <?= htmlspecialchars($s['gender']) ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($s['allocation_id']) || $s['app_status'] === 'Allocated'): ?>
                                <span class="badge badge-success">
                                    🛏 Allocated <?= !empty($s['bunk_number']) ? '(' . htmlspecialchars($s['bunk_number']) . ')' : '' ?>
                                </span>
                            <?php elseif ($s['app_status'] === 'Approved'): ?>
                                <span class="badge badge-success" style="background:#e0f2fe;color:#0369a1;">
                                    Approved
                                </span>
                            <?php elseif ($s['app_status'] === 'Pending'): ?>
                                <span class="badge badge-warning">
                                    Pending
                                </span>
                            <?php elseif ($s['app_status'] === 'Rejected'): ?>
                                <span class="badge badge-danger" style="background:#fee2e2;color:#991b1b;" title="<?= htmlspecialchars($s['rejection_reason'] ?? '') ?>">
                                    Rejected
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background:#f1f5f9;color:#64748b;">
                                    <?= htmlspecialchars($s['app_status'] ?? 'None') ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($s['phone']) ?></td>

                        <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1 || $total_students_count > 0): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;font-size:0.85rem;color:#64748b;">
                    <div>
                        Showing <?= min($total_students_count, $offset + 1) ?> to <?= min($total_students_count, $offset + $limit) ?> of <?= $total_students_count ?> students
                    </div>

                    <div class="pagination">
                        <?php
                        $queryParams = $_GET;
                        function adminStudentsPageUrl($p, $params) {
                            $params['page'] = $p;
                            return 'students.php?' . http_build_query($params);
                        }
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="<?= adminStudentsPageUrl($page - 1, $queryParams) ?>" class="prev-btn">&larr; Previous</a>
                        <?php else: ?>
                            <span class="page-btn disabled prev-btn">&larr; Previous</span>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <?php if ($p == $page): ?>
                                <span class="page-btn active"><?= $p ?></span>
                            <?php elseif ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                                <a href="<?= adminStudentsPageUrl($p, $queryParams) ?>"><?= $p ?></a>
                            <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                                <span style="padding:0 4px;color:#94a3b8;">&hellip;</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="<?= adminStudentsPageUrl($page + 1, $queryParams) ?>" class="next-btn">Next &rarr;</a>
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
