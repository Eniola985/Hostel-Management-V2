<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare(
        'SELECT ha.admin_id, ha.username, ha.password_hash, ha.hostel_id, h.hostel_name
         FROM hostel_admins ha
         JOIN hostels h ON h.hostel_id = ha.hostel_id
         WHERE ha.username = ?'
    );
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['username'];
        $_SESSION['admin_hostel_id'] = (int) $admin['hostel_id'];
        $_SESSION['admin_hostel_name'] = $admin['hostel_name'];

        $pdo->prepare('UPDATE hostel_admins SET last_login_at = NOW() WHERE admin_id = ?')
            ->execute([$admin['admin_id']]);

        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hostel Admin Login - Hostel Management System</title>
<link rel="stylesheet" href="../css/style.css?v=7">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <img class="auth-logo" src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo">
            <h2>Hostel Admin Login</h2>
            <p>Sign in to your assigned hostel portal</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST" autocomplete="on">
            <div class="form-group">
                <label>Hostel Username</label>
                <input type="text" name="username" placeholder="e.g. Olori Hostel" required autocomplete="username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Login to Dashboard</button>
        </form>
        <div class="auth-link"><a href="../index.php">&larr; Back to Home</a></div>
    </div>
</div>
</body>
</html>
