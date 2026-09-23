<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }
require_once '../includes/db.php';

$sid = $_SESSION['student_id'];
$msg = $err = '';

$student = $pdo->prepare("SELECT * FROM students WHERE student_id=?");
$student->execute([$sid]);
$student = $student->fetch();

if (isset($_POST['update_profile'])) {
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $pdo->prepare("UPDATE students SET phone=?, email=? WHERE student_id=?")->execute([$phone, $email, $sid]);
    $msg = 'Profile updated successfully.';
    $student['phone'] = $phone;
    $student['email'] = $email;
}

if (isset($_POST['change_password'])) {
    $old = trim($_POST['old_password']);
    $new = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);
    if ($old !== $student['password']) {
        $err = 'Current password is incorrect.';
    } elseif ($new !== $confirm) {
        $err = 'New passwords do not match.';
    } elseif (strlen($new) < 6) {
        $err = 'New password must be at least 6 characters.';
    } else {
        $pdo->prepare("UPDATE students SET password=? WHERE student_id=?")->execute([$new, $sid]);
        $msg = 'Password changed successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="wrapper">
<aside class="sidebar">
    <div class="user-info">
        <div class="avatar"><?= strtoupper(substr($student['full_name'],0,1)) ?></div>
        <div class="name"><?= htmlspecialchars(explode(' ',$student['full_name'])[0]) ?></div>
        <div class="role"><?= htmlspecialchars($student['matric_no']) ?></div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php">🏠 Dashboard</a>
        <a href="apply.php">📝 Apply for Hostel</a>
        <a href="status.php">📊 My Application Status</a>
        <a href="allocation.php">🛏 My Room Allocation</a>
        <a href="payments.php">💰 Payments</a>
        <a href="reports.php">📄 My Report</a>
        <a href="profile.php" class="active">👤 My Profile</a>
        <a href="logout.php">🚪 Logout</a>
    </nav>
</aside>
<main class="main">
    <div class="page-title">My Profile</div>
    <div class="page-subtitle">View and update your personal information</div>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <!-- Profile Info -->
        <div class="form-card">
            <h3>👤 Personal Information</h3>
            <div style="margin-bottom:16px;padding:16px;background:#f8fafc;border-radius:8px;">
                <div style="display:grid;gap:12px;">
                    <div><strong>Full Name:</strong><br><span style="color:#475569;"><?= htmlspecialchars($student['full_name']) ?></span></div>
                    <div><strong>Matric Number:</strong><br><span style="color:#475569;"><?= htmlspecialchars($student['matric_no']) ?></span></div>
                    <div><strong>Department:</strong><br><span style="color:#475569;"><?= htmlspecialchars($student['department']) ?></span></div>
                    <div><strong>Level:</strong><br><span style="color:#475569;"><?= htmlspecialchars($student['level']) ?></span></div>
                    <div><strong>Gender:</strong><br><span style="color:#475569;"><?= htmlspecialchars($student['gender']) ?></span></div>
                </div>
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($student['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($student['email'] ?? '') ?>">
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="form-card">
            <h3>🔐 Change Password</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="old_password" placeholder="Enter current password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Repeat new password" required>
                </div>
                <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
            </form>
        </div>
    </div>
</main>
</div>
<div class="footer">&copy; <?= date('Y') ?> The Polytechnic, Ibadan</div>
</body>
</html>
