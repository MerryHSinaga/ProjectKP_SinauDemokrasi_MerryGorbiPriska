<?php
declare(strict_types=1);
session_start();

$ERROR = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    $VALID_USER = "AdminSinauPemilu";
    $VALID_PASS = "KPUYogyakart4#";

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
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin | DIY</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root{
            --maroon:#700D09;
            --bg:#E9EDFF;
            --gold:#f4c430;
        }

        body{
            margin:0;
            font-family:'Inter';
            background:var(--bg);
            min-height:100vh;
        }

        .bg-maroon{
            background:#700D09;
        }

        .navbar{
            padding:20px 0;
            border-bottom:1px solid #000;
        }

        .nav-link{
            color:#fff;
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
            background:#f4c430;
            transition:0.3s ease;
        }

        .nav-hover:hover::after{
            width:100%;
        }

        /* agar class nav-active tidak sia-sia (tampilan sama feel seperti hover) */
        .nav-active::after{
            width:100%;
        }

        .page{
            padding-top:110px;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .login-card{
            width:min(980px, 92vw);
            background:#fff;
            border-radius:22px;
            box-shadow:0 12px 26px rgba(0,0,0,.18);
            overflow:hidden;
            display:grid;
            grid-template-columns: 1fr 1.2fr;
        }

        .card-left{
            background:#ffffff;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:40px 20px;
        }

        .user-icon{
            width:280px;
            height:280px;
        }

        .user-icon circle,
        .user-icon path{
            stroke:var(--maroon);
            stroke-width:10;
            fill:none;
            stroke-linecap:round;
            stroke-linejoin:round;
        }

        .card-right{
            color:#fff;
            padding:54px 56px;
            background:linear-gradient(145deg, #8f0f0b 0%, #7a0c08 45%, var(--maroon) 100%);
            border-top-left-radius:60px;
            border-bottom-left-radius:60px;
        }

        .title{
            text-align:center;
            font-weight:800;
            font-size:30px;
            margin:8px 0 34px;
        }

        .form-label{
            font-weight:700;
            margin-bottom:6px;
        }

        .input{
            width:100%;
            background:transparent;
            border:0;
            border-bottom:2px solid rgba(255,255,255,.35);
            color:#fff;
            font-size:16px;
            padding:10px 0 10px;
            outline:none;
        }
        .input::placeholder{ color:rgba(255,255,255,.55); }

        /* wrapper supaya icon absolute tidak lari (tanpa ubah tampilan) */
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
            margin-top:16px;
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
        .btn-submit:hover{ opacity:.95; }
        .btn-submit:active{ transform:translateY(1px); }

        .alert{
            border-radius:12px;
        }

        @media (max-width: 900px){
            .login-card{
                grid-template-columns:1fr;
            }
            .card-left{
                display:none;
            }
            .card-right{
                border-top-left-radius:22px;
                border-bottom-left-radius:22px;
            }
        }

        @media (max-width: 600px){
            .navbar-nav{ gap:14px !important; }
            .card-right{ padding:40px 22px; }
            .title{ font-size:24px; }
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
            <li class="nav-item"><a class="nav-link nav-hover" href="dashboard.php">HOME</a></li>
            <li class="nav-item"><a class="nav-link nav-hover" href="materi.php">MATERI</a></li>
            <li class="nav-item"><a class="nav-link nav-hover" href="daftar_kuis.php">KUIS</a></li>
            <li class="nav-item"><a class="nav-link nav-hover" href="kontak.php">KONTAK</a></li>
            <li class="nav-item"><a class="nav-link nav-hover nav-active" href="login_admin.php">LOGIN</a></li>
        </ul>
    </div>
</nav>

<main class="page">
    <section class="login-card">

        <div class="card-left">
            <svg class="user-icon" viewBox="0 0 256 256" aria-hidden="true">
                <circle cx="128" cy="128" r="92"></circle>
                <circle cx="128" cy="104" r="28"></circle>
                <path d="M72 192c14-28 40-44 56-44s42 16 56 44"></path>
            </svg>
        </div>

        <div class="card-right">
            <h1 class="title">Masuk Sebagai Admin</h1>

            <form method="post" autocomplete="off">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <input class="input" name="username" placeholder="Username" required>
                </div>

                <!-- dibungkus agar icon absolute nempel di input -->
                <div class="mb-4 password-wrap">
                    <label class="form-label">Password</label>
                    <input id="password" class="input" type="password" name="password" placeholder="Password" required>
                    <i id="togglePassword" class="bi bi-eye toggle-password" role="button" aria-label="Tampilkan password" tabindex="0"></i>
                </div>

                <div class="actions">
                    <button class="btn-submit" type="submit">Masuk</button>
                </div>

                <?php if($ERROR): ?>
                    <div class="alert alert-danger mt-4 fw-bold text-center">
                        <?= htmlspecialchars($ERROR) ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>

    </section>
</main>

<script>
(function () {
    const toggle = document.getElementById("togglePassword");
    const password = document.getElementById("password");

    if (!toggle || !password) return;

    function doToggle() {
        const isPassword = password.getAttribute("type") === "password";
        password.setAttribute("type", isPassword ? "text" : "password");
        toggle.classList.toggle("bi-eye", !isPassword);
        toggle.classList.toggle("bi-eye-slash", isPassword);
    }

    toggle.addEventListener("click", doToggle);
    toggle.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            doToggle();
        }
    });
})();
</script>

<?php include 'footer.php'; ?>

</body>
</html>
