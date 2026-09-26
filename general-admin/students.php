<?php
require_once '../includes/general_admin_auth.php';

$admin = current_general_admin($pdo);

$search = trim($_GET['search'] ?? '');

/*
 * General Admin sees students system-wide.
 *
 * A student is included when they have an application
 * associated with a hostel.
 */

$sql = "SELECT DISTINCT
            s.*,
            h.hostel_name,
            h.hostel_type
        FROM students s
        LEFT JOIN applications a
            ON a.student_id = s.student_id
        LEFT JOIN hostels h
            ON h.hostel_id = a.hostel_id
        WHERE 1=1";

$params = [];

if ($search !== '') {

    $sql .= " AND (
        s.full_name LIKE ?
        OR s.matric_no LIKE ?
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

$sql .= " ORDER BY s.full_name";

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

<aside class="sidebar">

    <div class="user-info">

        <div class="avatar">
            <?= htmlspecialchars(strtoupper(substr($admin['username'], 0, 1))) ?>
        </div>

        <div class="name">
            <?= htmlspecialchars($admin['username']) ?>
        </div>

        <div class="role">
            General Administrator
        </div>

    </div>


    <nav class="sidebar-nav">

        <a href="dashboard.php">
            📊 <span>Dashboard</span>
        </a>

        <div class="nav-section">
            Hostels
        </div>

        <?php foreach ($hostels as $hostel): ?>

            <a href="hostel.php?hostel_id=<?= (int)$hostel['hostel_id'] ?>">
                🏠
                <span>
                    <?= htmlspecialchars($hostel['hostel_name']) ?>
                </span>
            </a>

        <?php endforeach; ?>


        <div class="nav-section">
            Management
        </div>

        <a href="add_hostel.php">
            ➕ <span>Add Hostel</span>
        </a>

        <a href="rooms.php">
            🚪 <span>Rooms</span>
        </a>

        <a href="students.php" class="active">
            👨‍🎓 <span>Students</span>
        </a>

        <a href="reports.php">
            📈 <span>Reports</span>
        </a>


        <div class="nav-section">
            Account
        </div>

        <a href="logout.php">
            🚪 <span>Logout</span>
        </a>

    </nav>

</aside>


<main class="main">

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
                Student Records (<?= count($students) ?>)
            </h3>


            <form
                method="GET"
                style="display:flex;gap:8px;"
            >

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search name, matric, dept, hostel..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:280px;"
                >

                <button
                    type="submit"
                    class="btn btn-info btn-sm"
                >
                    Search
                </button>


                <?php if ($search): ?>

                    <a
                        href="students.php"
                        class="btn btn-sm"
                        style="background:#f1f5f9;color:#475569;"
                    >
                        Clear
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
                        No students found.
                    </p>

                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Matric No</th>
                            <th>Department</th>
                            <th>Level</th>
                            <th>Gender</th>
                            <th>Hostel</th>
                            <th>Phone</th>
                            <th>Registered</th>
                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($students as $i => $student): ?>

                        <tr>

                            <td>
                                <?= $i + 1 ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($student['full_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($student['matric_no'] ?? '') ?>
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
                                <?= htmlspecialchars($student['phone']) ?>
                            </td>

                            <td>
                                <?= date('d M Y', strtotime($student['created_at'])) ?>
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


<div class="footer">
    &copy; <?= date('Y') ?> The Polytechnic, Ibadan
</div>

</body>
</html>
