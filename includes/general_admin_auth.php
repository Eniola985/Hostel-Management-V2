<?php
// General-admin authentication and authorization helpers.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

if (!isset($_SESSION['general_admin_id'])) {
    header('Location: login.php');
    exit;
}

$general_admin_id = (int) $_SESSION['general_admin_id'];
$general_admin_name = $_SESSION['general_admin_name'] ?? '';

function current_general_admin(PDO $pdo): array {
    global $general_admin_id;

    $stmt = $pdo->prepare(
        'SELECT admin_id, username, created_at, last_login_at
         FROM general_admins
         WHERE admin_id = ?'
    );
    $stmt->execute([$general_admin_id]);

    $admin = $stmt->fetch();

    if (!$admin) {
        $_SESSION = [];
        session_destroy();

        header('Location: login.php');
        exit;
    }

    return $admin;
}
?>
