<?php
declare(strict_types=1);
session_start();
require_once 'db.php';

if (
    !isset($_SESSION['user_logged_in']) ||
    $_SESSION['user_logged_in'] !== true ||
    !isset($_SESSION['user_id'])
) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['timeout'], $_SESSION['last_activity'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

if (time() - (int)$_SESSION['last_activity'] > (int)$_SESSION['timeout']) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$_SESSION['last_activity'] = time();
$user_id = (int) $_SESSION['user_id'];

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function column_exists(string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $st = db()->prepare($sql);
    $st->execute([$table, $column]);
    return (int) $st->fetchColumn() > 0;
}

function table_exists(string $table): bool
{
    $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $st = db()->prepare($sql);
    $st->execute([$table]);
    return (int) $st->fetchColumn() > 0;
}

function format_datetime_id(?string $datetime): string
{
    if (!$datetime) return '-';
    $ts = strtotime($datetime);
    if ($ts === false) return (string) $datetime;
    return date('d M Y, H:i', $ts);
}

function do_logout_and_redirect(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

function validate_password_rules(string $password): array
{
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
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password wajib mengandung simbol.';
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'logout') {
    do_logout_and_redirect();
}

$hasEmail = column_exists('users', 'email');
$hasFoto  = column_exists('users', 'foto_path');

$errors        = [];
$success       = false;
$ada_perubahan = false;

$selectFields = ['id', 'nama', 'username', 'password'];
if ($hasEmail) $selectFields[] = 'email';
if ($hasFoto)  $selectFields[] = 'foto_path';

$stmt = db()->prepare("SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $nama     = trim((string) ($_POST['nama'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password_baru'] ?? '');

    if ($nama === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

    if ($username === '') {
        $errors[] = 'Username wajib diisi.';
    }

    if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($password !== '') {
        $errors = array_merge($errors, validate_password_rules($password));
    }

    try {
        $st = db()->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
        $st->execute([$username, $user_id]);
        if ($st->fetch()) {
            $errors[] = 'Username sudah digunakan.';
        }

        if ($hasEmail && $email !== '') {
            $st = db()->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
            $st->execute([$email, $user_id]);
            if ($st->fetch()) {
                $errors[] = 'Email sudah digunakan.';
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Gagal memvalidasi data pengguna.';
    }

    $fotoPathBaru  = null;
    $ada_foto_baru = false;
    if (
        isset($_FILES['foto']) &&
        is_array($_FILES['foto']) &&
        ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
    ) {
        $fileError = (int) ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload foto profil gagal.';
        } else {
            $tmpName  = (string) $_FILES['foto']['tmp_name'];
            $fileName = (string) $_FILES['foto']['name'];
            $fileSize = (int) ($_FILES['foto']['size'] ?? 0);
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $errors[] = 'Foto profil harus berupa JPG, JPEG, PNG, atau WEBP.';
            }

            if ($fileSize > 2 * 1024 * 1024) {
                $errors[] = 'Ukuran foto profil maksimal 2MB.';
            }

            if (!$errors) {
                $uploadDir = __DIR__ . '/uploads/profile';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }

                $newName = 'profile_' . $user_id . '' . date('Ymd_His') . '' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest    = $uploadDir . '/' . $newName;

                if (!move_uploaded_file($tmpName, $dest)) {
                    $errors[] = 'Gagal menyimpan foto profil.';
                } else {
                    $fotoPathBaru  = 'uploads/profile/' . $newName;
                    $ada_foto_baru = true;
                }
            }
        }
    }

    if (!$errors) {
        $nama_berubah     = $nama !== (string)($user['nama'] ?? '');
        $username_berubah = $username !== (string)($user['username'] ?? '');
        $email_berubah    = $hasEmail && $email !== (string)($user['email'] ?? '');
        $password_berubah = $password !== '';
        $foto_berubah     = $ada_foto_baru;

        $ada_perubahan = $nama_berubah || $username_berubah || $email_berubah || $password_berubah || $foto_berubah;

        if ($ada_perubahan) {
            try {
                $updateFields = ['nama = ?', 'username = ?'];
                $params       = [$nama, $username];
                if ($hasEmail) {
                    $updateFields[] = 'email = ?';
                    $params[] = ($email !== '' ? $email : null);
                }

                if ($fotoPathBaru !== null && $hasFoto) {
                    $updateFields[] = 'foto_path = ?';
                    $params[] = $fotoPathBaru;
                }

                if ($password_berubah) {
                    $updateFields[] = 'password = ?';
                    $params[] = $password;
                }

                $params[] = $user_id;
                db()->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?")->execute($params);
                if ($fotoPathBaru !== null) {
                    $_SESSION['foto_path'] = $fotoPathBaru;
                }

                $_SESSION['username'] = $username;
                $_SESSION['nama']     = $nama;

                $success = true;
                $stmt = db()->prepare("SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $errors[] = 'Terjadi kesalahan saat menyimpan perubahan profil.';
            }
        }
    }
}

$riwayat = [];
if (table_exists('user_aktivitas_kuis')) {
    try {
        $stmt = db()->prepare("\n            SELECT id, judul_kuis, skor, lulus, sertifikat_path, created_at\n            FROM user_aktivitas_kuis\n            WHERE user_id = ?\n            ORDER BY created_at DESC, id DESC\n            LIMIT 30\n        ");
        $stmt->execute([$user_id]);
        $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $riwayat = [];
    }
}

$site_title = 'Profil Saya';
$activePage = '';
include 'identitas.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --maroon: #700D09;
            --bg: #E9EDFF;
            --text: #122033;
            --shadow: 0 14px 32px rgba(0,0,0,.10);
            --soft: #f6f7fb;
            --green: #0f7b3b;
            --red: #b42318;
            --radius-lg: 28px;
            --radius-md: 18px;
            --radius-sm: 14px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 120px 16px 60px;
        }

        .profile-card {
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid rgba(112,13,9,.07);
            overflow: hidden;
        }

        .profile-card-header {
            background: linear-gradient(120deg, var(--maroon) 0%, #9d120d 100%);
            padding: 32px 36px;
        }

        .profile-main-layout {
            display: flex;
            align-items: center;
            gap: 36px;
            max-width: 680px;
        }

        .avatar-col { flex-shrink: 0; }

        .avatar-wrap {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 4px solid rgba(255,255,255,.4);
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,.22);
        }

        .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-icon { font-size: 90px; color: rgba(255,255,255,.75); line-height: 1; }

        .info-col {
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(255,255,255,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,.85);
            font-size: 13px;
            flex-shrink: 0;
        }

        .info-text { min-width: 0; }

        .info-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: rgba(255,255,255,.55);
            line-height: 1;
            margin-bottom: 3px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            word-break: break-word;
            line-height: 1.3;
        }

        .info-value.name-val {
            font-size: 20px;
            font-weight: 900;
        }

        .info-value.pw-val {
            letter-spacing: 3px;
            font-size: 13px;
            color: rgba(255,255,255,.65);
        }

        .btn-edit-profile {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            background: rgba(255,255,255,.18);
            color: #fff;
            border: 2px solid rgba(255,255,255,.32);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: background .18s, transform .18s;
            margin-top: 4px;
            font-family: inherit;
            align-self: flex-start;
        }

        .btn-edit-profile:hover {
            background: rgba(255,255,255,.28);
            transform: translateY(-1px);
        }

        .edit-panel {
            display: none;
            padding: 26px 32px;
            background: var(--soft);
            border-top: 1px solid rgba(112,13,9,.08);
        }

        .edit-panel.show { display: block; }

        .edit-panel-title {
            font-size: 15px;
            font-weight: 900;
            color: var(--maroon);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px 16px;
        }

        .form-group.full { grid-column: 1 / -1; }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: #5e6e84;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 7px;
        }

        .input-wrap { position: relative; }

        .form-input {
            width: 100%;
            border: 1.5px solid #dde0ea;
            border-radius: var(--radius-sm);
            padding: 11px 14px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            background: #fff;
            outline: none;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-input.has-eye { padding-right: 44px; }

        .form-input:focus {
            border-color: rgba(112,13,9,.4);
            box-shadow: 0 0 0 3px rgba(112,13,9,.07);
        }

        .eye-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: 0;
            color: #8898aa;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            padding: 0;
            transition: color .18s;
        }

        .eye-toggle:hover { color: var(--maroon); }

        .form-note {
            font-size: 12px;
            color: #8493a8;
            font-weight: 600;
            margin-top: 6px;
        }

        .pw-checklist {
            list-style: none;
            padding: 0;
            margin: 10px 0 0;
            font-size: 11px;
            font-weight: 700;
        }
        .pw-item {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
            color: #b42318;
        }
        .pw-item.valid {
            color: #0f7b3b;
        }

        .action-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        .btn-act {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 20px;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 800;
            border: 0;
            cursor: pointer;
            transition: transform .18s, filter .18s;
            font-family: inherit;
        }

        .btn-act:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn-act:hover:not(:disabled) { transform: translateY(-2px); }

        .btn-primary-act {
            background: var(--maroon);
            color: #fff;
            box-shadow: 0 6px 16px rgba(112,13,9,.2);
        }

        .btn-primary-act:hover {
            color: #fff;
            filter: brightness(.93);
        }

        .btn-outline-act {
            background: #fff;
            color: var(--maroon);
            border: 2px solid rgba(112,13,9,.2);
        }

        .flash-wrap { padding: 16px 32px 0; }

        .flash {
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .flash-error {
            background: #fdeceb;
            color: var(--red);
            border: 1px solid #f4c7c4;
        }

        .section-title {
            font-size: clamp(20px, 3vw, 28px);
            font-weight: 900;
            color: var(--text);
            margin: 32px 0 14px;
            letter-spacing: -.3px;
        }

        .table-card {
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid rgba(112,13,9,.07);
            overflow: hidden;
        }

        .table-scroll { overflow-x: auto; }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px;
        }

        .activity-table thead th {
            background: #f5f5f7;
            color: #2a3444;
            padding: 14px 20px;
            font-size: 12px;
            font-weight: 800;
            text-align: left;
            border-bottom: 1px solid #ececf1;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .activity-table tbody td {
            padding: 14px 20px;
            font-size: 14px;
            color: #1a2333;
            border-bottom: 1px solid #f2f2f5;
            vertical-align: middle;
        }

        .activity-table tbody tr:last-child td { border-bottom: none; }
        .activity-table tbody tr:hover td { background: #fafafa; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-pass { color: var(--green); background: #dff4e6; }
        .status-fail { color: var(--red); background: #fdeceb; }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: var(--maroon);
            color: #fff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 800;
            border-radius: var(--radius-sm);
            transition: filter .18s, transform .18s;
        }

        .btn-download:hover {
            color: #fff;
            filter: brightness(.92);
            transform: translateY(-1px);
        }

        .empty-row td {
            text-align: center;
            color: #8898aa;
            font-weight: 700;
            padding: 40px !important;
            font-size: 14px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.60);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: #fff;
            width: 100%;
            max-width: 380px;
            padding: 28px 24px 24px;
            border-radius: 24px;
            text-align: center;
            box-shadow: 0 20px 48px rgba(0,0,0,.22);
        }

        .modal-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .modal-icon.success {
            background: #e8f7ee;
            color: #118a4e;
        }

        .modal-title {
            font-weight: 800;
            font-size: 18px;
            margin-bottom: 8px;
            color: #122033;
            text-align: center;
        }

        .modal-message {
            font-size: 14px;
            color: #444;
            margin-bottom: 22px;
            line-height: 1.6;
            text-align: center;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
        }

        .btn-modal-confirm {
            flex: 1;
            border: none;
            border-radius: 999px;
            padding: 12px 0;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            background: #7A0C07;
            color: #fff;
            cursor: pointer;
            transition: .2s ease;
        }

        .btn-modal-confirm:hover {
            transform: translateY(-1px);
        }

       .popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 20px;
}

