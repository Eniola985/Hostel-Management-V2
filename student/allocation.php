<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../includes/db.php';
require_once '../includes/csrf.php';

$sid = (int)$_SESSION['student_id'];

$studentStmt = $pdo->prepare("
    SELECT *
    FROM students
    WHERE student_id = ?
    LIMIT 1
");
$studentStmt->execute([$sid]);
$student = $studentStmt->fetch();

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$msg = '';
$err = '';

/*
 * Finalize bunk selection.
 * The student can only choose a bunk after an administrator
 * has assigned a room.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['choose_bunk'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $err = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $bunk_number = trim((string)($_POST['bunk_number'] ?? ''));

        if ($bunk_number === '' || !ctype_digit($bunk_number) || (int)$bunk_number < 1) {
            $err = 'Please select a valid bunk.';
        } else {
            $bunk_number = (string)(int)$bunk_number;

            try {
                $pdo->beginTransaction();

                /*
                 * Lock the student's approved application and obtain
                 * the room assigned by the administrator.
                 */
                $appStmt = $pdo->prepare("
                    SELECT
                        a.*,
                        h.hostel_name,
                        h.hostel_type,
                        h.uses_bunks,
                        r.room_number,
                        r.capacity,
                        r.occupied
                    FROM applications a
                    JOIN hostels h ON a.hostel_id = h.hostel_id
                    JOIN rooms r ON a.preferred_room_id = r.room_id
                    WHERE a.student_id = ?
                      AND a.status = 'Approved'
                      AND a.preferred_room_id IS NOT NULL
                    ORDER BY a.applied_at DESC
                    LIMIT 1
                    FOR UPDATE
                ");
                $appStmt->execute([$sid]);
                $application = $appStmt->fetch();

                if (!$application) {
                    throw new RuntimeException(
                        'Your room has not been assigned yet, or your allocation has already been completed.'
                    );
                }

                if ((int)$application['uses_bunks'] !== 1) {
                    throw new RuntimeException(
                        'This hostel does not use bunks. Your room allocation does not require bunk selection.'
                    );
                }

                $room_id = (int)$application['preferred_room_id'];
                $capacity = (int)$application['capacity'];

                if ($capacity < 1) {
                    throw new RuntimeException('The assigned room has an invalid capacity.');
                }

                if ((int)$application['uses_bunks'] !== 1 && (int)$application['occupied'] >= $capacity) {
                    throw new RuntimeException(
                        'There are no remaining spaces in the assigned room.'
                    );
                }

                if ((int)$bunk_number > $capacity) {
                    throw new RuntimeException(
                        'The selected bunk is not valid for this room.'
                    );
                }

                /*
                 * Lock the room before checking occupancy/bunk usage.
                 */
                $roomStmt = $pdo->prepare("
                    SELECT r.*, h.uses_bunks
                    FROM rooms r
                    JOIN hostels h ON r.hostel_id = h.hostel_id
                    WHERE r.room_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $roomStmt->execute([$room_id]);
                $room = $roomStmt->fetch();

                if (!$room) {
                    throw new RuntimeException('The assigned room could not be found.');
                }

                /*
                 * Count active allocations and room assignments that
                 * have already reserved a place in this room.
                 *
                 * The current admin workflow increments rooms.occupied
                 * when a bunk-enabled room is assigned, so occupied
                 * already represents the reserved slot at this stage.
                 */
                $occupied = (int)$room['occupied'];

                if ((int)$room['uses_bunks'] !== 1 && $occupied >= (int)$room['capacity']) {
                    throw new RuntimeException(
                        'There are no remaining spaces in the assigned room.'
                    );
                }

                /*
                 * Check whether this bunk has already been taken.
                 */
                $bunkStmt = $pdo->prepare("
                    SELECT allocation_id
                    FROM allocations
                    WHERE room_id = ?
                      AND bunk_number = ?
                      AND status = 'Active'
                    LIMIT 1
                    FOR UPDATE
                ");
                $bunkStmt->execute([$room_id, $bunk_number]);

                if ($bunkStmt->fetch()) {
                    throw new RuntimeException(
                        'That bunk has already been taken. Please choose another available bunk.'
                    );
                }

                /*
                 * Determine which bunk numbers are already occupied
                 * or reserved by other students.
                 */
                $usedBunksStmt = $pdo->prepare("
                    SELECT bunk_number
                    FROM allocations
                    WHERE room_id = ?
                      AND status = 'Active'
                      AND bunk_number IS NOT NULL
                ");
                $usedBunksStmt->execute([$room_id]);

                $usedBunks = [];
                while ($row = $usedBunksStmt->fetch()) {
                    $usedBunks[(string)(int)$row['bunk_number']] = true;
                }

                /*
                 * The student's own room assignment is already represented
                 * by rooms.occupied, but does not yet have an allocations row.
                 *
                 * Therefore the selected bunk must not be one of the active
                 * allocation bunks.
                 */
                if (isset($usedBunks[$bunk_number])) {
                    throw new RuntimeException(
                        'That bunk is already occupied. Please choose another available bunk.'
                    );
                }

                /*
                 * If exactly one bunk remains, selecting anything other
                 * than that final available bunk is prevented by the same
                 * availability check below.
                 */
                $availableBunks = [];

                for ($i = 1; $i <= (int)$room['capacity']; $i++) {
                    $key = (string)$i;

                    if (!isset($usedBunks[$key])) {
                        $availableBunks[] = $key;
                    }
                }

                /*
                 * The student's reserved room slot is included in occupied,
                 * but the student has not yet chosen a bunk. The number
                 * of actual available bunks therefore needs to be sufficient
                 * for the current student.
                 */
                if (count($availableBunks) < 1) {
                    throw new RuntimeException(
                        'No bunk is available in the assigned room.'
                    );
                }

                if (!in_array($bunk_number, $availableBunks, true)) {
                    throw new RuntimeException(
                        'The selected bunk is no longer available. Please choose another bunk.'
                    );
                }

                /*
                 * Create the student's final allocation.
                 */
                $insertAllocation = $pdo->prepare("
                    INSERT INTO allocations
                        (student_id, room_id, bunk_number, status)
                    VALUES
                        (?, ?, ?, 'Active')
                ");
                $insertAllocation->execute([
                    $sid,
                    $room_id,
                    $bunk_number
                ]);

                /*
                 * The room was already reserved by admin assignment,
                 * so do not increment occupied a second time.
                 *
                 * The application now becomes fully Allocated.
                 */
                $updateApp = $pdo->prepare("
                    UPDATE applications
                    SET status = 'Allocated',
                        preferred_bunk = ?
                    WHERE app_id = ?
                      AND student_id = ?
                      AND status = 'Approved'
                ");
                $updateApp->execute([
                    $bunk_number,
                    (int)$application['app_id'],
                    $sid
                ]);

                $pdo->commit();

                $msg = 'Bunk selected successfully. Your room allocation is now complete.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $err = $e->getMessage();
            }
        }
    }
}

/*
 * Get the student's latest application together with any room
 * assigned by the administrator.
 */
$appStmt = $pdo->prepare("
    SELECT
        a.*,
        h.hostel_name,
        h.hostel_type,
        h.uses_bunks,
        r.room_id,
        r.room_number,
        r.capacity,
        r.occupied
    FROM applications a
    JOIN hostels h ON a.hostel_id = h.hostel_id
    LEFT JOIN rooms r ON a.preferred_room_id = r.room_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
    LIMIT 1
");
$appStmt->execute([$sid]);
$application = $appStmt->fetch();

/*
 * Get the student's finalized active allocation, if one exists.
 */
$allocationStmt = $pdo->prepare("
    SELECT
        al.*,
        r.room_number,
        r.capacity,
        r.occupied,
        h.hostel_name,
        h.hostel_type,
        h.uses_bunks
    FROM allocations al
    JOIN rooms r ON al.room_id = r.room_id
    JOIN hostels h ON r.hostel_id = h.hostel_id
    WHERE al.student_id = ?
      AND al.status = 'Active'
    ORDER BY al.allocation_date DESC
    LIMIT 1
");
$allocationStmt->execute([$sid]);
$allocation = $allocationStmt->fetch();

/*
 * Determine available bunks when a room has been assigned
 * but the student's final bunk has not yet been selected.
 */
$availableBunks = [];

if (
    $application &&
    $application['status'] === 'Approved' &&
    !empty($application['room_id']) &&
    (int)$application['uses_bunks'] === 1 &&
    empty($application['preferred_bunk']) &&
    !$allocation
) {
    $usedBunksStmt = $pdo->prepare("
        SELECT bunk_number
        FROM allocations
        WHERE room_id = ?
          AND status = 'Active'
          AND bunk_number IS NOT NULL
    ");
    $usedBunksStmt->execute([(int)$application['room_id']]);

    $usedBunks = [];

    while ($row = $usedBunksStmt->fetch()) {
        $usedBunks[(string)(int)$row['bunk_number']] = true;
    }

    for ($i = 1; $i <= (int)$application['capacity']; $i++) {
        $key = (string)$i;

        if (!isset($usedBunks[$key])) {
            $availableBunks[] = $key;
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Room Allocation</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
@media print {
    .navbar,.sidebar,.no-print { display:none!important; }
    .wrapper { display:block; }
    .main { padding:0; }
    .print-header { display:block!important; }
}

.print-header {
    display:none;
    text-align:center;
    margin-bottom:20px;
    border-bottom:2px solid #1e3a5f;
    padding-bottom:16px;
}

@page {
    size: A4 portrait;
    margin: 10mm;
}

@media print {
    html,
    body {
        width: 210mm;
        min-height: 297mm;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #111827 !important;
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: 9pt !important;
    }

    .navbar,
    .sidebar,
    .no-print,
    .footer {
        display: none !important;
    }

    .wrapper {
        display: block !important;
        min-height: 0 !important;
    }

    .main {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    .print-header {
        display: block !important;
        text-align: center !important;
        margin: 0 0 7px !important;
        padding: 0 0 6px !important;
        border-bottom: 1.5px solid #1e3a5f !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .print-header img {
        width: 52px !important;
        height: 52px !important;
        margin: 0 0 2px !important;
    }

    .print-header h2 {
        margin: 0 !important;
        font-size: 14pt !important;
        line-height: 1.1 !important;
    }

    .print-header p {
        margin: 2px 0 !important;
        font-size: 7.5pt !important;
        line-height: 1.15 !important;
    }

    .print-header h3 {
        margin: 4px 0 1px !important;
        font-size: 10.5pt !important;
        line-height: 1.15 !important;
        letter-spacing: .3px !important;
    }

    .allocation-slip {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        border: 1.5px solid #1e3a5f !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        overflow: hidden !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .allocation-slip .card-header {
        min-height: 0 !important;
        padding: 7px 10px !important;
        background: #1e3a5f !important;
        color: #fff !important;
        border: 0 !important;
    }

    .allocation-slip .card-header h3 {
        margin: 0 !important;
        color: #fff !important;
        font-size: 10pt !important;
        line-height: 1.2 !important;
    }

    .allocation-slip .card-header .badge {
        padding: 3px 7px !important;
        font-size: 7pt !important;
    }

    .allocation-slip .card-body {
        padding: 9px !important;
    }

    .allocation-summary {
        display: block !important;
        margin: 0 0 8px !important;
        padding: 8px !important;
        background: #f8fafc !important;
        border-bottom: 1px solid #dbe4ee !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .allocation-summary > div:nth-child(1) {
        font-size: 0 !important;
        margin-bottom: 2px !important;
    }

    .allocation-summary > div:nth-child(1)::after {
        content: "ROOM ALLOCATION";
        font-size: 7pt !important;
        font-weight: 700 !important;
        letter-spacing: 1px !important;
        color: #64748b !important;
    }

    .allocation-summary > div:nth-child(2) {
        margin: 0 !important;
        font-size: 21pt !important;
        line-height: 1.05 !important;
        font-weight: 700 !important;
        color: #1e3a5f !important;
    }

    .allocation-summary > div:nth-child(3) {
        margin: 2px 0 0 !important;
        font-size: 10pt !important;
        line-height: 1.2 !important;
        color: #059669 !important;
    }

    .allocation-summary > div:nth-child(4) {
        margin: 2px 0 0 !important;
        font-size: 7.5pt !important;
        line-height: 1.2 !important;
        color: #64748b !important;
    }

    .student-profile-print {
        display: grid !important;
        grid-template-columns: 55px 1fr !important;
        align-items: center !important;
        gap: 9px !important;
        margin: 0 0 8px !important;
        padding: 7px !important;
        background: #f8fafc !important;
        border: 1px solid #dbe4ee !important;
        border-radius: 5px !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .student-profile-print > div:first-child {
        width: 55px !important;
        height: 68px !important;
        border-radius: 4px !important;
    }

    .student-profile-print img {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
    }

    .student-profile-print span {
        font-size: 17pt !important;
    }

    .student-profile-print > div:last-child {
        min-width: 0 !important;
    }

    .student-profile-print > div:last-child > div:first-child {
        margin-bottom: 2px !important;
        font-size: 6.5pt !important;
    }

    .student-profile-print > div:last-child > div:last-child {
        font-size: 10pt !important;
        line-height: 1.2 !important;
    }

    .student-details {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        gap: 0 !important;
        margin: 0 !important;
        border: 1px solid #dbe4ee !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .student-details > div {
        min-width: 0 !important;
        padding: 5px 7px !important;
        border-right: 1px solid #dbe4ee !important;
        border-bottom: 1px solid #dbe4ee !important;
    }

    .student-details > div:nth-child(even) {
        border-right: 0 !important;
    }

    .student-details > div > div:first-child {
        margin-bottom: 2px !important;
        font-size: 6.5pt !important;
        line-height: 1.05 !important;
        color: #64748b !important;
    }

    .student-details > div > div:last-child {
        font-size: 8pt !important;
        line-height: 1.2 !important;
        font-weight: 600 !important;
        color: #111827 !important;
        overflow-wrap: anywhere !important;
    }

    .allocation-note {
        margin-top: 7px !important;
        padding: 6px 8px !important;
        border-radius: 4px !important;
        font-size: 7pt !important;
        line-height: 1.3 !important;
        color: #065f46 !important;
        background: #f0fdf4 !important;
        border: 1px solid #bbf7d0 !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .allocation-note strong {
        font-size: 7pt !important;
    }
}
.bunk-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(90px,1fr));
    gap:12px;
    margin-top:16px;
}

.bunk-option {
    position:relative;
}

.bunk-option input {
    position:absolute;
    opacity:0;
    pointer-events:none;
}

.bunk-option label {
    display:block;
    padding:16px 10px;
    border:2px solid #cbd5e1;
    border-radius:8px;
    text-align:center;
    cursor:pointer;
    font-weight:600;
    background:#fff;
}

.bunk-option input:checked + label {
    border-color:#1e3a5f;
    background:#eff6ff;
}

.bunk-option label:hover {
    border-color:#64748b;
}
</style>
</head>
<body>
<div class="wrapper">

<aside class="sidebar no-print">
    <div class="user-info">
        <div class="avatar"><?php if (!empty($student['profile_photo'])): ?><img src="../<?= htmlspecialchars($student['profile_photo']) ?>" alt="Student photograph" style="width:100%;height:100%;object-fit:cover;border-radius:50%;"><?php else: ?><?= strtoupper(substr($student['full_name'], 0, 1)) ?><?php endif; ?></div>
        <div class="name"><?= htmlspecialchars(explode(' ', $student['full_name'])[0]) ?></div>
       <div class="role"><?= htmlspecialchars($student['form_no']) ?></div>    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="apply.php">My Room Allocation</a>
        <a href="payments.php">Payments</a>
        <a href="reports.php">My Report</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
    </nav>
</aside>

<main class="main">

    <div class="page-title no-print">My Room Allocation</div>
    <div class="page-subtitle no-print">
        View your accommodation application, room assignment and final allocation.
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

    <?php if (!$application): ?>

        <div class="alert alert-info">
            You have not submitted a hostel application yet.
            <a href="apply.php"><strong>Apply for hostel accommodation &rarr;</strong></a>
        </div>

    <?php elseif ($application['status'] === 'Rejected'): ?>

        <div class="alert alert-error">
            <strong>Your hostel application was rejected.</strong>

            <?php if (!empty($application['rejection_reason'])): ?>
                <div style="margin-top:8px;">
                    Reason:
                    <?= htmlspecialchars($application['rejection_reason']) ?>
                </div>
            <?php endif; ?>

            <div style="margin-top:12px;">
                Please contact the hostel management office for further information.
            </div>
        </div>

    <?php elseif ($allocation): ?>

        <!-- Print Header -->
        <div class="print-header">
            <img src="../assets/POLYLOGO.jpg"
                 alt="The Polytechnic, Ibadan Logo"
                 style="width:90px;height:90px;object-fit:contain;margin-bottom:10px;">

            <h2>THE POLYTECHNIC, IBADAN</h2>
            <p>Computerized Hostel Accommodation Management System</p>
            <h3>ROOM ALLOCATION NOTICE</h3>
            <p>Session: <?= date('Y') ?>/<?= date('Y') + 1 ?></p>
        </div>

        <div class="no-print" style="margin-bottom:16px;">
            <button onclick="window.print()" class="btn btn-info">
                Print Allocation Letter
            </button>
        </div>

        <!-- Allocation Card -->
        <div class="card allocation-slip" style="border:2px solid #059669;max-width:640px;">
            <div class="card-header"
                 style="background:linear-gradient(135deg,#1e3a5f,#2d6a4f);color:white;">
                <h3 style="color:white;">Room Allocation Confirmed</h3>
                <span class="badge badge-success"
                      style="background:rgba(255,255,255,0.2);color:white;">
                    Active
                </span>
            </div>

            <div class="card-body">

                <div class="allocation-summary" style="text-align:center;padding:24px 0;border-bottom:1px solid #f1f5f9;margin-bottom:24px;">
                    <div style="font-size:4rem;margin-bottom:8px;">Room</div>

                    <div style="font-size:2.5rem;font-weight:700;color:#1e3a5f;">
                        Room <?= htmlspecialchars($allocation['room_number']) ?>
                    </div>

                    <div style="font-size:1.1rem;color:#059669;font-weight:600;">
                        <?= htmlspecialchars($allocation['hostel_name']) ?>
                    </div>

                    <div style="font-size:0.875rem;color:#64748b;">
                        <?= htmlspecialchars($allocation['hostel_type']) ?> Hostel
                        <?php if (!empty($allocation['bunk_number'])): ?>
                            , Bunk <?= htmlspecialchars($allocation['bunk_number']) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="student-profile-print" style="display:flex;align-items:center;gap:18px;margin-bottom:24px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">                    <div style="width:90px;height:110px;border-radius:8px;overflow:hidden;border:1px solid #cbd5e1;background:#e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">                        <?php if (!empty($student['profile_photo'])): ?>                            <img src="../<?= htmlspecialchars($student['profile_photo']) ?>" alt="Student photograph" style="width:100%;height:100%;object-fit:cover;">                        <?php else: ?>                            <span style="font-size:2rem;font-weight:700;color:#64748b;"><?= strtoupper(substr($student['full_name'], 0, 1)) ?></span>                        <?php endif; ?>                    </div>                    <div>                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">Student Photograph</div>                        <div style="font-weight:600;color:#1e3a5f;"><?= htmlspecialchars($student['full_name']) ?></div>                    </div>                </div>                <div class="student-details" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Student Name
                        </div>
                        <div style="font-weight:600;">
                            <?= htmlspecialchars($student['full_name']) ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Form Number
                        </div>
                        <div style="font-weight:600;">
                           <?= htmlspecialchars($student['form_no']) ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Department
                        </div>
                        <div style="font-weight:600;">
                            <?= htmlspecialchars($student['department']) ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Level
                        </div>
                        <div style="font-weight:600;">
                            <?= htmlspecialchars($student['level']) ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Room Capacity
                        </div>
                        <div style="font-weight:600;">
                            <?= (int)$allocation['capacity'] ?> students
                        </div>
                    </div>

                    <?php if (!empty($allocation['bunk_number'])): ?>
                        <div>
                            <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                                Bunk
                            </div>
                            <div style="font-weight:600;">
                                Bunk <?= htmlspecialchars($allocation['bunk_number']) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Current Occupants
                        </div>
                        <div style="font-weight:600;">
                            <?= (int)$allocation['occupied'] ?> of <?= (int)$allocation['capacity'] ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Date Allocated
                        </div>
                        <div style="font-weight:600;">
                            <?= date('d F Y', strtotime($allocation['allocation_date'])) ?>
                        </div>
                    </div>

                    <div>
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;margin-bottom:4px;">
                            Academic Session
                        </div>
                        <div style="font-weight:600;">
                            <?= date('Y') ?>/<?= date('Y') + 1 ?>
                        </div>
                    </div>

                </div>

                <div class="allocation-note" style="margin-top:24px;padding:14px;background:#f0fdf4;border-radius:8px;font-size:0.875rem;color:#065f46;border:1px solid #bbf7d0;">
                    <strong>Note:</strong>
                    Please report to the hostel management office with this allocation notice,
                    your student ID card, and your payment receipt to collect your room key
                    and complete the check-in process.
                </div>

            </div>
        </div>

    <?php elseif (
        $application['status'] === 'Approved' &&
        !empty($application['room_id']) &&
        (int)$application['uses_bunks'] === 1
    ): ?>

        <div class="card" style="max-width:640px;">
            <div class="card-header">
                <h3>Select Your Bunk</h3>
            </div>

            <div class="card-body">

                <div class="alert alert-info">
                    <strong>Your room has been assigned.</strong><br>
                    Hostel:
                    <?= htmlspecialchars($application['hostel_name']) ?><br>
                    Room:
                    <?= htmlspecialchars($application['room_number']) ?><br>
                    Capacity:
                    <?= (int)$application['capacity'] ?> students
                </div>

                <?php if (count($availableBunks) === 1): ?>

                    <div class="alert alert-success">
                        <strong>One bunk remains in this room.</strong><br>
                        The system will assign the remaining bunk automatically.
                    </div>

                    <form method="post">
                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($csrf) ?>">

                        <input type="hidden"
                               name="bunk_number"
                               value="<?= htmlspecialchars($availableBunks[0]) ?>">

                        <button type="submit"
                                name="choose_bunk"
                                class="btn btn-primary">
                            Assign Remaining Bunk <?= htmlspecialchars($availableBunks[0]) ?>
                        </button>
                    </form>

                <?php elseif (count($availableBunks) > 1): ?>

                    <p>
                        Choose one of the available bunks in your assigned room.
                    </p>

                    <form method="post">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars($csrf) ?>">

                        <div class="bunk-grid">
                            <?php foreach ($availableBunks as $bunk): ?>
                                <div class="bunk-option">
                                    <input type="radio"
                                           id="bunk_<?= htmlspecialchars($bunk) ?>"
                                           name="bunk_number"
                                           value="<?= htmlspecialchars($bunk) ?>"
                                           required>

                                    <label for="bunk_<?= htmlspecialchars($bunk) ?>">
                                        Bunk <?= htmlspecialchars($bunk) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top:20px;">
                            <button type="submit"
                                    name="choose_bunk"
                                    class="btn btn-primary">
                                Confirm Bunk Selection
                            </button>
                        </div>

                    </form>

                <?php else: ?>

                    <div class="alert alert-error">
                        No bunk is currently available in the assigned room.
                        Please contact hostel management.
                    </div>

                <?php endif; ?>

            </div>
        </div>

    <?php elseif (
        $application['status'] === 'Approved' &&
        empty($application['room_id'])
    ): ?>

        <div class="alert alert-info">
            <strong>Your application has been approved.</strong><br>
            Your hostel room has not been assigned yet.
            Please check back later.
        </div>

    <?php elseif ($application['status'] === 'Pending'): ?>

        <div class="alert alert-info">
            <strong>Your application has been submitted successfully.</strong><br>
            It is currently awaiting administrator approval.
            You will be able to see your room assignment here after approval.
        </div>

    <?php else: ?>

        <div class="alert alert-info">
            Your accommodation application is currently being processed.
            Please check back later.
        </div>

    <?php endif; ?>

</main>
</div>

<div class="footer no-print">
    &copy; <?= date('Y') ?> The Polytechnic, Ibadan
</div>

</body>
</html>
