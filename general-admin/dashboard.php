<?php
require_once '../includes/general_admin_auth.php';

$admin = current_general_admin($pdo);

/*
 * GENERAL ADMIN DASHBOARD
 *
 * This page provides system-wide oversight only.
 * It does NOT perform student take-in, allocation approval,
 * room assignment, or hostel-admin operations.
 */

// System-wide counts.
$total_hostels = (int) $pdo
    ->query('SELECT COUNT(*) FROM hostels')
    ->fetchColumn();

$total_students = (int) $pdo
    ->query('SELECT COUNT(*) FROM students')
    ->fetchColumn();

$total_rooms = (int) $pdo
    ->query('SELECT COUNT(*) FROM rooms')
    ->fetchColumn();

$active_allocations = (int) $pdo
    ->query("SELECT COUNT(*) FROM allocations WHERE status = 'Active'")
    ->fetchColumn();

$pending_applications = (int) $pdo
    ->query("SELECT COUNT(*) FROM applications WHERE status = 'Pending'")
    ->fetchColumn();

// Hostel counts by gender/type.
$male_hostels = (int) $pdo
    ->query("SELECT COUNT(*) FROM hostels WHERE hostel_type = 'Male'")
    ->fetchColumn();

$female_hostels = (int) $pdo
    ->query("SELECT COUNT(*) FROM hostels WHERE hostel_type = 'Female'")
    ->fetchColumn();

/*
 * Load all hostels dynamically.
 *
 * Room and allocation totals are calculated from actual records,
 * rather than relying only on hostels.total_rooms.
 */
$hostel_stmt = $pdo->query(
    "SELECT
        h.hostel_id,
        h.hostel_name,
        h.hostel_type,
        h.total_rooms,

        COUNT(DISTINCT r.room_id) AS actual_rooms,

        COUNT(
            DISTINCT CASE
                WHEN a.status = 'Active' THEN a.allocation_id
            END
        ) AS active_allocations

     FROM hostels h

     LEFT JOIN rooms r
        ON r.hostel_id = h.hostel_id

     LEFT JOIN allocations a
        ON a.room_id = r.room_id

     GROUP BY
        h.hostel_id,
        h.hostel_name,
        h.hostel_type,
        h.total_rooms

     ORDER BY
        h.hostel_type,
        h.hostel_name"
);

$hostels = $hostel_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>General Admin Dashboard - Hostel Management System</title>

    <link rel="stylesheet" href="../css/style.css?v=7">

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >
</head>

<body>

