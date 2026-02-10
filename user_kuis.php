<?php
session_start();


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$activePage = 'kuis';


if (
    !isset($_SESSION['flow_step']) ||
    !in_array($_SESSION['flow_step'], ['BIODATA_OK', 'IN_KUIS'])
) {
    session_unset();
    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}


$_SESSION['flow_step'] = 'IN_KUIS';


if (!isset($_SESSION['kuis_id'], $_SESSION['nama'])) {
    session_unset();
    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}


function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    return $pdo = new PDO(
        "mysql:host=localhost;dbname=sinau_pemilu;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

$paketId = (int) $_SESSION['kuis_id'];


if (!isset($_SESSION['kuis_mulai'])) {
    $_SESSION['kuis_mulai'] = true;
    $_SESSION['jawaban'] = [];
    unset($_SESSION['soal_acak_' . $paketId]);
}


$stmt = db()->prepare("SELECT judul FROM kuis_paket WHERE id = ?");
$stmt->execute([$paketId]);
$paket = $stmt->fetch();

if (!$paket) {
    session_unset();
    session_destroy();
    header("Location: daftar_kuis.php");
    exit;
}

$sessionKey = 'soal_acak_' . $paketId;

if (!isset($_SESSION[$sessionKey])) {
    $stmt = db()->prepare("
        SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban
        FROM kuis_soal
        WHERE paket_id = ?
    ");
    $stmt->execute([$paketId]);
    $soalAsli = $stmt->fetchAll();

    shuffle($soalAsli);

    foreach ($soalAsli as &$s) {
        $opsi = [
            'A' => $s['opsi_a'],
            'B' => $s['opsi_b'],
            'C' => $s['opsi_c'],
            'D' => $s['opsi_d']
        ];
        $keys = array_keys($opsi);
        shuffle($keys);
        $s['opsi_acak'] = [];
        foreach ($keys as $k) $s['opsi_acak'][$k] = $opsi[$k];
    }
    unset($s);

    $_SESSION[$sessionKey] = $soalAsli;
}

$soal = $_SESSION[$sessionKey];
$totalSoal = count($soal);


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['soal_id'], $_POST['jawaban'])) {
        $_SESSION['jawaban'][(int)$_POST['soal_id']] = $_POST['jawaban'];
    }

    if (isset($_POST['submit_kuis'])) {

        $benar = 0;
        foreach ($soal as $s) {
            $jawab = $_SESSION['jawaban'][$s['id']] ?? '';
            if (strtoupper($jawab) === strtoupper($s['jawaban'])) {
                $benar++;
            }
        }

        $_SESSION['skor']   = round(($benar / $totalSoal) * 100);
        $_SESSION['materi'] = $paket['judul'];

        $_SESSION['flow_step'] = 'SELESAI';

        unset($_SESSION['kuis_mulai'], $_SESSION[$sessionKey]);

        header("Location: user_nilaikuis.php");
        exit;
    }
}


$index = isset($_POST['target_index']) ? (int)$_POST['target_index'] : 0;
$index = max(0, min($index, $totalSoal - 1));
$curSoal = $soal[$index];
$jawabanTersimpan = $_SESSION['jawaban'] ?? [];
$bagian = $_SESSION['materi_bagian'] ?? 'Materi Pemilu';
?>

<?php
$site_title = 'User | Kuis';
include 'identitas.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --maroon:#700D09;
    --gold:#f4c430;
    --navbar-h:90px;
}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:#E5E8FF;
    padding-top:var(--navbar-h);
    padding-bottom:50px;
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
}

