<?php
require_once '../includes/admin_auth.php';
$hostel = current_admin_hostel($pdo);
$hostel_id = $admin_hostel_id;
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_room'])) {
    $room_number = trim($_POST['room_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);

    if ($room_number === '' || $capacity < 1 || $capacity > 10) {
        $err = 'Enter a valid room number and capacity between 1 and 10.';
    } else {
        try {
            $pdo->prepare('INSERT INTO rooms (hostel_id, room_number, capacity) VALUES (?,?,?)')
                ->execute([$hostel_id, $room_number, $capacity]);
            $msg = 'Room added successfully.';
        } catch (PDOException $e) {
            $err = 'That room number already exists in this hostel, or the room could not be added.';
        }
    }
}

if (isset($_GET['delete_room'])) {
    $room_id = (int)$_GET['delete_room'];
    $stmt = $pdo->prepare('SELECT occupied FROM rooms WHERE room_id=? AND hostel_id=?');
    $stmt->execute([$room_id, $hostel_id]);
    $room = $stmt->fetch();

    if (!$room) {
        $err = 'Room not found in your hostel.';
    } elseif ((int)$room['occupied'] > 0) {
        $err = 'An occupied room cannot be deleted.';
    } else {
        $pdo->prepare('DELETE FROM rooms WHERE room_id=? AND hostel_id=?')->execute([$room_id, $hostel_id]);
        $msg = 'Room deleted.';
    }
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 20, 30, 40, 50], true)) {
    $limit = 10;
}
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$sql_base = "FROM rooms r WHERE r.hostel_id = ?";
$params = [$hostel_id];

if ($search !== '') {
    $sql_base .= " AND r.room_number LIKE ?";
    $params[] = "%{$search}%";
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

$roomsStmt = $pdo->prepare("SELECT r.*, COUNT(a.allocation_id) AS alloc_count
    " . $sql_base . "
    LEFT JOIN allocations a ON r.room_id=a.room_id AND a.status='Active'
    GROUP BY r.room_id
    ORDER BY r.room_number LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
$roomsStmt->execute($params);
$rooms = $roomsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rooms - <?= htmlspecialchars($hostel['hostel_name']) ?></title>
<link rel="stylesheet" href="../css/style.css?v=8">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="wrapper admin-layout">
<?php $current_page = 'rooms'; require_once '../includes/admin_sidebar.php'; ?>
<main class="main">
    <a href="dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
    <div class="breadcrumb"><a href="hostels.php"><?= htmlspecialchars($hostel['hostel_name']) ?></a> &rsaquo; Rooms</div>
    <div class="page-title">Rooms — <?= htmlspecialchars($hostel['hostel_name']) ?></div>
    <div class="page-subtitle">Only rooms belonging to your assigned hostel are visible here.</div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="form-card" style="max-width:100%;margin-bottom:24px;">
        <h3>🚪 Add Room</h3>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" placeholder="e.g. 106" maxlength="50" required>
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
            <h3>Rooms (<?= $total_rooms_count ?>)</h3>

            <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search room number..."
                    style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:180px;"
                >

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

                <?php if ($search || $status_filter || $limit !== 10): ?>
                    <a href="rooms.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="room-grid">
            <?php foreach ($rooms as $r): ?>
            <div class="room-item <?= strtolower($r['status']) ?>">
                <div class="room-num">Room <?= htmlspecialchars($r['room_number']) ?></div>
                <div style="font-size:.8rem;color:#64748b;margin:4px 0;"><?= (int)$r['occupied'] ?>/<?= (int)$r['capacity'] ?> occupied</div>
                <div class="room-status"><?= htmlspecialchars($r['status']) ?></div>
                <div style="margin-top:8px;">
                    <?php if ((int)$r['occupied'] === 0): ?>
                        <a href="?delete_room=<?= (int)$r['room_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete room <?= htmlspecialchars($r['room_number'], ENT_QUOTES) ?>?')">Delete</a>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:.8rem;">Occupied</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$rooms): ?>
                <div class="empty-state"><span class="empty-icon">🚪</span><p>No rooms match current filters.</p></div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1 || $total_rooms_count > 0): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-top:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;font-size:0.85rem;color:#64748b;">
                <div>
                    Showing <?= min($total_rooms_count, $offset + 1) ?> to <?= min($total_rooms_count, $offset + $limit) ?> of <?= $total_rooms_count ?> rooms
                </div>

                <div class="pagination">
                    <?php
                    $queryParams = $_GET;
                    function adminRoomsPageUrl($p, $params) {
                        $params['page'] = $p;
                        return 'rooms.php?' . http_build_query($params);
                    }
                    ?>

                    <?php if ($page > 1): ?>
                        <a href="<?= adminRoomsPageUrl($page - 1, $queryParams) ?>" class="prev-btn">&larr; Previous</a>
                    <?php else: ?>
                        <span class="page-btn disabled prev-btn">&larr; Previous</span>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <?php if ($p == $page): ?>
                            <span class="page-btn active"><?= $p ?></span>
                        <?php elseif ($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)): ?>
                            <a href="<?= adminRoomsPageUrl($p, $queryParams) ?>"><?= $p ?></a>
                        <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
                            <span style="padding:0 4px;color:#94a3b8;">&hellip;</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?= adminRoomsPageUrl($page + 1, $queryParams) ?>" class="next-btn">Next &rarr;</a>
                    <?php else: ?>
                        <span class="page-btn disabled next-btn">Next &rarr;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
