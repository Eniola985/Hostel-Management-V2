<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$msg = '';
$search = trim($_GET['search'] ?? '');

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM students WHERE student_id=?")->execute([$_GET['delete']]);
    $msg = 'Student record deleted.';
}

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE full_name LIKE ? OR matric_no LIKE ? OR department LIKE ? ORDER BY full_name");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM students ORDER BY full_name");
}
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Students - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="hostels.php">Hostels</a></li>
        <li><a href="students.php" class="active">Students</a></li>
        <li><a href="applications.php">Applications</a></li>
        <li><a href="allocations.php">Allocations</a></li>
        <li><a href="reports.php">Reports</a></li>
        <li><a href="logout.php" class="logout">Logout</a></li>
    </ul>
</nav>
<div class="wrapper">
<aside class="sidebar">
    <div class="user-info">
        <div class="avatar">A</div>
        <div class="name"><?= htmlspecialchars($_SESSION['admin_name']) ?></div>
        <div class="role">Administrator</div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="hostels.php">🏨 Manage Hostels</a>
        <a href="students.php" class="active">👥 Students</a>
        <a href="applications.php">📋 Applications</a>
        <a href="allocations.php">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Students</div>
    <div class="page-subtitle">All registered students in the system</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Student Records (<?= count($students) ?>)</h3>
            <form method="GET" style="display:flex;gap:8px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, matric number, dept..." style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:Inter,sans-serif;font-size:0.875rem;width:260px;">
                <button type="submit" class="btn btn-info btn-sm">Search</button>
                <?php if ($search): ?><a href="students.php" class="btn btn-sm" style="background:#f1f5f9;color:#475569;">Clear</a><?php endif; ?>
            </form>
        </div>
        <div class="table-wrap">
            <?php if (empty($students)): ?>
                <div class="empty-state"><span class="empty-icon">👥</span><p>No students found.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Full Name</th><th>Matric No</th><th>Department</th><th>Level</th><th>Gender</th><th>Phone</th><th>Registered</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($s['full_name']) ?></strong></td>
                    <td><?= htmlspecialchars($s['matric_no']) ?></td>
                    <td><?= htmlspecialchars($s['department']) ?></td>
                    <td><?= htmlspecialchars($s['level']) ?></td>
                    <td><span class="badge <?= $s['gender']==='Male' ? 'badge-info' : 'badge-warning' ?>"><?= $s['gender'] ?></span></td>
                    <td><?= htmlspecialchars($s['phone']) ?></td>
                    <td><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                    <td>
                        <a href="?delete=<?= $s['student_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this student?')">Delete</a>
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
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
