<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   PROSES LOGOUT
   - submit ke halaman saat ini
   - session dihancurkan
   - redirect pakai JS agar aman meski header.php di-include di tengah halaman
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout']) && $_POST['confirm_logout'] === '1') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool)$params['secure'],
            (bool)$params['httponly']
        );
    }

    session_destroy();

    echo '<!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta http-equiv="refresh" content="0;url=login.php">
      <script>window.location.href="login.php";</script>
      <title>Logout...</title>
    </head>
    <body></body>
    </html>';
    exit;
}

$activePage = $activePage ?? '';

$isLoggedIn = !empty($_SESSION['user_logged_in']) || !empty($_SESSION['admin_logged_in']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$isUserProfilePage = ($currentPage === 'user_profile.php');

/* =========================
   AUTH MENU LOGIC
========================= */
$authHref  = 'login.php';
$authLabel = 'LOGIN';
$authIsLogout = false;
$authActiveClass = ($activePage === 'login') ? 'active' : '';

if ($isLoggedIn) {
    if ($isUserProfilePage) {
        $authLabel = 'LOGOUT';
        $authHref = '#';
        $authIsLogout = true;
        $authActiveClass = 'active';
    } else {
        $authLabel = 'PROFIL';
        $authHref = 'user_profile.php';
        $authIsLogout = false;
        $authActiveClass = ($currentPage === 'user_profile.php') ? 'active' : '';
    }
}

$berandaHref = 'dashboard.php';
$materiHref  = $isLoggedIn ? 'daftar_materi.php' : 'login.php';
$kuisHref    = $isLoggedIn ? 'daftar_kuis.php' : 'login.php';
$kontakHref  = $isLoggedIn ? 'kontak.php' : 'login.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
:root{
  --maroon:#700D09;
  --gold:#f4c430;
  --navbar-h:90px;
}

.navbar-kpu{
  position:fixed;
  top:0;left:0;right:0;
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
  flex-shrink:0;
}

.brand img{height:36px}

.brand-text{
  color:#fff;
  line-height:1.05;
  gap:2px;
  flex-direction:column;
  display:flex;
}

.brand-text strong{
  font-size:.95rem;
  font-weight:700;
}

.brand-text span{
  font-size:.85rem;
  font-weight:400;
}

.nav-menu{
  display:flex;
  gap:26px;
  align-items:center;
}

.nav-menu a,
.nav-menu .auth-btn{
  color:#fff;
  font-weight:600;
  font-size:.85rem;
  letter-spacing:.5px;
  text-decoration:none;
  position:relative;
  white-space:nowrap;
}

.nav-menu a::after,
.nav-menu .auth-btn::after{
  content:"";
  position:absolute;
  left:0;bottom:-6px;
  width:0;height:3px;
  background:var(--gold);
  transition:.3s;
}

.nav-menu a:hover::after,
.nav-menu a.active::after,
.nav-menu .auth-btn:hover::after,
.nav-menu .auth-btn.active::after{
  width:100%;
}

.auth-btn{
  border:0;
  background:transparent;
  padding:0;
  cursor:pointer;
}

.hamburger{
  display:none;
  position:relative;
}

.hamburger-btn{
  width:40px;
  height:40px;
  border-radius:10px;
  border:1.5px solid #fff;
  background:transparent;
  color:#fff;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:22px;
  cursor:pointer;
  transition:.25s ease;
  z-index:1002;
}

.hamburger-btn:hover{
  background:#fff;
  color:var(--maroon);
}

.hamburger.open .hamburger-btn{
  background:#fff;
  color:var(--maroon);
}

.hamburger-menu{
  position:absolute;
  top:52px;
  right:0;
  background:#fff;
  border-radius:14px;
  min-width:210px;
  padding:10px 0;
  box-shadow:0 18px 40px rgba(0,0,0,.25);
  opacity:0;
  transform:translateY(8px);
  pointer-events:none;
  transition:.25s ease;
  z-index:1002;
}

.hamburger.open .hamburger-menu{
  opacity:1;
  transform:translateY(0);
  pointer-events:auto;
}

.hamburger-menu a,
.hamburger-menu .auth-menu-btn{
  display:block;
  width:100%;
  text-align:left;
  padding:10px 20px;
  color:#222;
  font-weight:600;
  font-size:.85rem;
  text-decoration:none;
  border:0;
  background:transparent;
  cursor:pointer;
}

.hamburger-menu a:hover,
.hamburger-menu .auth-menu-btn:hover{
  background:#f2f2f2;
  color:var(--maroon);
}

.hamburger-backdrop{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.25);
  opacity:0;
  pointer-events:none;
  transition:.25s ease;
  z-index:1001;
}

.hamburger.open ~ .hamburger-backdrop{
  opacity:1;
  pointer-events:auto;
}

/* popup konfirmasi */
.logout-popup-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.35);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:2000;
  padding:16px;
}