.popup-content-custom {
    background: #ffffff;
    width: 100%;
    max-width: 360px;
    padding: 26px;
    border-radius: 18px;
    text-align: center;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
}

.popup-title {
    font-weight: 800;
    font-size: 16px;
    color: #0B2447;
    margin-bottom: 8px;
}

.popup-message {
    font-size: 13px;
    color: #333;
    margin-bottom: 22px;
    line-height: 1.5;
}

.popup-actions {
    display: flex;
    gap: 10px;
}

.popup-actions button {
    flex: 1;
    border: 0;
    border-radius: 20px;
    padding: 10px 0;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}

.btn-popup-cancel {
    background: #e9e9e9;
    color: #111;
}

.btn-popup-action {
    background: #700D09;
    color: #ffffff;
}

.btn-popup-cancel:hover { background: #dfdfdf; }
.btn-popup-action:hover { opacity: 0.9; transform: translateY(-1px); }

        @media (max-width: 768px) {
            .page { padding: 100px 12px 48px; }
            .profile-card-header { padding: 24px 20px; }
            .profile-main-layout {
                flex-direction: column;
                align-items: center;
                gap: 20px;
                max-width: 100%;
            }
            .avatar-wrap { width: 120px; height: 120px; }
            .avatar-icon { font-size: 60px; }
            .info-col { align-items: flex-start; width: 100%; gap: 10px; }
            .info-value.name-val { font-size: 18px; }
            .edit-panel { padding: 20px 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .flash-wrap { padding: 14px 16px 0; }
            .action-row { flex-direction: column; }
            .btn-act { justify-content: center; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="page">
    <div class="profile-card">
        <div class="profile-card-header">
            <div class="profile-main-layout">
                <div class="avatar-col">
                    <div class="avatar-wrap">
                        <?php if ($hasFoto && !empty($user['foto_path'])): ?>
                            <img src="<?= h($user['foto_path']) ?>" alt="Foto Profil">
                        <?php else: ?>
                            <i class="bi bi-person-fill avatar-icon"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-col">
                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-person-fill"></i></div>
                        <div class="info-text">
                            <div class="info-label">Nama</div>
                            <div class="info-value name-val"><?= h($user['nama'] ?? '-') ?></div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-at"></i></div>
                        <div class="info-text">
                            <div class="info-label">Username</div>
                            <div class="info-value">@<?= h($user['username'] ?? '-') ?></div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-envelope-fill"></i></div>
                        <div class="info-text">
                            <div class="info-label">Email</div>
                            <div class="info-value">
                                <?= ($hasEmail && !empty($user['email'])) ? h($user['email']) : '-' ?>
                            </div>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-icon"><i class="bi bi-shield-lock-fill"></i></div>
                        <div class="info-text">
                            <div class="info-label">Password</div>
                            <div class="info-value pw-val">••••••••</div>
                        </div>
                    </div>

                    <button type="button" class="btn-edit-profile" id="toggleEditBtn">
                        <i class="bi bi-pencil-square"></i> Edit Profil
                    </button>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-wrap">
                <?php foreach ($errors as $err): ?>
                    <div class="flash flash-error">
                        <i class="bi bi-exclamation-circle-fill"></i> <?= h($err) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="edit-panel <?= !empty($errors) ? 'show' : '' ?>" id="editPanel">
            <div class="edit-panel-title">
                <i class="bi bi-pencil-square"></i> Edit Profil
            </div>

            <form method="post" enctype="multipart/form-data" autocomplete="off" id="formEditProfile">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-input" value="<?= h((string)($user['nama'] ?? '')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-input" value="<?= h((string)($user['username'] ?? '')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-input" value="<?= h((string)($user['email'] ?? '')) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password (Isi hanya jika ingin mengganti password)</label>
                        <div class="input-wrap">
                            <input type="password" name="password_baru" id="inputPassword" class="form-input has-eye" value="<?= h((string)($user['password'] ?? '')) ?>">
                            <button type="button" class="eye-toggle" id="btnEyeForm">
                                <i class="bi bi-eye" id="eyeIconForm"></i>
                            </button>
                        </div>
                        <ul class="pw-checklist" id="pwChecklist">
                            <li class="pw-item" data-rule="length"><i class="bi bi-circle"></i> Minimal 8 karakter</li>
                            <li class="pw-item" data-rule="upper"><i class="bi bi-circle"></i> Huruf besar</li>
                            <li class="pw-item" data-rule="lower"><i class="bi bi-circle"></i> Huruf kecil</li>
                            <li class="pw-item" data-rule="number"><i class="bi bi-circle"></i> Angka</li>
                            <li class="pw-item" data-rule="symbol"><i class="bi bi-circle"></i> Simbol (!@#$%^&*, dll)</li>
                        </ul>
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Foto Profil</label>
                        <input type="file" name="foto" class="form-input" accept=".jpg,.jpeg,.png,.webp">
                        <div class="form-note">Format: JPG, JPEG, PNG, WEBP — Maks. 2MB</div>
                    </div>
                </div>

                <div class="action-row">
                    <button type="submit" class="btn-act btn-primary-act" id="btnSubmitProfile">
                        <i class="bi bi-check2-circle"></i> Simpan Perubahan
                    </button>
                    <button type="button" class="btn-act btn-outline-act" id="cancelEditBtn">
                        <i class="bi bi-x-circle"></i> Tutup
                    </button>
                </div>
            </form>
        </div>
    </div>

    <h2 class="section-title">Riwayat Kuis</h2>
    <div class="table-card">
        <div class="table-scroll">
            <table class="activity-table">
                <thead>
                    <tr>
                        <th>Judul Kuis</th>
                        <th>Skor</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$riwayat): ?>
                    <tr class="empty-row">
                        <td colspan="5">
                            <i class="bi bi-inbox" style="font-size:26px;display:block;margin-bottom:8px;opacity:.35;"></i>
                            Belum ada aktivitas kuis
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($riwayat as $row):
                        $skor  = (float)($row['skor'] ?? 0);
                        $lulus = (int)($row['lulus'] ?? 0) === 1;
                    ?>
                    <tr>
                        <td><?= h((string)($row['judul_kuis'] ?? '-')) ?></td>
                        <td><strong><?= number_format($skor, 0) ?></strong></td>
                        <td>
                            <span class="status-badge <?= $lulus ? 'status-pass' : 'status-fail' ?>">
                                <i class="bi bi-<?= $lulus ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                                <?= $lulus ? 'Lulus' : 'Tidak Lulus' ?>
                            </span>
                        </td>
                        <td><?= h(format_datetime_id($row['created_at'] ?? null)) ?></td>
                        <td>
                            <?php if ($lulus): ?>
                                <a href="download_sertifikat.php?id=<?= (int)($row['id'] ?? 0) ?>" class="btn-download">
                                    <i class="bi bi-download"></i> Unduh
                                </a>
                            <?php else: ?>
                                <span style="color:#bbb;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<div class="popup-overlay" id="popupOverlay">
    <div class="popup-content-custom">
        <p class="popup-title">Konfirmasi Logout</p>
        <p class="popup-message">
            Apakah Anda yakin ingin logout dari akun ini?</p>
        <div class="popup-actions">
            <button type="button" class="btn-popup-cancel" id="btnCancelLogout">Batal</button>
            <button type="button" class="btn-popup-action" id="btnConfirmLogout">Logout</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="successModal" aria-hidden="true">
    <div class="modal-box">
        <div class="modal-icon success">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="modal-title">Berhasil</div>
        <div class="modal-message">Profil Anda berhasil diperbarui.</div>
        <div class="modal-actions">
            <button type="button" class="btn-modal-confirm" id="successOkBtn">Oke</button>
        </div>
    </div>
</div>

<script>
    const toggleEditBtn = document.getElementById('toggleEditBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const editPanel = document.getElementById('editPanel');
    const inputPassword = document.getElementById('inputPassword');
    const btnEyeForm = document.getElementById('btnEyeForm');
    const eyeIconForm = document.getElementById('eyeIconForm');
    const btnSubmitProfile = document.getElementById('btnSubmitProfile');
    const successModal = document.getElementById('successModal');
    const successOkBtn = document.getElementById('successOkBtn');
   const popupOverlay = document.getElementById('popupOverlay');
const btnCancelLogout = document.getElementById('btnCancelLogout');
const btnConfirmLogout = document.getElementById('btnConfirmLogout');

function openLogoutPopup() {
    popupOverlay.style.display = 'flex';
}

function closeLogoutPopup() {
    popupOverlay.style.display = 'none';
}

document.querySelectorAll('a[href*="logout"]').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        openLogoutPopup();
    });
});

btnCancelLogout.onclick = closeLogoutPopup;
btnConfirmLogout.onclick = () => { window.location.href = 'logout.php'; };

window.onclick = (e) => {
    if (e.target === popupOverlay) closeLogoutPopup();
};
    function validatePasswordRealtime() {
        const val = inputPassword.value;
        if (val === "") {
            document.querySelectorAll('.pw-item').forEach(item => {
                item.classList.remove('valid');
                item.querySelector('i').className = 'bi bi-circle';
            });
            btnSubmitProfile.disabled = false;
            return;
        }

        const rules = {
            length: val.length >= 8,
            upper: /[A-Z]/.test(val),
            lower: /[a-z]/.test(val),
            number: /[0-9]/.test(val),
            symbol: /[^A-Za-z0-9]/.test(val)
        };

        let allValid = true;
        for (const [rule, isValid] of Object.entries(rules)) {
            const el = document.querySelector(`.pw-item[data-rule="${rule}"]`);
            if (isValid) {
                el.classList.add('valid');
                el.querySelector('i').className = 'bi bi-check-circle-fill';
            } else {
                el.classList.remove('valid');
                el.querySelector('i').className = 'bi bi-circle';
                allValid = false;
            }
        }
        btnSubmitProfile.disabled = !allValid;
    }

    inputPassword?.addEventListener('input', validatePasswordRealtime);

    function closePopup() {
        if (!popupOverlay) return;
        popupOverlay.style.display = 'none';
        popupOverlay.setAttribute('aria-hidden', 'true');
        if (popupActions) popupActions.innerHTML = '';
        popupLocked = false;
    }

    function openPopup({ title = 'Konfirmasi Logout', message = 'Apakah Anda yakin ingin logout dari akun ini?', okText = 'Logout', cancelText = 'Batal', onOk = null, onCancel = null }) {
        if (!popupOverlay || !popupTitle || !popupMessage || !popupActions || popupLocked) return;
        popupLocked = true;
        popupTitle.textContent = title;
        popupMessage.textContent = message;
        popupActions.innerHTML = '';

        if (cancelText) {
            const btnCancel = document.createElement('button');
            btnCancel.type = 'button';
            btnCancel.className = 'btn-popup-cancel';
            btnCancel.textContent = cancelText;
            btnCancel.addEventListener('click', () => {
                closePopup();
                if (typeof onCancel === 'function') onCancel();
            });
            popupActions.appendChild(btnCancel);
        }

        const btnOk = document.createElement('button');
        btnOk.type = 'button';
        btnOk.className = 'btn-popup-action';
        btnOk.textContent = okText;
        btnOk.addEventListener('click', () => {
            closePopup();
            if (typeof onOk === 'function') onOk();
        });
        popupActions.appendChild(btnOk);

        popupOverlay.style.display = 'flex';
        popupOverlay.setAttribute('aria-hidden', 'false');
    }

    function bindLogoutConfirmation() {
        const logoutForms = Array.from(document.querySelectorAll('form')).filter((form) => {
            const actionInput = form.querySelector('input[name="action"][value="logout"]');
            const actionAttr = (form.getAttribute('action') || '').toLowerCase();
            return Boolean(actionInput) || actionAttr.includes('logout');
        });

        logoutForms.forEach((form) => {
            if (form.dataset.logoutBound === '1') return;
            form.dataset.logoutBound = '1';

            form.addEventListener('submit', (e) => {
                if (form.dataset.confirmed === '1') {
                    form.dataset.confirmed = '0';
                    return;
                }
                e.preventDefault();
                openPopup({
                    title: 'Konfirmasi Logout',
                    message: 'Apakah Anda yakin ingin logout dari akun ini?',
                    okText: 'Logout',
                    cancelText: 'Batal',
                    onOk: () => {
                        form.dataset.confirmed = '1';
                        form.requestSubmit ? form.requestSubmit() : form.submit();
                    }
                });
            });
        });

        const logoutLinks = document.querySelectorAll('a[href*="logout"], [data-logout-href]');
        logoutLinks.forEach((link) => {
            if (link.dataset.logoutBound === '1') return;
            link.dataset.logoutBound = '1';

            link.addEventListener('click', (e) => {
                const href = link.getAttribute('data-logout-href') || link.getAttribute('href');
                if (!href) return;
                e.preventDefault();
                openPopup({
                    onOk: () => { window.location.href = href; }
                });
            });
        });
    }

    function openSuccessModal() {
        if (!successModal) return;
        successModal.classList.add('show');
        successModal.setAttribute('aria-hidden', 'false');
    }

    function closeSuccessModal() {
        if (!successModal) return;
        successModal.classList.remove('show');
        successModal.setAttribute('aria-hidden', 'true');
    }

    toggleEditBtn?.addEventListener('click', () => {
        editPanel?.classList.toggle('show');
    });

    cancelEditBtn?.addEventListener('click', () => {
        editPanel?.classList.remove('show');
    });

    btnEyeForm?.addEventListener('click', () => {
        if (!inputPassword || !eyeIconForm) return;
        const isText = inputPassword.type === 'text';
        inputPassword.type = isText ? 'password' : 'text';
        eyeIconForm.className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    successOkBtn?.addEventListener('click', closeSuccessModal);

    successModal?.addEventListener('click', (e) => {
        if (e.target === successModal) closeSuccessModal();
    });
    popupOverlay?.addEventListener('click', (e) => {
        if (e.target !== popupOverlay) return;
        const hasCancel = popupActions?.querySelector('.btn-popup-cancel');
        if (!hasCancel) closePopup();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        if (successModal?.classList.contains('show')) closeSuccessModal();
        if (popupOverlay?.style.display === 'flex') closePopup();
    });

    bindLogoutConfirmation();

    <?php if ($success): ?>
    window.addEventListener('DOMContentLoaded', () => {
        editPanel?.classList.remove('show');
        openSuccessModal();
    });
    <?php endif; ?>
</script>

</body>
</html>
