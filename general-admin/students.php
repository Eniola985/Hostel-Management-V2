<?php
require_once '../includes/general_admin_auth.php';

$admin = current_general_admin($pdo);

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 20, 30, 40, 50], true)) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

/*
 * General Admin sees students system-wide with status filtering and pagination.
 */
$sql_base = "FROM students s
             LEFT JOIN applications a ON a.student_id = s.student_id
             LEFT JOIN hostels h ON h.hostel_id = a.hostel_id
             LEFT JOIN allocations al ON al.student_id = s.student_id AND al.status = 'Active'
             WHERE 1=1";

$params = [];

if ($search !== '') {
    $sql_base .= " AND (
        s.full_name LIKE ?
        OR s.form_no LIKE ?
        OR s.department LIKE ?
        OR s.phone LIKE ?
        OR h.hostel_name LIKE ?
    )";

    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter === 'Allocated') {
    $sql_base .= " AND (a.status = 'Allocated' OR al.allocation_id IS NOT NULL)";
} elseif ($status_filter === 'Approved') {
    $sql_base .= " AND a.status = 'Approved' AND al.allocation_id IS NULL";
} elseif ($status_filter === 'Pending') {
    $sql_base .= " AND a.status = 'Pending'";
} elseif ($status_filter === 'Rejected') {
    $sql_base .= " AND a.status = 'Rejected'";
} elseif ($status_filter === 'Not Applied') {
    $sql_base .= " AND a.app_id IS NULL";
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
            h.hostel_name,
            h.hostel_type,
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

// Load hostels for sidebar.
$hostelsStmt = $pdo->query(
    'SELECT hostel_id, hostel_name, hostel_type
     FROM hostels
     ORDER BY hostel_type, hostel_name'
);

$hostels = $hostelsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Students - General Admin</title>

<link rel="stylesheet" href="../css/style.css?v=7">

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>

</head>

<body>

<div class="wrapper admin-layout">

<?php $current_page = 'students'; require_once '../includes/general_admin_sidebar.php'; ?>


<main class="main">

    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>

    <div class="breadcrumb">

        <span>General Admin</span>

        <span>&rsaquo;</span>

        <strong>Students</strong>

    </div>


    <div class="page-title">
        Students
    </div>

    <div class="page-subtitle">
        System-wide student records across all hostels.
    </div>


    <div class="card">

        <div class="card-header">

            <h3>
                Student Records (<?= $total_students_count ?>)
            </h3>

            <form
                method="GET"
                style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;"
            >
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search name, form, dept, hostel..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:240px;"
                >

                <select
                    name="status"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="">All Statuses</option>
                    <option value="Allocated" <?= $status_filter === 'Allocated' ? 'selected' : '' ?>>Accepted / Allocated</option>
                    <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending Review</option>
                    <option value="Rejected" <?= $status_filter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="Not Applied" <?= $status_filter === 'Not Applied' ? 'selected' : '' ?>>Not Applied</option>
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

                <button
                    type="submit"
                    class="btn btn-info btn-sm"
                >
                    Filter
                </button>

                <?php if ($search || $status_filter || $limit !== 10): ?>
                    <a
                        href="students.php"
                        class="btn btn-sm"
                        style="background:#f1f5f9;color:#475569;"
                    >
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <span class="empty-icon">
                        👨‍🎓
                    </span>
                    <p>
                        No students found matching current filters.
                    </p>
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
                            <th>Hostel</th>
                            <th>Status</th>
                            <th>Phone</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $i => $student): ?>
                        <tr>
                            <td>
                                <?= $offset + $i + 1 ?>
                            </td>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($student['full_name']) ?>
                                </strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($student['form_no'] ?? '') ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($student['department']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($student['level']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $student['gender'] === 'Male' ? 'badge-info' : 'badge-warning' ?>">
                                    <?= htmlspecialchars($student['gender']) ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($student['hostel_name'] ?? 'Not assigned') ?>
                            </td>
                            <td>
                                <?php if (!empty($student['allocation_id']) || $student['app_status'] === 'Allocated'): ?>
                                    <span class="badge badge-success" style="background:#d1fae5;color:#065f46;">
                                        Allocated (<?= htmlspecialchars($student['bunk_number'] ?? 'Bed Space') ?>)
                                    </span>
                                <?php elseif ($student['app_status'] === 'Approved'): ?>
                                    <span class="badge badge-info" style="background:#dbeafe;color:#1e40af;">
                                        Approved
                                    </span>
                                <?php elseif ($student['app_status'] === 'Pending'): ?>
                                    <span class="badge badge-warning" style="background:#fef3c7;color:#92400e;">
                                        Pending
                                    </span>
                                <?php elseif ($student['app_status'] === 'Rejected'): ?>
                                    <span class="badge badge-danger" style="background:#fee2e2;color:#991b1b;" title="<?= htmlspecialchars($student['rejection_reason'] ?? '') ?>">
                                        Rejected
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f1f5f9;color:#64748b;">
                                        Not Applied
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($student['phone']) ?>
                            </td>
                            <td>
                                <?= date('d M Y', strtotime($student['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1 || $total_students_count > 0): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;font-size:0.85rem;color:#64748b;">
                        <div>
                            Showing <?= min($total_students_count, $offset + 1) ?> to <?= min($total_students_count, $offset + $limit) ?> of <?= $total_students_count ?> students
                        </div>

                        <div style="display:flex;gap:4px;align-items:center;">
                            <?php
                            $queryParams = $_GET;
                            function pageUrl($p, $params) {
                                $params['page'] = $p;
                                return 'students.php?' . http_build_query($params);
                            }
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?= pageUrl($page - 1, $queryParams) ?>" class="btn btn-sm" style="background:#f1f5f9;color:#334155;">&laquo; Prev</a>
                            <?php else: ?>
                                <span class="btn btn-sm" style="background:#f8fafc;color:#cbd5e1;cursor:not-allowed;">&laquo; Prev</span>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <?php if ($p == $page): ?>
                                    <span class="btn btn-sm" style="background:#075985;color:white;font-weight:700;"><?= $p ?></span>
                                <?php elseif ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                                    <a href="<?= pageUrl($p, $queryParams) ?>" class="btn btn-sm" style="background:#f1f5f9;color:#334155;"><?= $p ?></a>
                                <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                                    <span style="padding:0 4px;">...</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= pageUrl($page + 1, $queryParams) ?>" class="btn btn-sm" style="background:#f1f5f9;color:#334155;">Next &raquo;</a>
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


<div class="footer">
    &copy; <?= date('Y') ?> The Polytechnic, Ibadan
</div>

</body>
</html>
