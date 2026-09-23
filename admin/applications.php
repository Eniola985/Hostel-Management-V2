<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = (int)($_POST['app_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    if ($app_id < 1 || !in_array($action, ['approve', 'reject'], true)) {
        $err = 'That application action is not valid. Please try again.';
    } elseif ($action === 'reject' && $rejection_reason === '') {
        $err = 'Please provide a reason before rejecting an application.';
    } else {
        try {
            if ($action === 'approve') {
                $statement = $pdo->prepare("UPDATE applications SET status='Approved', rejection_reason=NULL WHERE app_id=? AND status='Pending'");
                $statement->execute([$app_id]);
                $msg = $statement->rowCount() ? 'Application approved successfully.' : 'This application is no longer pending.';
            } else {
                $statement = $pdo->prepare("UPDATE applications SET status='Rejected', rejection_reason=? WHERE app_id=? AND status='Pending'");
                $statement->execute([$rejection_reason, $app_id]);
                $msg = $statement->rowCount() ? 'Application rejected and the reason was saved.' : 'This application is no longer pending.';
            }
        } catch (PDOException $e) {
            $err = 'The application could not be updated. Please try again.';
        }
    }
}

$apps = $pdo->query("SELECT a.*, s.full_name, s.matric_no, s.department, s.level, s.gender, h.hostel_name, h.hostel_type 
    FROM applications a 
    JOIN students s ON a.student_id = s.student_id 
    JOIN hostels h ON a.hostel_id = h.hostel_id 
    ORDER BY a.applied_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Applications - Admin</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar">
    <div class="brand"><img src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo"> Poly Ibadan HMS Admin</div>
    <ul class="nav-links">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="hostels.php">Hostels</a></li>
        <li><a href="students.php">Students</a></li>
        <li><a href="applications.php" class="active">Applications</a></li>
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
        <a href="students.php">👥 Students</a>
        <a href="applications.php" class="active">📋 Applications</a>
        <a href="allocations.php">🛏 Allocations</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📊 Reports</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">Hostel Applications</div>
    <div class="page-subtitle">Review and process student accommodation applications</div>
    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>All Applications (<?= count($apps) ?>)</h3></div>
        <div class="table-wrap">
            <?php if (empty($apps)): ?>
                <div class="empty-state"><span class="empty-icon">📋</span><p>No applications submitted yet.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>#</th><th>Student</th><th>Matric No</th><th>Dept / Level</th><th>Gender</th><th>Hostel Applied</th><th>Payment Ref</th><th>Date Applied</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($apps as $i => $a): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($a['full_name']) ?></strong></td>
                    <td><?= htmlspecialchars($a['matric_no']) ?></td>
                    <td><?= htmlspecialchars($a['department']) ?> / <?= htmlspecialchars($a['level']) ?></td>
                    <td><span class="badge <?= $a['gender']==='Male'?'badge-info':'badge-warning' ?>"><?= $a['gender'] ?></span></td>
                    <td><?= htmlspecialchars($a['hostel_name']) ?> <small>(<?= $a['hostel_type'] ?>)</small></td>
                    <td><?= htmlspecialchars($a['payment_ref'] ?? 'N/A') ?></td>
                    <td><?= date('d M Y', strtotime($a['applied_at'])) ?></td>
                    <td>
                        <?php
                        $badge = match($a['status']) {
                            'Approved' => 'badge-success',
                            'Rejected' => 'badge-danger',
                            default => 'badge-warning'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= $a['status'] ?></span>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if ($a['status'] === 'Pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="app_id" value="<?= $a['app_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <details style="display:inline-block;vertical-align:middle;">
                                <summary class="btn btn-danger btn-sm" style="cursor:pointer;list-style:none;">Reject</summary>
                                <form method="POST" style="position:absolute;z-index:2;background:white;border:1px solid #e2e8f0;border-radius:8px;padding:12px;width:260px;box-shadow:0 8px 20px rgba(15,23,42,0.15);">
                                    <input type="hidden" name="app_id" value="<?= $a['app_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <label for="reason-<?= $a['app_id'] ?>" style="display:block;font-size:0.8rem;font-weight:600;margin-bottom:6px;">Reason for rejection</label>
                                    <textarea id="reason-<?= $a['app_id'] ?>" name="rejection_reason" rows="3" required maxlength="1000" style="width:100%;margin-bottom:8px;" placeholder="Explain what the student needs to correct"></textarea>
                                    <button type="submit" class="btn btn-danger btn-sm">Confirm rejection</button>
                                </form>
                            </details>
                        <?php elseif ($a['status'] === 'Approved'): ?>
                            <a href="allocations.php?student_id=<?= $a['student_id'] ?>" class="btn btn-info btn-sm">Allocate Room</a>
                        <?php else: ?>
                            <span style="color:#94a3b8;font-size:0.8rem;">No action</span>
                        <?php endif; ?>
                        <?php if ($a['status'] === 'Rejected' && !empty($a['rejection_reason'])): ?>
                            <div style="margin-top:6px;color:#991b1b;font-size:0.8rem;white-space:normal;max-width:220px;"><strong>Reason:</strong> <?= htmlspecialchars($a['rejection_reason']) ?></div>
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
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
