<?php
declare(strict_types=1);
session_start();
require_once 'db.php';

$ERROR = "";
$SUCCESS = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nama     = trim($_POST["nama"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($nama) || empty($username) || empty($password)) {
        $ERROR = "Semua field wajib diisi!";
    }

    elseif (!preg_match('/^[A-Z](?=.*[a-z])(?=.*\d)(?=.*[@$!%*?&#]).{7,}$/', $password)) {
        $ERROR = "PASSWORD_INVALID";
    }

    else {

        $stmt = db()->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $ERROR = "USERNAME_TAKEN";
        }

        else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insert = db()->prepare("
                INSERT INTO users (nama, username, password)
                VALUES (?, ?, ?)
            ");

            $insert->execute([
                $nama,
                $username,
                $hashedPassword,
                ]);

            $SUCCESS = true;
        }
    }
}
?>

<?php
$site_title = 'SI-NAU Demokrasi | Registrasi Pengguna';
include 'identitas.php';
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

body{
margin:0;
font-family:'Inter',sans-serif;
background:var(--bg);
min-height:100vh;
}

.page{
padding-top:110px;
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
}

.register-card{
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

.profile-icon{
font-size:180px;
color:var(--maroon);
}

.card-right{
color:#fff;
padding:54px 56px;
background:linear-gradient(145deg,#8f0f0b 0%,#7a0c08 45%,var(--maroon) 100%);
border-top-left-radius:60px;
border-bottom-left-radius:60px;
}

.title{
text-align:center;
font-weight:700;
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

.status-text{
font-style:italic;
font-size:0.8rem;
color:#aaa;
}

.actions{
display:flex;
justify-content:flex-end;
margin-top:24px;
}

.btn-submit{
background:#fff;
color:var(--maroon);
font-weight:800;
font-size:14px;
padding:8px 34px;
border-radius:999px;
border:0;
box-shadow:0 8px 18px rgba(0,0,0,.18);
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

.modal-box{
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
margin-bottom:18px;
}

.popup-actions{
display:flex;
justify-content:center;
}

.btn-modal{
background:var(--maroon);
color:#fff;
border:0;
padding:8px 20px;
border-radius:20px;
font-weight:600;
}
</style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="page">
<section class="register-card">

<div class="card-left">
<i class="bi bi-person-plus-fill profile-icon"></i>
</div>

<div class="card-right">

<h1 class="title">Daftar Akun Pengguna</h1>

<form method="post" autocomplete="off">

<div class="form-group">
<label>Nama Peserta</label>
<input type="text" class="input" name="nama" maxlength="36" pattern="[A-Za-z\s]+" required>
</div>

<div class="form-group">
<label>Username</label>
<input class="input" name="username" id="usernameInput" required>

<small id="usernameStatus" class="status-text">
*Username belum diperiksa
</small>
</div>

<div class="form-group">
<label>Password</label>
<input class="input" type="password" name="password" required>

<small class="status-text">
*Minimal 8 karakter, huruf pertama kapital, mengandung huruf kecil, angka, dan simbol
</small>
</div>

<div class="actions">
<button class="btn-submit" type="submit">Daftarkan Akun</button>
</div>

</form>

</div>
</section>
</main>

<!-- MODAL PASSWORD -->
<div class="modal-overlay" id="passwordModal">
<div class="modal-box">
<p class="popup-title">Perhatian</p>
<p class="popup-message">
Password yang anda masukkan belum memenuhi syarat keamanan yang ditentukan.
</p>
<div class="popup-actions">
<button class="btn-modal" onclick="closePasswordModal()">Kembali</button>
</div>
</div>
</div>

<!-- MODAL USERNAME -->
<div class="modal-overlay" id="usernameModal">
<div class="modal-box">
<p class="popup-title">Perhatian</p>
<p class="popup-message">
Username tidak tersedia.
</p>
<div class="popup-actions">
<button class="btn-modal" onclick="closeUsernameModal()">Kembali</button>
</div>
</div>
</div>

<!-- MODAL SUKSES -->
<div class="modal-overlay" id="successModal">
<div class="modal-box">
<p class="popup-title">Perhatian</p>
<p class="popup-message">
Pendaftaran akun berhasil dilakukan.
</p>
<div class="popup-actions">
<button class="btn-modal" onclick="goLogin()">Halaman Login</button>
</div>
</div>
</div>

<script>

function closePasswordModal(){
document.getElementById("passwordModal").style.display="none";
}

function closeUsernameModal(){
document.getElementById("usernameModal").style.display="none";
}

function goLogin(){
window.location.href="login.php";
}

<?php if ($ERROR === "PASSWORD_INVALID"): ?>
document.getElementById("passwordModal").style.display="flex";
<?php endif; ?>

<?php if ($ERROR === "USERNAME_TAKEN"): ?>
document.getElementById("usernameModal").style.display="flex";
<?php endif; ?>

<?php if ($SUCCESS): ?>
document.getElementById("successModal").style.display="flex";
<?php endif; ?>

const usernameInput=document.getElementById("usernameInput");
const usernameStatus=document.getElementById("usernameStatus");

usernameInput.addEventListener("input",async function(){

const username=this.value.trim();

if(username.length<3){
usernameStatus.style.color="#aaa";
usernameStatus.innerText="*Username terlalu pendek";
return;
}

const response=await fetch("check_username.php?username="+encodeURIComponent(username));
const result=await response.text();

if(result==="taken"){
usernameStatus.style.color="#ff6b6b";
usernameStatus.innerText="*Username tidak tersedia";
}else{
usernameStatus.style.color="#7CFC00";
usernameStatus.innerText="*Username dapat digunakan";
}

});

</script>

<?php include 'footer.php'; ?>

</body>
</html>