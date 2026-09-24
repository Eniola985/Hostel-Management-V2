<?php
session_start();
require_once '../includes/db.php';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric = trim($_POST['matric_no'] ?? '');
    $form_no = strtoupper(trim($_POST['form_no'] ?? ''));
    $name = trim($_POST['full_name'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $level = $_POST['level'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[FD][0-9]{7}$/', $form_no)) {
        $err = 'Form number is required and must be in the format F2405297 or D2405297.';
    } elseif ($matric !== '' && !preg_match('/^\d{13}$/', $matric)) {
        $err = 'Matriculation number must be exactly 13 digits when provided.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please provide a valid email address.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $err = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT student_id FROM students WHERE form_no=? OR (matric_no IS NOT NULL AND matric_no=?) OR email=? LIMIT 1");
        $check->execute([$form_no, $matric ?: null, $email]);

        if ($check->fetch()) {
            $err = 'A student with this form number, matriculation number, or email already exists.';
        } else {
            $password_hash = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO students (matric_no, form_no, full_name, department, level, gender, phone, email, password) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$matric ?: null, $form_no, $name, $dept, $level, $gender, $phone, $email, $password_hash]);
            $msg = 'Registration successful! You can now login with your form number.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Registration - Hostel Management</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-box" style="max-width:520px;">
        <div class="auth-header">
            <img class="auth-logo" src="../assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo">
            <h2>Student Registration</h2>
            <p>Create your hostel accommodation account</p>
        </div>
        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Form Number <small>(required)</small></label>
                    <input type="text" name="form_no" placeholder="e.g. F2405297" pattern="[FDfd][0-9]{7}" maxlength="8" minlength="8" style="text-transform:uppercase" required>
                    <small>Use F or D followed by exactly 7 digits.</small>
                </div>
                <div class="form-group">
                    <label>Matriculation Number <small>(optional)</small></label>
                    <input type="text" name="matric_no" placeholder="13-digit matric number" pattern="\d{13}" maxlength="13">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Surname First" required>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g. Computer Science" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Level</label>
                    <select name="level" required>
                        <option value="">Select Level</option>
                        <option value="ND1">ND 1</option>
                        <option value="ND2">ND 2</option>
                        <option value="HND1">HND 1</option>
                        <option value="HND2">HND 2</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="e.g. 08012345678" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your@email.com" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password <small>(minimum 8 characters)</small></label>
                    <input type="password" name="password" placeholder="Create password" required>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Register Now</button>
        </form>
        <div class="auth-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
        <div class="auth-link">
            <a href="../index.php">&larr; Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