.logout-popup-overlay.show{
  display:flex;
}

.logout-popup{
  width:100%;
  max-width:380px;
  background:#fff;
  border-radius:18px;
  box-shadow:0 18px 40px rgba(0,0,0,.25);
  padding:22px 20px;
}

.logout-popup-title{
  margin:0 0 8px;
  font-size:20px;
  font-weight:700;
  color:#111;
}

.logout-popup-text{
  margin:0;
  font-size:14px;
  color:#444;
  line-height:1.55;
}

.logout-popup-actions{
  display:flex;
  justify-content:flex-end;
  gap:10px;
  margin-top:18px;
}

.logout-popup-actions button{
  border:0;
  border-radius:10px;
  padding:10px 14px;
  font-size:14px;
  font-weight:600;
  cursor:pointer;
}

.btn-cancel-logout{
  background:#ececec;
  color:#222;
}

.btn-confirm-logout{
  background:var(--maroon);
  color:#fff;
}

@media(max-width:992px){
  .nav-menu{display:none}
  .hamburger{display:block}
}

.main-content{
  margin-top:var(--navbar-h);
}
</style>

<nav class="navbar-kpu">
  <div class="inner">

    <a href="<?= $berandaHref ?>" class="brand">
      <img src="Asset/LogoKPU.png" alt="KPU">
      <div class="brand-text">
        <strong>KPU</strong>
        <span>DIY</span>
      </div>
    </a>

    <div class="nav-menu">
      <a href="<?= $berandaHref ?>" class="<?= $activePage==='dashboard' ? 'active' : '' ?>">BERANDA</a>
      <a href="<?= $materiHref ?>" class="<?= $activePage==='materi' ? 'active' : '' ?>">MATERI</a>
      <a href="<?= $kuisHref ?>" class="<?= $activePage==='kuis' ? 'active' : '' ?>">KUIS</a>
      <a href="<?= $kontakHref ?>" class="<?= $activePage==='kontak' ? 'active' : '' ?>">KONTAK</a>

      <?php if ($authIsLogout): ?>
        <button type="button" class="auth-btn <?= $authActiveClass ?>" onclick="openLogoutPopup()">LOGOUT</button>
      <?php else: ?>
        <a href="<?= htmlspecialchars($authHref, ENT_QUOTES, 'UTF-8') ?>" class="<?= $authActiveClass ?>">
          <?= htmlspecialchars($authLabel, ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endif; ?>
    </div>

    <div class="hamburger" id="hamburger">
      <div class="hamburger-btn">
        <i class="bi bi-list"></i>
      </div>
      <div class="hamburger-menu">
        <a href="<?= $berandaHref ?>">BERANDA</a>
        <a href="<?= $materiHref ?>">MATERI</a>
        <a href="<?= $kuisHref ?>">KUIS</a>
        <a href="<?= $kontakHref ?>">KONTAK</a>

        <?php if ($authIsLogout): ?>
          <button type="button" class="auth-menu-btn" onclick="openLogoutPopup()">LOGOUT</button>
        <?php else: ?>
          <a href="<?= htmlspecialchars($authHref, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($authLabel, ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</nav>

<div class="hamburger-backdrop" id="hamburgerBackdrop"></div>

<div class="logout-popup-overlay" id="logoutPopupOverlay">
  <div class="logout-popup">
    <h3 class="logout-popup-title">Konfirmasi Logout</h3>
    <p class="logout-popup-text">Apakah Anda yakin ingin logout dari akun ini?</p>

    <div class="logout-popup-actions">
      <button type="button" class="btn-cancel-logout" onclick="closeLogoutPopup()">Batal</button>

      <form method="post" style="margin:0;">
        <input type="hidden" name="confirm_logout" value="1">
        <button type="submit" class="btn-confirm-logout">Logout</button>
      </form>
    </div>
  </div>
</div>

<script>
const hb = document.getElementById('hamburger');
const bd = document.getElementById('hamburgerBackdrop');
const logoutPopupOverlay = document.getElementById('logoutPopupOverlay');

if (hb && bd) {
  hb.addEventListener('click', (e) => {
    if (e.target.closest('.hamburger-menu')) return;
    hb.classList.toggle('open');
  });

  bd.addEventListener('click', () => hb.classList.remove('open'));

  window.addEventListener('resize', () => {
    if (innerWidth > 992) hb.classList.remove('open');
  });
}

function openLogoutPopup() {
  if (hb) hb.classList.remove('open');
  if (logoutPopupOverlay) logoutPopupOverlay.classList.add('show');
}

function closeLogoutPopup() {
  if (logoutPopupOverlay) logoutPopupOverlay.classList.remove('show');
}

if (logoutPopupOverlay) {
  logoutPopupOverlay.addEventListener('click', function(e) {
    if (e.target === logoutPopupOverlay) {
      closeLogoutPopup();
    }
  });
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeLogoutPopup();
  }
});
</script>