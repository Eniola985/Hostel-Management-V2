<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$msg = $err = '';
$preselect_student = (int)($_GET['student_id'] ?? 0);

// Allocate room
if (isset($_POST['allocate'])) {
    $student_id = (int)$_POST['student_id'];
    $room_id = (int)$_POST['room_id'];
    $bunk_number = trim($_POST['bunk_number'] ?? '');

    // Check if student already allocated
    $existing = $pdo->prepare("SELECT * FROM allocations WHERE student_id=? AND status='Active'");
    $existing->execute([$student_id]);
    if ($existing->fetch()) {
        $err = 'This student already has an active room allocation.';
    } else {
        // Check room availability
        $room = $pdo->prepare("SELECT * FROM rooms WHERE room_id=?");
        $room->execute([$room_id]);
        $room = $room->fetch();
        $hostel = $pdo->prepare("SELECT h.hostel_type FROM hostels h JOIN rooms r ON r.hostel_id=h.hostel_id WHERE r.room_id=?");
        $hostel->execute([$room_id]);
        $hostel = $hostel->fetch();
        $bunk_taken = false;
        if ($bunk_number) {
            $bunk_check = $pdo->prepare("SELECT COUNT(*) FROM allocations WHERE room_id=? AND bunk_number=? AND status='Active'");
            $bunk_check->execute([$room_id, $bunk_number]);
            $bunk_taken = (bool)$bunk_check->fetchColumn();
        }
        if ($room && $room['status'] === 'Available' && !$bunk_taken && (!$bunk_number || ($hostel['hostel_type'] === 'Female' && in_array($bunk_number, ['1', '2', '3'], true)))) {
            $pdo->prepare("INSERT INTO allocations (student_id, room_id, bunk_number) VALUES (?,?,?)")->execute([$student_id, $room_id, $bunk_number ?: null]);
            $new_occ = $room['occupied'] + 1;
            $new_status = $new_occ >= $room['capacity'] ? 'Full' : 'Available';
            $pdo->prepare("UPDATE rooms SET occupied=?, status=? WHERE room_id=?")->execute([$new_occ, $new_status, $room_id]);
            $pdo->prepare("UPDATE applications SET status='Allocated' WHERE student_id=?")->execute([$student_id]);
            $msg = 'Room allocated successfully!';
        } else {
            $err = $bunk_taken ? 'Selected bunk is already occupied.' : 'Selected room or bunk is not available.';
        }
    }
}

// Vacate room
if (isset($_GET['vacate'])) {
    $alloc_id = (int)$_GET['vacate'];
    $alloc = $pdo->prepare("SELECT * FROM allocations WHERE allocation_id=?");
    $alloc->execute([$alloc_id]);
    $alloc = $alloc->fetch();
    if ($alloc) {
        $pdo->prepare("UPDATE allocations SET status='Vacated' WHERE allocation_id=?")->execute([$alloc_id]);
        $room = $pdo->prepare("SELECT * FROM rooms WHERE room_id=?");
        $room->execute([$alloc['room_id']]);
        $room = $room->fetch();
        $new_occ = max(0, $room['occupied'] - 1);
        $pdo->prepare("UPDATE rooms SET occupied=?, status='Available' WHERE room_id=?")->execute([$new_occ, $alloc['room_id']]);
        $msg = 'Room vacated successfully.';
    }
}

