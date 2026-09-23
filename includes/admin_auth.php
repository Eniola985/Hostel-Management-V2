<?php
// Hostel-admin authentication and authorization helpers.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['admin_id'], $_SESSION['admin_hostel_id'])) {
    header('Location: login.php');
    exit;
}

$admin_hostel_id = (int) $_SESSION['admin_hostel_id'];
$admin_hostel_name = $_SESSION['admin_hostel_name'] ?? '';

function require_admin_hostel(int $hostel_id): void {
    global $admin_hostel_id;
    if ($hostel_id !== $admin_hostel_id) {
        http_response_code(403);
        exit('Access denied: this hostel portal cannot access another hostel.');
    }
}

function current_admin_hostel(PDO $pdo): array {
    global $admin_hostel_id;
    $stmt = $pdo->prepare('SELECT hostel_id, hostel_name, hostel_type, total_rooms FROM hostels WHERE hostel_id = ?');
    $stmt->execute([$admin_hostel_id]);
    $hostel = $stmt->fetch();
    if (!$hostel) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
    return $hostel;
}
