<?php
declare(strict_types=1);
session_start();
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: daftar_materi.php");
    exit;
}

$materiId = (int) $_GET['id'];

$stmt = db()->prepare("SELECT * FROM materi WHERE id = ?");
$stmt->execute([$materiId]);
$materi = $stmt->fetch();

if (!$materi || $materi['tipe'] !== 'pdf') {
    header("Location: daftar_materi.php");
    exit;
}

$stmt = db()->prepare("SELECT file_path FROM materi_media WHERE materi_id = ? LIMIT 1");
$stmt->execute([$materiId]);
$file = $stmt->fetch();

if (!$file) {
    header("Location: daftar_materi.php");
    exit;
}

$pdfPath = "uploads/materi/" . $file['file_path'];
?>

  <?php
    include 'identitas.php';
  ?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<style>
:root{
    --maroon:#700D09;
    --gold:#f4c430;
    --bg:#eef1ff;
    --navbar-h:90px;
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:var(--bg);
    color:#222;
    padding-top:var(--navbar-h);
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
}

.brand{
    display:flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
}

.brand-text{
    color:#fff;
    line-height:1.05;
}

.brand-text strong{font-size:.95rem;font-weight:700}
.brand-text span{font-size:.85rem}

.btn-logout{
    width:42px;
    height:42px;
    border-radius:12px;
    background:rgba(255,255,255,.15);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    text-decoration:none;
}

.page{
    max-width:1200px;
    margin:auto;
    padding:40px 20px 60px;
}

.subtitle{
    font-size:.8rem;
    color:#666;
}

.title{
    font-size:32px;
    font-weight:800;
    color:var(--maroon);
    line-height:1.2;
    margin:6px 0 10px;
}

.meta-actions{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.badge-bagian{
    background:rgba(112,13,9,.08);
    color:var(--maroon);
    font-size:.75rem;
    font-weight:700;
    padding:6px 16px;
    border-radius:999px;
}

.btn-download{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:var(--maroon);
    color:#fff;
    font-size:12px;
    font-weight:700;
    padding:6px 14px;
    border-radius:999px;
    text-decoration:none;
}

.viewer{
    margin-top:22px;
    background:#fff;
    border-radius:26px;
    padding:18px;
    box-shadow:0 30px 60px rgba(0,0,0,.18);
}

.canvas-wrap{
    background:#111;
    border-radius:16px;
    padding:8px;
    display:flex;
    justify-content:center;
}

canvas{
    max-width:100%;
    border-radius:14px;
    background:#111;
}

.controls{
    margin-top:18px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:20px;
}

.nav-btn{
    width:40px;
    height:40px;
    border-radius:50%;
    background:var(--maroon);
    color:#fff;
    border:none;
    font-size:18px;
}

.indicator{
    font-size:14px;
    font-weight:600;
    color:#555;
}

.footer-note{
    margin-top:22px;
    text-align:center;
    font-size:13px;
    color:#777;
}

@media(max-width:768px){
    .title{font-size:22px}
}
</style>
</head>

<body>

<nav class="navbar-kpu">
    <div class="inner">
        <a href="dashboard.php" class="brand">
            <img src="Asset/LogoKPU.png" height="36">
            <div class="brand-text">
                <strong>KPU</strong><br>
                <span>DIY</span>
            </div>
        </a>
        <a href="javascript:history.back()" class="btn-logout">
            <i class="bi bi-x-lg"></i>
        </a>
    </div>
</nav>

<main class="page">
    <div class="subtitle">Materi Edukasi Demokrasi dan Kepemiluan</div>
    <div class="title"><?= htmlspecialchars($materi['judul']) ?></div>

    <div class="meta-actions">
        <span class="badge-bagian"><?= htmlspecialchars($materi['bagian']) ?></span>
        <a href="<?= $pdfPath ?>" download class="btn-download">
            <i class="bi bi-download"></i> PDF
        </a>
    </div>

    <div class="viewer">
        <div class="canvas-wrap">
            <canvas id="pdfCanvas"></canvas>
        </div>
        <div class="controls">
            <button class="nav-btn" id="prevBtn"><i class="bi bi-chevron-left"></i></button>
            <div class="indicator" id="pageInfo">1 / 1</div>
            <button class="nav-btn" id="nextBtn"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>

    <div class="footer-note">
        © <?= date('Y') ?> Komisi Pemilihan Umum Daerah Istimewa Yogyakarta
    </div>
</main>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
const pdfUrl="<?= $pdfPath ?>";
const canvas=document.getElementById("pdfCanvas");
const ctx=canvas.getContext("2d");
let pdfDoc=null,pageNum=1,totalPages=1,scale=1.25;
const pageInfo=document.getElementById("pageInfo");

pdfjsLib.getDocument(pdfUrl).promise.then(pdf=>{
    pdfDoc=pdf;
    totalPages=pdf.numPages;
    renderPage(pageNum);
});

function renderPage(n){
    pdfDoc.getPage(n).then(p=>{
        const v=p.getViewport({scale});
        canvas.width=v.width;
        canvas.height=v.height;
        p.render({canvasContext:ctx,viewport:v});
        pageInfo.textContent=n+" / "+totalPages;
    });
}

prevBtn.onclick=()=>{if(pageNum>1){pageNum--;renderPage(pageNum)}};
nextBtn.onclick=()=>{if(pageNum<totalPages){pageNum++;renderPage(pageNum)}};
</script>

</body>
</html>
