<?php
// Shared consistent general admin sidebar
$page = $current_page ?? '';
$g_admin_name = $admin['username'] ?? ($_SESSION['general_admin_name'] ?? 'General Admin');

// Ensure $hostels is loaded if not already passed
if (!isset($hostels) || !is_array($hostels)) {
    if (isset($all_hostels) && is_array($all_hostels)) {
        $hostels = $all_hostels;
    } elseif (isset($pdo)) {
        try {
            $hostels = $pdo->query('SELECT hostel_id, hostel_name, hostel_type FROM hostels ORDER BY hostel_type, hostel_name')->fetchAll();
        } catch (Exception $e) {
            $hostels = [];
        }
    } else {
        $hostels = [];
    }
}
$active_h_id = isset($hostel_id) ? (int)$hostel_id : 0;
?>
<aside class="sidebar no-print">
    <div class="user-info">
        <div class="avatar bg-amber-600">⚙️</div>
        <div class="name"><?= htmlspecialchars($g_admin_name) ?></div>
        <div class="role">General Administrator</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
            📊 <span>Dashboard</span>
        </a>
        
        <div class="nav-section">Hostels</div>
        <div class="nav-section" style="font-size:0.7rem;color:#94a3b8;padding-left:12px;margin-top:2px;">Male Hostels</div>
        <?php foreach ($hostels as $h): if (($h['hostel_type'] ?? '') === 'Male'): ?>
            <a href="hostel.php?hostel_id=<?= (int)$h['hostel_id'] ?>" class="<?= ($page === 'hostel' && $active_h_id === (int)$h['hostel_id']) ? 'active' : '' ?>">
                🏠 <span><?= htmlspecialchars($h['hostel_name']) ?></span>
            </a>
        <?php endif; endforeach; ?>
        
        <div class="nav-section" style="font-size:0.7rem;color:#94a3b8;padding-left:12px;margin-top:2px;">Female Hostels</div>
        <?php foreach ($hostels as $h): if (($h['hostel_type'] ?? '') === 'Female'): ?>
            <a href="hostel.php?hostel_id=<?= (int)$h['hostel_id'] ?>" class="<?= ($page === 'hostel' && $active_h_id === (int)$h['hostel_id']) ? 'active' : '' ?>">
                🏠 <span><?= htmlspecialchars($h['hostel_name']) ?></span>
            </a>
        <?php endif; endforeach; ?>
        
        <div class="nav-section">Management</div>
        <a href="add_hostel.php" class="<?= $page === 'add_hostel' ? 'active' : '' ?>">
            ➕ <span>Add Hostel</span>
        </a>
        <a href="rooms.php" class="<?= $page === 'rooms' ? 'active' : '' ?>">
            🚪 <span>Rooms</span>
        </a>
        <a href="students.php" class="<?= $page === 'students' ? 'active' : '' ?>">
            👨‍🎓 <span>Students</span>
        </a>
        <a href="reports.php" class="<?= $page === 'reports' ? 'active' : '' ?>">
            📈 <span>Reports</span>
        </a>
        
        <div class="nav-section">Navigation</div>
        <a href="../index.php" style="color:#0284c7;font-weight:600;">
            &larr; <span>Back to Main Portal</span>
        </a>
        <a href="logout.php" style="color:#dc2626;">
            🚪 <span>Logout</span>
        </a>
    </nav>
</aside>
