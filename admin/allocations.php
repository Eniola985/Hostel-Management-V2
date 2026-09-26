<?php
require_once '../includes/admin_auth.php';

$hostel = current_admin_hostel($pdo);

$msg = '';
$err = '';
$preselect_student = (int)($_GET['student_id'] ?? 0);

// Assign a room to an approved student.
// For bunk-enabled hostels, this only assigns the room.
// The student chooses the bunk later.
if (isset($_POST['allocate'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($student_id <= 0 || $room_id <= 0) {
        $err = 'Please select a student and room.';
    } else {
        try {
            $pdo->beginTransaction();

            // Confirm the student has an approved application in this hostel.
            $approved = $pdo->prepare("SELECT ap.app_id
                FROM applications ap
                WHERE ap.student_id=?
                  AND ap.hostel_id=?
                  AND ap.status='Approved'
                  AND ap.preferred_room_id IS NULL
                LIMIT 1
                FOR UPDATE");
            $approved->execute([$student_id, $admin_hostel_id]);
            $approvedApplication = $approved->fetch();

            if (!$approvedApplication) {
                throw new RuntimeException(
                    'Only students with an approved application awaiting room assignment can be allocated here.'
                );
            }

            // Lock the room row so concurrent allocations cannot overfill it.
            $roomStmt = $pdo->prepare("SELECT r.*, h.hostel_name, h.hostel_type, h.uses_bunks
                FROM rooms r
                JOIN hostels h ON h.hostel_id=r.hostel_id
                WHERE r.room_id=?
                  AND r.hostel_id=?
                LIMIT 1
                FOR UPDATE");
            $roomStmt->execute([$room_id, $admin_hostel_id]);
            $room = $roomStmt->fetch();

            if (!$room) {
                throw new RuntimeException(
                    'Selected room is not available in your hostel.'
                );
            }

            if ($room['status'] !== 'Available') {
                throw new RuntimeException(
                    'Selected room is already full.'
                );
            }

            // Recalculate the actual active allocation count while the room is locked.
            $occupancyStmt = $pdo->prepare("SELECT COUNT(*)
                FROM allocations
                WHERE room_id=?
                  AND status='Active'");
            $occupancyStmt->execute([$room_id]);
            $actualOccupied = (int)$occupancyStmt->fetchColumn();

            if ($actualOccupied >= (int)$room['capacity']) {
                $pdo->prepare("UPDATE rooms SET occupied=?, status='Full' WHERE room_id=?")
                    ->execute([$actualOccupied, $room_id]);

                throw new RuntimeException(
                    'Selected room has no available space.'
                );
            }

            // Make sure the student's application has not somehow acquired
            // an active allocation elsewhere.
            $activeAllocation = $pdo->prepare("SELECT a.allocation_id
                FROM allocations a
                JOIN rooms r ON r.room_id=a.room_id
                WHERE a.student_id=?
                  AND a.status='Active'
                LIMIT 1");
            $activeAllocation->execute([$student_id]);

            if ($activeAllocation->fetch()) {
                throw new RuntimeException(
                    'This student already has an active room allocation.'
                );
            }

            if ((int)$room['uses_bunks'] === 1) {
                // Bunk-enabled hostel:
                // Assign only the room. The student chooses the bunk later.
                $pdo->prepare("UPDATE applications
                    SET preferred_room_id=?, preferred_bunk=NULL
                    WHERE app_id=?
                      AND hostel_id=?
                      AND status='Approved'")
                    ->execute([
                        $room_id,
                        $approvedApplication['app_id'],
                        $admin_hostel_id
                    ]);

                // Keep the room occupancy synchronized.
                $newOccupied = $actualOccupied + 1;
                $newStatus = $newOccupied >= (int)$room['capacity']
                    ? 'Full'
                    : 'Available';

                // Do not create an allocation yet because the student
                // still needs to choose a bunk.
                $pdo->prepare("UPDATE rooms
                    SET occupied=?, status=?
                    WHERE room_id=?
                      AND hostel_id=?")
                    ->execute([
                        $newOccupied,
                        $newStatus,
                        $room_id,
                        $admin_hostel_id
                    ]);

                $pdo->commit();

                $msg = 'Room assigned successfully. The student can now choose an available bunk.';
            } else {
                // No-bunk hostel:
                // Room assignment completes the allocation immediately.
                $pdo->prepare("INSERT INTO allocations
                    (student_id, room_id, bunk_number)
                    VALUES (?,?,NULL)")
                    ->execute([$student_id, $room_id]);

                $newOccupied = $actualOccupied + 1;
                $newStatus = $newOccupied >= (int)$room['capacity']
                    ? 'Full'
                    : 'Available';

                $pdo->prepare("UPDATE rooms
                    SET occupied=?, status=?
                    WHERE room_id=?
                      AND hostel_id=?")
                    ->execute([
                        $newOccupied,
                        $newStatus,
                        $room_id,
                        $admin_hostel_id
                    ]);

                $pdo->prepare("UPDATE applications
                    SET status='Allocated',
                        preferred_room_id=?,
                        preferred_bunk=NULL
                    WHERE app_id=?
                      AND hostel_id=?
                      AND status='Approved'")
                    ->execute([
                        $room_id,
                        $approvedApplication['app_id'],
                        $admin_hostel_id
                    ]);

                $pdo->commit();

                $msg = 'Room allocated successfully.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $err = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The room assignment could not be completed. Please try again.';
        }
    }
}

// Vacate only allocations belonging to this hostel.
if (isset($_GET['vacate'])) {
    $alloc_id = (int)$_GET['vacate'];

    $allocStmt = $pdo->prepare("SELECT a.*, r.occupied, r.room_id
        FROM allocations a
        JOIN rooms r ON r.room_id=a.room_id
        WHERE a.allocation_id=?
          AND r.hostel_id=?
          AND a.status='Active'");
    $allocStmt->execute([$alloc_id, $admin_hostel_id]);
    $alloc = $allocStmt->fetch();

    if (!$alloc) {
        $err = 'Allocation not found in your hostel.';
    } else {
        $pdo->beginTransaction();

        try {
            $pdo->prepare("UPDATE allocations
                SET status='Vacated'
                WHERE allocation_id=?")
                ->execute([$alloc_id]);

            $new_occ = max(0, (int)$alloc['occupied'] - 1);

            $pdo->prepare("UPDATE rooms
                SET occupied=?,
                    status='Available'
                WHERE room_id=?
                  AND hostel_id=?")
                ->execute([
                    $new_occ,
                    $alloc['room_id'],
                    $admin_hostel_id
                ]);

            $pdo->commit();

            $msg = 'Room vacated successfully.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $err = 'The room could not be vacated. Please try again.';
        }
    }
}

// Current active and vacated allocations in this hostel.
$allocStmt = $pdo->prepare("SELECT a.*, s.full_name, s.form_no, s.department, s.level,
        r.room_number, h.hostel_name
    FROM allocations a
    JOIN students s ON a.student_id=s.student_id
    JOIN rooms r ON a.room_id=r.room_id
    JOIN hostels h ON r.hostel_id=h.hostel_id
    WHERE r.hostel_id=?
    ORDER BY a.allocation_date DESC");
$allocStmt->execute([$admin_hostel_id]);
$allocs = $allocStmt->fetchAll();

// Approved students who have not yet been assigned a room.
$unallocStmt = $pdo->prepare("SELECT s.*, ap.preferred_room_id, ap.preferred_bunk
    FROM students s
    JOIN applications ap ON s.student_id=ap.student_id
    LEFT JOIN allocations al
        ON s.student_id=al.student_id
        AND al.status='Active'
    WHERE ap.hostel_id=?
      AND ap.status='Approved'
      AND ap.preferred_room_id IS NULL
      AND al.allocation_id IS NULL
    ORDER BY ap.applied_at ASC");
$unallocStmt->execute([$admin_hostel_id]);
$unalloc = $unallocStmt->fetchAll();

// Available rooms for this hostel.
$roomsStmt = $pdo->prepare("SELECT r.*, h.hostel_name, h.hostel_type, h.uses_bunks
    FROM rooms r
    JOIN hostels h ON r.hostel_id=h.hostel_id
    WHERE r.hostel_id=?
      AND r.status='Available'
    ORDER BY r.room_number");
$roomsStmt->execute([$admin_hostel_id]);
$avail_rooms = $roomsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Allocations - <?= htmlspecialchars($hostel['hostel_name']) ?></title>
<link rel="stylesheet" href="../css/style.css?v=7">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

<div class="wrapper admin-layout">

<aside class="sidebar">

    <div class="user-info">
        <div class="avatar">A</div>

        <div class="name">
            <?= htmlspecialchars($_SESSION['admin_name']) ?>
        </div>

        <div class="role">
            <?= htmlspecialchars($hostel['hostel_name']) ?> Admin
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="hostels.php">My Hostel</a>
        <a href="students.php">Students</a>
        <a href="applications.php">Applications</a>
        <a href="allocations.php" class="active">Allocations</a>
        <a href="payments.php">Payments</a>
        <a href="reports.php">Reports</a>
        <a href="logout.php">Logout</a>
    </nav>

</aside>

<main class="main">

    <div class="page-title">
        Room Allocations — <?= htmlspecialchars($hostel['hostel_name']) ?>
    </div>

    <div class="page-subtitle">
        Assign rooms only to approved students applying to your hostel.
        Students choose their bunk after a room is assigned when the hostel uses bunks.
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

    <?php if ($unalloc): ?>

    <div class="form-card" style="max-width:100%;margin-bottom:24px;">

        <h3>Assign Room</h3>

        <form method="POST">

            <div class="form-row">

                <div class="form-group">

                    <label for="allocation-student">
                        Select Approved Student
                    </label>

                    <select
                        name="student_id"
                        id="allocation-student"
                        required
                    >

                        <option value="">
                            -- Select Student --
                        </option>

                        <?php foreach ($unalloc as $s): ?>

                        <option
                            value="<?= (int)$s['student_id'] ?>"
                            <?= $preselect_student === (int)$s['student_id'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($s['full_name']) ?>
                            (<?= htmlspecialchars($s['form_no']) ?>)
                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="allocation-room">
                        Select Available Room
                    </label>

                    <select
                        name="room_id"
                        id="allocation-room"
                        required
                    >

                        <option value="">
                            -- Select Room --
                        </option>

                        <?php foreach ($avail_rooms as $r): ?>

                        <option value="<?= (int)$r['room_id'] ?>">
                            Room <?= htmlspecialchars($r['room_number']) ?>
                            (<?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?> occupied)
                            — <?= (int)$r['uses_bunks'] === 1 ? 'Uses Bunks' : 'No Bunks' ?>
                        </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <div class="alert alert-info" style="margin-bottom:16px;">
                <strong>After room assignment:</strong>
                <?php if ((int)$hostel['uses_bunks'] === 1): ?>
                    this hostel uses bunks, so the student will choose an available bunk from the assigned room.
                <?php else: ?>
                    this hostel does not use bunks, so the room assignment will complete the student's allocation.
                <?php endif; ?>
            </div>

            <button
                type="submit"
                name="allocate"
                class="btn btn-success"
            >
                Assign Room
            </button>

        </form>

    </div>

    <?php else: ?>

    <div class="alert alert-info">
        No approved students are awaiting room assignment in
        <?= htmlspecialchars($hostel['hostel_name']) ?>.
    </div>

    <?php endif; ?>

    <div class="card">

        <div class="card-header">
            <h3>Allocations (<?= count($allocs) ?>)</h3>
        </div>

        <div class="table-wrap">

        <?php if (!$allocs): ?>

            <div class="empty-state">
                <span class="empty-icon">??</span>
                <p>No finalized allocations in this hostel yet.</p>
            </div>

        <?php else: ?>

        <table>

            <thead>
                <tr>
                    <th>#</th>
                    <th>Student</th>
                    <th>Identifier</th>
                    <th>Dept / Level</th>
                    <th>Room</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($allocs as $i => $a): ?>

            <tr>

                <td><?= $i + 1 ?></td>

                <td>
                    <strong>
                        <?= htmlspecialchars($a['full_name']) ?>
                    </strong>
                </td>

                <td>
                    <?= htmlspecialchars($a['form_no'] ?? '') ?>

                <td>
                    <?= htmlspecialchars($a['department']) ?>
                    /
                    <?= htmlspecialchars($a['level']) ?>
                </td>

                <td>
                    Room <?= htmlspecialchars($a['room_number']) ?>

                    <?php if ($a['bunk_number']): ?>
                        , Bunk <?= htmlspecialchars($a['bunk_number']) ?>
                    <?php endif; ?>
                </td>

                <td>
                    <?= date('d M Y', strtotime($a['allocation_date'])) ?>
                </td>

                <td>
                    <span class="badge <?= $a['status']==='Active' ? 'badge-success' : 'badge-secondary' ?>">
                        <?= htmlspecialchars($a['status']) ?>
                    </span>
                </td>

                <td>
                    <?php if ($a['status'] === 'Active'): ?>

                        <a
                            href="?vacate=<?= (int)$a['allocation_id'] ?>"
                            class="btn btn-warning btn-sm"
                            onclick="return confirm('Vacate this room?')"
                        >
                            Vacate
                        </a>

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
