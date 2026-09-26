<?php
require_once '../includes/general_admin_auth.php';

$admin = current_general_admin($pdo);

$hostel_id = filter_input(INPUT_GET, 'hostel_id', FILTER_VALIDATE_INT);

if (!$hostel_id) {
    http_response_code(400);
    exit('Invalid hostel ID.');
}

/*
 * Load the selected hostel.
 */
$hostel_stmt = $pdo->prepare(
    'SELECT hostel_id, hostel_name, hostel_type, total_rooms, created_at
     FROM hostels
     WHERE hostel_id = ?'
);

$hostel_stmt->execute([$hostel_id]);
$hostel = $hostel_stmt->fetch();

if (!$hostel) {
    http_response_code(404);
    exit('Hostel not found.');
}

/*
 * Hostel-wide statistics.
 */
$room_stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM rooms
     WHERE hostel_id = ?'
);

$room_stmt->execute([$hostel_id]);
$actual_rooms = (int) $room_stmt->fetchColumn();

$allocation_stmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM allocations a
     INNER JOIN rooms r ON r.room_id = a.room_id
     WHERE r.hostel_id = ?
       AND a.status = "Active"'
);

$allocation_stmt->execute([$hostel_id]);
$active_allocations = (int) $allocation_stmt->fetchColumn();

$student_stmt = $pdo->prepare(
    'SELECT COUNT(DISTINCT a.student_id)
     FROM allocations a
     INNER JOIN rooms r ON r.room_id = a.room_id
     WHERE r.hostel_id = ?
       AND a.status = "Active"'
);

$student_stmt->execute([$hostel_id]);
$students_in_hostel = (int) $student_stmt->fetchColumn();

/*
 * Load rooms belonging only to this hostel.
 */
$rooms_stmt = $pdo->prepare(
    'SELECT
        r.room_id,
        r.room_number,
        r.capacity,
        r.occupied,
        r.status
     FROM rooms r
     WHERE r.hostel_id = ?
     ORDER BY r.room_number'
);

$rooms_stmt->execute([$hostel_id]);
$rooms = $rooms_stmt->fetchAll();

/*
 * Load all hostels for the sidebar.
 */
$all_hostels_stmt = $pdo->query(
    'SELECT hostel_id, hostel_name, hostel_type
     FROM hostels
     ORDER BY hostel_type, hostel_name'
);

$all_hostels = $all_hostels_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($hostel['hostel_name']) ?>
        - General Admin
    </title>

    <link rel="stylesheet" href="../css/style.css?v=7">

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >
</head>

<body>

<div class="wrapper admin-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="user-info">

            <div class="avatar">
                <?= htmlspecialchars(
                    strtoupper(substr($admin['username'], 0, 1))
                ) ?>
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
                📊 Dashboard
            </a>

            <div class="nav-section">
                Hostels
            </div>

            <div class="nav-section">
                Male Hostels
            </div>

            <?php foreach ($all_hostels as $item): ?>

                <?php if ($item['hostel_type'] === 'Male'): ?>

                    <a
                        href="hostel.php?hostel_id=<?= (int) $item['hostel_id'] ?>"
                        class="<?= (int) $item['hostel_id'] === (int) $hostel_id ? 'active' : '' ?>"
                    >
                        🏠 <?= htmlspecialchars($item['hostel_name']) ?>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>


            <div class="nav-section">
                Female Hostels
            </div>

            <?php foreach ($all_hostels as $item): ?>

                <?php if ($item['hostel_type'] === 'Female'): ?>

                    <a
                        href="hostel.php?hostel_id=<?= (int) $item['hostel_id'] ?>"
                        class="<?= (int) $item['hostel_id'] === (int) $hostel_id ? 'active' : '' ?>"
                    >
                        🏠 <?= htmlspecialchars($item['hostel_name']) ?>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>


            <div class="nav-section">
                Management
            </div>

            <a href="add_hostel.php">
                ➕ Add Hostel
            </a>

            <a href="rooms.php">
                🚪 Rooms
            </a>

            <a href="students.php">
                👨‍🎓 Students
            </a>

            <a href="reports.php">
                📈 Reports
            </a>


            <div class="nav-section">
                Account
            </div>

            <a href="logout.php">
                🚪 Logout
            </a>

        </nav>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="main">

        <div class="breadcrumb">
            General Admin ›
            <?= htmlspecialchars($hostel['hostel_name']) ?>
        </div>


        <h1 class="page-title">
            <?= htmlspecialchars($hostel['hostel_name']) ?>
        </h1>

        <p class="page-subtitle">
            Read-only overview of this hostel.
        </p>


        <!-- HOSTEL INFORMATION -->
        <div class="card">

            <div class="card-header">
                <h3>Hostel Information</h3>
            </div>

            <div class="card-body">

                <div class="stats-grid">

                    <div class="stat-card">

                        <div class="stat-icon blue">
                            🏠
                        </div>

                        <div class="stat-info">

                            <div class="value">
                                <?= htmlspecialchars($hostel['hostel_type']) ?>
                            </div>

                            <div class="label">
                                Hostel Type
                            </div>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon green">
                            🚪
                        </div>

                        <div class="stat-info">

                            <div class="value">
                                <?= $actual_rooms ?>
                            </div>

                            <div class="label">
                                Rooms
                            </div>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon orange">
                            👨‍🎓
                        </div>

                        <div class="stat-info">

                            <div class="value">
                                <?= $students_in_hostel ?>
                            </div>

                            <div class="label">
                                Students
                            </div>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon purple">
                            📋
                        </div>

                        <div class="stat-info">

                            <div class="value">
                                <?= $active_allocations ?>
                            </div>

                            <div class="label">
                                Active Allocations
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ROOMS -->
        <div class="card">

            <div class="card-header">

                <h3>
                    Rooms in <?= htmlspecialchars($hostel['hostel_name']) ?>
                </h3>

            </div>

            <div class="card-body">

                <?php if (!$rooms): ?>

                    <div class="alert alert-info">
                        No rooms have been added to this hostel yet.
                    </div>

                <?php else: ?>

                    <div class="table-wrap">

                        <table>

                            <thead>

                                <tr>

                                    <th>Room</th>

                                    <th>Capacity</th>

                                    <th>Occupied</th>

                                    <th>Available</th>

                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($rooms as $room): ?>

                                <?php
                                $capacity = (int) $room['capacity'];
                                $occupied = (int) $room['occupied'];
                                $available = max(0, $capacity - $occupied);
                                ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($room['room_number']) ?>
                                    </td>

                                    <td>
                                        <?= $capacity ?>
                                    </td>

                                    <td>
                                        <?= $occupied ?>
                                    </td>

                                    <td>
                                        <?= $available ?>
                                    </td>

                                    <td>

                                        <?php if ($room['status'] === 'Full'): ?>

                                            <span class="badge badge-danger">
                                                Full
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-success">
                                                Available
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <div class="footer">
            Hostel Management System — General Administration
        </div>

    </main>

</div>

</body>
</html>