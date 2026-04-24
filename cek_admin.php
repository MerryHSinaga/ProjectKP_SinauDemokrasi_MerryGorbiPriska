<?php
declare(strict_types=1);
session_start();

if (
    empty($_SESSION['admin']) ||
    empty($_SESSION['admin_user']) ||
    empty($_SESSION['last_activity']) ||
    empty($_SESSION['timeout'])
) {
    header("Location: login_admin.php");
    exit;
}

if ((time() - $_SESSION['last_activity']) > $_SESSION['timeout']) {
    session_unset();
    session_destroy();
    header("Location: login_admin.php");
    exit;
}

$_SESSION['last_activity'] = time();
