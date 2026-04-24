<?php
session_start();
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: daftar_kuis.php");
    exit;
}

$kuis_id = (int)$_GET['id'];

$stmt = db()->prepare("SELECT id, judul, bagian FROM kuis_paket WHERE id = ?");
$stmt->execute([$kuis_id]);
$paket = $stmt->fetch();

if (!$paket) {
    header("Location: daftar_kuis.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mulai Kuis</title>

<link rel="icon" type="image/png" href="assets/LogoKPU.png">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --maroon:#700D09;
    --gold:#f4c430;
}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:#E5E8FF;
    min-height:100vh;
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
</style>

<?php
$site_title = 'Mulai Kuis | SI-NAU Demokrasi';
include 'identitas.php';
?>

</head>
<body>


<div class="modal-overlay" id="startQuizModal">
    <div class="modal-content-custom">
        <p class="popup-title">Mulai Kuis</p>
        <p class="popup-message">
            Apakah Anda yakin akan memulai kuis<br>
            <strong><?= htmlspecialchars($paket['judul']) ?></strong>?
        </p>
        <div class="popup-actions">
            <button class="btn-modal-cancel" onclick="cancelStart()">Tidak</button>
            <button class="btn-modal-action" onclick="confirmStart()">Iya</button>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    document.getElementById('startQuizModal').style.display = 'flex';
});

function cancelStart() {
    window.location.href = 'daftar_kuis.php';
}

function confirmStart() {
    window.location.href = 'start_kuis_proses.php?id=<?= $kuis_id ?>';
}
</script>

</body>
</html>