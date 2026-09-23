<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$msg = $err = '';

// Add hostel
if (isset($_POST['add_hostel'])) {
    $name = trim($_POST['hostel_name']);
    $type = $_POST['hostel_type'];
    $total = (int)$_POST['total_rooms'];
    $pdo->prepare("INSERT INTO hostels (hostel_name, hostel_type, total_rooms) VALUES (?,?,?)")->execute([$name,$type,$total]);
    $msg = 'Hostel added successfully!';
}

// Add room
if (isset($_POST['add_room'])) {
    $hid = (int)$_POST['hostel_id'];
    $rnum = trim($_POST['room_number']);
    $cap = (int)$_POST['capacity'];
    $pdo->prepare("INSERT INTO rooms (hostel_id, room_number, capacity) VALUES (?,?,?)")->execute([$hid,$rnum,$cap]);
    $msg = 'Room added successfully!';
}

// Delete hostel and its dependent records safely
if (isset($_GET['delete_hostel'])) {
    $hid = (int)$_GET['delete_hostel'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM allocations WHERE room_id IN (SELECT room_id FROM rooms WHERE hostel_id=?)")->execute([$hid]);
        $pdo->prepare("DELETE FROM applications WHERE hostel_id=?")->execute([$hid]);
        $pdo->prepare("DELETE FROM rooms WHERE hostel_id=?")->execute([$hid]);
        $pdo->prepare("DELETE FROM hostels WHERE hostel_id=?")->execute([$hid]);
        $pdo->commit();
        $msg = 'Hostel and related rooms/applications deleted.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $err = 'Unable to delete hostel. Please try again.';
    }
}

$hostels = $pdo->query("SELECT h.*, COUNT(r.room_id) as room_count, SUM(CASE WHEN r.status='Available' THEN 1 ELSE 0 END) as available_count FROM hostels h LEFT JOIN rooms r ON h.hostel_id=r.hostel_id GROUP BY h.hostel_id ORDER BY h.hostel_name")->fetchAll();
$all_hostels = $pdo->query("SELECT * FROM hostels")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Hostels</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="hostels.php" class="active">Hostels</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="allocations.php">Allocations</a></li>
        <li><a href="reports.php">Reports</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</nav>
<div class="wrapper">
<aside class="sidebar">
    <div class="user-info">
        <div class="avatar">A</div>
        <div class="name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
        <div class="role">Administrator</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="hostels.php" class="active">🏨 Manage Hostels</a>
        <a href="students.php">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Manage Hostels &amp; Rooms</div>
    <div class="page-subtitle">Add and manage hostel blocks and rooms</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
        <div class="form-card">
            <h3>🏨 Add New Hostel</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Hostel Name</label>
                    <input type="text" name="hostel_name" placeholder="e.g. Olori Hostel" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="hostel_type" required>
                        <option value="">Select Type</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Total Rooms</label>
                    <input type="number" name="total_rooms" min="1" placeholder="e.g. 50" required>
                </div>
                <button type="submit" name="add_hostel" class="btn btn-primary">Add Hostel</button>
            </form>
        </div>
        <div class="form-card">
            <h3>🚪 Add Room to Hostel</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Hostel</label>
                    <select name="hostel_id" required>
                        <option value="">Select Hostel</option>
                        <?php foreach ($all_hostels as $h): ?>
                            <option value="<?= $h['hostel_id'] ?>"><?= htmlspecialchars($h['hostel_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" placeholder="e.g. 101" required>
                </div>
                <div class="form-group">
                    <label>Capacity (Students)</label>
                    <input type="number" name="capacity" min="1" max="10" value="4" required>
                </div>
                <button type="submit" name="add_room" class="btn btn-success">Add Room</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>All Hostels</h3></div>
        <div class="table-wrap">
            <?php if (empty($hostels)): ?>
                <div class="empty-state"><span class="empty-icon">🏨</span><p>No hostels added yet.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Hostel Name</th><th>Type</th><th>Total Rooms</th><th>Available</th><th>Occupancy</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($hostels as $i => $h): 
                    $occ = $h['room_count'] > 0 ? round((($h['room_count'] - $h['available_count']) / $h['room_count']) * 100) : 0;
                ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($h['hostel_name']) ?></strong></td>
                    <td><span class="badge <?= $h['hostel_type']==='Male' ? 'badge-info' : 'badge-warning' ?>"><?= $h['hostel_type'] ?></span></td>
                    <td><?= $h['room_count'] ?></td>
                    <td><?= $h['available_count'] ?? 0 ?></td>
                    <td>
                        <div style="background:#e2e8f0;border-radius:20px;height:8px;width:100px;">
                            <div style="background:<?= $occ>80?'#dc2626':($occ>50?'#d97706':'#059669') ?>;height:8px;border-radius:20px;width:<?= $occ ?>%;"></div>
                        </div>
                        <small><?= $occ ?>% full</small>
                    </td>
                    <td>
                        <a href="rooms.php?hostel_id=<?= $h['hostel_id'] ?>" class="btn btn-info btn-sm">View Rooms</a>
                        <a href="?delete_hostel=<?= $h['hostel_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this hostel?')">Delete</a>
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
