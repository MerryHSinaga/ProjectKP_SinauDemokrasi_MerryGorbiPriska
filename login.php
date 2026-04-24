<?php
declare(strict_types=1);
session_start();

require_once 'db.php';

const ADMIN_USERNAME = 'AdminSinauDemokrasi';
const ADMIN_PASSWORD = 'KPUYogyakart4#';

if (
    isset($_SESSION['last_activity'], $_SESSION['timeout']) &&
    (time() - (int)$_SESSION['last_activity']) <= (int)$_SESSION['timeout']
) {
    $_SESSION['last_activity'] = time();

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && !empty($_SESSION['admin_logged_in'])) {
        header('Location: admin.php');
        exit;
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'user' && !empty($_SESSION['user_logged_in'])) {
        header('Location: dashboard.php');
        exit;
    }
} else {
    if (isset($_SESSION['last_activity'], $_SESSION['timeout'])) {
        session_unset();
        session_destroy();
        session_start();
    }
}

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function seconds_left_from_datetime(?string $datetime): int {
    if (!$datetime) {
        return 0;
    }

    $ts = strtotime($datetime);
    if ($ts === false) {
        return 0;
    }

    return max(0, $ts - time());
}

function format_remaining_time(int $seconds): string {
    $seconds = max(0, $seconds);
    $minutes = intdiv($seconds, 60);
    $remainSeconds = $seconds % 60;

    if ($minutes > 0 && $remainSeconds > 0) {
        return $minutes . ' menit ' . $remainSeconds . ' detik';
    }
    if ($minutes > 0) {
        return $minutes . ' menit';
    }

    return $remainSeconds . ' detik';
}

function column_exists(string $table, string $column): bool {
    $sql = "
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";
    $st = db()->prepare($sql);
    $st->execute([$table, $column]);

    return (int)$st->fetchColumn() > 0;
}

function index_exists(string $table, string $indexName): bool {
    $sql = "
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ";
    $st = db()->prepare($sql);
    $st->execute([$table, $indexName]);

    return (int)$st->fetchColumn() > 0;
}

function ensure_users_table(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama VARCHAR(120) NOT NULL,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(120) NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            failed_login_count INT NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_users_username (username),
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!column_exists('users', 'id')) {
        db()->exec('ALTER TABLE users ADD COLUMN id INT AUTO_INCREMENT PRIMARY KEY FIRST');
    }

    if (!column_exists('users', 'nama')) {
        db()->exec("ALTER TABLE users ADD COLUMN nama VARCHAR(120) NOT NULL DEFAULT '' AFTER id");
    }

    if (!column_exists('users', 'username')) {
        db()->exec('ALTER TABLE users ADD COLUMN username VARCHAR(50) NOT NULL AFTER nama');
    }

    if (!column_exists('users', 'email')) {
        db()->exec('ALTER TABLE users ADD COLUMN email VARCHAR(120) NULL AFTER username');
    }

    if (!column_exists('users', 'password')) {
        db()->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER email");
    }

    if (!column_exists('users', 'role')) {
        db()->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password");
    }

    if (!column_exists('users', 'failed_login_count')) {
        db()->exec('ALTER TABLE users ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0 AFTER role');
    }

    if (!column_exists('users', 'locked_until')) {
        db()->exec('ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL AFTER failed_login_count');
    }

    if (!column_exists('users', 'last_login_at')) {
        db()->exec('ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER locked_until');
    }

    if (!column_exists('users', 'created_at')) {
        db()->exec('ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER last_login_at');
    }

    if (!index_exists('users', 'uq_users_username')) {
        db()->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)');
    }

    if (!index_exists('users', 'uq_users_email')) {
        db()->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)');
    }

    db()->exec("UPDATE users SET email = NULL WHERE email = ''");
    db()->exec('ALTER TABLE users MODIFY email VARCHAR(120) NULL');
}

function reset_session_login_attempts(): void {
    unset($_SESSION['login_fail_count'], $_SESSION['login_blocked_until']);
}

