<?php
require_once '../includes/admin_auth.php';

$hostel = current_admin_hostel($pdo);
$hostel_id = $admin_hostel_id;
$msg = '';
$err = '';

// Hostel admins may manage rooms inside their assigned hostel, but cannot create,
// delete, or modify hostel blocks. The hostel list is intentionally scoped to one hostel.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $room_number = trim($_POST['room_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);

    if ($room_number === '' || $capacity < 1 || $capacity > 10) {
        $err = 'Enter a valid room number and capacity between 1 and 10.';
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, capacity) VALUES (?,?,?)');
            $stmt->execute([$hostel_id, $room_number, $capacity]);
            $msg = 'Room added successfully to ' . $hostel['hostel_name'] . '.';
        } catch (PDOException $e) {
            $err = 'That room number already exists in this hostel, or the room could not be added.';
        }
    }
}

if (isset($_GET['delete_room'])) {
    $room_id = (int)$_GET['delete_room'];
    $stmt = $pdo->prepare("SELECT occupied FROM rooms WHERE room_id=? AND hostel_id=?");
    $stmt->execute([$room_id, $hostel_id]);
    $room = $stmt->fetch();

    if (!$room) {
        $err = 'Room not found in your hostel.';
    } elseif ((int)$room['occupied'] > 0) {
        $err = 'An occupied room cannot be deleted.';
    } else {
        $pdo->prepare('DELETE FROM rooms WHERE room_id=? AND hostel_id=?')->execute([$room_id, $hostel_id]);
        $msg = 'Room deleted successfully.';
    }
}

$roomsStmt = $pdo->prepare("SELECT r.*, COUNT(a.allocation_id) AS active_allocations
    FROM rooms r
    LEFT JOIN allocations a ON a.room_id=r.room_id AND a.status='Active'
    WHERE r.hostel_id=?
    GROUP BY r.room_id
    ORDER BY r.room_number");
$roomsStmt->execute([$hostel_id]);
$rooms = $roomsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Hostel - <?= htmlspecialchars($hostel['hostel_name']) ?></title>
<link rel="stylesheet" href="../css/style.css?v=7">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="wrapper admin-layout">
<aside class="sidebar">
    <div class="user-info">
        <div class="avatar">A</div>
        <div class="name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
        <div class="role"><?= htmlspecialchars($hostel['hostel_name']) ?> Admin</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="hostels.php" class="active">🏨 My Hostel</a>
        <a href="students.php">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title"><?= htmlspecialchars($hostel['hostel_name']) ?></div>
    <div class="page-subtitle"><?= htmlspecialchars($hostel['hostel_type']) ?> Hostel — you can manage rooms here, but you cannot create or delete hostel blocks.</div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header"><h3>Hostel Details</h3></div>
        <div style="padding:20px;display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
            <div><strong>Hostel:</strong><br><?= htmlspecialchars($hostel['hostel_name']) ?></div>
            <div><strong>Type:</strong><br><?= htmlspecialchars($hostel['hostel_type']) ?></div>
            <div><strong>Portal Access:</strong><br>Restricted to this hostel</div>
        </div>
    </div>

    <div class="form-card" style="max-width:100%;margin-bottom:24px;">
        <h3>🚪 Add Room to <?= htmlspecialchars($hostel['hostel_name']) ?></h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" placeholder="e.g. 106" required maxlength="50">
                </div>
                <div class="form-group">
                    <label>Capacity (Students)</label>
                    <input type="number" name="capacity" min="1" max="10" value="4" required>
                </div>
            </div>
            <button type="submit" name="add_room" class="btn btn-success">Add Room</button>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Rooms in <?= htmlspecialchars($hostel['hostel_name']) ?> (<?= count($rooms) ?>)</h3>
            <a href="rooms.php" class="btn btn-info btn-sm">Room Details</a>
        </div>
        <div class="table-wrap">
            <?php if (!$rooms): ?>
                <div class="empty-state"><span class="empty-icon">🚪</span><p>No rooms have been added yet.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Room</th><th>Capacity</th><th>Occupied</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($rooms as $i => $room): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong>Room <?= htmlspecialchars($room['room_number']) ?></strong></td>
                    <td><?= (int)$room['capacity'] ?></td>
                    <td><?= (int)$room['occupied'] ?></td>
                    <td><span class="badge <?= $room['status']==='Available' ? 'badge-success' : 'badge-danger' ?>"><?= htmlspecialchars($room['status']) ?></span></td>
                    <td>
                        <?php if ((int)$room['occupied'] === 0): ?>
                            <a href="?delete_room=<?= (int)$room['room_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this room?')">Delete</a>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:.8rem;">Occupied</span>
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
