<?php
require_once '../includes/general_admin_auth.php';

$admin = current_general_admin($pdo);

$error = '';
$success = '';

$hostel_name = '';
$hostel_type = '';
$uses_bunks = '1';
$total_rooms = '0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $hostel_name = trim($_POST['hostel_name'] ?? '');
    $hostel_type = $_POST['hostel_type'] ?? '';
    $uses_bunks = $_POST['uses_bunks'] ?? '1';
    $total_rooms = trim($_POST['total_rooms'] ?? '0');

    /*
     * Validate hostel name.
     */
    if ($hostel_name === '') {
        $error = 'Hostel name is required.';
    }

    /*
     * Validate hostel type.
     */
    elseif (!in_array($hostel_type, ['Male', 'Female'], true)) {
        $error = 'Please select a valid hostel type.';
    }

    /*
     * Validate bunk configuration.
     */
    elseif (!in_array($uses_bunks, ['0', '1'], true)) {
        $error = 'Please select a valid bunk configuration.';
    }

    /*
     * Validate total rooms.
     */
    elseif (
        !ctype_digit($total_rooms) ||
        (int) $total_rooms < 0
    ) {
        $error = 'Total rooms must be a valid number greater than or equal to zero.';
    }

    else {

        $total_rooms = (int) $total_rooms;
        $uses_bunks = (int) $uses_bunks;

        /*
         * Prevent duplicate hostel names.
         */
        $check_stmt = $pdo->prepare(
            'SELECT hostel_id
             FROM hostels
             WHERE hostel_name = ?
             LIMIT 1'
        );

        $check_stmt->execute([$hostel_name]);

        if ($check_stmt->fetch()) {

            $error = 'A hostel with this name already exists.';

        } else {

            try {

                $stmt = $pdo->prepare(
                    'INSERT INTO hostels
                        (hostel_name, hostel_type, uses_bunks, total_rooms)
                     VALUES (?, ?, ?, ?)'
                );

                $stmt->execute([
                    $hostel_name,
                    $hostel_type,
                    $uses_bunks,
                    $total_rooms
                ]);

                $new_hostel_id = (int) $pdo->lastInsertId();

                header(
                    'Location: hostel.php?hostel_id=' .
                    $new_hostel_id .
                    '&created=1'
                );
                exit;

            } catch (PDOException $e) {

                $error = 'Unable to create the hostel. Please try again.';
            }
        }
    }
}


/*
 * Load hostels for the sidebar.
 */
$hostels_stmt = $pdo->query(
    'SELECT hostel_id, hostel_name, hostel_type
     FROM hostels
     ORDER BY hostel_type, hostel_name'
);

$hostels = $hostels_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Hostel - General Admin</title>

    <link
        rel="stylesheet"
        href="../css/style.css?v=7"
    >

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

            <?php foreach ($hostels as $hostel): ?>

                <?php if ($hostel['hostel_type'] === 'Male'): ?>

                    <a href="hostel.php?hostel_id=<?= (int) $hostel['hostel_id'] ?>">
                        🏠 <?= htmlspecialchars($hostel['hostel_name']) ?>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>


            <div class="nav-section">
                Female Hostels
            </div>

            <?php foreach ($hostels as $hostel): ?>

                <?php if ($hostel['hostel_type'] === 'Female'): ?>

                    <a href="hostel.php?hostel_id=<?= (int) $hostel['hostel_id'] ?>">
                        🏠 <?= htmlspecialchars($hostel['hostel_name']) ?>
                    </a>

                <?php endif; ?>

            <?php endforeach; ?>


            <div class="nav-section">
                Management
            </div>

            <a href="add_hostel.php" class="active">
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
            General Admin › Add Hostel
        </div>


        <h1 class="page-title">
            Add Hostel
        </h1>

        <p class="page-subtitle">
            Create a new hostel within the hostel management system.
        </p>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <div class="card">

            <div class="card-header">

                <h3>
                    Hostel Details
                </h3>

            </div>


            <div class="card-body">

                <form method="POST">

                    <div class="form-group">

                        <label for="hostel_name">
                            Hostel Name
                        </label>

                        <input
                            type="text"
                            id="hostel_name"
                            name="hostel_name"
                            value="<?= htmlspecialchars($hostel_name) ?>"
                            placeholder="e.g. New Hostel"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="hostel_type">
                            Hostel Type
                        </label>

                        <select
                            id="hostel_type"
                            name="hostel_type"
                            required
                        >

                            <option value="">
                                Select hostel type
                            </option>

                            <option
                                value="Male"
                                <?= $hostel_type === 'Male' ? 'selected' : '' ?>
                            >
                                Male
                            </option>

                            <option
                                value="Female"
                                <?= $hostel_type === 'Female' ? 'selected' : '' ?>
                            >
                                Female
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="uses_bunks">
                            Bunk Configuration
                        </label>

                        <select
                            id="uses_bunks"
                            name="uses_bunks"
                            required
                        >

                            <option
                                value="1"
                                <?= $uses_bunks === '1' ? 'selected' : '' ?>
                            >
                                Uses Bunks
                            </option>

                            <option
                                value="0"
                                <?= $uses_bunks === '0' ? 'selected' : '' ?>
                            >
                                No Bunks
                            </option>

                        </select>

                        <small>
                            Select "No Bunks" for hostels such as Unity Hall.
                        </small>

                    </div>


                    <div class="form-group">

                        <label for="total_rooms">
                            Total Rooms
                        </label>

                        <input
                            type="number"
                            id="total_rooms"
                            name="total_rooms"
                            value="<?= htmlspecialchars($total_rooms) ?>"
                            min="0"
                            step="1"
                            required
                        >

                        <small>
                            This records the planned room count. Actual room
                            records can be created from the Rooms section.
                        </small>

                    </div>


                    <div style="display:flex; gap:10px; margin-top:20px;">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            Create Hostel
                        </button>

                        <a
                            href="dashboard.php"
                            class="btn btn-info"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </div>


        <div class="footer">
            Hostel Management System — General Administration
        </div>

    </main>

</div>

</body>

</html>