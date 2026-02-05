<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION["admin"])) {
  header("Location: login_admin.php");
  exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST["action"] ?? "") === "logout") {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], (bool)$p["secure"], (bool)$p["httponly"]);
  }
  session_destroy();
  header("Location: login_admin.php");
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin | DIY</title>

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
      --title-red:#C01A1A;
    }

    body{
      margin:0;
      font-family:'Inter';
      background:var(--bg);
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }

    .bg-maroon{background:var(--maroon)!important}

    .navbar{
      padding:20px 0;
      border-bottom:1px solid #000;
    }

    .nav-link{
      color:#fff !important;
      font-weight:500;
    }

    .nav-hover{
      position:relative;
      padding-bottom:6px;
    }

    .nav-hover::after{
      content:"";
      position:absolute;
      left:0;
      bottom:0;
      width:0;
      height:3px;
      background:var(--gold);
      transition:0.3s ease;
    }

    .nav-hover:hover::after,
    .nav-active::after{
      width:100%;
    }

    .page{
      max-width:1200px;
      margin:0 auto;
      width:100%;
      padding:150px 20px 30px; 
      flex:1;
      display:flex;
      flex-direction:column;
      justify-content:center;
    }

    .title{
      text-align:center;
      margin:10px 0 60px;
      font-weight:900;
      font-size:42px;
      background: linear-gradient(90deg, #8B0000, #E02727, #750000);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      color: transparent;
    }

    .grid{
      display:grid;
      grid-template-columns:repeat(2, 280px);
      justify-content:center;
      gap:110px;
      align-content:center;
      padding-bottom:30px;
    }

    .choice-card{
      background:#fff;
      border-radius:16px;
      height:320px;
      border:1px solid rgba(0,0,0,.06);
      box-shadow:0 20px 26px -18px rgba(0,0,0,.45);
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      gap:34px;
      transition:transform .25s ease, box-shadow .25s ease;
    }

    .choice-card:hover{
      transform:translateY(-6px);
      box-shadow:0 26px 30px -20px rgba(0,0,0,.55);
    }

    .choice-icon{
      font-size:78px;
      color:var(--maroon);
      line-height:1;
      transition:transform .25s ease;
    }

    .choice-card:hover .choice-icon{
      transform:scale(1.06);
    }

    .choice-btn{
      background:var(--maroon);
      color:#fff;
      font-weight:800;
      font-size:14px;
      padding:10px 34px;
      border-radius:999px;
      text-decoration:none;
      display:inline-block;
      margin-top:12px;
      transition:transform .2s ease, filter .2s ease;
    }

    .choice-btn:hover{
      color:#fff;
      filter:brightness(0.92);
      transform:translateY(2px);
    }

    .choice-btn:active{
      transform:translateY(3px);
    }

    .btn-logout{
      border:0;
      background:transparent;
      color:#fff;
      font-weight:500;
      padding:0;
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
    }
    .btn-modal-cancel{
      border:0;
      border-radius:20px;
      padding:6px 22px;
      font-weight:600;
      background:#e9e9e9;
      color:#111;
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

    @media (max-width: 992px){
      .grid{grid-template-columns:1fr;gap:26px}
      .choice-card{width:min(420px,100%); margin:0 auto;}
      .title{font-size:36px; margin-bottom:34px;}
      .page{padding-top:120px;}
      .modal-content-custom{width:min(360px, 92vw);}
    }
  </style>
</head>
<body>

<nav class="navbar navbar-dark bg-maroon fixed-top">
  <div class="container d-flex justify-content-between align-items-center">
    <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
      <img src="Asset/LogoKPU.png" height="40" alt="KPU">
      <span class="lh-sm text-white fs-6">
        <strong>KPU</strong><br>DIY
      </span>
    </a>

    <ul class="navbar-nav flex-row gap-5 align-items-center">
      <li class="nav-item">
        <form method="post" id="logoutForm" class="m-0">
          <input type="hidden" name="action" value="logout">
          <button type="submit" class="nav-link nav-hover btn-logout" id="btnLogout">LOGOUT</button>
        </form>
      </li>
    </ul>
  </div>
</nav>

<main class="page">
  <h1 class="title">Halo, Ingin Menambahkan Apa Hari Ini?</h1>

  <section class="grid">
    <div class="choice-card">
      <i class="bi bi-clipboard-check choice-icon"></i>
      <a class="choice-btn" href="tambah_materi_admin.php">Tambah Materi</a>
    </div>

    <div class="choice-card">
      <i class="bi bi-card-checklist choice-icon"></i>
      <a class="choice-btn" href="kuis_admin.php">Tambah Soal</a>
    </div>
  </section>
</main>

<div class="modal-overlay" id="popupOverlay" aria-hidden="true">
  <div class="modal-content-custom" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
    <p class="popup-title" id="popupTitle">Konfirmasi</p>
    <p class="popup-message" id="popupMessage">Pesan</p>
    <div class="popup-actions" id="popupActions"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const popupOverlay = document.getElementById('popupOverlay');
  const popupTitle   = document.getElementById('popupTitle');
  const popupMessage = document.getElementById('popupMessage');
  const popupActions = document.getElementById('popupActions');

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

  popupOverlay.addEventListener('click', (e) => {
    if (e.target === popupOverlay) {
      const hasCancel = popupActions.querySelector('.btn-modal-cancel');
      if (!hasCancel) closePopup();
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === "Escape" && popupOverlay.style.display === "flex") {
      const hasCancel = popupActions.querySelector('.btn-modal-cancel');
      if (!hasCancel) closePopup();
    }
  });

  const logoutForm = document.getElementById('logoutForm');
  const btnLogout  = document.getElementById('btnLogout');

  if (logoutForm && btnLogout) {
    btnLogout.addEventListener('click', (e) => {
      e.preventDefault();
      openPopup({
        title: "Konfirmasi",
        message: "Yakin ingin logout?",
        okText: "Logout",
        cancelText: "Batal",
        onOk: () => logoutForm.submit()
      });
    });
  }
})();
</script>

<?php include 'footer.php'; ?>

</body>
</html>