function session_is_blocked(): array {
    $blockUntil = (int)($_SESSION['login_blocked_until'] ?? 0);
    $remaining = max(0, $blockUntil - time());

    return [$remaining > 0, $remaining];
}

function add_session_failed_attempt(): array {
    $count = (int)($_SESSION['login_fail_count'] ?? 0);
    $count++;
    $_SESSION['login_fail_count'] = $count;

    if ($count >= 3) {
        $_SESSION['login_blocked_until'] = time() + 300;
        $_SESSION['login_fail_count'] = 0;

        return [true, 300];
    }

    return [false, 0];
}

function validate_nama(string $nama): string {
    $nama = trim($nama);

    if ($nama === '') {
        throw new RuntimeException('Nama lengkap wajib diisi.');
    }

    if (mb_strlen($nama, 'UTF-8') > 120) {
        throw new RuntimeException('Nama lengkap maksimal 120 karakter.');
    }

    return $nama;
}

function validate_username(string $username): string {
    $username = trim($username);

    if ($username === '') {
        throw new RuntimeException('Username wajib diisi.');
    }

    if (mb_strlen($username, 'UTF-8') < 4 || mb_strlen($username, 'UTF-8') > 30) {
        throw new RuntimeException('Username harus 4 sampai 30 karakter.');
    }

    if (!preg_match('/^[A-Za-z0-9._]+$/', $username)) {
        throw new RuntimeException('Username hanya boleh berisi huruf, angka, titik, dan underscore.');
    }

    return $username;
}

function validate_email(string $email): string {
    $email = trim($email);

    if ($email === '') {
        throw new RuntimeException('Email wajib diisi.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Format email tidak valid.');
    }

    if (mb_strlen($email, 'UTF-8') > 120) {
        throw new RuntimeException('Email maksimal 120 karakter.');
    }

    return $email;
}

function validate_password_rules(string $password): array {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password wajib mengandung huruf besar.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password wajib mengandung huruf kecil.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password wajib mengandung angka.';
    }
    if (!preg_match('/[!@#$%^&*]/', $password)) {
        $errors[] = 'Password wajib mengandung simbol (!@#$%^&*).';
    }

    return $errors;
}

function validate_password(string $password, string $confirmPassword): string {
    if ($password === '') {
        throw new RuntimeException('Password wajib diisi.');
    }

    $errors = validate_password_rules($password);
    if ($errors !== []) {
        throw new RuntimeException($errors[0]);
    }

    if ($password !== $confirmPassword) {
        throw new RuntimeException('Konfirmasi password tidak sama.');
    }

    return $password;
}

function find_user_by_username(string $username): array|false {
    $st = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);

    return $st->fetch(PDO::FETCH_ASSOC);
}

function find_user_by_email(string $email): array|false {
    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);

    return $st->fetch(PDO::FETCH_ASSOC);
}

