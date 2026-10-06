<?php
// Shared consistent hostel admin sidebar
$page = $current_page ?? '';
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$h_name = $hostel['hostel_name'] ?? ($_SESSION['admin_hostel_name'] ?? 'Hostel');
$pending_count = $pending ?? 0;

if (empty($pending_count) && isset($pdo, $admin_hostel_id)) {
    try {
        $pStmt = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE hostel_id=? AND status='Pending'");
        $pStmt->execute([$admin_hostel_id]);
        $pending_count = (int)$pStmt->fetchColumn();
    } catch (Exception $e) {
        $pending_count = 0;
    }
}
?>
<aside class="sidebar no-print">
    <div class="user-info">
        <div class="avatar">🏨</div>
        <div class="name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="role"><?= htmlspecialchars($h_name) ?> Admin</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main Menu</div>
        <a href="dashboard.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a>
        <a href="hostels.php" class="<?= $page === 'hostels' ? 'active' : '' ?>">🏨 My Hostel</a>
        <a href="rooms.php" class="<?= $page === 'rooms' ? 'active' : '' ?>">🚪 Rooms</a>
        <a href="students.php" class="<?= $page === 'students' ? 'active' : '' ?>">👥 Students</a>
        
        <div class="nav-section">Operations</div>
        <a href="applications.php" class="<?= $page === 'applications' ? 'active' : '' ?>">
            📋 Applications <?php if (!empty($pending_count) && $pending_count > 0): ?><span class="badge badge-warning"><?= (int)$pending_count ?></span><?php endif; ?>
        </a>
        <a href="allocations.php" class="<?= $page === 'allocations' ? 'active' : '' ?>">🛏 Allocations</a>
        <a href="payments.php" class="<?= $page === 'payments' ? 'active' : '' ?>">💰 Payments</a>
        
        <div class="nav-section">Reports & Exit</div>
        <a href="reports.php" class="<?= $page === 'reports' ? 'active' : '' ?>">📊 Reports</a>
        <a href="../index.php" style="color:#0284c7;font-weight:600;">&larr; Back to Main Portal</a>
        <a href="logout.php" style="color:#dc2626;">🚪 Logout</a>
    </nav>
</aside>
