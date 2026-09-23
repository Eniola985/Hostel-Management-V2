<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$hostel_id = (int)($_GET['hostel_id'] ?? 0);
$msg = '';

if (isset($_GET['delete_room'])) {
    $pdo->prepare("DELETE FROM rooms WHERE room_id=?")->execute([$_GET['delete_room']]);
    $msg = 'Room deleted.';
}

$hostel = $pdo->prepare("SELECT * FROM hostels WHERE hostel_id=?");
$hostel->execute([$hostel_id]);
$hostel = $hostel->fetch();

if (!$hostel) { header('Location: hostels.php'); exit; }

$rooms = $pdo->prepare("SELECT r.*, COUNT(a.allocation_id) as alloc_count FROM rooms r LEFT JOIN allocations a ON r.room_id=a.room_id AND a.status='Active' WHERE r.hostel_id=? GROUP BY r.room_id ORDER BY r.room_number");
$rooms->execute([$hostel_id]);
$rooms = $rooms->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rooms - <?= htmlspecialchars($hostel['hostel_name']) ?></title>
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
    <div class="breadcrumb"><a href="hostels.php">Hostels</a> &rsaquo; <?= htmlspecialchars($hostel['hostel_name']) ?></div>
    <div class="page-title"><?= htmlspecialchars($hostel['hostel_name']) ?></div>
    <div class="page-subtitle"><?= $hostel['hostel_type'] ?> Hostel, <?= count($rooms) ?> rooms</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="room-grid">
        <?php foreach ($rooms as $r): ?>
        <div class="room-item <?= strtolower($r['status']) ?>">
            <div class="room-num">Room <?= htmlspecialchars($r['room_number']) ?></div>
            <div style="font-size:0.8rem;color:#64748b;margin:4px 0;"><?= $r['occupied'] ?>/<?= $r['capacity'] ?> occupied</div>
            <div class="room-status"><?= $r['status'] ?></div>
            <div style="margin-top:8px;">
                <a href="?hostel_id=<?= $hostel_id ?>&delete_room=<?= $r['room_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete room <?= $r['room_number'] ?>?')">Delete</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($rooms)): ?>
            <div class="empty-state"><span class="empty-icon">🚪</span><p>No rooms added yet. <a href="hostels.php">Add rooms</a></p></div>
        <?php endif; ?>
    </div>
    <div style="margin-top:20px;">
        <a href="hostels.php" class="btn btn-info">&larr; Back to Hostels</a>
    </div>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
