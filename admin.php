<?php
declare(strict_types=1);
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
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

require_once 'db.php';

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function do_logout_and_redirect(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $p["path"],
            $p["domain"],
            (bool)$p["secure"],
            (bool)$p["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST["action"] ?? "") === "logout") {
    do_logout_and_redirect();
}

function col_exists(string $table, string $column): bool {
    $st = db()->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function idx_exists(string $table, string $index): bool {
    $st = db()->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $st->execute([$table, $index]);
    return (int)$st->fetchColumn() > 0;
}

function ensure_admin_tables(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(120) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            nama VARCHAR(120) NOT NULL,
            foto_path VARCHAR(255) DEFAULT NULL,
            failed_login_count INT NOT NULL DEFAULT 0,
            locked_until DATETIME DEFAULT NULL,
            last_login_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_users_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!col_exists('users', 'email')) {
        db()->exec("ALTER TABLE users ADD COLUMN email VARCHAR(120) DEFAULT NULL AFTER username");
    }
    if (!col_exists('users', 'role')) {
        db()->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password_hash");
    }
    if (!col_exists('users', 'nama')) {
        db()->exec("ALTER TABLE users ADD COLUMN nama VARCHAR(120) NOT NULL DEFAULT '' AFTER role");
    }
    if (!col_exists('users', 'foto_path')) {
        db()->exec("ALTER TABLE users ADD COLUMN foto_path VARCHAR(255) DEFAULT NULL AFTER nama");
    }
    if (!col_exists('users', 'failed_login_count')) {
        db()->exec("ALTER TABLE users ADD COLUMN failed_login_count INT NOT NULL DEFAULT 0 AFTER foto_path");
    }
    if (!col_exists('users', 'locked_until')) {
        db()->exec("ALTER TABLE users ADD COLUMN locked_until DATETIME DEFAULT NULL AFTER failed_login_count");
    }
    if (!col_exists('users', 'last_login_at')) {
        db()->exec("ALTER TABLE users ADD COLUMN last_login_at DATETIME DEFAULT NULL AFTER locked_until");
    }
    if (!col_exists('users', 'updated_at')) {
        db()->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS kuis_paket (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(255) NOT NULL,
            input_mode ENUM('csv','manual') NOT NULL DEFAULT 'csv',
            bagian ENUM(
                'Keuangan',
                'Umum dan Logistik',
                'Teknis Penyelenggara Pemilu',
                'Partisipasi Hubungan Masyarakat',
                'Hukum dan SDM',
                'Perencanaan',
                'Data dan Informasi'
            ) DEFAULT NULL,
            thumbnail VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!col_exists('kuis_paket', 'thumbnail')) {
        db()->exec("ALTER TABLE kuis_paket ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER bagian");
    }
    if (!col_exists('kuis_paket', 'updated_at')) {
        db()->exec("ALTER TABLE kuis_paket ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }
    if (!idx_exists('kuis_paket', 'uq_kuis_paket_judul')) {
        try {
            db()->exec("ALTER TABLE kuis_paket ADD UNIQUE KEY uq_kuis_paket_judul (judul)");
        } catch (Throwable $e) {}
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS materi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(255) NOT NULL,
            bagian ENUM(
                'Keuangan',
                'Umum dan Logistik',
                'Teknis Penyelenggara Pemilu',
                'Partisipasi Hubungan Masyarakat',
                'Hukum dan SDM',
                'Perencanaan',
                'Data dan Informasi'
            ) NOT NULL DEFAULT 'Umum dan Logistik',
            tipe ENUM('pdf','video','link') NOT NULL DEFAULT 'pdf',
            jumlah_slide INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            thumbnail VARCHAR(255) DEFAULT NULL,
            content_url TEXT DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_materi_judul (judul)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!col_exists('materi', 'thumbnail')) {
        if (col_exists('materi', 'thumbnail_path')) {
            try {
                db()->exec("ALTER TABLE materi CHANGE COLUMN thumbnail_path thumbnail VARCHAR(255) DEFAULT NULL");
            } catch (Throwable $e) {}
        } else {
            db()->exec("ALTER TABLE materi ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER created_at");
        }
    }

    if (!col_exists('materi', 'content_url')) {
        db()->exec("ALTER TABLE materi ADD COLUMN content_url TEXT DEFAULT NULL AFTER thumbnail");
    }

    if (!col_exists('materi', 'updated_at')) {
        db()->exec("ALTER TABLE materi ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER content_url");
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS user_aktivitas_kuis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            kuis_id INT DEFAULT NULL,
            judul_kuis VARCHAR(255) NOT NULL,
            skor DECIMAL(5,2) NOT NULL DEFAULT 0,
            lulus TINYINT(1) NOT NULL DEFAULT 0,
            sertifikat_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_aktivitas_user (user_id),
            CONSTRAINT fk_aktivitas_user FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

ensure_admin_tables();

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST["action"] ?? "") === "logout") {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $p["path"],
            $p["domain"],
            (bool)$p["secure"],
            (bool)$p["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit;
}

$totalMateri = (int)(db()->query("SELECT COUNT(*) FROM materi")->fetchColumn() ?: 0);
$totalKuis   = (int)(db()->query("SELECT COUNT(*) FROM kuis_paket")->fetchColumn() ?: 0);
$totalUser   = (int)(db()->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn() ?: 0);

$adminDbCount = (int)(db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() ?: 0);
$totalAdmin   = $adminDbCount + 1;

$site_title = 'SI-NAU Demokrasi | Admin';
include 'identitas.php';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root{
  --maroon:#700D09;
  --gold:#f4c430;
  --bg:#E9EDFF;
  --navbar-h:90px;
}
body{
  margin:0;
  font-family:'Inter',sans-serif;
  background:var(--bg);
  min-height:100vh;
}
.navbar-kpu{
  position:fixed; inset:0 0 auto 0;
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
}
.brand{
  display:flex;
  align-items:center;
  gap:8px;
  text-decoration:none;
}
.brand img{height:36px}
.brand-text{color:#fff;line-height:1.1}
.brand-text strong{font-size:.95rem;font-weight:700}
.brand-text span{font-size:.85rem}
.btn-logout{
  border:0;background:transparent;color:#fff;font-weight:600;font-size:.85rem;
  letter-spacing:.5px;position:relative;
}
.btn-logout::after{
  content:"";position:absolute;left:0;bottom:-6px;width:0;height:3px;background:var(--gold);transition:.3s;
}
.btn-logout:hover::after{width:100%}
.page{
  max-width:1180px;
  margin:0 auto;
  padding:calc(var(--navbar-h) + 42px) 20px 48px;
}
.heading{
  display:flex;justify-content:space-between;align-items:flex-start;gap:18px;flex-wrap:wrap;
}
.title{
  margin:0;
  font-size:48px;
  font-weight:900;
  color:#111;
}
.subtitle{
  margin-top:8px;
  color:#333;
  font-style:italic;
  font-size:14px;
}
.stats{
  display:grid;
  grid-template-columns:repeat(4,minmax(180px,1fr));
  gap:18px;
  margin-top:34px;
}
.stat-card{
  background:#fff;border-radius:24px;padding:22px;box-shadow:0 14px 24px rgba(0,0,0,.12);
}
.stat-label{
  font-size:14px;color:#555;font-weight:700;margin-bottom:8px;
}
.stat-value{
  font-size:36px;font-weight:900;color:var(--maroon);line-height:1;
}
.menu-grid{
  margin-top:34px;
  display:grid;
  grid-template-columns:repeat(3,minmax(240px,1fr));
  gap:22px;
}
.menu-card{
  text-decoration:none;
  color:#111;
  background:#fff;
  border-radius:28px;
  padding:26px 24px;
  box-shadow:0 16px 28px rgba(0,0,0,.14);
  transition:.2s ease;
  min-height:220px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
}
.menu-card:hover{
  transform:translateY(-3px);
  box-shadow:0 20px 32px rgba(0,0,0,.18);
}
.icon-bubble{
  width:76px;height:76px;border-radius:22px;
  background:rgba(112,13,9,.10);
  color:var(--maroon);
  display:flex;align-items:center;justify-content:center;
  font-size:34px;
}
.menu-title{
  margin-top:18px;
  font-size:26px;
  font-weight:900;
}
.menu-text{
  margin-top:8px;
  color:#555;
  font-size:14px;
  line-height:1.5;
}
.menu-go{
  margin-top:16px;
  display:inline-flex;align-items:center;gap:8px;
  color:var(--maroon);font-weight:800;font-size:14px;
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

@media (max-width: 991px){
  .stats{grid-template-columns:repeat(2,minmax(160px,1fr))}
  .menu-grid{grid-template-columns:1fr}
}
@media (max-width: 576px){
  .title{font-size:34px}
  .stats{grid-template-columns:1fr}
}
</style>
</head>
<body>

<nav class="navbar-kpu">
  <div class="inner">
    <a href="admin.php" class="brand">
      <img src="Asset/LogoKPU.png" alt="KPU">
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
  <div class="heading">
    <div>
      <h1 class="title">Dashboard Admin</h1>
      <div class="subtitle">Kelola materi, kuis, dan profil user dari satu tempat.</div>
    </div>
  </div>

  <section class="stats">
    <div class="stat-card">
      <div class="stat-label">Total Materi</div>
      <div class="stat-value"><?= $totalMateri ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Total Kuis</div>
      <div class="stat-value"><?= $totalKuis ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">User</div>
      <div class="stat-value"><?= $totalUser ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Admin</div>
      <div class="stat-value"><?= $totalAdmin ?></div>
    </div>
  </section>

  <section class="menu-grid">
    <a href="tambah_materi_admin.php" class="menu-card">
      <div>
        <div class="icon-bubble"><i class="bi bi-journal-richtext"></i></div>
        <div class="menu-title">Materi</div>
        <div class="menu-text">Upload materi berupa PDF, video, atau link.</div>
      </div>
      <div class="menu-go">Buka menu <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="tambah_kuis_admin.php" class="menu-card">
      <div>
        <div class="icon-bubble"><i class="bi bi-ui-checks-grid"></i></div>
        <div class="menu-title">Kuis</div>
        <div class="menu-text">Kelola kuis CSV/manual, sampai 100 soal.</div>
      </div>
      <div class="menu-go">Buka menu <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="edit_profile_admin.php" class="menu-card">
      <div>
        <div class="icon-bubble"><i class="bi bi-people-fill"></i></div>
        <div class="menu-title">Lihat Profil User</div>
        <div class="menu-text">Lihat user, detail akun, aktivitas kuis.</div>
      </div>
      <div class="menu-go">Buka menu <i class="bi bi-arrow-right"></i></div>
    </a>
  </section>
</main>

<div class="modal-overlay" id="popupOverlay" aria-hidden="true">
  <div class="modal-content-custom" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
    <p class="popup-title" id="popupTitle">Konfirmasi</p>
    <p class="popup-message" id="popupMessage">Pesan</p>
    <div class="popup-actions" id="popupActions"></div>
  </div>
</div>

<script>
(function(){
  const popupOverlay = document.getElementById('popupOverlay');
  const popupTitle   = document.getElementById('popupTitle');
  const popupMessage = document.getElementById('popupMessage');
  const popupActions = document.getElementById('popupActions');

  const logoutFormDesktop = document.getElementById('logoutFormDesktop');
  const btnLogoutDesktop  = document.getElementById('btnLogoutDesktop');

  let popupLocked = false;

  function closePopup(){
    popupOverlay.style.display = "none";
    popupOverlay.setAttribute("aria-hidden","true");
    popupActions.innerHTML = '';
    popupLocked = false;
  }

  function openPopup({ title="Konfirmasi", message="", okText="OK", cancelText="", onOk=null, onCancel=null }){
    if (popupLocked) return;
    popupLocked = true;

    popupTitle.textContent = title;
    popupMessage.textContent = message;
    popupActions.innerHTML = '';

    if (cancelText) {
      const btnCancel = document.createElement('button');
      btnCancel.type = "button";
      btnCancel.className = "btn-modal-cancel";
      btnCancel.textContent = cancelText;
      btnCancel.addEventListener('click', () => {
        closePopup();
        if (typeof onCancel === "function") onCancel();
      });
      popupActions.appendChild(btnCancel);
    }

    const btnOk = document.createElement('button');
    btnOk.type = "button";
    btnOk.className = "btn-modal-action";
    btnOk.textContent = okText;
    btnOk.addEventListener('click', () => {
      closePopup();
      if (typeof onOk === "function") onOk();
    });
    popupActions.appendChild(btnOk);

    popupOverlay.style.display = "flex";
    popupOverlay.setAttribute("aria-hidden","false");
  }

  if (logoutFormDesktop && btnLogoutDesktop) {
    btnLogoutDesktop.addEventListener('click', (e) => {
      e.preventDefault();
      openPopup({
        title: "Konfirmasi Logout",
        message: "Apakah Anda yakin ingin logout dari akun ini?",
        okText: "Logout",
        cancelText: "Batal",
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
    if (e.key !== "Escape") return;
    if (popupOverlay.style.display !== "flex") return;
    const hasCancel = popupActions.querySelector('.btn-modal-cancel');
    if (!hasCancel) closePopup();
  });
})();
</script>

</body>
</html>
