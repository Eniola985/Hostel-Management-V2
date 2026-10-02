<?php
require_once '../includes/general_admin_auth.php';

$admin = current_general_admin($pdo);

$msg = '';
$err = '';

/*
 * GENERAL ADMIN ROOM MANAGEMENT
 *
 * General Admin can view rooms across all hostels.
 * Rooms are always associated with a specific hostel.
 */

// Add room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {

    $hostel_id = (int)($_POST['hostel_id'] ?? 0);
    $room_number = trim($_POST['room_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);

    if ($hostel_id < 1) {
        $err = 'Please select a hostel.';
    } elseif ($room_number === '' || $capacity < 1 || $capacity > 10) {
        $err = 'Enter a valid room number and capacity between 1 and 10.';
    } else {

        $hostelCheck = $pdo->prepare(
            'SELECT hostel_id FROM hostels WHERE hostel_id = ?'
        );
        $hostelCheck->execute([$hostel_id]);

        if (!$hostelCheck->fetch()) {
            $err = 'Selected hostel does not exist.';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO rooms (hostel_id, room_number, capacity)
                     VALUES (?, ?, ?)'
                )->execute([
                    $hostel_id,
                    $room_number,
                    $capacity
                ]);

                $msg = 'Room added successfully.';
            } catch (PDOException $e) {
                $err = 'That room number already exists in this hostel, or the room could not be added.';
            }
        }
    }
}

// Delete room
if (isset($_GET['delete_room'])) {

    $room_id = (int)$_GET['delete_room'];

    $stmt = $pdo->prepare(
        'SELECT room_id, room_number, occupied
         FROM rooms
         WHERE room_id = ?'
    );
    $stmt->execute([$room_id]);

    $room = $stmt->fetch();

    if (!$room) {
        $err = 'Room not found.';
    } elseif ((int)$room['occupied'] > 0) {
        $err = 'An occupied room cannot be deleted.';
    } else {
        $pdo->prepare(
            'DELETE FROM rooms WHERE room_id = ?'
        )->execute([$room_id]);

        $msg = 'Room deleted successfully.';
    }
}

// Load hostels
$hostelsStmt = $pdo->query(
    'SELECT hostel_id, hostel_name, hostel_type
     FROM hostels
     ORDER BY hostel_type, hostel_name'
);

$hostels = $hostelsStmt->fetchAll();

// Load all rooms
$roomsStmt = $pdo->query(
    "SELECT
        r.*,
        h.hostel_name,
        h.hostel_type,
        COUNT(a.allocation_id) AS alloc_count
     FROM rooms r
     JOIN hostels h
        ON h.hostel_id = r.hostel_id
     LEFT JOIN allocations a
        ON a.room_id = r.room_id
        AND a.status = 'Active'
     GROUP BY
        r.room_id,
        h.hostel_name,
        h.hostel_type
     ORDER BY
        h.hostel_type,
        h.hostel_name,
        r.room_number"
);

$rooms = $roomsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Rooms - General Admin</title>

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

        <a href="rooms.php" class="active">
            🚪 <span>Rooms</span>
        </a>

        <a href="students.php">
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
        <strong>Rooms</strong>
    </div>

    <div class="page-title">
        Room Management
    </div>

    <div class="page-subtitle">
        View and manage rooms across all registered hostels.
    </div>


    <?php if ($msg): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>


    <?php if ($err): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($err) ?>
        </div>
    <?php endif; ?>


    <div class="form-card" style="max-width:100%;margin-bottom:24px;">

        <h3>🚪 Add Room</h3>

        <form method="POST">

            <div class="form-row">

                <div class="form-group">

                    <label>Hostel</label>

                    <select name="hostel_id" required>

                        <option value="">
                            Select Hostel
                        </option>

                        <?php foreach ($hostels as $h): ?>

                            <option value="<?= (int)$h['hostel_id'] ?>">

                                <?= htmlspecialchars($h['hostel_name']) ?>
                                -
                                <?= htmlspecialchars($h['hostel_type']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>Room Number</label>

                    <input
                        type="text"
                        name="room_number"
                        placeholder="e.g. 106"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Capacity (Students)</label>

                    <input
                        type="number"
                        name="capacity"
                        min="1"
                        max="10"
                        value="4"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                name="add_room"
                class="btn btn-success"
            >
                Add Room
            </button>

        </form>

    </div>


    <div class="card">

        <div class="card-header">

            <h3>
                All Rooms (<?= count($rooms) ?>)
            </h3>

        </div>


        <div class="table-wrap">

            <?php if (!$rooms): ?>

                <div class="empty-state">

                    <span class="empty-icon">
                        🚪
                    </span>

                    <p>
                        No rooms have been added yet.
                    </p>

                </div>

            <?php else: ?>

                <table>

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Hostel</th>
                            <th>Type</th>
                            <th>Room</th>
                            <th>Capacity</th>
                            <th>Occupied</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($rooms as $i => $room): ?>

                        <tr>

                            <td>
                                <?= $i + 1 ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($room['hostel_name']) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="badge <?= $room['hostel_type'] === 'Male' ? 'badge-info' : 'badge-warning' ?>">
                                    <?= htmlspecialchars($room['hostel_type']) ?>
                                </span>
                            </td>

                            <td>
                                Room <?= htmlspecialchars($room['room_number']) ?>
                            </td>

                            <td>
                                <?= (int)$room['capacity'] ?>
                            </td>

                            <td>
                                <?= (int)$room['occupied'] ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($room['status']) ?>
                            </td>

                            <td>

                                <?php if ((int)$room['occupied'] === 0): ?>

                                    <a
                                        href="?delete_room=<?= (int)$room['room_id'] ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete room <?= htmlspecialchars($room['room_number'], ENT_QUOTES) ?>?')"
                                    >
                                        Delete
                                    </a>

                                <?php else: ?>

                                    <span style="color:#94a3b8;font-size:.8rem;">
                                        Occupied
                                    </span>

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

<div class="footer">
    &copy; <?= date('Y') ?> The Polytechnic, Ibadan
</div>

</body>
</html>
