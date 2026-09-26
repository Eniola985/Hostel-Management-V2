<?php
session_start();

unset(
    $_SESSION['general_admin_id'],
    $_SESSION['general_admin_name']
);

header('Location: login.php');
exit;
