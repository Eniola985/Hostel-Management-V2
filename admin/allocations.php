<?php
require_once '../includes/admin_auth.php';

$hostel = current_admin_hostel($pdo);

$msg = '';
$err = '';
$preselect_student = (int)($_GET['student_id'] ?? 0);

// Confirm the room/bunk already selected by an approved student.
if (isset($_POST['confirm_allocation'])) {
    $student_id = (int)($_POST['student_id'] ?? 0);
    $app_id = (int)($_POST['app_id'] ?? 0);

    if ($student_id <= 0 || $app_id <= 0) {
        $err = 'The selected application is not valid.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock and confirm the approved application belongs to this hostel.
            $approved = $pdo->prepare("
                SELECT ap.*, h.hostel_name, h.hostel_type, h.uses_bunks
                FROM applications ap
                JOIN hostels h ON h.hostel_id=ap.hostel_id
                WHERE ap.app_id=?
                  AND ap.student_id=?
                  AND ap.hostel_id=?
                  AND ap.status='Approved'
                LIMIT 1
                FOR UPDATE
            ");
            $approved->execute([
                $app_id,
                $student_id,
                $admin_hostel_id
            ]);

            $approvedApplication = $approved->fetch();

            if (!$approvedApplication) {
                throw new RuntimeException(
                    'Only an approved application in your hostel can be allocated.'
                );
            }

            $room_id = (int)($approvedApplication['preferred_room_id'] ?? 0);
            $bunk_number = trim((string)($approvedApplication['preferred_bunk'] ?? ''));

            if ($room_id <= 0) {
                throw new RuntimeException(
                    'This student has not selected a room.'
                );
            }

            if ((int)$approvedApplication['uses_bunks'] === 1 && $bunk_number === '') {
                throw new RuntimeException(
                    'This student has not selected a bunk.'
                );
            }

            if ((int)$approvedApplication['uses_bunks'] !== 1) {
                $bunk_number = null;
            }

            // Lock the selected room.
            $roomStmt = $pdo->prepare("
                SELECT r.*, h.hostel_name, h.hostel_type, h.uses_bunks
                FROM rooms r
                JOIN hostels h ON h.hostel_id=r.hostel_id
                WHERE r.room_id=?
                  AND r.hostel_id=?
                LIMIT 1
                FOR UPDATE
            ");
            $roomStmt->execute([
                $room_id,
                $admin_hostel_id
            ]);

            $room = $roomStmt->fetch();

            if (!$room) {
                throw new RuntimeException(
                    'The student selected a room that is not in your hostel.'
                );
            }

            if ($room['status'] !== 'Available') {
                throw new RuntimeException(
                    'The selected room is currently full or unavailable.'
                );
            }

            // Prevent duplicate active allocation for this student.
            $activeAllocation = $pdo->prepare("
                SELECT allocation_id
                FROM allocations
                WHERE student_id=?
                  AND status='Active'
                LIMIT 1
            ");
            $activeAllocation->execute([$student_id]);

            if ($activeAllocation->fetch()) {
                throw new RuntimeException(
                    'This student already has an active room allocation.'
                );
            }

            // Recalculate actual active occupancy while the room is locked.
            $occupancyStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM allocations
                WHERE room_id=?
                  AND status='Active'
            ");
            $occupancyStmt->execute([$room_id]);
            $actualOccupied = (int)$occupancyStmt->fetchColumn();

            if ($actualOccupied >= (int)$room['capacity']) {
                throw new RuntimeException(
                    'The selected room has no available space.'
                );
            }

            if ((int)$room['uses_bunks'] === 1) {
                // The selected bunk must not already be occupied.
                $bunkAllocation = $pdo->prepare("
                    SELECT allocation_id
                    FROM allocations
                    WHERE room_id=?
                      AND bunk_number=?
                      AND status='Active'
                    LIMIT 1
                ");
                $bunkAllocation->execute([
                    $room_id,
                    $bunk_number
                ]);

                if ($bunkAllocation->fetch()) {
                    throw new RuntimeException(
                        'The selected bunk has already been taken.'
                    );
                }

                // The selected bunk must not be reserved by another application.
                $bunkReservation = $pdo->prepare("
                    SELECT app_id
                    FROM applications
                    WHERE preferred_room_id=?
                      AND preferred_bunk=?
                      AND status IN ('Pending','Approved')
                      AND app_id<>?
                    LIMIT 1
                ");
                $bunkReservation->execute([
                    $room_id,
                    $bunk_number,
                    $app_id
                ]);

                if ($bunkReservation->fetch()) {
                    throw new RuntimeException(
                        'The selected bunk has already been reserved by another student.'
                    );
                }
            } else {
                // A non-bunk room can have multiple students up to its capacity.
                $reservedRoomStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM applications
                    WHERE preferred_room_id=?
                      AND preferred_bunk IS NULL
                      AND status IN ('Pending','Approved')
                      AND app_id<>?
                ");
                $reservedRoomStmt->execute([
                    $room_id,
                    $app_id
                ]);

                $reservedRoomSpaces = (int)$reservedRoomStmt->fetchColumn();

                if ($actualOccupied + $reservedRoomSpaces > (int)$room['capacity']) {
                    throw new RuntimeException(
                        'The selected room no longer has enough available space.'
                    );
                }
            }

            // Create the final allocation using the student's selected room/bunk.
            $pdo->prepare("
                INSERT INTO allocations
                    (student_id, room_id, bunk_number)
                VALUES (?,?,?)
            ")->execute([
                $student_id,
                $room_id,
                $bunk_number
            ]);

            $newOccupied = $actualOccupied + 1;
            $newStatus = $newOccupied >= (int)$room['capacity']
                ? 'Full'
                : 'Available';

            $pdo->prepare("
                UPDATE rooms
                SET occupied=?,
                    status=?
                WHERE room_id=?
                  AND hostel_id=?
            ")->execute([
                $newOccupied,
                $newStatus,
                $room_id,
                $admin_hostel_id
            ]);

            $pdo->prepare("
                UPDATE applications
                SET status='Allocated'
                WHERE app_id=?
                  AND hostel_id=?
                  AND status='Approved'
            ")->execute([
                $app_id,
                $admin_hostel_id
            ]);

            $pdo->commit();

            $msg = 'Student allocation confirmed successfully.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $err = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'The student allocation could not be completed. Please try again.';
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

// Approved students who have selected their room/bunk but do not yet have an active allocation.
$unallocStmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.full_name,
        s.form_no,
        s.department,
        s.level,
        ap.app_id,
        ap.preferred_room_id,
        ap.preferred_bunk,
        ap.applied_at,
        r.room_number,
        h.hostel_name,
        h.hostel_type,
        h.uses_bunks
    FROM students s
    JOIN applications ap
        ON s.student_id=ap.student_id
    JOIN rooms r
        ON r.room_id=ap.preferred_room_id
    JOIN hostels h
        ON h.hostel_id=ap.hostel_id
    LEFT JOIN allocations al
        ON s.student_id=al.student_id
        AND al.status='Active'
    WHERE ap.hostel_id=?
      AND ap.status='Approved'
      AND ap.preferred_room_id IS NOT NULL
      AND al.allocation_id IS NULL
    ORDER BY ap.applied_at ASC
");
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
<?php $current_page = 'allocations'; require_once '../includes/admin_sidebar.php'; ?>
<main class="main">
    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
    <div class="page-title">
        Room Allocations  <?= htmlspecialchars($hostel['hostel_name']) ?>
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
<div class="card">
    <h2>Confirm Student Allocation</h2>
    <p class="muted">
        Review the room and bunk selected by each approved student, then confirm the allocation.
    </p>

    <form method="post">
        <div class="form-group">
            <label for="student_id">Approved Student</label>
            <select name="student_id" id="student_id" required>
                <option value="">Select an approved student</option>

                <?php foreach ($unalloc as $student): ?>
                    <option
                        value="<?= (int)$student['student_id'] ?>"
                        data-app-id="<?= (int)$student['app_id'] ?>"
                    >
                        <?= htmlspecialchars($student['full_name']) ?>
                        
                        <?= htmlspecialchars($student['form_no'] ?: 'No Form No.') ?>
                         Room <?= htmlspecialchars($student['room_number']) ?>
                        <?php if ((int)$student['uses_bunks'] === 1 && !empty($student['preferred_bunk'])): ?>
                             Bunk <?= htmlspecialchars($student['preferred_bunk']) ?>
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div
            id="selected-allocation-details"
            style="display:none;margin:16px 0;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;"
        >
            <strong style="display:block;margin-bottom:10px;">
                Selected Accommodation
            </strong>

            <div id="selected-room"></div>
            <div id="selected-bunk" style="margin-top:6px;"></div>
        </div>

        <input type="hidden" name="app_id" id="app_id" value="">

        <button type="submit" name="confirm_allocation" class="btn btn-success">
            Confirm Allocation
        </button>
    </form>
</div>

<script>
const approvedStudents = <?= json_encode(
    array_map(
        static function ($student) {
            return [
                'student_id' => (int)$student['student_id'],
                'app_id' => (int)$student['app_id'],
                'room_number' => $student['room_number'],
                'preferred_bunk' => $student['preferred_bunk'],
                'uses_bunks' => (int)$student['uses_bunks'],
            ];
        },
        $unalloc
    ),
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;

const studentSelect = document.getElementById('student_id');
const appIdInput = document.getElementById('app_id');
const details = document.getElementById('selected-allocation-details');
const selectedRoom = document.getElementById('selected-room');
const selectedBunk = document.getElementById('selected-bunk');

studentSelect.addEventListener('change', function () {
    const studentId = parseInt(this.value, 10);

    const student = approvedStudents.find(function (item) {
        return item.student_id === studentId;
    });

    if (!student) {
        appIdInput.value = '';
        details.style.display = 'none';
        selectedRoom.textContent = '';
        selectedBunk.textContent = '';
        return;
    }

    appIdInput.value = student.app_id;

    selectedRoom.textContent = 'Room: ' + student.room_number;

    if (student.uses_bunks === 1 && student.preferred_bunk) {
        selectedBunk.textContent = 'Bunk: ' + student.preferred_bunk;
        selectedBunk.style.display = 'block';
    } else {
        selectedBunk.textContent = '';
        selectedBunk.style.display = 'none';
    }

    details.style.display = 'block';
});
</script>
<?php else: ?>
<div class="card">
    <h2>Confirm Student Allocation</h2>
    <p class="muted">
        No approved students are awaiting allocation confirmation in this hostel.
    </p>
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