.brand-text{color:#fff;line-height:1.15}
.brand-text strong{font-size:.95rem;font-weight:700}
.brand-text span{font-size:.85rem}

.btn-logout{
    border:0;
    background:transparent;
    color:#fff;
    font-weight:600;
    font-size:.85rem;
    position:relative;
    padding:0;
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

.container{margin-top:32px}

.page-header{margin-bottom:36px}

.subtitle{
    font-size:.8rem;
    color:#6c757d;
    margin-bottom:6px;
}

.title{
    font-size:32px;
    font-weight:800;
    color:var(--maroon);
    line-height:1.25;
    margin-bottom:12px;
}

.meta-actions{
    display:flex;
    align-items:center;
    gap:12px;
}

.badge-bagian{
    background:rgba(112,13,9,.08);
    color:var(--maroon);
    font-size:.75rem;
    font-weight:700;
    padding:6px 16px;
    border-radius:999px;
}

.soal-card{
    background:#fff;
    border-radius:20px;
    padding:30px;
    box-shadow:0 8px 20px rgba(0,0,0,.2);
}

.opsi-container{
    display:flex;
    flex-direction:column;
    gap:10px;
    margin-top:20px;
}

.opsi-item input{accent-color:var(--maroon)}

.nav-soal{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    justify-content:center;
    margin-top:25px;
}

.nav-item{
    width:36px;
    height:36px;
    border-radius:50%;
    border:2px solid var(--maroon);
    color:var(--maroon);
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
}

.nav-item.answered{background:var(--maroon);color:#fff}
.nav-item.active{background:rgba(112,13,9,.2)}

.btn-custom{
    border-radius:25px;
    padding:8px 30px;
    border:none;
    color:#fff;
}

.btn-prev{background:rgba(112,13,9,.8)}
.btn-next{background:var(--maroon)}
.btn-submit{background:#459517}

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
</style>
</head>

<body>

<nav class="navbar-kpu">
    <div class="inner">
        <div class="brand">
            <img src="Asset/LogoKPU.png" height="36">
            <div class="brand-text">
                <strong>KPU</strong><br>
                <span>DIY</span>
            </div>
        </div>
        <button type="button" class="btn-logout" onclick="openCancelModal()">BATALKAN KUIS</button>
    </div>
</nav>

<div class="container">
    <div class="page-header">
        <div class="subtitle">Materi Edukasi Demokrasi dan Kepemiluan</div>
        <div class="title"><?= htmlspecialchars($paket['judul']) ?></div>
        <div class="meta-actions">
            <span class="badge-bagian"><?= htmlspecialchars($bagian) ?></span>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-9">

            <div class="soal-card">
                <form method="post" id="quizForm">
                    <input type="hidden" name="target_index" id="target_index" value="<?= $index ?>">
                    <input type="hidden" name="soal_id" value="<?= $curSoal['id'] ?>">

                    <p><b><?= $index + 1 ?>.</b> <?= htmlspecialchars($curSoal['pertanyaan']) ?></p>

                    <div class="opsi-container">
                        <?php foreach ($curSoal['opsi_acak'] as $k => $v): ?>
                            <label class="opsi-item">
                                <input type="radio" name="jawaban" value="<?= $k ?>"
                                    <?= (($jawabanTersimpan[$curSoal['id']] ?? '') === $k) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($v) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <?php if ($index > 0): ?>
                            <button type="button" class="btn-custom btn-prev" onclick="navigate(<?= $index - 1 ?>)">Sebelumnya</button>
                        <?php else: ?><div></div><?php endif; ?>

                        <?php if ($index < $totalSoal - 1): ?>
                            <button type="button" class="btn-custom btn-next" onclick="navigate(<?= $index + 1 ?>)">Selanjutnya</button>
                        <?php else: ?>
                            <button type="button" class="btn-custom btn-submit" onclick="attemptSubmit()">Kirim</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="nav-soal">
                <?php foreach ($soal as $i => $s): ?>
                    <div class="nav-item <?= isset($jawabanTersimpan[$s['id']]) ? 'answered' : '' ?> <?= ($i === $index) ? 'active' : '' ?>"
                         onclick="navigate(<?= $i ?>)">
                        <?= $i + 1 ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>
</div>

<div class="modal-overlay" id="cancelQuizModal">
    <div class="modal-content-custom">
        <p class="popup-title">Konfirmasi</p>
        <p class="popup-message">Apakah Anda yakin akan membatalkan Kuis?</p>
        <div class="popup-actions">
            <button class="btn-modal-cancel" onclick="closeModal('cancelQuizModal')">Tidak</button>
            <button class="btn-modal-action" onclick="confirmCancelQuiz()">Iya</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="incompleteModal">
    <div class="modal-content-custom">
        <p class="popup-title">Perhatian</p>
        <p class="popup-message">Jawaban tidak dapat dikirim. Pastikan Anda telah menjawab seluruh soal.</p>
        <div class="popup-actions">
            <button class="btn-modal-action" onclick="closeModal('incompleteModal')">Kembali</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="confirmSubmitModal">
    <div class="modal-content-custom">
        <p class="popup-title">Konfirmasi</p>
        <p class="popup-message">Apakah Anda yakin akan mengakhiri Kuis? Pastikan kembali jawaban Anda.</p>
        <div class="popup-actions">
            <button class="btn-modal-cancel" onclick="closeModal('confirmSubmitModal')">Tidak</button>
            <button class="btn-modal-action" onclick="finalSubmit()">Iya</button>
        </div>
    </div>
</div>

<script>
let isNavigatingInternal=false;
const totalSoal=<?= $totalSoal ?>;

function navigate(i){
    if(i<0||i>=totalSoal)return;
    isNavigatingInternal=true;
    document.getElementById('target_index').value=i;
    document.getElementById('quizForm').submit();
}

function attemptSubmit(){
    const answered=<?= count($jawabanTersimpan) ?>;
    const currentAnswered=<?= isset($jawabanTersimpan[$curSoal['id']])?'true':'false' ?>;
    const radio=document.querySelector('input[name="jawaban"]:checked');
    let total=answered;
    if(!currentAnswered&&radio)total++;
    
    if(total<totalSoal)document.getElementById('incompleteModal').style.display='flex';
    else document.getElementById('confirmSubmitModal').style.display='flex';
}

function finalSubmit(){
    isNavigatingInternal=true;
    const f=document.getElementById('quizForm');
    const h=document.createElement('input');
    h.type='hidden';h.name='submit_kuis';h.value='1';
    f.appendChild(h);f.submit();
}

function openCancelModal(){
    document.getElementById('cancelQuizModal').style.display='flex';
}

function closeModal(id){
    document.getElementById(id).style.display='none';
}

function confirmCancelQuiz(){
    isNavigatingInternal=true;
    window.location.href='daftar_kuis.php';
}

history.pushState(null,null,location.href);
window.onpopstate=()=>{if(!isNavigatingInternal){openCancelModal();history.pushState(null,null,location.href)}};
window.onbeforeunload=e=>{if(!isNavigatingInternal){e.preventDefault();return""}};
</script>

</body>
</html>