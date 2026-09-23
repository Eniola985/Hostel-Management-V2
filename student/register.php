<?php
session_start();
require_once '../includes/db.php';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric = trim($_POST['matric_no']);
    $name = trim($_POST['full_name']);
    $dept = trim($_POST['department']);
    $level = $_POST['level'];
    $gender = $_POST['gender'];
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $pass = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);

    if (!preg_match('/^\d{13}$/', $matric)) {
        $err = 'Matriculation number must be exactly 13 digits.';
    } elseif ($pass !== $confirm) {
        $err = 'Passwords do not match.';
    } else {
        $check = $pdo->prepare("SELECT * FROM students WHERE matric_no=?");
        $check->execute([$matric]);
        if ($check->fetch()) {
            $err = 'A student with this matriculation number already exists.';
        } else {
            $pdo->prepare("INSERT INTO students (matric_no, full_name, department, level, gender, phone, email, password) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$matric, $name, $dept, $level, $gender, $phone, $email, $pass]);
            $msg = 'Registration successful! You can now login.';
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
                    <label>Matriculation Number (13 digits)</label>
                    <input type="text" name="matric_no" placeholder="e.g. 2024705010106" pattern="\d{13}" maxlength="13" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" placeholder="Surname First" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" placeholder="e.g. Computer Science" required>
                </div>
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
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="e.g. 08012345678" required>
                </div>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your@email.com">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
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
