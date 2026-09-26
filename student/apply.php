<?php
session_start();
if (!isset($_SESSION['student_id'])) { header('Location: login.php'); exit; }

require_once '../includes/db.php';
require_once '../includes/csrf.php';
require_once '../includes/remita.php';

$sid = (int)$_SESSION['student_id'];
$msg = $err = '';
$hostelFee = 25000.00;

$studentStmt = $pdo->prepare("SELECT * FROM students WHERE student_id=? LIMIT 1");
$studentStmt->execute([$sid]);
$student = $studentStmt->fetch();

$existingStmt = $pdo->prepare("SELECT * FROM applications WHERE student_id=? ORDER BY applied_at DESC LIMIT 1");
$existingStmt->execute([$sid]);
$existing = $existingStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $err = 'Your session token is invalid or expired. Please refresh the page and try again.';
    } elseif (!$student) {
        $err = 'Your student account could not be found. Please log in again.';
    } else {
        $hostel_id = (int)($_POST['hostel_id'] ?? 0);
        $rrr = preg_replace('/\s+/', '', trim($_POST['payment_ref'] ?? ''));

        // The server re-checks hostel and gender regardless of what the browser sends.
        $hostelStmt = $pdo->prepare("SELECT * FROM hostels WHERE hostel_id=? LIMIT 1");
        $hostelStmt->execute([$hostel_id]);
        $hostel = $hostelStmt->fetch();

        if (!$hostel) {
            $err = 'Please select a valid hostel.';
        } elseif ($hostel['hostel_type'] !== $student['gender']) {
            $err = 'You can only apply to a hostel assigned to your gender.';
        } elseif ($rrr === '' || !preg_match('/^[0-9-]{8,30}$/', $rrr)) {
            $err = 'Enter the Remita Retrieval Reference (RRR) from your payment receipt.';
        } else {
            // Verify the RRR immediately before creating the application.
            $verification = verify_remita_rrr($rrr, $hostelFee);

            if (!$verification['verified']) {
                $err = $verification['message'];
            } else {
                try {
                    $pdo->beginTransaction();

                    // Re-check that the student has not submitted another application.
                    $lock = $pdo->prepare("SELECT app_id FROM applications WHERE student_id=? LIMIT 1 FOR UPDATE");
                    $lock->execute([$sid]);
                    if ($lock->fetch()) {
                        throw new RuntimeException('You already have an application.');
                    }

                    // Prevent the same Remita RRR from being used more than once.
                    $duplicatePayment = $pdo->prepare("SELECT payment_id FROM payments WHERE payment_ref=? LIMIT 1 FOR UPDATE");
                    $duplicatePayment->execute([$rrr]);
                    if ($duplicatePayment->fetch()) {
                        throw new RuntimeException('This RRR has already been used.');
                    }

                    // Record the verified payment.
                    $pdo->prepare("INSERT INTO payments
                        (student_id, amount, payment_ref, verified, verification_source, verified_at)
                        VALUES (?,?,?,?,?,NOW())")
                        ->execute([$sid, $hostelFee, $rrr, 'Yes', 'Remita']);

                    // The student only applies for a hostel at this stage.
                    // Room and bunk assignment happen after admin approval.
                    $pdo->prepare("INSERT INTO applications
                        (student_id, hostel_id, preferred_room_id, preferred_bunk, payment_ref, status, applied_at)
                        VALUES (?,?,NULL,NULL,?,'Pending',NOW())")
                        ->execute([$sid, $hostel_id, $rrr]);

                    $applicationId = (int)$pdo->lastInsertId();

                    $message = $student['full_name'] . ' submitted a new hostel application.';
                    $pdo->prepare("INSERT INTO admin_notifications
                        (hostel_id, application_id, type, message)
                        VALUES (?,?,?,?)")
                        ->execute([$hostel_id, $applicationId, 'new_application', $message]);

                    $pdo->commit();

                    $msg = 'Payment verified and your hostel application was submitted successfully. Your application time has been recorded for first-come, first-served processing.';

                    $existingStmt->execute([$sid]);
                    $existing = $existingStmt->fetch();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $err = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Your application could not be submitted. Please try again.';
                }
            }
        }
    }
}

$hostels = [];

if ($student) {
    $hostelStmt = $pdo->prepare("SELECT h.*, COUNT(r.room_id) AS available_rooms
        FROM hostels h
        LEFT JOIN rooms r
            ON r.hostel_id=h.hostel_id
            AND r.status='Available'
        WHERE h.hostel_type=?
        GROUP BY h.hostel_id
        ORDER BY h.hostel_name");

    $hostelStmt->execute([$student['gender']]);
    $hostels = $hostelStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply for Hostel</title>
<link rel="stylesheet" href="../css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="wrapper">

<aside class="sidebar">

    <div class="user-info">
        <div class="avatar">
            <?= $student ? htmlspecialchars(strtoupper(substr($student['full_name'],0,1))) : '?' ?>
        </div>

        <div class="name">
            <?= $student ? htmlspecialchars(explode(' ',$student['full_name'])[0]) : 'Student' ?>
        </div>

        <div class="role">
            <?= $student ? htmlspecialchars($student['matric_no'] ?: $student['form_no']) : '' ?>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="apply.php" class="active">My Room Allocation</a>
        <a href="payments.php">Payments</a>
        <a href="reports.php">My Report</a>
        <a href="profile.php">My Profile</a>
        <a href="logout.php">Logout</a>
    </nav>

</aside>

<main class="main">

    <div class="page-title">Apply for Hostel Accommodation</div>

    <div class="page-subtitle">
        Payment is verified before your application is accepted.
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

    <?php if (!$student): ?>

        <div class="alert alert-error">
            Your student account could not be found. Please log in again.
        </div>

    <?php elseif ($existing): ?>

        <div class="alert alert-info">
            You have already submitted an application.
            Current status:
            <strong><?= htmlspecialchars($existing['status']) ?></strong>.
            <a href="status.php">View your application &rarr;</a>
        </div>

    <?php else: ?>

        <div class="alert alert-warning">
            <strong>Important:</strong>
            Enter the <strong>Remita Retrieval Reference (RRR)</strong>
            printed on your payment receipt.

            The system will verify the RRR with Remita before creating your application.

            Hostel fee:
            <strong>₦<?= number_format($hostelFee, 2) ?></strong>.
        </div>

        <div class="alert alert-info">
            <strong>Application process:</strong>
            Select your preferred hostel and enter your Remita RRR.
            Room assignment will be handled by the hostel administrator after your application is approved.
            If your assigned hostel uses bunks, you will choose your bunk after a room has been assigned to you.
        </div>

        <div class="form-card" style="max-width:100%;">

            <h3>Hostel Application</h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrf_token()) ?>"
                >

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;padding:16px;background:#f8fafc;border-radius:8px;">

                    <div>
                        <strong>Name:</strong>
                        <?= htmlspecialchars($student['full_name']) ?>
                    </div>

                    <div>
                        <strong>Matric No:</strong>
                        <?= htmlspecialchars($student['matric_no'] ?: 'Not assigned') ?>
                    </div>

                    <div>
                        <strong>Form No:</strong>
                        <?= htmlspecialchars($student['form_no'] ?: 'Not assigned') ?>
                    </div>

                    <div>
                        <strong>Department:</strong>
                        <?= htmlspecialchars($student['department']) ?>
                    </div>

                    <div>
                        <strong>Level:</strong>
                        <?= htmlspecialchars($student['level']) ?>
                    </div>

                    <div>
                        <strong>Gender:</strong>
                        <?= htmlspecialchars($student['gender']) ?>
                    </div>

                </div>

                <div class="form-group">

                    <label for="hostel_id">
                        Select Preferred Hostel
                    </label>

                    <select name="hostel_id" id="hostel_id" required>

                        <option value="">
                            -- Select Hostel --
                        </option>

                        <?php foreach ($hostels as $h): ?>

                            <option value="<?= (int)$h['hostel_id'] ?>">

                                <?= htmlspecialchars($h['hostel_name']) ?>

                                (<?= htmlspecialchars($h['hostel_type']) ?>),

                                <?= (int)$h['available_rooms'] ?>
                                rooms available

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small>
                        You are only shown hostels available for your gender.
                    </small>

                </div>

                <div class="form-group">

                    <label for="payment_ref">
                        Remita Retrieval Reference (RRR)
                    </label>

                    <input
                        type="text"
                        name="payment_ref"
                        id="payment_ref"
                        placeholder="e.g. 1234-5678-9012"
                        maxlength="30"
                        required
                    >

                    <small style="color:#64748b;font-size:0.8rem;">
                        Use the RRR shown on your Remita payment receipt.
                        Bank teller references are no longer accepted here.
                    </small>

                </div>

                <div style="padding:14px;background:#fef3c7;border-radius:8px;margin-bottom:16px;font-size:0.875rem;color:#92400e;">

                    <strong>Declaration:</strong>
                    I confirm that the information provided is accurate and that the RRR belongs to my hostel accommodation payment.

                </div>

                <button type="submit" class="btn btn-primary">
                    Verify Payment &amp; Submit Application
                </button>

            </form>

        </div>

    <?php endif; ?>

</main>

</div>

<div class="footer">
    &copy; <?= date('Y') ?> The Polytechnic, Ibadan
</div>

</body>
</html>
