<?php
session_start();
require_once 'db.php';

$activePage = 'kuis';

$todayISO  = date('Y-m-d');
$todayView = date('d-M-Y');

if (!isset($_GET['id'])) {
    session_unset();
    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}

$kuis_id = (int) $_GET['id'];

$stmt = db()->prepare("SELECT judul FROM kuis_paket WHERE id = ?");
$stmt->execute([$kuis_id]);
$kuis = $stmt->fetch();

if (!$kuis) {
    session_unset();
    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}

if (isset($_POST['mulai'])) {

    $nama    = trim($_POST['nama'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $tanggal = trim($_POST['tanggal'] ?? '');

    if (
        !empty($nama) &&
        !empty($alamat) &&
        $tanggal === $todayISO &&
        preg_match('/^[A-Za-z\s]{1,37}$/', $nama)
    ) {
  
        $_SESSION['nama']    = $nama;
        $_SESSION['alamat']  = $alamat;
        $_SESSION['tanggal'] = $todayView;


        $_SESSION['kuis_id']       = $kuis_id;
        $_SESSION['materi_judul']  = $kuis['judul'];

    
       $_SESSION['flow_step'] = 'BIODATA_OK';


        unset($_SESSION['jawaban'], $_SESSION['skor'], $_SESSION['predikat']);

        header("Location: user_kuis.php");
        exit;
    }
}
?>

<?php
$site_title = 'Uji Kemahiran Kepemiluan';
include 'identitas.php';
?>

<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

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
    padding-top:var(--navbar-h);
}

.navbar-kpu{
    position:fixed;
    top:0;left:0;right:0;
    height:var(--navbar-h);
    background:var(--maroon);
    z-index:1000;
    border-bottom:1px solid #000;
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
    padding:0;
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
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
}

.card-main{
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
}

.user-icon{
    font-size:200px;
    color:var(--maroon);
    opacity:.9;
}

.card-right{
    color:#fff;
    padding:48px 52px;
    background:linear-gradient(145deg,#8f0f0b 0%,#7a0c08 45%,var(--maroon) 100%);
    border-top-left-radius:60px;
    border-bottom-left-radius:60px;
}

.card-right h1{
    text-align:center;
    font-weight:800;
    font-size:30px;
    margin-bottom:28px;
}

.form-group-custom{
    margin-bottom:20px;
}

.form-group-custom label{
    font-size:14px;
    font-weight:600;
}

.input-date{
    position:relative;
}

.input-date input,
.form-group-custom input{
    width:100%;
    background:transparent;
    border:0;
    border-bottom:2px solid rgba(255,255,255,.35);
    color:#fff;
    font-size:16px;
    padding:8px 0;
    outline:none;
}

.input-date input{
    padding-right:34px;
    color:#fff;
}

.input-date i{
    position:absolute;
    right:0;
    top:50%;
    transform:translateY(-50%);
    font-size:18px;
    color:#fff;
    pointer-events:none;
}

.input-date input::-webkit-calendar-picker-indicator{
    filter: invert(1);
    opacity: 1;
    cursor: pointer;
    position: absolute;
    right: 0;
}

.actions{
    display:flex;
    justify-content:flex-end;
    margin-top:32px;
}

.btn-kuis{
    background:#fff;
    color:var(--maroon);
    font-weight:800;
    padding:8px 34px;
    border-radius:999px;
    border:0;
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
}

.popup-title{
    font-weight:900;
    font-size:16px;
    margin-bottom:8px;
}

.popup-message{
    font-size:13px;
    color:#333;
    margin-bottom:18px;
}

.popup-actions{
    display:flex;
    gap:10px;
}

.btn-modal-action,
.btn-modal-cancel{
    flex:1;
    border:0;
    border-radius:20px;
    padding:8px 0;
    font-weight:600;
}

.btn-modal-action{
    background:var(--maroon);
    color:#fff;
}

.btn-modal-cancel{
    background:#e9e9e9;
    color:#111;
}

@media(max-width:900px){
    .card-main{grid-template-columns:1fr}
    .card-left{display:none}
    .card-right{border-radius:22px}
}
</style>
</head>

<body>

<nav class="navbar-kpu">
    <div class="inner">
        <a href="#" class="brand">
            <img src="Asset/LogoKPU.png">
            <div class="brand-text">
                <strong>KPU</strong><br>
                <span>DIY</span>
            </div>
        </a>
        <button type="button" class="btn-logout" id="btnCancel">BATALKAN KUIS</button>
    </div>
</nav>

<main class="page">
<section class="card-main">

<div class="card-left">
    <i class="bi bi-person-circle user-icon"></i>
</div>

<div class="card-right">
<h1>Uji Kemahiran Kepemiluan</h1>

<form method="post" action="?id=<?= $kuis_id ?>" onsubmit="allowNavigate=true;">
    <div class="form-group-custom">
        <label>Tanggal Ujian</label>
        <div class="input-date">
            <input type="date" name="tanggal" value="<?= $todayISO ?>" min="<?= $todayISO ?>" max="<?= $todayISO ?>" required>
        </div>
    </div>

    <div class="form-group-custom">
        <label>Nama Peserta</label>
        <input type="text" name="nama" maxlength="36" pattern="[A-Za-z\s]+" required>
    </div>

    <div class="form-group-custom">
        <label>Alamat</label>
        <input type="text" name="alamat" required>
    </div>

    <div class="actions">
        <button type="submit" name="mulai" class="btn-kuis">Mulai Kuis</button>
    </div>
</form>
</div>

</section>
</main>

<div class="modal-overlay" id="popupOverlay">
    <div class="modal-content-custom">
        <p class="popup-title">Konfirmasi</p>
        <p class="popup-message" id="popupMessage"></p>
        <div class="popup-actions" id="popupActions"></div>
    </div>
</div>

<script>
let allowNavigate=false;
const popupOverlay=document.getElementById('popupOverlay');
const popupMessage=document.getElementById('popupMessage');
const popupActions=document.getElementById('popupActions');

function closePopup(){
    popupOverlay.style.display="none";
    popupActions.innerHTML="";
}

function openConfirm(message,onOk){
    popupMessage.textContent=message;
    popupActions.innerHTML="";
    const cancel=document.createElement("button");
    cancel.className="btn-modal-cancel";
    cancel.textContent="Tidak";
    cancel.onclick=closePopup;
    const ok=document.createElement("button");
    ok.className="btn-modal-action";
    ok.textContent="Iya";
    ok.onclick=onOk;
    popupActions.append(cancel,ok);
    popupOverlay.style.display="flex";
}

document.getElementById("btnCancel").addEventListener("click",()=>{
    openConfirm("Apakah Anda yakin akan membatalkan Kuis?",()=>{
        allowNavigate=true;
        window.location.href="daftar_kuis.php";
    });
});

history.pushState(null,"",window.location.href);

window.addEventListener("popstate",()=>{
    if(!allowNavigate){
        openConfirm("Apakah Anda yakin akan membatalkan Kuis?",()=>{
            allowNavigate=true;
            window.location.href="daftar_kuis.php";
        });
        history.pushState(null,"",window.location.href);
    }
});

window.addEventListener("beforeunload",e=>{
    if(!allowNavigate){
        e.preventDefault();
        e.returnValue="";
    }
});
</script>

</body>
</html>