$allocs = $pdo->query("SELECT a.*, s.full_name, s.matric_no, s.department, s.level, r.room_number, h.hostel_name 
    FROM allocations a 
    JOIN students s ON a.student_id=s.student_id 
    JOIN rooms r ON a.room_id=r.room_id 
    JOIN hostels h ON r.hostel_id=h.hostel_id 
    ORDER BY a.allocation_date DESC")->fetchAll();

// Students with approved applications not yet allocated
$unalloc = $pdo->query("SELECT s.*, ap.preferred_room_id, ap.preferred_bunk FROM students s 
    JOIN applications ap ON s.student_id=ap.student_id 
    LEFT JOIN allocations al ON s.student_id=al.student_id AND al.status='Active'
    WHERE ap.status='Approved' AND al.allocation_id IS NULL")->fetchAll();

$avail_rooms = $pdo->query("SELECT r.*, h.hostel_name, h.hostel_type FROM rooms r JOIN hostels h ON r.hostel_id=h.hostel_id WHERE r.status='Available' ORDER BY h.hostel_name, r.room_number")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allocations - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="hostels.php">Hostels</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="allocations.php" class="active">Allocations</a></li>
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
        <a href="hostels.php">🏨 Manage Hostels</a>
        <a href="students.php">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php" class="active">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Room Allocations</div>
    <div class="page-subtitle">Assign rooms to approved students</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if (!empty($unalloc)): ?>
    <div class="form-card" style="max-width:100%;margin-bottom:24px;">
        <h3>🛏 Allocate Room to Student</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Select Student (Approved)</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($unalloc as $s): ?>
                            <option value="<?= $s['student_id'] ?>" data-room="<?= $s['preferred_room_id'] ?? '' ?>" data-bunk="<?= htmlspecialchars($s['preferred_bunk'] ?? '') ?>" <?= $preselect_student==$s['student_id']?'selected':'' ?>>
                                <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['matric_no']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Available Room</label>
                    <select name="room_id" id="allocation-room" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($avail_rooms as $r): ?>
                            <option value="<?= $r['room_id'] ?>">
                                <?= htmlspecialchars($r['hostel_name']) ?> (<?= $r['hostel_type'] ?>), Room <?= htmlspecialchars($r['room_number']) ?> (<?= $r['occupied'] ?>/<?= $r['capacity'] ?> occupied)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bunk (ladies hostel only)</label>
                    <select name="bunk_number" id="allocation-bunk">
                        <option value="">No bunk selection</option>
                        <option value="1">Bunk 1</option>
                        <option value="2">Bunk 2</option>
                        <option value="3">Bunk 3</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="allocate" class="btn btn-success">Allocate Room</button>
        </form>
    </div>
    <?php else: ?>
    <div class="alert alert-info">No approved students awaiting room allocation. <a href="applications.php">Review applications</a> first.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>All Allocations (<?= count($allocs) ?>)</h3></div>
        <div class="table-wrap">
            <?php if (empty($allocs)): ?>
                <div class="empty-state"><span class="empty-icon">🛏</span><p>No allocations yet.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Student</th><th>Matric No</th><th>Dept / Level</th><th>Hostel</th><th>Room</th><th>Date Allocated</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($allocs as $i => $a): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($a['full_name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['matric_no']) ?></td>
                    <td><?= htmlspecialchars($a['department']) ?> / <?= $a['level'] ?></td>
                    <td><?= htmlspecialchars($a['hostel_name']) ?></td>
                    <td>Room <?= htmlspecialchars($a['room_number']) ?><?= $a['bunk_number'] ? ', Bunk ' . htmlspecialchars($a['bunk_number']) : '' ?></td>
                    <td><?= date('d M Y', strtotime($a['allocation_date'])) ?></td>
                    <td><span class="badge <?= $a['status']==='Active'?'badge-success':'badge-secondary' ?>"><?= $a['status'] ?></span></td>
                    <td>
                        <?php if ($a['status'] === 'Active'): ?>
                            <a href="?vacate=<?= $a['allocation_id'] ?>" class="btn btn-warning btn-sm" onclick="return confirm('Vacate this room?')">Vacate</a>
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
<script>
const studentSelect = document.querySelector('select[name="student_id"]');
const roomSelect = document.getElementById('allocation-room');
const bunkSelect = document.getElementById('allocation-bunk');
if (studentSelect) {
    studentSelect.addEventListener('change', () => {
        const student = studentSelect.selectedOptions[0];
        if (student.dataset.room) roomSelect.value = student.dataset.room;
        if (student.dataset.bunk) bunkSelect.value = student.dataset.bunk;
    });
    if (studentSelect.value) studentSelect.dispatchEvent(new Event('change'));
}
</script>
</body>
</html>
