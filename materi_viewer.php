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
$materi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$materi) {
    header("Location: daftar_materi.php");
    exit;
}

$stmt = db()->prepare("SELECT file_path FROM materi_media WHERE materi_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
$stmt->execute([$materiId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

$tipe = strtolower(trim((string)($materi['tipe'] ?? '')));
$pdfPath = '';
$videoPath = '';
$viewerMode = '';

if ($tipe === 'pdf') {
    if (!$file || empty($file['file_path'])) {
        header("Location: daftar_materi.php");
        exit;
    }

    $pdfPath = "uploads/materi/" . $file['file_path'];
    $viewerMode = 'pdf';
} elseif ($tipe === 'video') {
    if (!$file || empty($file['file_path'])) {
        header("Location: daftar_materi.php");
        exit;
    }

    $videoPath = "uploads/materi/" . $file['file_path'];
    $viewerMode = 'video';
} else {
    header("Location: daftar_materi.php");
    exit;
}
?>

<?php include 'identitas.php'; ?>

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
    --bg:#eef1ff;
    --navbar-h:90px;
}

*{
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

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
    border-bottom:1px solid rgba(0,0,0,.2);
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

.brand-text strong{
    font-size:.95rem;
    font-weight:700;
}

.brand-text span{
    font-size:.85rem;
}

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
    border:0;
}

.page{
    max-width:980px;
    margin:auto;
    padding:30px 16px 42px;
}

.subtitle{
    font-size:.8rem;
    color:#666;
    margin-bottom:4px;
}

.title{
    font-size:30px;
    font-weight:800;
    color:var(--maroon);
    line-height:1.2;
    margin:0 0 12px;
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
    padding:7px 16px;
    border-radius:999px;
}

.btn-download,
.btn-fullscreen{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:var(--maroon);
    color:#fff;
    font-size:12px;
    font-weight:700;
    padding:7px 14px;
    border-radius:999px;
    text-decoration:none;
    border:0;
}

.viewer{
    margin-top:18px;
}

.frame-box{
    width:100%;
    max-width:760px;
    margin:0 auto;
    background:#fff;
    border:1px solid #dfe3eb;
    border-radius:16px;
    padding:14px;
}

.canvas-wrap{
    width:100%;
    display:flex;
    justify-content:center;
    overflow:auto;
    background:#f5f7fb;
    border:1px solid #e5e9f0;
    border-radius:12px;
    padding:12px;
}

canvas{
    width:100%;
    height:auto;
    display:block;
    border-radius:8px;
    background:#fff;
}

.video-wrap{
    width:100%;
    background:#111;
    border-radius:12px;
    padding:10px;
}

.video-player{
    width:100%;
    max-height:54vh;
    display:block;
    border-radius:8px;
    background:#000;
}

.controls{
    margin-top:14px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
}

.nav-btn{
    width:38px;
    height:38px;
    border-radius:50%;
    background:var(--maroon);
    color:#fff;
    border:none;
    font-size:16px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.indicator{
    font-size:14px;
    font-weight:700;
    color:#555;
    min-width:70px;
    text-align:center;
}

.footer-note{
    margin-top:18px;
    text-align:center;
    font-size:13px;
    color:#777;
}

.fullscreen-pdf{
    position:fixed;
    inset:0;
    background:#f3f5fb;
    z-index:3000;
    display:none;
    overflow-y:auto;
}

.fullscreen-pdf.active{
    display:block;
}

.fullscreen-stickybar{
    position:sticky;
    top:0;
    z-index:10;
    height:70px;
    background:rgba(255,255,255,.92);
    border-bottom:1px solid rgba(0,0,0,.06);
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 20px;
}

.fullscreen-logo{
    height:34px;
    width:auto;
    display:block;
}

.fullscreen-close{
    width:42px;
    height:42px;
    border-radius:12px;
    border:0;
    background:rgba(112,13,9,.12);
    color:var(--maroon);
    font-size:20px;
    display:flex;
    align-items:center;
    justify-content:center;
}

.fullscreen-body{
    max-width:980px;
    margin:0 auto;
    padding:22px 18px 34px;
}

.fullscreen-pages{
    display:flex;
    flex-direction:column;
    gap:22px;
}

.fullscreen-page{
    width:100%;
    display:flex;
    justify-content:center;
}

.fullscreen-page canvas{
    width:100%;
    max-width:820px;
    height:auto;
    background:#fff;
    border-radius:12px;
    box-shadow:0 12px 28px rgba(0,0,0,.08);
}

.fullscreen-loader{
    text-align:center;
    color:#666;
    font-size:14px;
    padding:28px 0 10px;
}

@media(max-width:768px){
    .title{
        font-size:22px;
    }

    .page{
        padding:26px 12px 34px;
    }

    .frame-box{
        border-radius:12px;
        padding:10px;
    }

    .canvas-wrap{
        padding:8px;
        border-radius:10px;
    }

    .video-wrap{
        padding:8px;
        border-radius:10px;
    }

    .video-player{
        max-height:42vh;
    }

    .fullscreen-stickybar{
        height:64px;
        padding:0 14px;
    }

    .fullscreen-body{
        padding:14px 10px 24px;
    }
}
</style>
</head>

<body>

<nav class="navbar-kpu">
    <div class="inner">
        <a href="dashboard.php" class="brand">
            <img src="Asset/LogoKPU.png" height="36" alt="KPU">
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
        <?php if ($viewerMode === 'pdf'): ?>
            <a href="<?= htmlspecialchars($pdfPath) ?>" download class="btn-download">
                <i class="bi bi-download"></i> PDF
            </a>
            <button type="button" class="btn-fullscreen" id="openFullscreenPdf">
                <i class="bi bi-arrows-fullscreen"></i> Fullscreen
            </button>
        <?php endif; ?>
    </div>

    <div class="viewer">
        <div class="frame-box">
            <?php if ($viewerMode === 'pdf'): ?>
                <div class="canvas-wrap">
                    <canvas id="pdfCanvas"></canvas>
                </div>
                <div class="controls">
                    <button class="nav-btn" id="prevBtn" type="button"><i class="bi bi-chevron-left"></i></button>
                    <div class="indicator" id="pageInfo">1 / 1</div>
                    <button class="nav-btn" id="nextBtn" type="button"><i class="bi bi-chevron-right"></i></button>
                </div>
            <?php elseif ($viewerMode === 'video'): ?>
                <div class="video-wrap">
                    <video class="video-player" controls playsinline preload="metadata">
                        <source src="<?= htmlspecialchars($videoPath) ?>">
                    </video>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-note">
        © <?= date('Y') ?> Komisi Pemilihan Umum Daerah Istimewa Yogyakarta
    </div>
</main>

<?php if ($viewerMode === 'pdf'): ?>
<div class="fullscreen-pdf" id="fullscreenPdfModal">
    <div class="fullscreen-stickybar">
        <img src="Asset/LogoKPU.png" alt="KPU" class="fullscreen-logo">
        <button type="button" class="fullscreen-close" id="closeFullscreenPdf">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="fullscreen-body">
        <div class="fullscreen-loader" id="fullscreenLoader">Memuat halaman PDF...</div>
        <div class="fullscreen-pages" id="fullscreenPages"></div>
    </div>
</div>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

const pdfUrl = <?= json_encode($pdfPath) ?>;
const canvas = document.getElementById("pdfCanvas");
const ctx = canvas.getContext("2d");
const pageInfo = document.getElementById("pageInfo");
const prevBtn = document.getElementById("prevBtn");
const nextBtn = document.getElementById("nextBtn");
const openFullscreenPdf = document.getElementById("openFullscreenPdf");
const closeFullscreenPdf = document.getElementById("closeFullscreenPdf");
const fullscreenPdfModal = document.getElementById("fullscreenPdfModal");
const fullscreenPages = document.getElementById("fullscreenPages");
const fullscreenLoader = document.getElementById("fullscreenLoader");

let pdfDoc = null;
let pageNum = 1;
let totalPages = 1;
let fullscreenRendered = false;

pdfjsLib.getDocument(pdfUrl).promise.then(pdf => {
    pdfDoc = pdf;
    totalPages = pdf.numPages;
    renderPage(pageNum);
});

function getNormalScale(page){
    const wrap = document.querySelector('.canvas-wrap');
    const viewport = page.getViewport({ scale: 1 });
    const availableWidth = Math.max((wrap.clientWidth || 640) - 24, 240);
    return availableWidth / viewport.width;
}

function renderNormal(n){
    return pdfDoc.getPage(n).then(page => {
        const scale = getNormalScale(page);
        const viewport = page.getViewport({ scale });
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        return page.render({
            canvasContext: ctx,
            viewport: viewport
        }).promise;
    });
}

function updateIndicators(){
    pageInfo.textContent = pageNum + " / " + totalPages;
}

function renderPage(n){
    updateIndicators();
    renderNormal(n);
}

async function renderAllFullscreenPages(){
    if (fullscreenRendered) return;

    fullscreenPages.innerHTML = '';

    for (let i = 1; i <= totalPages; i++) {
        const page = await pdfDoc.getPage(i);
        const viewportBase = page.getViewport({ scale: 1 });
        const wrapper = document.createElement('div');
        wrapper.className = 'fullscreen-page';

        const pageCanvas = document.createElement('canvas');
        const containerWidth = Math.min(window.innerWidth - 48, 820);
        const scale = containerWidth / viewportBase.width;
        const viewport = page.getViewport({ scale });

        pageCanvas.width = viewport.width;
        pageCanvas.height = viewport.height;

        wrapper.appendChild(pageCanvas);
        fullscreenPages.appendChild(wrapper);

        await page.render({
            canvasContext: pageCanvas.getContext('2d'),
            viewport: viewport
        }).promise;
    }

    fullscreenRendered = true;
    fullscreenLoader.style.display = 'none';
}

async function openFullscreenMode(){
    fullscreenPdfModal.classList.add('active');
    document.body.style.overflow = 'hidden';
    fullscreenLoader.style.display = 'block';
    await renderAllFullscreenPages();
}

function closeFullscreenMode(){
    fullscreenPdfModal.classList.remove('active');
    document.body.style.overflow = '';
}

prevBtn.onclick = () => {
    if (pageNum > 1) {
        pageNum--;
        renderPage(pageNum);
    }
};

nextBtn.onclick = () => {
    if (pageNum < totalPages) {
        pageNum++;
        renderPage(pageNum);
    }
};

openFullscreenPdf.onclick = openFullscreenMode;
closeFullscreenPdf.onclick = closeFullscreenMode;

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && fullscreenPdfModal.classList.contains('active')) {
        closeFullscreenMode();
    }
});

window.addEventListener('resize', () => {
    if (pdfDoc) {
        renderPage(pageNum);
    }
});
</script>
<?php endif; ?>

</body>
</html>