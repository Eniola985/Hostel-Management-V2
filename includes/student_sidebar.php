<?php
/**
 * Student Portal Reusable Sidebar Navigation
 * Expects $student (array), optionally $allocation (array), and $current_page (string).
 */
if (!isset($allocation) && isset($pdo, $_SESSION['student_id'])) {
    try {
        $allocStmt = $pdo->prepare("SELECT al.*, r.room_number, h.hostel_name, h.hostel_type 
            FROM allocations al 
            JOIN rooms r ON al.room_id=r.room_id 
            JOIN hostels h ON r.hostel_id=h.hostel_id 
            WHERE al.student_id=? AND al.status='Active' LIMIT 1");
        $allocStmt->execute([(int)$_SESSION['student_id']]);
        $allocation = $allocStmt->fetch();
    } catch (Exception $e) {
        $allocation = null;
    }
}

$active_page = $current_page ?? 'dashboard';
$student_name = $student['full_name'] ?? 'Student';
$first_name = explode(' ', trim($student_name))[0];
$student_id_label = !empty($student['email']) ? $student['email'] : (!empty($student['form_no']) ? $student['form_no'] : ($student['matric_no'] ?? ''));
$student_photo = $student['profile_photo'] ?? '';
?>
<aside class="sidebar no-print">
    <div class="user-info">
        <div class="avatar">
            <?php if (!empty($student_photo)): ?>
                <img src="../<?= htmlspecialchars($student_photo) ?>" alt="Student photograph" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else: ?>
                <?= htmlspecialchars(strtoupper(substr($student_name, 0, 1))) ?>
            <?php endif; ?>
        </div>
        <div class="name"><?= htmlspecialchars($first_name) ?></div>
        <div class="role"><?= htmlspecialchars($student_id_label) ?></div>
        <div class="hostel-badge">
            <?php if (!empty($allocation['hostel_name'])): ?>
                🏨 <?= htmlspecialchars($allocation['hostel_name']) ?>
            <?php else: ?>
                🏨 Hostel not assigned
            <?php endif; ?>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Student Menu</div>
        <a href="dashboard.php" class="<?= $active_page === 'dashboard' ? 'active' : '' ?>">🏠 Dashboard</a>
        <a href="apply.php" class="<?= $active_page === 'apply' ? 'active' : '' ?>">📝 Apply for Hostel</a>
        <a href="status.php" class="<?= $active_page === 'status' ? 'active' : '' ?>">📊 My Application Status</a>
        <a href="allocation.php" class="<?= $active_page === 'allocation' ? 'active' : '' ?>">🛏 My Room Allocation</a>
        <a href="payments.php" class="<?= $active_page === 'payments' ? 'active' : '' ?>">💰 Payments</a>
        <a href="reports.php" class="<?= $active_page === 'reports' ? 'active' : '' ?>">📄 My Report</a>
        <a href="profile.php" class="<?= $active_page === 'profile' ? 'active' : '' ?>">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
