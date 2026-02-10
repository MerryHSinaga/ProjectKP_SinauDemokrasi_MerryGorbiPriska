<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function forceLogout() {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }

    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}

function requireState(string $expected) {
    if (
        empty($_SESSION['flow']) ||
        empty($_SESSION['flow']['state']) ||
        $_SESSION['flow']['state'] !== $expected ||
        ($_SESSION['flow']['locked'] ?? false) === true
    ) {
        forceLogout();
    }
}