function reset_user_failed_login_count(int $userId): void {
    $st = db()->prepare('
        UPDATE users
        SET failed_login_count = 0,
            locked_until = NULL
        WHERE id = ?
    ');
    $st->execute([$userId]);
}

function register_failed_attempt_for_user(int $userId, int $currentFailedAttempts): array {
    $failedAttempts = $currentFailedAttempts + 1;
    $lockUntil = null;
    $isBlocked = false;
    $remaining = 0;

    if ($failedAttempts >= 3) {
        $lockUntil = date('Y-m-d H:i:s', time() + 300);
        $failedAttempts = 0;
        $isBlocked = true;
        $remaining = 300;
    }

    $st = db()->prepare('
        UPDATE users
        SET failed_login_count = ?,
            locked_until = ?
        WHERE id = ?
    ');
    $st->execute([$failedAttempts, $lockUntil, $userId]);

    return [$isBlocked, $remaining];
}

function login_as_admin(string $username): void {
    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $username;

    $_SESSION['user_logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['nama'] = 'Administrator';
    $_SESSION['role'] = 'admin';

    $_SESSION['timeout'] = 1800;
    $_SESSION['last_activity'] = time();

    header('Location: admin.php');
    exit;
}

function login_as_user(array $user): void {
    session_regenerate_id(true);

    unset($_SESSION['admin_logged_in'], $_SESSION['admin_user']);

    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['nama'] = (string)$user['nama'];
    $_SESSION['role'] = 'user';

    $_SESSION['timeout'] = 1800;
    $_SESSION['last_activity'] = time();

    header('Location: dashboard.php');
    exit;
}

ensure_users_table();

$site_title = 'SI-NAU Demokrasi | Login';
include 'identitas.php';

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$error = '';
$success = '';

$oldEmail = '';
$oldNama = '';
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'login');
    $mode = $action === 'register' ? 'register' : 'login';

    try {
        if ($action === 'register') {
            $oldNama = trim((string)($_POST['nama'] ?? ''));
            $oldUsername = trim((string)($_POST['username'] ?? ''));
            $oldEmail = trim((string)($_POST['email'] ?? ''));

            $namaLengkap = validate_nama($oldNama);
            $username = validate_username($oldUsername);
            $email = validate_email($oldEmail);
            $password = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            validate_password($password, $confirmPassword);

            if (strcasecmp($username, ADMIN_USERNAME) === 0) {
                throw new RuntimeException('Username tersebut tidak bisa digunakan.');
            }

            $existing = find_user_by_username($username);
            if ($existing) {
                throw new RuntimeException('Username sudah digunakan. Silakan pakai username lain.');
            }

            $existingEmail = find_user_by_email($email);
            if ($existingEmail) {
                throw new RuntimeException('Email sudah digunakan. Silakan pakai email lain.');
            }

            $st = db()->prepare('
                INSERT INTO users (nama, username, email, password, role)
                VALUES (?, ?, ?, ?, \'user\')
            ');
            $st->execute([$namaLengkap, $username, $email, $password]);

            $success = 'Akun berhasil dibuat. Silakan login.';
            $mode = 'login';
            $oldNama = '';
            $oldUsername = '';
            $oldEmail = '';
        }

        if ($action === 'login') {
            [$sessionBlocked, $sessionRemaining] = session_is_blocked();
            if ($sessionBlocked) {
                throw new RuntimeException(
                    'Login diblokir sementara. Silakan coba lagi dalam ' . format_remaining_time($sessionRemaining) . '.'
                );
            }

            $username = validate_username((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            if ($password === '') {
                throw new RuntimeException('Password wajib diisi.');
            }

            $oldUsername = $username;

            if ($username === ADMIN_USERNAME) {
                if ($password === ADMIN_PASSWORD) {
                    reset_session_login_attempts();
                    login_as_admin($username);
                }

                [$blockedBySession, $remainingBySession] = add_session_failed_attempt();

                if ($blockedBySession) {
                    throw new RuntimeException(
                        'Kesempatan login habis. Silakan coba lagi dalam ' . format_remaining_time($remainingBySession) . '.'
                    );
                }

                throw new RuntimeException('Username atau password salah.');
            }

            $user = find_user_by_username($username);

            if ($user) {
                $dbRemaining = seconds_left_from_datetime((string)($user['locked_until'] ?? ''));
                if ($dbRemaining > 0) {
                    throw new RuntimeException(
                        'Login diblokir sementara. Silakan coba lagi dalam ' . format_remaining_time($dbRemaining) . '.'
                    );
                }
            }

            if (!$user || $password !== (string)$user['password']) {
                if ($user) {
                    [$blockedByUser, $remainingByUser] = register_failed_attempt_for_user(
                        (int)$user['id'],
                        (int)$user['failed_login_count']
                    );

                    if ($blockedByUser) {
                        throw new RuntimeException(
                            'Kesempatan login habis. Silakan coba lagi dalam ' . format_remaining_time($remainingByUser) . '.'
                        );
                    }
                } else {
                    [$blockedBySession, $remainingBySession] = add_session_failed_attempt();

                    if ($blockedBySession) {
                        throw new RuntimeException(
                            'Kesempatan login habis. Silakan coba lagi dalam ' . format_remaining_time($remainingBySession) . '.'
                        );
                    }
                }

                throw new RuntimeException('Username atau password salah.');
            }

            reset_user_failed_login_count((int)$user['id']);
            reset_session_login_attempts();

            db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);

            login_as_user($user);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
    --maroon:#700D09;
    --bg:#E9EDFF;
    --gold:#f4c430;
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:var(--bg);
    min-height:100vh;
}

.page{
    padding:110px 16px 40px;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.auth-card{
    width:min(980px,92vw);
    background:#fff;
    border-radius:22px;
    box-shadow:0 12px 26px rgba(0,0,0,.18);
    overflow:hidden;
    display:grid;
    grid-template-columns:1fr 1.2fr;
}

.card-left{
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px 20px;
    background:#fff;
}

.profile-icon{
    font-size:180px;
    color:var(--maroon);
}

.card-right{
    color:#fff;
    padding:54px 56px 42px;
    background:linear-gradient(145deg,#8f0f0b 0%,#7a0c08 45%,var(--maroon) 100%);
    border-top-left-radius:60px;
    border-bottom-left-radius:60px;
}

.title{
    text-align:center;
    font-weight:800;
    font-size:30px;
    margin-bottom:32px;
}

.form-group{
    margin-bottom:22px;
}

.form-group label{
    display:block;
    font-weight:700;
    font-size:14px;
    margin-bottom:8px;
}

.input{
    width:100%;
    background:transparent;
    border:0;
    border-bottom:2px solid rgba(255,255,255,.35);
    color:#fff;
    font-size:16px;
    padding:8px 0;
    outline:none;
}

.input::placeholder{
    color:rgba(255,255,255,.55);
}

.password-wrap{
    position:relative;
}

.toggle-password{
    position:absolute;
    right:0;
    bottom:10px;
    cursor:pointer;
    color:#fff;
    opacity:.7;
    font-size:20px;
}

.toggle-password:hover{
    opacity:1;
}

.pw-checklist{
    list-style:none;
    padding:0;
    margin:12px 0 0;
    font-size:11px;
    font-weight:700;
}

.pw-item{
    display:flex;
    align-items:center;
    gap:6px;
    margin-bottom:4px;
    color:#ffffff;
    transition:color .2s ease;
}

.pw-item i{
    font-size:14px;
    line-height:1;
}

.pw-item.valid{
    color:#0f7b3b;
}

.actions{
    display:flex;
    justify-content:center;
    margin-top:28px;
}

.btn-submit{
    background:#fff;
    color:var(--maroon);
    font-weight:800;
    font-size:16px;
    padding:12px 40px;
    border-radius:999px;
    border:0;
    box-shadow:0 8px 18px rgba(0,0,0,.18);
    transition:.2s ease;
}

.btn-submit:hover{
    transform:translateY(-1px);
    filter:brightness(.98);
}

.switch-text{
    margin-top:18px;
    text-align:center;
    font-size:14px;
    color:rgba(255,255,255,.95);
}

.switch-text a{
    color:#fff;
    font-weight:700;
    text-decoration:none;
}

.switch-text a:hover{
    text-decoration:underline;
}

.alert{
    border-radius:12px;
    margin-top:20px;
    font-weight:700;
    text-align:center;
}

.alert-danger{
    background:#fff;
    color:#8b0000;
    border:none;
}

.alert-success{
    background:#fff;
    color:#146c43;
    border:none;
}

@media (max-width:900px){
    .auth-card{
        grid-template-columns:1fr;
    }
    .card-left{
        display:none;
    }
    .card-right{
        border-radius:22px;
        padding:38px 26px 34px;
    }
    .title{
        font-size:26px;
    }
}
</style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="page">
<section class="auth-card">

    <div class="card-left">
        <i class="bi bi-person-circle profile-icon"></i>
    </div>

    <div class="card-right">
        <?php if ($mode === 'register'): ?>
            <h1 class="title">Buat Akun</h1>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input
                        class="input"
                        type="text"
                        name="nama"
                        value="<?= h($oldNama) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input
                        class="input"
                        type="text"
                        name="username"
                        value="<?= h($oldUsername) ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input
                        class="input"
                        type="email"
                        name="email"
                        value="<?= h($oldEmail) ?>"
                        required
                    >
                </div>

                <div class="form-group password-wrap">
                    <label>Password</label>
                    <input
                        id="registerPassword"
                        class="input"
                        type="password"
                        name="password"
                        minlength="8"
                        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*]).{8,}$"
                        title="Password minimal 8 karakter, terdiri dari huruf besar, huruf kecil, angka, dan simbol !@#$%^&*"
                        autocomplete="new-password"
                        required
                    >
                    <i class="bi bi-eye toggle-password" data-target="registerPassword"></i>
                    <ul class="pw-checklist" id="pwChecklist">
                        <li class="pw-item" data-rule="length"><i class="bi bi-circle"></i> Minimal 8 karakter</li>
                        <li class="pw-item" data-rule="upper"><i class="bi bi-circle"></i> Huruf besar</li>
                        <li class="pw-item" data-rule="lower"><i class="bi bi-circle"></i> Huruf kecil</li>
                        <li class="pw-item" data-rule="number"><i class="bi bi-circle"></i> Angka</li>
                        <li class="pw-item" data-rule="symbol"><i class="bi bi-circle"></i> Simbol (!@#$%^&*)</li>
                    </ul>
                </div>

                <div class="form-group password-wrap">
                    <label>Konfirmasi Password</label>
                    <input
                        id="registerConfirmPassword"
                        class="input"
                        type="password"
                        name="confirm_password"
                        autocomplete="new-password"
                        required
                    >
                    <i class="bi bi-eye toggle-password" data-target="registerConfirmPassword"></i>
                </div>

                <div class="actions">
                    <button class="btn-submit" type="submit">Buat Akun</button>
                </div>

                <div class="switch-text">
                    Sudah punya akun? <a href="login.php">Silakan masuk</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <h1 class="title">Silakan Login</h1>

            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label>Username</label>
                    <input
                        class="input"
                        type="text"
                        name="username"
                        value="<?= h($oldUsername) ?>"
                        required
                    >
                </div>

                <div class="form-group password-wrap">
                    <label>Password</label>
                    <input
                        id="loginPassword"
                        class="input"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                    <i class="bi bi-eye toggle-password" data-target="loginPassword"></i>
                </div>

                <div class="actions">
                    <button class="btn-submit" type="submit">Masuk</button>
                </div>

                <div class="switch-text">
                    Belum punya akun? <a href="login.php?mode=register">Silakan buat akun</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= h($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= h($success) ?>
                    </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

</section>
</main>

<script>
document.querySelectorAll('.toggle-password').forEach(toggle => {
    toggle.addEventListener('click', () => {
        const targetId = toggle.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.className = show
            ? 'bi bi-eye-slash toggle-password'
            : 'bi bi-eye toggle-password';
        toggle.setAttribute('data-target', targetId);
    });
});

const registerPassword = document.getElementById('registerPassword');
const registerConfirmPassword = document.getElementById('registerConfirmPassword');
const pwChecklistItems = document.querySelectorAll('#pwChecklist .pw-item');

const passwordRules = {
    length: value => value.length >= 8,
    upper: value => /[A-Z]/.test(value),
    lower: value => /[a-z]/.test(value),
    number: value => /[0-9]/.test(value),
    symbol: value => /[!@#$%^&*]/.test(value)
};

function updatePasswordCriteria() {
    if (!registerPassword) return;

    const passwordValue = registerPassword.value;

    pwChecklistItems.forEach(item => {
        const ruleName = item.getAttribute('data-rule');
        const icon = item.querySelector('i');
        const isValid = passwordRules[ruleName] ? passwordRules[ruleName](passwordValue) : false;

        item.classList.toggle('valid', isValid);

        if (icon) {
            icon.className = isValid ? 'bi bi-check-circle-fill' : 'bi bi-circle';
        }
    });
}

if (registerPassword) {
    registerPassword.addEventListener('input', updatePasswordCriteria);
}

if (registerConfirmPassword) {
    registerConfirmPassword.addEventListener('input', updatePasswordCriteria);
}

updatePasswordCriteria();
</script>

<?php include 'footer.php'; ?>

</body>
</html>
