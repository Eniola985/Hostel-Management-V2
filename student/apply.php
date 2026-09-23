<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$sid = $_SESSION['student_id'];
$msg = $err = '';

// Check existing application
$existing = $pdo->prepare("SELECT * FROM applications WHERE student_id=?");
$existing->execute([$sid]);
$existing = $existing->fetch();

$student = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
$student->execute([$sid]);
$student = $student->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $hostel_id = (int)($_POST['hostel_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);
    $bunk_number = trim($_POST['bunk_number'] ?? '');
    $pay_ref = trim($_POST['payment_ref'] ?? '');

    // Validate hostel gender match
    $hostel = $pdo->prepare("SELECT * FROM hostels WHERE hostel_id=?");
    $hostel->execute([$hostel_id]);
    $hostel = $hostel->fetch();

    $room_check = $pdo->prepare("SELECT * FROM rooms WHERE room_id=? AND hostel_id=? AND status='Available'");
    $room_check->execute([$room_id, $hostel_id]);
    $room = $room_check->fetch();
    $bunk_taken = false;
    if ($room && $bunk_number !== '') {
        $bunk_check = $pdo->prepare("SELECT COUNT(*) FROM allocations WHERE room_id=? AND bunk_number=? AND status='Active'");
        $bunk_check->execute([$room_id, $bunk_number]);
        $bunk_taken = (bool)$bunk_check->fetchColumn();
    }

    if (!$student) {
        $err = 'Your student account could not be found. Please log in again.';
    } elseif (!$hostel) {
        $err = 'Please select a valid hostel.';
    } elseif ($pay_ref === '') {
        $err = 'Please enter your payment reference number.';
    } elseif ($hostel['hostel_type'] !== $student['gender'] && $hostel['hostel_type'] !== 'Mixed') {
        $err = 'You cannot apply to a ' . $hostel['hostel_type'] . ' hostel. Please select a hostel that matches your gender.';
    } elseif (!$room) {
        $err = 'Please select an available room in the selected hostel.';
    } elseif ($hostel['hostel_type'] === 'Female' && !in_array($bunk_number, ['1', '2', '3'], true)) {
        $err = 'Please select bunk 1, 2, or 3 for a ladies hostel.';
    } elseif ($hostel['hostel_type'] !== 'Female') {
        $bunk_number = null;
    } elseif ($bunk_taken) {
        $err = 'That bunk has just been selected by another student. Please choose another bunk.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO applications (student_id, hostel_id, preferred_room_id, preferred_bunk, payment_ref) VALUES (?,?,?,?,?)")
                ->execute([$sid, $hostel_id, $room_id, $bunk_number ?: null, $pay_ref]);
            $pdo->prepare("INSERT INTO payments (student_id, amount, payment_ref) VALUES (?,?,?)")
                ->execute([$sid, 25000, $pay_ref]);
            $pdo->commit();
            $msg = 'Application submitted successfully! The hostel administrator will review your application shortly.';
            $existing = $pdo->prepare("SELECT * FROM applications WHERE student_id=?");
            $existing->execute([$sid]);
            $existing = $existing->fetch();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = 'Your application could not be submitted. Please check your details and try again.';
        }
    }
}

