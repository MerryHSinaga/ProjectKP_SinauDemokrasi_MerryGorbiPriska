<?php

declare(strict_types=1);
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['timeout'], $_SESSION['last_activity']) || (time() - (int)$_SESSION['last_activity'] > (int)$_SESSION['timeout'])) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

$_SESSION['last_activity'] = time();

require_once 'db.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function str_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function build_user_list_url(string $searchQuery = ''): string
{
    $params = [];
    if ($searchQuery !== '') $params['search'] = $searchQuery;
    return 'edit_profile_admin.php' . ($params ? '?' . http_build_query($params) : '');
}

function build_user_detail_url(int $userId, string $searchQuery = ''): string
{
    $params = ['user_id' => $userId];
    if ($searchQuery !== '') $params['search'] = $searchQuery;
    return 'edit_profile_admin.php?' . http_build_query($params);
}

function do_logout_and_redirect(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

function format_datetime_id(?string $datetime): string
{
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    return $timestamp === false ? (string)$datetime : date('d M Y, H:i', $timestamp);
}


function ensure_tables(): void
{
    $db = db();
    

    $db->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            UNIQUE KEY uq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $db->exec(
        "CREATE TABLE IF NOT EXISTS user_aktivitas_kuis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            kuis_id INT DEFAULT NULL,
            judul_kuis VARCHAR(255) NOT NULL,
            skor DECIMAL(5,2) NOT NULL DEFAULT 0,
            lulus TINYINT(1) NOT NULL DEFAULT 0,
            sertifikat_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_aktivitas_user (user_id),
            CONSTRAINT fk_aktivitas_user_profile FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $st = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
    $existingCols = $st->fetchAll(PDO::FETCH_COLUMN);
    
   
    if (!in_array('email', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN email VARCHAR(120) DEFAULT NULL AFTER username");
        $existingCols[] = 'email';
    }

    if (!in_array('password', $existingCols)) {
        $after = in_array('email', $existingCols) ? 'email' : 'username';
        $db->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER {$after}");
        $existingCols[] = 'password';
    }

    if (!in_array('role', $existingCols)) {
        $after = in_array('password', $existingCols) ? 'password' : (in_array('password_hash', $existingCols) ? 'password_hash' : 'email');
        $db->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER {$after}");
    }

    if (!in_array('nama', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN nama VARCHAR(120) NOT NULL DEFAULT '' AFTER role");
    }

    if (!in_array('foto_path', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN foto_path VARCHAR(255) DEFAULT NULL AFTER nama");
    }

    if (!in_array('failed_login_count', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0 AFTER foto_path");
    }

    if (!in_array('locked_until', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL AFTER failed_login_count");
    }

    if (!in_array('last_login_at', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER locked_until");
    }

    if (!in_array('created_at', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    if (!in_array('updated_at', $existingCols)) {
        $db->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }
}

if (!isset($_SESSION['db_setup_done'])) {
    ensure_tables();
    $_SESSION['db_setup_done'] = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'logout') {
    do_logout_and_redirect();
}

$hasPassword = true; 

$selectedUserId = (int)($_GET['user_id'] ?? 0);
$searchQuery = trim((string)($_GET['search'] ?? ''));

$userSql = 'SELECT id, username, nama, email, foto_path FROM users';
$userParams = [];

if ($searchQuery !== '') {
    $userSql .= ' WHERE nama LIKE ? OR username LIKE ? OR email LIKE ?';
    $searchLike = '%' . $searchQuery . '%';
    $userParams = [$searchLike, $searchLike, $searchLike];
}

$userSql .= ' ORDER BY created_at DESC, id DESC';

$userStmt = db()->prepare($userSql);
$userStmt->execute($userParams);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);


$selectedUser = null;
$activities = [];

if ($selectedUserId > 0) {
    $selectFields = ['id', 'username', 'email', 'role', 'nama', 'foto_path', 'last_login_at', 'created_at', 'updated_at', 'password'];

    $st = db()->prepare('SELECT ' . implode(', ', $selectFields) . ' FROM users WHERE id = ? LIMIT 1');
    $st->execute([$selectedUserId]);
    $selectedUser = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($selectedUser) {
        $st = db()->prepare(
            'SELECT id, judul_kuis, skor, lulus, sertifikat_path, created_at
             FROM user_aktivitas_kuis
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC'
        );
        $st->execute([$selectedUserId]);
        $activities = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}

$site_title = 'Admin | Profil User';

if(file_exists('identitas.php')) include 'identitas.php';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

:root{
  --maroon:#700D09;
  --bg:#E9EDFF;
  --gold:#f4c430;
  --text:#122033;
  --soft:#f6f7fb;
  --green:#0f7b3b;
  --red:#b42318;
  --navbar-h:90px;
  --shadow:0 14px 32px rgba(0,0,0,.10);
  --radius-lg:28px;
  --radius-md:18px;
  --radius-sm:14px;
}

*{box-sizing:border-box}

body{
  margin:0;
  font-family:'Inter',sans-serif;
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
}

.navbar-kpu{
  position:fixed;
  top:0;
  left:0;
  right:0;
  height:var(--navbar-h);
  background:var(--maroon);
  border-bottom:1px solid #000;
  z-index:1000;
}

.navbar-kpu .inner{
  max-width:1330px;
  height:100%;
  margin:auto;
  padding:0 16px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:14px;
  position:relative;
}

.btn-back{
  position:absolute;
  left:-40px;
  top:50%;
  transform:translateY(-50%);
  width:42px;
  height:42px;
  border-radius:12px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  color:#fff;
  text-decoration:none;
  background:transparent;
}

.btn-back:hover{
  filter:brightness(1.05);
  transform:translateY(-50%) translateY(-1px);
  background:rgba(255,255,255,.12);
}

.btn-back i{font-size:22px;line-height:1}

.brand{display:flex;align-items:center;gap:8px;text-decoration:none}
.brand img{height:36px}
.brand-text{color:#fff;line-height:1.05}

.btn-logout{
  border:0;
  background:transparent;
  color:#fff;
  font-weight:600;
  font-size:.85rem;
  letter-spacing:.5px;
  position:relative;
}

.btn-logout::after{
  content:"";
  position:absolute;
  left:0;
  bottom:-6px;
  width:0;
  height:3px;
  background:var(--gold);
  transition:.3s;
}

.btn-logout:hover::after{width:100%}

.page{
  max-width:1240px;
  margin:0 auto;
  padding:calc(var(--navbar-h) + 32px) 20px 48px;
}

.title{
  font-weight:900;
  font-size:44px;
  margin:0;
  color:#111;
}

.subtitle{
  margin-top:8px;
  color:#444;
  font-size:14px;
  font-style:italic;
}

.layout{
  display:grid;
  grid-template-columns:360px 1fr;
  gap:24px;
  margin-top:28px;
  align-items:start;
}

.panel{
  background:#fff;
  border-radius:var(--radius-lg);
  box-shadow:var(--shadow);
  overflow:hidden;
  border:1px solid rgba(112,13,9,.07);
}

.panel-head{
  padding:20px 24px;
  background:#f0f0f0;
  font-weight:900;
  font-size:20px;
  color:#111;
}

.search-box{
  padding:16px 16px 0;
}

.search-form{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
}

.search-input-wrap{
  position:relative;
  flex:1 1 220px;
}

.search-input-icon{
  position:absolute;
  left:14px;
  top:50%;
  transform:translateY(-50%);
  color:#7d8795;
  font-size:15px;
  pointer-events:none;
}

.search-input{
  width:100%;
  height:46px;
  border-radius:14px;
  border:1px solid #d8deea;
  background:#fff;
  padding:0 16px 0 42px;
  font-size:14px;
  font-weight:600;
  color:#1a2333;
  outline:none;
  transition:.2s ease;
}

.search-input::placeholder{
  color:#8a94a6;
  font-weight:500;
}

.search-input:focus{
  border-color:rgba(112,13,9,.45);
  box-shadow:0 0 0 4px rgba(112,13,9,.08);
}

.btn-search,
.btn-search-reset{
  height:46px;
  border:0;
  border-radius:14px;
  padding:0 16px;
  font-size:14px;
  font-weight:800;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:8px;
  text-decoration:none;
  transition:.2s ease;
}

.btn-search{
  background:var(--maroon);
  color:#fff;
}

.btn-search:hover{
  color:#fff;
  filter:brightness(1.05);
}

.search-helper{
  margin-top:10px;
  font-size:12px;
  color:#677487;
  font-weight:600;
}

.user-list{
  max-height:720px;
  overflow:auto;
  padding:12px;
}

.user-item{
  display:flex;
  align-items:center;
  gap:14px;
  text-decoration:none;
  color:#111;
  padding:14px;
  border-radius:18px;
  transition:.2s ease;
  margin-bottom:10px;
}

.user-item:hover,
.user-item.active{
  background:rgba(112,13,9,.08);
}

.avatar{
  width:58px;
  height:58px;
  border-radius:50%;
  overflow:hidden;
  background:#d9d9d9;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#8a8a8a;
  font-size:26px;
  flex-shrink:0;
}

.avatar img{
  width:100%;
  height:100%;
  object-fit:cover;
}

.user-info{min-width:0}
.user-name{
  font-weight:900;
  font-size:16px;
  color:#111;
  line-height:1.25;
}

.user-email{
  font-size:13px;
  color:#666;
  margin-top:4px;
  word-break:break-word;
}

.detail-wrap{padding:24px}

.profile-card{
  background:#fff;
  border-radius:var(--radius-lg);
  box-shadow:var(--shadow);
  border:1px solid rgba(112,13,9,.07);
  overflow:hidden;
}

.profile-card-header{
  background:linear-gradient(120deg, var(--maroon) 0%, #9d120d 100%);
  padding:32px 36px;
}

.profile-main-layout{
  display:flex;
  align-items:center;
  gap:36px;
}

.avatar-col{flex-shrink:0}

.avatar-wrap{
  width:180px;
  height:180px;
  border-radius:50%;
  background:rgba(255,255,255,.15);
  display:flex;
  align-items:center;
  justify-content:center;
  border:4px solid rgba(255,255,255,.4);
  overflow:hidden;
  box-shadow:0 8px 28px rgba(0,0,0,.22);
}

.avatar-wrap img{
  width:100%;
  height:100%;
  object-fit:cover;
}

.avatar-icon{
  font-size:90px;
  color:rgba(255,255,255,.75);
  line-height:1;
}

.info-col{
  display:flex;
  flex-direction:column;
  gap:14px;
  flex:1;
  min-width:0;
}

.info-row{
  min-width:0;
}

.info-label{
  font-size:10px;
  font-weight:800;
  text-transform:uppercase;
  letter-spacing:.5px;
  color:rgba(255,255,255,.55);
  line-height:1;
  margin-bottom:5px;
}

.info-value{
  font-size:14px;
  font-weight:700;
  color:#fff;
  word-break:break-word;
  line-height:1.35;
}

.info-value.name-val{
  font-size:22px;
  font-weight:900;
}

.info-value.password-value{
  letter-spacing:.3px;
}

.profile-meta{
  padding:24px 28px 28px;
  background:var(--soft);
  border-top:1px solid rgba(112,13,9,.08);
}

.meta-grid{
  display:grid;
  grid-template-columns:repeat(2, minmax(220px, 1fr));
  gap:14px 16px;
}

.meta-box{
  background:#fff;
  border-radius:var(--radius-md);
  padding:15px 16px;
  border:1px solid #ececf1;
}

.meta-label{
  display:block;
  font-size:11px;
  font-weight:800;
  color:#5e6e84;
  text-transform:uppercase;
  letter-spacing:.4px;
  margin-bottom:7px;
}

.meta-value{
  font-size:14px;
  font-weight:800;
  color:#1a2333;
  word-break:break-word;
}

.section-title{
  font-size:clamp(20px, 3vw, 28px);
  font-weight:900;
  color:var(--text);
  margin:32px 0 14px;
  letter-spacing:-.3px;
}

.table-card{
  background:#fff;
  border-radius:var(--radius-lg);
  box-shadow:var(--shadow);
  border:1px solid rgba(112,13,9,.07);
  overflow:hidden;
}

.table-scroll{overflow-x:auto}

.activity-table{
  width:100%;
  border-collapse:collapse;
  min-width:640px;
}

.activity-table thead th{
  background:#f5f5f7;
  color:#2a3444;
  padding:14px 20px;
  font-size:12px;
  font-weight:800;
  text-align:left;
  border-bottom:1px solid #ececf1;
  white-space:nowrap;
  text-transform:uppercase;
  letter-spacing:.4px;
}

.activity-table tbody td{
  padding:14px 20px;
  font-size:14px;
  color:#1a2333;
  border-bottom:1px solid #f2f2f5;
  vertical-align:middle;
}

.activity-table tbody tr:last-child td{border-bottom:none}
.activity-table tbody tr:hover td{background:#fafafa}

.status-badge{
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:5px 12px;
  border-radius:999px;
  font-size:12px;
  font-weight:800;
  white-space:nowrap;
}

.status-pass{color:var(--green);background:#dff4e6}
.status-fail{color:var(--red);background:#fdeceb}

.btn-download{
  display:inline-flex;
  align-items:center;
  gap:6px;
  padding:7px 14px;
  background:var(--maroon);
  color:#fff;
  text-decoration:none;
  font-size:12px;
  font-weight:800;
  border-radius:12px;
}

.btn-download:hover{
  color:#fff;
  filter:brightness(1.05);
}

.empty{
  padding:26px;
  color:#666;
}

.mobile-user-toggle{display:none}

.btn-mobile-list{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:10px 14px;
  border-radius:12px;
  background:var(--maroon);
  color:#fff;
  text-decoration:none;
  font-weight:700;
  margin-bottom:14px;
}

.btn-mobile-list:hover{
  color:#fff;
  filter:brightness(1.05);
}

.modal-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.6);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:9999;
}

.modal-content-custom{
  background:#fff;
  padding:26px;
  border-radius:18px;
  width:360px;
  text-align:center;
  box-shadow:0 18px 34px rgba(0,0,0,.25);
}

.btn-modal-action{
  border:0;
  border-radius:20px;
  padding:6px 22px;
  font-weight:600;
  background:var(--maroon);
  color:#fff;
  min-width:120px;
}

.btn-modal-cancel{
  border:0;
  border-radius:20px;
  padding:6px 22px;
  font-weight:600;
  background:#e9e9e9;
  color:#111;
  min-width:120px;
}

.popup-title{
  font-weight:900;
  font-size:16px;
  margin:0 0 8px 0;
  color:#111;
}

.popup-message{
  font-size:13px;
  color:#333;
  margin:0 0 18px 0;
  line-height:1.45;
  white-space:pre-wrap;
}

.popup-actions{
  display:flex;
  gap:10px;
  justify-content:center;
  flex-wrap:wrap;
  margin-top:6px;
}

@media (max-width:1200px){
  .btn-back{left:0}
}

@media (max-width:992px){
  .layout{grid-template-columns:1fr}
  .meta-grid{grid-template-columns:1fr}
}

@media (max-width:768px){
  .page{
    padding:calc(var(--navbar-h) + 28px) 14px 28px;
  }

  .title{
    font-size:32px;
    line-height:1.1;
  }

  .subtitle{font-size:13px}

  .layout{
    margin-top:18px;
    gap:16px;
  }

  .layout.has-selected .panel-users{display:none}
  .layout.has-selected .panel-detail{display:block}

  .mobile-user-toggle{display:block}

  .panel-head{
    padding:16px 18px;
    font-size:18px;
  }

  .search-box{
    padding:14px 14px 0;
  }

  .search-form{
    flex-direction:column;
    align-items:stretch;
  }

  .detail-wrap{padding:16px}

  .profile-card-header{padding:24px 20px}

  .profile-main-layout{
    flex-direction:column;
    align-items:center;
    text-align:center;
    gap:20px;
  }

  .avatar-wrap{
    width:130px;
    height:130px;
  }

  .avatar-icon{font-size:62px}

  .info-col{width:100%}

  .user-list{
    max-height:none;
    overflow:visible;
    padding:10px;
  }

  .user-item{
    padding:12px;
    border-radius:16px;
  }

  .avatar{
    width:52px;
    height:52px;
    font-size:22px;
  }
}

@media (max-width:576px){
  .navbar-kpu .inner{padding:0 12px}

  .btn-back{
    width:42px;
    height:42px;
    border-radius:12px;
  }

  .btn-back i{
    font-size:22px;
    line-height:1;
  }

  .brand img{height:32px}

  .brand-text strong,
  .brand-text span{font-size:14px}

  .profile-meta{padding:18px 16px 20px}
}
</style>
</head>
<body>

<nav class="navbar-kpu">
  <div class="inner">
    <a href="admin.php" class="btn-back" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>

    <a href="admin.php" class="brand">
      <img src="assets/LogoKPU.png" alt="KPU">
      <div class="brand-text">
        <strong>KPU</strong><br>
        <span>DIY</span>
      </div>
    </a>

    <form method="post" id="logoutFormDesktop" class="m-0">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="btn-logout" id="btnLogoutDesktop">LOGOUT</button>
    </form>
  </div>
</nav>

<main class="page">
  <h1 class="title">Profil User</h1>
  <div class="subtitle">Klik salah satu user untuk melihat detail akun dan aktivitas kuisnya.</div>

  <div class="layout <?= $selectedUser ? 'has-selected' : '' ?>">
    <section class="panel panel-users">
      <div class="panel-head">Daftar User</div>

      <div class="search-box">
        <form method="get" class="search-form" id="searchUserForm" autocomplete="off">
          <?php if ($selectedUserId > 0): ?>
            <input type="hidden" name="user_id" value="<?= $selectedUserId ?>">
          <?php endif; ?>

          <div class="search-input-wrap">
           
            <input
              type="text"
              name="search"
              id="userSearchInput"
              class="search-input"
              placeholder="Cari akun"
              value="<?= h($searchQuery) ?>"
            >
          </div>

        </form>

        <div class="search-helper">
          Cari user berdasarkan nama, username, atau email.
        </div>
      </div>

      <div class="user-list" id="userList">
        <?php if (!$users): ?>
          <div class="empty" id="serverEmptyState">
            <?= $searchQuery !== '' ? 'User tidak ditemukan untuk kata kunci tersebut.' : 'Belum ada user.' ?>
          </div>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <?php
              $userSearchText = str_lower(
                  trim(
                      (string)($u['nama'] ?? '') . ' ' .
                      (string)($u['username'] ?? '') . ' ' .
                      (string)($u['email'] ?? '')
                  )
              );
            ?>
            <a
              href="<?= h(build_user_detail_url((int)$u['id'], $searchQuery)) ?>"
              class="user-item <?= $selectedUserId === (int)$u['id'] ? 'active' : '' ?>"
              data-search="<?= h($userSearchText) ?>"
            >
              <div class="avatar">
                <?php if (!empty($u['foto_path'])): ?>
                  <img src="<?= h((string)$u['foto_path']) ?>" alt="Foto">
                <?php else: ?>
                  <i class="bi bi-person-fill"></i>
                <?php endif; ?>
              </div>
              <div class="user-info">
                <div class="user-name"><?= h((string)$u['nama']) ?></div>
                <div class="user-email"><?= h((string)($u['email'] ?: '-')) ?></div>
              </div>
            </a>
          <?php endforeach; ?>

          <div class="empty" id="clientEmptyState" style="display:none;">User tidak ditemukan.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="panel panel-detail">
      <div class="panel-head">Detail Akun</div>
      <div class="detail-wrap">
        <?php if (!$selectedUser): ?>
          <div class="empty">Pilih salah satu user untuk melihat detail akun.</div>
        <?php else: ?>
          <div class="mobile-user-toggle">
            <a href="<?= h(build_user_list_url($searchQuery)) ?>" class="btn-mobile-list">
              <i class="bi bi-list-ul"></i> Lihat Daftar User
            </a>
          </div>

          <div class="profile-card">
            <div class="profile-card-header">
              <div class="profile-main-layout">
                <div class="avatar-col">
                  <div class="avatar-wrap">
                    <?php if (!empty($selectedUser['foto_path'])): ?>
                      <img src="<?= h((string)$selectedUser['foto_path']) ?>" alt="Foto Profil">
                    <?php else: ?>
                      <i class="bi bi-person-fill avatar-icon"></i>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="info-col">
                  <div class="info-row">
                    <div class="info-label">Nama</div>
                    <div class="info-value name-val"><?= h((string)$selectedUser['nama']) ?></div>
                  </div>

                  <div class="info-row">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?= h((string)$selectedUser['username']) ?></div>
                  </div>

                  <div class="info-row">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?= h((string)($selectedUser['email'] ?: '-')) ?></div>
                  </div>

                  <div class="info-row">
                    <div class="info-label">Password</div>
                    <div class="info-value password-value"><?= h((string)($hasPassword ? ($selectedUser['password'] ?? '-') : '-')) ?></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="profile-meta">
              <div class="meta-grid">
                <div class="meta-box">
                  <span class="meta-label">Login Terakhir</span>
                  <div class="meta-value"><?= h(format_datetime_id((string)($selectedUser['last_login_at'] ?? ''))) ?></div>
                </div>
                <div class="meta-box">
                  <span class="meta-label">Dibuat Tanggal</span>
                  <div class="meta-value"><?= h(format_datetime_id((string)($selectedUser['created_at'] ?? ''))) ?></div>
                </div>
                <div class="meta-box">
                  <span class="meta-label">Terakhir Diubah</span>
                  <div class="meta-value"><?= h(format_datetime_id((string)($selectedUser['updated_at'] ?? ''))) ?></div>
                </div>
              </div>
            </div>
          </div>

          <h2 class="section-title">Aktivitas Kuis</h2>

          <div class="table-card">
            <?php if (!$activities): ?>
              <div class="empty">Belum ada aktivitas kuis untuk user ini.</div>
            <?php else: ?>
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
                    <?php foreach ($activities as $activity): ?>
                      <tr>
                        <td><?= h((string)$activity['judul_kuis']) ?></td>
                        <td><?= h((string)$activity['skor']) ?></td>
                        <td>
                          <?php if ((int)$activity['lulus'] === 1): ?>
                            <span class="status-badge status-pass"><i class="bi bi-check-circle-fill"></i> Lulus</span>
                          <?php else: ?>
                            <span class="status-badge status-fail"><i class="bi bi-x-circle-fill"></i> Belum</span>
                          <?php endif; ?>
                        </td>
                        <td><?= h(format_datetime_id((string)($activity['created_at'] ?? ''))) ?></td>
                        <td>
                          <?php if (!empty($activity['sertifikat_path'])): ?>
                            <a class="btn-download" href="<?= h((string)$activity['sertifikat_path']) ?>" target="_blank">
                              <i class="bi bi-download"></i> Download
                            </a>
                          <?php else: ?>
                            <span style="color:#777;font-weight:700;">-</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>

<div class="modal-overlay" id="popupOverlay" aria-hidden="true">
  <div class="modal-content-custom" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
    <p class="popup-title" id="popupTitle">Konfirmasi</p>
    <p class="popup-message" id="popupMessage">Pesan</p>
    <div class="popup-actions" id="popupActions"></div>
  </div>
</div>

<script>
// JS dibiarkan sama persis
(function(){
  const popupOverlay = document.getElementById('popupOverlay');
  const popupTitle   = document.getElementById('popupTitle');
  const popupMessage = document.getElementById('popupMessage');
  const popupActions = document.getElementById('popupActions');

  const logoutFormDesktop = document.getElementById('logoutFormDesktop');
  const btnLogoutDesktop  = document.getElementById('btnLogoutDesktop');

  let popupLocked = false;

  function closePopup(){
    popupOverlay.style.display = 'none';
    popupOverlay.setAttribute('aria-hidden','true');
    popupActions.innerHTML = '';
    popupLocked = false;
  }

  function openPopup({ title='Konfirmasi', message='', okText='OK', cancelText='', onOk=null, onCancel=null }){
    if (popupLocked) return;
    popupLocked = true;

    popupTitle.textContent = title;
    popupMessage.textContent = message;
    popupActions.innerHTML = '';

    if (cancelText) {
      const btnCancel = document.createElement('button');
      btnCancel.type = 'button';
      btnCancel.className = 'btn-modal-cancel';
      btnCancel.textContent = cancelText;
      btnCancel.addEventListener('click', () => {
        closePopup();
        if (typeof onCancel === 'function') onCancel();
      });
      popupActions.appendChild(btnCancel);
    }

    const btnOk = document.createElement('button');
    btnOk.type = 'button';
    btnOk.className = 'btn-modal-action';
    btnOk.textContent = okText;
    btnOk.addEventListener('click', () => {
      closePopup();
      if (typeof onOk === 'function') onOk();
    });
    popupActions.appendChild(btnOk);

    popupOverlay.style.display = 'flex';
    popupOverlay.setAttribute('aria-hidden','false');
  }

  if (logoutFormDesktop && btnLogoutDesktop) {
    btnLogoutDesktop.addEventListener('click', (e) => {
      e.preventDefault();
      openPopup({
        title: 'Konfirmasi',
        message: 'Yakin ingin logout?',
        okText: 'Logout',
        cancelText: 'Batal',
        onOk: () => logoutFormDesktop.submit()
      });
    });
  }

  popupOverlay.addEventListener('click', (e) => {
    if (e.target !== popupOverlay) return;
    const hasCancel = popupActions.querySelector('.btn-modal-cancel');
    if (!hasCancel) closePopup();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (popupOverlay.style.display !== 'flex') return;
    const hasCancel = popupActions.querySelector('.btn-modal-cancel');
    if (!hasCancel) closePopup();
  });

  const userSearchInput = document.getElementById('userSearchInput');
  const userList = document.getElementById('userList');
  const userItems = userList ? Array.from(userList.querySelectorAll('.user-item')) : [];
  const clientEmptyState = document.getElementById('clientEmptyState');
  const serverEmptyState = document.getElementById('serverEmptyState');

  function normalizeText(value) {
    return (value || '').toString().toLowerCase().trim();
  }

  function filterUserList() {
    if (!userSearchInput || !userItems.length) return;

    const keyword = normalizeText(userSearchInput.value);
    let visibleCount = 0;

    userItems.forEach((item) => {
      const haystack = normalizeText(item.dataset.search || item.textContent);
      const isMatch = keyword === '' || haystack.includes(keyword);

      item.style.display = isMatch ? '' : 'none';
      if (isMatch) visibleCount += 1;
    });

    if (clientEmptyState) {
      clientEmptyState.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    if (serverEmptyState && keyword === '') {
      serverEmptyState.style.display = '';
    }
  }

  if (userSearchInput) {
    userSearchInput.addEventListener('input', filterUserList);
    filterUserList();
  }
})();
</script>

</body>
</html>