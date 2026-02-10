<?php
declare(strict_types=1);
session_start();

$activePage = 'login';
$ERROR = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === $VALID_USER && $password === $VALID_PASS) {
        $_SESSION["admin"] = true;
        $_SESSION["admin_user"] = $username;
        header("Location: admin.php");
        exit;
    } else {
        $ERROR = "Username atau Password salah!";
    }
}
?>

<?php
$site_title = 'SI-NAU Demokrasi | Login Admin';
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

.login-card{
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
}

.toggle-password:hover{
    opacity:1;
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

.alert{
    border-radius:12px;
}

@media (max-width:900px){
    .login-card{
        grid-template-columns:1fr;
    }
    .card-left{
        display:none;
    }
    .card-right{
        border-radius:22px;
    }
}
</style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="page">
<section class="login-card">

<div class="card-left">
    <i class="bi bi-person-circle profile-icon"></i>
</div>

<div class="card-right">
    <h1 class="title">Masuk Sebagai Admin</h1>

    <form method="post" autocomplete="off">

        <div class="form-group">
            <label>Username</label>
            <input class="input" name="username" required>
        </div>

        <div class="form-group password-wrap">
            <label>Password</label>
            <input id="password" class="input" type="password" name="password" required>
            <i id="togglePassword" class="bi bi-eye toggle-password"></i>
        </div>

        <div class="actions">
            <button class="btn-submit" type="submit">Masuk</button>
        </div>

        <?php if ($ERROR): ?>
        <div class="alert alert-danger mt-4 fw-bold text-center">
            <?= htmlspecialchars($ERROR) ?>
        </div>
        <?php endif; ?>

    </form>
</div>

</section>
</main>

<script>
const toggle=document.getElementById("togglePassword");
const password=document.getElementById("password");

toggle.addEventListener("click",()=>{
    const show=password.type==="password";
    password.type=show?"text":"password";
    toggle.className=show
        ?"bi bi-eye-slash toggle-password"
        :"bi bi-eye toggle-password";
});
</script>

<?php include 'footer.php'; ?>

</body>
</html>
