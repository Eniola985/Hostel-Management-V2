<?php
$environmentFile = __DIR__ . '/../.env.local';
if (is_readable($environmentFile)) {
    $environment = parse_ini_file($environmentFile);
} else {
    $environment = [];
}

$host = $environment['DB_HOST'] ?? '';
$dbname = $environment['DB_NAME'] ?? '';
$username = $environment['DB_USER'] ?? '';
$password = $environment['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
