<?php
require_once '../includes/admin_auth.php';
$hostel = current_admin_hostel($pdo);

$msg = '';
$err = '';
$preselect_student = (int)($_GET['student_id'] ?? 0);

// Allocate a room only inside the currently authenticated hostel.
if (isset($_POST['allocate'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $bunk_number = trim($_POST['bunk_number'] ?? '');

    $existing = $pdo->prepare("SELECT a.allocation_id
        FROM allocations a
        JOIN rooms r ON r.room_id=a.room_id
        WHERE a.student_id=? AND a.status='Active' AND r.hostel_id=?");
    $existing->execute([$student_id, $admin_hostel_id]);

    if ($existing->fetch()) {
        $err = 'This student already has an active allocation in your hostel.';
    } else {
        $roomStmt = $pdo->prepare("SELECT r.*, h.hostel_type
            FROM rooms r JOIN hostels h ON h.hostel_id=r.hostel_id
            WHERE r.room_id=? AND r.hostel_id=?
            LIMIT 1");
        $roomStmt->execute([$room_id, $admin_hostel_id]);
        $room = $roomStmt->fetch();

        $approved = $pdo->prepare("SELECT ap.app_id
            FROM applications ap
            WHERE ap.student_id=? AND ap.hostel_id=? AND ap.status='Approved'
            LIMIT 1");
        $approved->execute([$student_id, $admin_hostel_id]);
        $approvedApplication = $approved->fetch();

        $bunk_taken = false;
        if ($bunk_number !== '') {
            $bunkCheck = $pdo->prepare("SELECT COUNT(*) FROM allocations
                WHERE room_id=? AND bunk_number=? AND status='Active'");
            $bunkCheck->execute([$room_id, $bunk_number]);
            $bunk_taken = (bool)$bunkCheck->fetchColumn();
        }

        if (!$approvedApplication) {
            $err = 'Only students with an approved application for your hostel can be allocated here.';
        } elseif (!$room || $room['status'] !== 'Available') {
            $err = 'Selected room is not available in your hostel.';
        } elseif ($bunk_taken) {
            $err = 'Selected bunk is already occupied.';
        } elseif ($bunk_number !== '' && !($room['hostel_type'] === 'Female' && in_array($bunk_number, ['1','2','3'], true))) {
            $err = 'The selected bunk is not valid for this hostel.';
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO allocations (student_id, room_id, bunk_number) VALUES (?,?,?)")
                    ->execute([$student_id, $room_id, $bunk_number ?: null]);
                $new_occ = (int)$room['occupied'] + 1;
                $new_status = $new_occ >= (int)$room['capacity'] ? 'Full' : 'Available';
                $pdo->prepare("UPDATE rooms SET occupied=?, status=? WHERE room_id=? AND hostel_id=?")
                    ->execute([$new_occ, $new_status, $room_id, $admin_hostel_id]);
                $pdo->prepare("UPDATE applications SET status='Allocated' WHERE student_id=? AND hostel_id=? AND status='Approved'")
                    ->execute([$student_id, $admin_hostel_id]);
                $pdo->commit();
                $msg = 'Room allocated successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $err = 'The room allocation could not be completed. Please try again.';
            }
        }
    }
}

// Vacate only allocations belonging to this hostel.
if (isset($_GET['vacate'])) {
    $alloc_id = (int)$_GET['vacate'];
    $allocStmt = $pdo->prepare("SELECT a.*, r.occupied, r.room_id
        FROM allocations a
        JOIN rooms r ON r.room_id=a.room_id
        WHERE a.allocation_id=? AND r.hostel_id=? AND a.status='Active'");
    $allocStmt->execute([$alloc_id, $admin_hostel_id]);
    $alloc = $allocStmt->fetch();

    if (!$alloc) {
        $err = 'Allocation not found in your hostel.';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE allocations SET status='Vacated' WHERE allocation_id=?")
                ->execute([$alloc_id]);
            $new_occ = max(0, (int)$alloc['occupied'] - 1);
            $pdo->prepare("UPDATE rooms SET occupied=?, status='Available' WHERE room_id=? AND hostel_id=?")
                ->execute([$new_occ, $alloc['room_id'], $admin_hostel_id]);
            $pdo->commit();
            $msg = 'Room vacated successfully.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'The room could not be vacated. Please try again.';
        }
    }
}

$allocStmt = $pdo->prepare("SELECT a.*, s.full_name, s.matric_no, s.department, s.level,
        r.room_number, h.hostel_name
    FROM allocations a
    JOIN students s ON a.student_id=s.student_id
    JOIN rooms r ON a.room_id=r.room_id
    JOIN hostels h ON r.hostel_id=h.hostel_id
    WHERE r.hostel_id=?
    ORDER BY a.allocation_date DESC");
$allocStmt->execute([$admin_hostel_id]);
$allocs = $allocStmt->fetchAll();

$unallocStmt = $pdo->prepare("SELECT s.*, ap.preferred_room_id, ap.preferred_bunk
    FROM students s
    JOIN applications ap ON s.student_id=ap.student_id
    LEFT JOIN allocations al ON s.student_id=al.student_id AND al.status='Active'
    WHERE ap.hostel_id=? AND ap.status='Approved' AND al.allocation_id IS NULL
    ORDER BY ap.applied_at ASC");
$unallocStmt->execute([$admin_hostel_id]);
$unalloc = $unallocStmt->fetchAll();

$roomsStmt = $pdo->prepare("SELECT r.*, h.hostel_name, h.hostel_type
    FROM rooms r JOIN hostels h ON r.hostel_id=h.hostel_id
    WHERE r.hostel_id=? AND r.status='Available'
    ORDER BY r.room_number");
$roomsStmt->execute([$admin_hostel_id]);
$avail_rooms = $roomsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allocations - <?= htmlspecialchars($hostel['hostel_name']) ?></title>
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
        <a href="hostels.php">🏨 My Hostel</a>
        <a href="students.php">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php" class="active">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Room Allocations — <?= htmlspecialchars($hostel['hostel_name']) ?></div>
    <div class="page-subtitle">Allocate rooms only to approved students applying to your hostel.</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if ($unalloc): ?>
    <div class="form-card" style="max-width:100%;margin-bottom:24px;">
        <h3>🛏 Allocate Room</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Select Approved Student</label>
                    <select name="student_id" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($unalloc as $s): ?>
                        <option value="<?= (int)$s['student_id'] ?>" data-room="<?= (int)($s['preferred_room_id'] ?? 0) ?>" data-bunk="<?= htmlspecialchars($s['preferred_bunk'] ?? '') ?>" <?= $preselect_student === (int)$s['student_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['matric_no'] ?: $s['form_no']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Available Room</label>
                    <select name="room_id" id="allocation-room" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($avail_rooms as $r): ?>
                        <option value="<?= (int)$r['room_id'] ?>">
                            Room <?= htmlspecialchars($r['room_number']) ?> (<?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?> occupied)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bunk (female hostel only)</label>
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
    <div class="alert alert-info">No approved students are awaiting allocation in <?= htmlspecialchars($hostel['hostel_name']) ?>.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>Allocations (<?= count($allocs) ?>)</h3></div>
        <div class="table-wrap">
        <?php if (!$allocs): ?>
            <div class="empty-state"><span class="empty-icon">🛏</span><p>No allocations in this hostel yet.</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>#</th><th>Student</th><th>Identifier</th><th>Dept / Level</th><th>Room</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($allocs as $i => $a): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><strong><?= htmlspecialchars($a['full_name']) ?></strong></td>
                <td><?= htmlspecialchars($a['matric_no'] ?: '') ?: htmlspecialchars($a['form_no'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['department']) ?> / <?= htmlspecialchars($a['level']) ?></td>
                <td>Room <?= htmlspecialchars($a['room_number']) ?><?= $a['bunk_number'] ? ', Bunk ' . htmlspecialchars($a['bunk_number']) : '' ?></td>
                <td><?= date('d M Y', strtotime($a['allocation_date'])) ?></td>
                <td><span class="badge <?= $a['status']==='Active' ? 'badge-success' : 'badge-secondary' ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                <td><?php if ($a['status'] === 'Active'): ?><a href="?vacate=<?= (int)$a['allocation_id'] ?>" class="btn btn-warning btn-sm" onclick="return confirm('Vacate this room?')">Vacate</a><?php endif; ?></td>
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
studentSelect?.addEventListener('change', () => {
    const student = studentSelect.selectedOptions[0];
    if (student.dataset.room) roomSelect.value = student.dataset.room;
    if (student.dataset.bunk) bunkSelect.value = student.dataset.bunk;
});
if (studentSelect?.value) studentSelect.dispatchEvent(new Event('change'));
</script>
</body>
</html>
