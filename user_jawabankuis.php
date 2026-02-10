<?php
session_start();

if (
    !isset($_SESSION['jawaban']) ||
    !isset($_SESSION['materi']) ||
    !isset($_SESSION['kuis_id'])
) {
    header("Location: user_nilaikuis.php");
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
$materi  = $_SESSION['materi'];
$jawaban = $_SESSION['jawaban'];
$bagian  = $_SESSION['materi_bagian'] ?? 'Materi Pemilu';

$stmt = db()->prepare("
    SELECT id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban
    FROM kuis_soal
    WHERE paket_id = ?
");
$stmt->execute([$paketId]);
$soal = $stmt->fetchAll();

if (!$soal) exit;

?>

<?php
$site_title = 'User | Jawaban Kuis';
include 'identitas.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
    --maroon:#700D09;
    --gold:#f4c430;
    --navbar-h:90px;
}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    background:#EEF1FF;
    padding-top:var(--navbar-h);
    padding-bottom:40px;
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
}

.brand-text{
    color:#fff;
    line-height:1.15;
}

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
    text-decoration:none;
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

.container{
    margin-top:32px;
}

.page-header{
    margin-bottom:36px;
}

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
    border-radius:20px;
    padding:26px;
    margin-bottom:24px;
}

.soal-benar{background:rgba(36,182,0,.22)}
.soal-salah{background:rgba(255,0,0,.22)}

.opsi label{
    display:block;
    margin-left:18px;
    margin-bottom:6px;
}

.opsi input[type="radio"]{
    accent-color:var(--maroon);
    margin-right:8px;
}

.opsi-benar{color:#459517;font-weight:700}
.opsi-salah{color:var(--maroon);font-weight:700}
.opsi-dipilih{font-weight:700}

.btn-selesai{
    background:#459517;
    color:#fff;
    border-radius:25px;
    padding:8px 40px;
    border:0;
    text-decoration:none;
}

.btn-selesai:hover{color:#fff}

@media(max-width:768px){
    .title{font-size:22px}
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
        <a href="user_nilaikuis.php" class="btn-logout">HASIL KUIS</a>
    </div>
</nav>

<div class="container">

    <div class="page-header">
        <div class="subtitle">Materi Edukasi Pemilu</div>
        <div class="title"><?= htmlspecialchars($materi) ?></div>
        <div class="meta-actions">
            <span class="badge-bagian"><?= htmlspecialchars($bagian) ?></span>
        </div>
    </div>

    <?php foreach ($soal as $i => $s):

        $jawabanUser  = $jawaban[$s['id']] ?? null;
        $jawabanBenar = strtoupper($s['jawaban']);
        $benar = ($jawabanUser === $jawabanBenar);

        $opsi = [
            'A' => $s['opsi_a'],
            'B' => $s['opsi_b'],
            'C' => $s['opsi_c'],
            'D' => $s['opsi_d']
        ];
    ?>

    <div class="soal-card <?= $benar ? 'soal-benar' : 'soal-salah' ?>">
        <p>
            <strong><?= $i + 1 ?>.</strong>
            <?= htmlspecialchars($s['pertanyaan']) ?>
        </p>

        <div class="opsi">
            <?php foreach ($opsi as $kode => $teks):

                $class = [];
                if ($kode === $jawabanBenar) $class[] = 'opsi-benar';
                elseif ($kode === $jawabanUser && !$benar) $class[] = 'opsi-salah';
                if ($kode === $jawabanUser) $class[] = 'opsi-dipilih';
            ?>
            <label class="<?= implode(' ', $class) ?>">
                <input type="radio" disabled <?= ($kode === $jawabanUser) ? 'checked' : '' ?>>
                <?= htmlspecialchars($teks) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <?php endforeach; ?>

    <div class="d-flex justify-content-end mt-5">
        <a href="user_nilaikuis.php" class="btn-selesai">Selesai</a>
    </div>

</div>

</body>
</html>