$hostels = $pdo->query("SELECT * FROM hostels WHERE hostel_type='" . $student['gender'] . "' OR hostel_type='Mixed' ORDER BY hostel_name")->fetchAll();
$room_query = $pdo->prepare("SELECT r.*, h.hostel_type,
    (SELECT GROUP_CONCAT(a.bunk_number) FROM allocations a WHERE a.room_id=r.room_id AND a.status='Active' AND a.bunk_number IS NOT NULL) AS occupied_bunks
    FROM rooms r JOIN hostels h ON h.hostel_id=r.hostel_id
    WHERE r.status='Available' ORDER BY r.room_number");
$room_query->execute();
$available_rooms = $room_query->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply for Hostel</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
<aside class="sidebar">
    <div class="user-info">
        <div class="avatar"><?= strtoupper(substr($student['full_name'],0,1)) ?></div>
        <div class="name"><?= htmlspecialchars(explode(' ',$student['full_name'])[0]) ?></div>
        <div class="role"><?= htmlspecialchars($student['matric_no']) ?></div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="apply.php" class="active">📝 Apply for Hostel</a>
        <a href="status.php">📊 My Application Status</a>
        <a href="allocation.php">🛏 My Room Allocation</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📄 My Report</a>
        <a href="profile.php">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Apply for Hostel Accommodation</div>
    <div class="page-subtitle">Submit your hostel accommodation application</div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if ($existing): ?>
    <div class="alert alert-info">
        You have already submitted an application. Current status: <strong><?= htmlspecialchars($existing['status']) ?></strong>.
        <a href="status.php">View your application &rarr;</a>
    </div>
    <?php else: ?>

    <div class="alert alert-warning">
        <strong>Important:</strong> Before applying, ensure you have paid your hostel accommodation fee at the bank and have your payment teller reference number ready.
        Hostel fee: <strong>₦25,000</strong> per session.
    </div>

    <div class="form-card" style="max-width:100%;">
        <h3>📝 Hostel Application Form</h3>
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;padding:16px;background:#f8fafc;border-radius:8px;">
                <div><strong>Name:</strong> <?= htmlspecialchars($student['full_name']) ?></div>
                <div><strong>Matric No:</strong> <?= htmlspecialchars($student['matric_no']) ?></div>
                <div><strong>Department:</strong> <?= htmlspecialchars($student['department']) ?></div>
                <div><strong>Level:</strong> <?= htmlspecialchars($student['level']) ?></div>
                <div><strong>Gender:</strong> <?= htmlspecialchars($student['gender']) ?></div>
            </div>

            <div class="form-group">
                <label>Select Preferred Hostel</label>
                <select name="hostel_id" id="hostel_id" required>
                    <option value="">-- Select Hostel --</option>
                    <?php foreach ($hostels as $h): 
                        $avail = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE hostel_id=? AND status='Available'");
                        $avail->execute([$h['hostel_id']]);
                        $avail_count = $avail->fetchColumn();
                    ?>
                    <option value="<?= $h['hostel_id'] ?>">
                        <?= htmlspecialchars($h['hostel_name']) ?> (<?= $h['hostel_type'] ?>), <?= $avail_count ?> rooms available
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Select Available Room</label>
                    <select name="room_id" id="room_id" required>
                        <option value="">-- Select a hostel first --</option>
                        <?php foreach ($available_rooms as $room): ?>
                        <option value="<?= $room['room_id'] ?>" data-hostel="<?= $room['hostel_id'] ?>" data-type="<?= htmlspecialchars($room['hostel_type']) ?>" data-bunks="<?= htmlspecialchars($room['occupied_bunks'] ?? '') ?>">
                            Room <?= htmlspecialchars($room['room_number']) ?> (<?= $room['occupied'] ?>/<?= $room['capacity'] ?> occupied)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="bunk-group" hidden>
                    <label>Select Bunk</label>
                    <select name="bunk_number" id="bunk_number">
                        <option value="">-- Select a bunk --</option>
                        <option value="1">Bunk 1</option>
                        <option value="2">Bunk 2</option>
                        <option value="3">Bunk 3</option>
                    </select>
                    <small>Available bunks are shown for ladies hostels.</small>
                </div>
            </div>

            <div class="form-group">
                <label>Bank Teller Receipt / Reference Number</label>
                <input type="text" name="payment_ref" placeholder="e.g. TRF202400001" required>
                <small style="color:#64748b;font-size:0.8rem;">Enter the teller receipt or transaction reference printed by the bank.</small>
            </div>

            <div style="padding:14px;background:#fef3c7;border-radius:8px;margin-bottom:16px;font-size:0.875rem;color:#92400e;">
                <strong>Declaration:</strong> I hereby confirm that all information provided is accurate and that I have paid the required hostel accommodation fee for this session.
            </div>

            <button type="submit" class="btn btn-primary">Submit Application</button>
        </form>
    </div>
    <?php endif; ?>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
<script>
const hostelSelect = document.getElementById('hostel_id');
const roomSelect = document.getElementById('room_id');
const bunkGroup = document.getElementById('bunk-group');
const bunkSelect = document.getElementById('bunk_number');

function updateRoomChoices() {
    const hostelId = hostelSelect.value;
    let firstRoom = '';
    Array.from(roomSelect.options).forEach(option => {
        const visible = !option.value || option.dataset.hostel === hostelId;
        option.hidden = !visible;
        if (visible && option.value && !firstRoom) firstRoom = option.value;
    });
    if (!roomSelect.value || roomSelect.selectedOptions[0].hidden) roomSelect.value = firstRoom;
    updateBunkChoices();
}

function updateBunkChoices() {
    const selected = roomSelect.selectedOptions[0];
    const ladiesRoom = selected && selected.dataset.type === 'Female';
    bunkGroup.hidden = !ladiesRoom;
    bunkSelect.required = ladiesRoom;
    const occupied = selected ? (selected.dataset.bunks || '').split(',') : [];
    Array.from(bunkSelect.options).forEach(option => {
        option.hidden = option.value && occupied.includes(option.value);
    });
    if (bunkSelect.selectedOptions[0] && bunkSelect.selectedOptions[0].hidden) bunkSelect.value = '';
    if (!ladiesRoom) bunkSelect.value = '';
}

hostelSelect.addEventListener('change', updateRoomChoices);
roomSelect.addEventListener('change', updateBunkChoices);
updateRoomChoices();
</script>
</html>