<div class="wrapper admin-layout">

    <!-- =========================
         SIDEBAR
         ========================= -->
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

            <a href="dashboard.php" class="active">
                <span>📊</span>
                <span>Dashboard</span>
            </a>

            <div class="nav-section">
                Hostels
            </div>

            <div class="nav-section">
                Male Hostels
            </div>

            <?php foreach ($hostels as $hostel): ?>

                <?php if ($hostel['hostel_type'] === 'Male'): ?>

                    <a href="hostel.php?hostel_id=<?= (int) $hostel['hostel_id'] ?>">
                        <span>🏠</span>
                        <span>
                            <?= htmlspecialchars($hostel['hostel_name']) ?>
                        </span>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

            <div class="nav-section">
                Female Hostels
            </div>

            <?php foreach ($hostels as $hostel): ?>

                <?php if ($hostel['hostel_type'] === 'Female'): ?>

                    <a href="hostel.php?hostel_id=<?= (int) $hostel['hostel_id'] ?>">
                        <span>🏠</span>
                        <span>
                            <?= htmlspecialchars($hostel['hostel_name']) ?>
                        </span>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>

            <div class="nav-section">
                Management
            </div>

            <a href="add_hostel.php">
                <span>➕</span>
                <span>Add Hostel</span>
            </a>

            <a href="rooms.php">
                <span>🚪</span>
                <span>Rooms</span>
            </a>

            <a href="students.php">
                <span>👨‍🎓</span>
                <span>Students</span>
            </a>

            <a href="reports.php">
                <span>📈</span>
                <span>Reports</span>
            </a>

            <div class="nav-section">
                Account
            </div>

            <a href="logout.php">
                <span>🚪</span>
                <span>Logout</span>
            </a>

        </nav>

    </aside>


    <!-- =========================
         MAIN CONTENT
         ========================= -->
    <main class="main">

        <div class="breadcrumb">
            <span>General Admin</span>
            <span>›</span>
            <strong>Dashboard</strong>
        </div>

        <h1 class="page-title">
            General Admin Dashboard
        </h1>

        <p class="page-subtitle">
            System-wide hostel administration overview.
        </p>


        <!-- =========================
             SYSTEM STATISTICS
             ========================= -->
        <div class="stats-grid">

            <div class="stat-card">

                <div class="stat-icon blue">
                    🏠
                </div>

                <div class="stat-info">
                    <div class="value">
                        <?= $total_hostels ?>
                    </div>

                    <div class="label">
                        Total Hostels
                    </div>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon purple">
                    👨‍🎓
                </div>

                <div class="stat-info">
                    <div class="value">
                        <?= $total_students ?>
                    </div>

                    <div class="label">
                        Students
                    </div>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon green">
                    🚪
                </div>

                <div class="stat-info">
                    <div class="value">
                        <?= $total_rooms ?>
                    </div>

                    <div class="label">
                        Total Rooms
                    </div>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon blue">
                    🛏️
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


            <div class="stat-card">

                <div class="stat-icon orange">
                    ⏳
                </div>

                <div class="stat-info">
                    <div class="value">
                        <?= $pending_applications ?>
                    </div>

                    <div class="label">
                        Pending Applications
                    </div>
                </div>

            </div>

        </div>


        <!-- =========================
             HOSTEL TYPE SUMMARY
             ========================= -->
        <div class="card">

            <div class="card-header">
                <h3>Hostel Overview</h3>
            </div>

            <div class="card-body">

                <div class="stats-grid" style="margin-bottom: 0;">

                    <div class="stat-card">

                        <div class="stat-icon blue">
                            👨
                        </div>

                        <div class="stat-info">

                            <div class="value">
                                <?= $male_hostels ?>
                            </div>

                            <div class="label">
                                Male Hostels
                            </div>

                        </div>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon purple">
                            👩
                        </div>

                        <div class="stat-info">

                            <div class="value">
                                <?= $female_hostels ?>
                            </div>

                            <div class="label">
                                Female Hostels
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =========================
             MALE HOSTELS
             ========================= -->
        <div class="card">

            <div class="card-header">
                <h3>Male Hostels</h3>
            </div>

            <div class="card-body">

                <?php
                $has_male = false;
                ?>

                <?php foreach ($hostels as $hostel): ?>

                    <?php if ($hostel['hostel_type'] !== 'Male') {
                        continue;
                    } ?>

                    <?php $has_male = true; ?>

                    <div class="card" style="margin-bottom: 12px;">

                        <div class="card-header">

                            <div>
                                <h3>
                                    <?= htmlspecialchars($hostel['hostel_name']) ?>
                                </h3>

                                <div style="margin-top: 5px;">
                                    <span class="badge badge-info">
                                        Male Hostel
                                    </span>
                                </div>
                            </div>

                            <a
                                href="hostel.php?hostel_id=<?= (int) $hostel['hostel_id'] ?>"
                                class="btn btn-info btn-sm"
                            >
                                View Hostel
                            </a>

                        </div>

                        <div class="card-body">

                            <div class="stats-grid" style="margin-bottom: 0;">

                                <div class="stat-card">

                                    <div class="stat-icon blue">
                                        🚪
                                    </div>

                                    <div class="stat-info">

                                        <div class="value">
                                            <?= (int) $hostel['actual_rooms'] ?>
                                        </div>

                                        <div class="label">
                                            Rooms
                                        </div>

                                    </div>

                                </div>


                                <div class="stat-card">

                                    <div class="stat-icon green">
                                        🛏️
                                    </div>

                                    <div class="stat-info">

                                        <div class="value">
                                            <?= (int) $hostel['active_allocations'] ?>
                                        </div>

                                        <div class="label">
                                            Active Allocations
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>


                <?php if (!$has_male): ?>

                    <div class="empty-state">
                        <span class="empty-icon">🏠</span>
                        <p>No male hostels have been registered.</p>
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- =========================
             FEMALE HOSTELS
             ========================= -->
        <div class="card">

            <div class="card-header">
                <h3>Female Hostels</h3>
            </div>

            <div class="card-body">

                <?php
                $has_female = false;
                ?>

                <?php foreach ($hostels as $hostel): ?>

                    <?php if ($hostel['hostel_type'] !== 'Female') {
                        continue;
                    } ?>

                    <?php $has_female = true; ?>

                    <div class="card" style="margin-bottom: 12px;">

                        <div class="card-header">

                            <div>
                                <h3>
                                    <?= htmlspecialchars($hostel['hostel_name']) ?>
                                </h3>

                                <div style="margin-top: 5px;">
                                    <span class="badge badge-secondary">
                                        Female Hostel
                                    </span>
                                </div>
                            </div>

                            <a
                                href="hostel.php?hostel_id=<?= (int) $hostel['hostel_id'] ?>"
                                class="btn btn-info btn-sm"
                            >
                                View Hostel
                            </a>

                        </div>

                        <div class="card-body">

                            <div class="stats-grid" style="margin-bottom: 0;">

                                <div class="stat-card">

                                    <div class="stat-icon purple">
                                        🚪
                                    </div>

                                    <div class="stat-info">

                                        <div class="value">
                                            <?= (int) $hostel['actual_rooms'] ?>
                                        </div>

                                        <div class="label">
                                            Rooms
                                        </div>

                                    </div>

                                </div>


                                <div class="stat-card">

                                    <div class="stat-icon green">
                                        🛏️
                                    </div>

                                    <div class="stat-info">

                                        <div class="value">
                                            <?= (int) $hostel['active_allocations'] ?>
                                        </div>

                                        <div class="label">
                                            Active Allocations
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>


                <?php if (!$has_female): ?>

                    <div class="empty-state">
                        <span class="empty-icon">🏠</span>
                        <p>No female hostels have been registered.</p>
                    </div>

                <?php endif; ?>

            </div>

        </div>


        <div class="footer">
            Hostel Management System &mdash; General Administration
        </div>

    </main>

</div>

</body>
</html>