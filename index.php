<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hostel Management System - The Polytechnic, Ibadan</title>
<link rel="stylesheet" href="css/style.css?v=6">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="landing-page">
<div class="hero">
    <div class="hero-content">
        <img class="hero-logo" src="assets/POLYLOGO.jpg" alt="The Polytechnic, Ibadan logo">
        <p class="eyebrow">The Polytechnic, Ibadan</p>
        <h1>Hostel accommodation, made simpler.</h1>
        <p class="subtitle">Apply, manage, and track campus accommodation in one place.</p>
        <div class="hero-cards">
            <a href="admin/login.php" class="hero-card">
                <span class="card-icon">🔐</span>
                <span>
                    <h3>Admin Portal</h3>
                    <p>Manage hostels, rooms, students and allocations</p>
                </span>
                <span class="card-arrow">&rarr;</span>
            </a>
            <a href="student/login.php" class="hero-card">
                <span class="card-icon">🎓</span>
                <span>
                    <h3>Student Portal</h3>
                    <p>Apply for accommodation and check your status</p>
                </span>
                <span class="card-arrow">&rarr;</span>
            </a>
        </div>
    </div>
</div>
<div class="footer">
    &copy; <?= date('Y') ?> The Polytechnic, Ibadan Computerized Hostel Accommodation Management System
</div>
</body>
</html>
