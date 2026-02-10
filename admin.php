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

<?php
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

html{
  overflow-y: scroll;
  scrollbar-gutter: stable;
}

body{
  margin:0;
  font-family:'Inter',sans-serif;
  background:var(--bg);
  min-height:100vh;
  display:flex;
  flex-direction:column;
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
  gap:14px;
}

.brand{
  display:flex;
  align-items:center;
  gap:8px;
  text-decoration:none;
}

.brand img{height:36px}

.brand-text{
  color:#fff;
  line-height:1.15;
}

.brand-text strong{
  font-size:.95rem;
  font-weight:700;
}

.brand-text span{
  font-size:.85rem;
  font-weight:400;
}

.btn-logout{
  border:0;
  background:transparent;
  color:#fff;
  font-weight:600;
  font-size:.85rem;
  letter-spacing:.5px;
  padding:0;
  position:relative;
  white-space:nowrap;
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
  max-width:1200px;
  margin:0 auto;
  width:100%;
  padding:calc(var(--navbar-h) + 100px) 20px 30px;
  flex:1;
  display:flex;
  flex-direction:column;
  justify-content:flex-start;
  align-items:center;
}

.title{
  text-align:center;
  margin:10px 0 60px;
  font-weight:900;
  font-size:42px;
  color:#B00000;
}

.grid{
  width:100%;
  max-width:820px;
  display:grid;
  grid-template-columns:repeat(2, minmax(240px, 1fr));
  justify-content:center;
  gap:110px;
  align-items:stretch;
}

.choice-card{
  width:100%;
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
  transition:transform .2s ease, filter .2s ease;
}

.choice-btn:hover{
  filter:brightness(.92);
  transform:translateY(2px);
}

.choice-btn:active{
  transform:translateY(3px);
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

.popup-title{
  font-weight:900;
  font-size:16px;
  margin:0 0 8px;
}

.popup-message{
  font-size:13px;
  color:#333;
  margin:0 0 18px;
}

.popup-actions{
  display:flex;
  gap:10px;
  justify-content:center;
}

.btn-modal-action,
.btn-modal-cancel{
  width:120px;
}

.btn-modal-action{
  background:var(--maroon);
  color:#fff;
  border:0;
  border-radius:20px;
  padding:6px 22px;
  font-weight:600;
}

.btn-modal-cancel{
  background:#e9e9e9;
  color:#111;
  border:0;
  border-radius:20px;
  padding:6px 22px;
  font-weight:600;
}

@media (max-width: 992px){
  .page{
    padding:calc(var(--navbar-h) + 70px) 16px 24px;
  }
  .title{
    font-size:34px;
    margin:6px 0 34px;
  }
  .grid{
    max-width:780px;
    gap:36px;
    grid-template-columns:repeat(2, minmax(200px, 1fr));
  }
  .choice-card{
    height:280px;
    gap:26px;
    border-radius:16px;
  }
  .choice-icon{
    font-size:66px;
  }
}

@media (max-width: 576px){
  .page{
    padding:calc(var(--navbar-h) + 55px) 14px 22px;
  }
  .title{
    font-size:28px;
    margin:4px 0 22px;
  }
  .grid{
    gap:18px;
    grid-template-columns:repeat(2, minmax(150px, 1fr));
    max-width:520px;
  }
  .choice-card{
    height:220px;
    gap:18px;
  }
  .choice-icon{
    font-size:54px;
  }
  .choice-btn{
    font-size:12px;
    padding:9px 18px;
  }

  .modal-content-custom{
    width:min(360px, 92vw);
  }
}

@media (max-width: 380px){
  .grid{
    grid-template-columns:1fr;
    max-width:420px;
    gap:14px;
  }
  .choice-card{
    height:220px;
  }
}
</style>
</head>

<body>

<nav class="navbar-kpu">
  <div class="inner">
    <a href="dashboard.php" class="brand">
      <img src="Asset/LogoKPU.png" alt="KPU">
      <div class="brand-text">
        <strong>KPU</strong><br>
        <span>DIY</span>
      </div>
    </a>

    <form method="post" id="logoutForm" class="m-0">
      <input type="hidden" name="action" value="logout">
      <button type="submit" class="btn-logout" id="btnLogout">LOGOUT</button>
    </form>
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
      <a class="choice-btn" href="tambah_kuis_admin.php">Tambah Soal</a>
    </div>
  </section>
</main>

<div class="modal-overlay" id="popupOverlay">
  <div class="modal-content-custom">
    <p class="popup-title" id="popupTitle">Konfirmasi</p>
    <p class="popup-message" id="popupMessage"></p>
    <div class="popup-actions" id="popupActions"></div>
  </div>
</div>

<script>
(function(){
  const popupOverlay=document.getElementById('popupOverlay');
  const popupMessage=document.getElementById('popupMessage');
  const popupActions=document.getElementById('popupActions');

  function closePopup(){
    popupOverlay.style.display="none";
    popupActions.innerHTML="";
  }

  function openPopup(message,onOk){
    popupMessage.textContent=message;
    popupActions.innerHTML="";
    const cancel=document.createElement('button');
    cancel.className="btn-modal-cancel";
    cancel.type="button";
    cancel.textContent="Batal";
    cancel.onclick=closePopup;

    const ok=document.createElement('button');
    ok.className="btn-modal-action";
    ok.type="button";
    ok.textContent="Logout";
    ok.onclick=onOk;

    popupActions.append(cancel,ok);
    popupOverlay.style.display="flex";
  }

  document.getElementById('btnLogout').addEventListener('click',e=>{
    e.preventDefault();
    openPopup("Yakin ingin logout?",()=>document.getElementById('logoutForm').submit());
  });

  popupOverlay.addEventListener('click',e=>{
    if(e.target===popupOverlay) closePopup();
  });
})();
</script>

</body>
</html>