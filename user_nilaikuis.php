<?php
session_start();

$activePage = 'kuis';


if (
    !isset($_SESSION['flow_step']) ||
    $_SESSION['flow_step'] !== 'SELESAI'
) {
    session_unset();
    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}

$nama = $_SESSION['nama'] ?? 'Peserta';
$skor = (int) ($_SESSION['skor'] ?? 0);

if ($skor >= 85) {
    $pred = 'A (Baik Sekali)';
} elseif ($skor >= 70) {
    $pred = 'B (Baik)';
} elseif ($skor >= 55) {
    $pred = 'C (Cukup)';
} else {
    $pred = 'Perlu Belajar Lagi';
}

$_SESSION['predikat'] = $pred;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Hasil Kuis</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
body{
    font-family:'Inter',sans-serif;
    background:#E5E8FF;
    padding-top:120px;
    padding-bottom:120px;
}
.title{
    text-align:center;
    font-size:56px;
    font-weight:900;
    color:#B00000;
    margin-bottom:40px;
}
.result-box{
    max-width:920px;
    margin:auto;
    background:linear-gradient(180deg,#700D09,#950600);
    border-radius:36px;
    padding:56px 50px;
    color:#fff;
    text-align:center;
    box-shadow:0 16px 36px rgba(0,0,0,.45);
}
.score{
    font-size:92px;
    font-weight:900;
}
.predikat{
    font-size:22px;
    font-weight:700;
}
.btn-wrap{
    margin-top:36px;
    display:flex;
    justify-content:center;
    flex-wrap:wrap;
    gap:16px;
}
.btn-glossy{
    background:linear-gradient(180deg,#FF1A1A,#B00000);
    color:#fff;
    border:none;
    border-radius:40px;
    padding:11px 36px;
    font-size:18px;
    font-weight:700;
    text-decoration:none;
}
.modal-exit{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.6);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:20px;
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
    color:#333;
    margin-bottom:18px;
}
.popup-actions{
    display:flex;
    gap:10px;
}
.btn-no,
.btn-yes{
    flex:1;
    border:0;
    border-radius:20px;
    padding:8px 0;
    font-weight:600;
}
.btn-no{
    background:#e9e9e9;
    color:#111;
}
.btn-yes{
    background:#700D09;
    color:#fff;
}
@media(max-width:768px){
    body{padding-top:96px}
    .title{font-size:36px}
    .result-box{padding:36px 24px}
    .score{font-size:64px}
}
</style>
</head>

<body>

<?php include 'header.php'; ?>
<?php
$site_title = 'User | Hasil Kuis';
include 'identitas.php';
?>

<div class="container">

    <div class="title">HASIL KUIS</div>

    <div class="result-box">

        <?php if ($pred === 'Perlu Belajar Lagi'): ?>

            <p>Terima kasih, Anda telah berusaha menyelesaikan kegiatan SINAU DEMOKRASI hari ini!</p>
            <p>
                Anda memperoleh skor <b><?= $skor ?> (Perlu Belajar Lagi)</b><br>
                sehingga pemahaman Anda masih perlu ditingkatkan.
            </p>

            <div class="btn-wrap">
                <a href="user_jawabankuis.php" class="btn-glossy nav-safe">
                    Cek Jawaban
                </a>
            </div>

        <?php else: ?>

            <p>Selamat, Anda telah berhasil menyelesaikan SINAU DEMOKRASI hari ini!</p>

            <div class="score"><?= $skor ?></div>
            <div class="predikat"><?= $pred ?></div>

            <div class="btn-wrap">
                <a href="user_jawabankuis.php" class="btn-glossy nav-safe">
                    Cek Jawaban
                </a>
                <a href="download_sertifikat.php" class="btn-glossy nav-safe">
                    Download Sertifikat
                </a>
            </div>

        <?php endif; ?>

    </div>
</div>

<div class="modal-exit" id="exitModal">
    <div class="modal-box">
        <p class="popup-title">Perhatian</p>
        <p class="popup-message">
            Pastikan telah mengunduh sertifikat atau memeriksa hasil kuis.
            Setelah Anda keluar, sesi Anda tidak akan disimpan.
        </p>
        <div class="popup-actions">
            <button class="btn-no" onclick="closeExit()">Kembali</button>
            <button class="btn-yes" onclick="confirmExit()">Keluar</button>
        </div>
    </div>
</div>

<script>
let allowNavigate=false;
let allowExit=false;

history.pushState(null,"",location.href);
window.addEventListener("popstate",()=>{
    if(!allowNavigate && !allowExit){
        openExit();
        history.pushState(null,"",location.href);
    }
});

window.addEventListener("beforeunload",e=>{
    if(!allowNavigate && !allowExit){
        e.preventDefault();
        e.returnValue="";
    }
});

document.querySelectorAll("a").forEach(link=>{
    link.addEventListener("click", e => {
        if (link.classList.contains("nav-safe")) {
            allowNavigate = true;
            return;
        }
        if (!allowNavigate && !allowExit) {
            e.preventDefault();
            openExit();
        }
    });
});

function openExit(){document.getElementById("exitModal").style.display="flex"}
function closeExit(){document.getElementById("exitModal").style.display="none"}
function confirmExit(){
    allowExit = true;
    window.location.href = "daftar_kuis.php?end=1";
}

</script>

</body>
</html>
