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

// Load hostels for dropdown & filter
$hostelsStmt = $pdo->query(
    'SELECT hostel_id, hostel_name, hostel_type
     FROM hostels
     ORDER BY hostel_type, hostel_name'
);

$hostels = $hostelsStmt->fetchAll();

// Pagination and filtering for rooms
$search = trim($_GET['search'] ?? '');
$hostel_filter = (int)($_GET['hostel_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 20, 30, 40, 50], true)) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql_base = "FROM rooms r
             JOIN hostels h ON h.hostel_id = r.hostel_id
             WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql_base .= " AND (r.room_number LIKE ? OR h.hostel_name LIKE ?)";
    $searchLike = "%{$search}%";
    $params[] = $searchLike;
    $params[] = $searchLike;
}

if ($hostel_filter > 0) {
    $sql_base .= " AND r.hostel_id = ?";
    $params[] = $hostel_filter;
}

if ($status_filter !== '') {
    $sql_base .= " AND r.status = ?";
    $params[] = $status_filter;
}

// Count total
$count_stmt = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$count_stmt->execute($params);
$total_rooms_count = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_rooms_count / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch slice
$sql = "SELECT r.*, h.hostel_name, h.hostel_type "
     . $sql_base
     . " ORDER BY h.hostel_type, h.hostel_name, r.room_number LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Rooms - General Admin</title>

<link rel="stylesheet" href="../css/style.css?v=8">

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>
</head>

<body>

<div class="wrapper admin-layout">

<?php $current_page = 'rooms'; require_once '../includes/general_admin_sidebar.php'; ?>

<main class="main">

    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>

    <div class="breadcrumb">
        <span>General Admin</span>
        <span>&rsaquo;</span>
        <strong>Rooms</strong>
    </div>

    <div class="page-title">
        Rooms Management
    </div>

    <div class="page-subtitle">
        System-wide room capacity and allocation overview.
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

    <div class="card" style="margin-bottom:24px;">

        <div class="card-header">
            <h3>Add New Room</h3>
        </div>

        <form method="POST" style="padding:20px;">

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">

                <div class="form-group">
                    <label>Select Hostel</label>
                    <select name="hostel_id" required>
                        <option value="">Select Hostel</option>
                        <?php foreach ($hostels as $hostel): ?>
                            <option value="<?= (int)$hostel['hostel_id'] ?>">
                                <?= htmlspecialchars($hostel['hostel_name']) ?>
                                (<?= htmlspecialchars($hostel['hostel_type']) ?>)
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
                All Rooms (<?= $total_rooms_count ?>)
            </h3>

            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search room, hostel..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:180px;"
                >

                <select
                    name="hostel_id"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="">All Hostels</option>
                    <?php foreach ($hostels as $h): ?>
                        <option value="<?= (int)$h['hostel_id'] ?>" <?= $hostel_filter === (int)$h['hostel_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['hostel_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select
                    name="status"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="">All Statuses</option>
                    <option value="Available" <?= $status_filter === 'Available' ? 'selected' : '' ?>>Available</option>
                    <option value="Full" <?= $status_filter === 'Full' ? 'selected' : '' ?>>Full</option>
                </select>

                <select
                    name="limit"
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;background:white;"
                >
                    <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10 per page</option>
                    <option value="20" <?= $limit === 20 ? 'selected' : '' ?>>20 per page</option>
                    <option value="30" <?= $limit === 30 ? 'selected' : '' ?>>30 per page</option>
                    <option value="40" <?= $limit === 40 ? 'selected' : '' ?>>40 per page</option>
                    <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 per page</option>
                </select>

                <button type="submit" class="btn btn-info btn-sm">Filter</button>

                <?php if ($search || $hostel_filter || $status_filter || $limit !== 10): ?>
                    <a href="rooms.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-wrap">

            <?php if (!$rooms): ?>

                <div class="empty-state">
                    <span class="empty-icon">🚪</span>
                    <p>No rooms found matching current filters.</p>
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
                            <td><?= $offset + $i + 1 ?></td>

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
                                <?= (int)$room['capacity'] ?> Beds
                            </td>

                            <td>
                                <?= (int)$room['occupied'] ?> / <?= (int)$room['capacity'] ?>
                            </td>

                            <td>
                                <span class="badge <?= $room['status'] === 'Available' ? 'badge-success' : 'badge-danger' ?>">
                                    <?= htmlspecialchars($room['status']) ?>
                                </span>
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

                <?php if ($total_pages > 1 || $total_rooms_count > 0): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;font-size:0.85rem;color:#64748b;">
                        <div>
                            Showing <?= min($total_rooms_count, $offset + 1) ?> to <?= min($total_rooms_count, $offset + $limit) ?> of <?= $total_rooms_count ?> rooms
                        </div>

                        <div class="pagination">
                            <?php
                            $queryParams = $_GET;
                            function gaRoomsPageUrl($p, $params) {
                                $params['page'] = $p;
                                return 'rooms.php?' . http_build_query($params);
                            }
                            ?>

                            <?php if ($page > 1): ?>
                                <a href="<?= gaRoomsPageUrl($page - 1, $queryParams) ?>" class="prev-btn">&larr; Previous</a>
                            <?php else: ?>
                                <span class="page-btn disabled prev-btn">&larr; Previous</span>
                            <?php endif; ?>

                            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                                <?php if ($p == $page): ?>
                                    <span class="page-btn active"><?= $p ?></span>
                                <?php elseif ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                                    <a href="<?= gaRoomsPageUrl($p, $queryParams) ?>"><?= $p ?></a>
                                <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                                    <span style="padding:0 4px;color:#94a3b8;">&hellip;</span>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?= gaRoomsPageUrl($page + 1, $queryParams) ?>" class="next-btn">Next &rarr;</a>
                            <?php else: ?>
                                <span class="page-btn disabled next-btn">Next &rarr;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

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
