<?php
session_start();

require_once '../includes/db.php';

$msg = '';
$err = '';

$form_no = strtoupper(trim($_POST['form_no'] ?? ''));
$name = trim($_POST['full_name'] ?? '');
$dept = trim($_POST['department'] ?? '');
$level = $_POST['level'] ?? '';
$gender = $_POST['gender'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$pass = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $photo_path = null;
    $saved_photo_path = null;

    if (!preg_match('/^[FD][0-9]{7}$/', $form_no)) {
        $err = 'Form number is required and must be in the format F2405297 or D2405297.';
    } elseif ($name === '') {
        $err = 'Please enter your full name.';
    } elseif ($dept === '') {
        $err = 'Please enter your department.';
    } elseif ($level === '') {
        $err = 'Please select your level.';
    } elseif ($gender === '') {
        $err = 'Please select your gender.';
    } elseif ($phone === '') {
        $err = 'Please enter your phone number.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please provide a valid email address.';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $err = 'Passwords do not match.';
    } elseif (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] === UPLOAD_ERR_NO_FILE) {
        $err = 'Please upload a passport or identification photograph.';
    } elseif ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $err = 'The photograph could not be uploaded. Please try again.';
    } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
        $err = 'The photograph must not be larger than 5 MB.';
    } else {
        $photo_tmp = $_FILES['profile_photo']['tmp_name'];

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $photo_mime = $finfo->file($photo_tmp);

        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowed_mimes[$photo_mime])) {
            $err = 'Invalid photograph format. Please upload a JPG, PNG, or WebP image.';
        } else {
            $check = $pdo->prepare(
                "SELECT student_id
                 FROM students
                 WHERE form_no=? OR email=?
                 LIMIT 1"
            );
            $check->execute([$form_no, $email]);

            if ($check->fetch()) {
                $err = 'A student with this form number or email already exists.';
            } else {
                $upload_dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'student_photos';

                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    $err = 'The photograph upload directory could not be created.';
                } else {
                    $extension = $allowed_mimes[$photo_mime];

                    $safe_form_no = preg_replace('/[^A-Za-z0-9_-]/', '', $form_no);
                    $unique_name = $safe_form_no . '_' . bin2hex(random_bytes(8)) . '.' . $extension;

                    $absolute_photo_path = $upload_dir . DIRECTORY_SEPARATOR . $unique_name;
                    $photo_path = 'uploads/student_photos/' . $unique_name;

                    if (!move_uploaded_file($photo_tmp, $absolute_photo_path)) {
                        $err = 'The photograph could not be saved. Please try again.';
                    } else {
                        $saved_photo_path = $absolute_photo_path;

                        try {
                            $password_hash = password_hash($pass, PASSWORD_DEFAULT);

                            $statement = $pdo->prepare(
                                "INSERT INTO students
                                (
                                    form_no,
                                    full_name,
                                    department,
                                    level,
                                    gender,
                                    phone,
                                    email,
                                    password,
                                    profile_photo
                                )
                                VALUES (?,?,?,?,?,?,?,?,?)"
                            );

                            $statement->execute([
                                $form_no,
                                $name,
                                $dept,
                                $level,
                                $gender,
                                $phone,
                                $email,
                                $password_hash,
                                $photo_path
                            ]);

                            $msg = 'Registration successful! You can now login with your form number.';

                            $form_no = '';
                            $name = '';
                            $dept = '';
                            $level = '';
                            $gender = '';
                            $phone = '';
                            $email = '';
                            $pass = '';
                            $confirm = '';
                        } catch (PDOException $e) {
                            if ($saved_photo_path && file_exists($saved_photo_path)) {
                                unlink($saved_photo_path);
                            }

                            $err = 'Registration could not be completed. Please try again.';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Student Registration - Hostel Management</title>

<link rel="stylesheet" href="../css/style.css?v=6">

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>
</head>

<body>

<div class="auth-page">

    <div class="auth-box" style="max-width:520px;">

        <div class="auth-header">

            <img
                class="auth-logo"
                src="../assets/POLYLOGO.jpg"
                alt="The Polytechnic, Ibadan logo"
            >

            <h2>Student Registration</h2>

            <p>Create your hostel accommodation account</p>

        </div>

        <?php if ($msg): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($err): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($err) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">

                <label for="profile_photo">
                    Passport / Identification Photograph
                </label>

                <input
                    type="file"
                    id="profile_photo"
                    name="profile_photo"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >

                <small>
                    Upload a clear passport or identification photograph.
                    JPG, PNG, or WebP only. Maximum size: 5 MB.
                </small>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Form Number</label>

                    <input
                        type="text"
                        name="form_no"
                        placeholder="e.g. D2405297"
                        value="<?= htmlspecialchars($form_no) ?>"
                        maxlength="8"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Full Name</label>

                    <input
                        type="text"
                        name="full_name"
                        placeholder="Surname First"
                        value="<?= htmlspecialchars($name) ?>"
                        required
                    >

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Department</label>

                    <input
                        type="text"
                        name="department"
                        placeholder="e.g. Computer Science"
                        value="<?= htmlspecialchars($dept) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Level</label>

                    <select name="level" required>

                        <option value="">Select Level</option>

                        <option
                            value="ND1"
                            <?= $level === 'ND1' ? 'selected' : '' ?>
                        >
                            ND 1
                        </option>

                        <option
                            value="ND2"
                            <?= $level === 'ND2' ? 'selected' : '' ?>
                        >
                            ND 2
                        </option>

                        <option
                            value="HND1"
                            <?= $level === 'HND1' ? 'selected' : '' ?>
                        >
                            HND 1
                        </option>

                        <option
                            value="HND2"
                            <?= $level === 'HND2' ? 'selected' : '' ?>
                        >
                            HND 2
                        </option>

                    </select>

                </div>

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>Gender</label>

                    <select name="gender" required>

                        <option value="">Select Gender</option>

                        <option
                            value="Male"
                            <?= $gender === 'Male' ? 'selected' : '' ?>
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            <?= $gender === 'Female' ? 'selected' : '' ?>
                        >
                            Female
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <label>Phone Number</label>

                    <input
                        type="text"
                        name="phone"
                        placeholder="e.g. 08012345678"
                        value="<?= htmlspecialchars($phone) ?>"
                        required
                    >

                </div>

            </div>

            <div class="form-group">

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    placeholder="your@email.com"
                    value="<?= htmlspecialchars($email) ?>"
                    required
                >

            </div>

            <div class="form-row">

                <div class="form-group">

                    <label>
                        Password
                        <small>(minimum 8 characters)</small>
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Create password"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Confirm Password</label>

                    <input
                        type="password"
                        name="confirm_password"
                        placeholder="Repeat password"
                        required
                    >

                </div>

            </div>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Register Now
            </button>

        </form>

        <div class="auth-link">

            Already have an account?
            <a href="login.php">Login here</a>

        </div>

        <div class="auth-link">

            <a href="../index.php">&larr; Back to Home</a>

        </div>

    </div>

</div>

</body>
</html>