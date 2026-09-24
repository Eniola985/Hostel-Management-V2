<?php
session_start();
require_once '../includes/db.php';
$err = '';
$form_no = strtoupper(trim($_POST['form_no'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';

    if (!preg_match('/^[FD][0-9]{7}$/', $form_no)) {
        $err = 'Enter a valid form number in the format F2405297 or D2405297.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM students WHERE form_no=? LIMIT 1");
        $stmt->execute([$form_no]);
        $student = $stmt->fetch();

        if ($student && password_verify($pass, $student['password'])) {
            session_regenerate_id(true);
            $_SESSION['student_id'] = $student['student_id'];
            $_SESSION['student_name'] = $student['full_name'];
            $_SESSION['student_matric'] = $student['matric_no'];
            $_SESSION['student_form_no'] = $student['form_no'];
            header('Location: dashboard.php');
            exit;
        }

        $err = 'Invalid form number or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login - Hostel Management</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <img class="auth-logo" src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo">
            <h2>Student Login</h2>
            <p>The Polytechnic, Ibadan Hostel Portal</p>
        </div>
        <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Form Number</label>
                <input type="text" name="form_no" placeholder="e.g. F2405297" pattern="[FDfd][0-9]{7}" maxlength="8" minlength="8" style="text-transform:uppercase" value="<?= htmlspecialchars($form_no) ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login to Portal</button>
        </form>
        <div class="auth-link">
            New student? <a href="register.php">Register here</a>
        </div>
        <div class="auth-link">
            <a href="../index.php">&larr; Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
