<?php
declare(strict_types=1);
session_start();
require_once 'db.php';

$activePage = 'materi';

$search = trim($_GET['search'] ?? '');
$bagian = trim($_GET['bagian'] ?? '');

$daftarBagian = [
    'Keuangan',
    'Umum dan Logistik',
    'Teknis Penyelenggara Pemilu',
    'Partisipasi Hubungan Masyarakat',
    'Hukum dan SDM',
    'Perencanaan',
    'Data dan Informasi'
];

$sql = "SELECT id, judul, bagian FROM materi WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND judul LIKE :search";
    $params['search'] = "%$search%";
}

if ($bagian !== '') {
    $sql .= " AND bagian = :bagian";
    $params['bagian'] = $bagian;
}

$sql .= " ORDER BY created_at DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$materi = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$site_title = 'SI-NAU Demokrasi | Materi';
include 'identitas.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
:root{
  --maroon:#700D09;
  --gold:#f4c430;
  --bg:#eef1ff;
}

body{
  margin:0;
  font-family:'Inter',sans-serif;
  background:var(--bg);
  min-height:100vh;
  display:flex;
  flex-direction:column;
}

main{
  flex:1;
  padding-bottom:80px;
}

.page-title{
  font-weight:800;
  color:var(--maroon);
  margin-bottom:10px;
}

.page-subtitle{
  max-width:640px;
  margin:0 auto 28px;
  color:#555;
  font-size:.95rem;
}

.form-control,
.form-select{
  color:#6c757d;
}

.form-control::placeholder{
  color:#6c757d;
  opacity:1;
}

.form-control:focus,
.form-select:focus{
  color:#6c757d;
}

.kpu-card{
  background:#fff;
  border-radius:26px;
  overflow:hidden;
  box-shadow:0 12px 28px rgba(0,0,0,.18);
  transition:.35s ease;
  height:100%;
}

.kpu-card:hover{
  transform:translateY(-10px);
  box-shadow:0 22px 45px rgba(0,0,0,.28);
}

.kpu-header{
  background:var(--maroon);
  height:190px;
  display:flex;
  align-items:center;
  justify-content:center;
  position:relative;
}

.kpu-header::after{
  content:"";
  position:absolute;
  bottom:0;
  left:0;
  width:100%;
  height:4px;
  background:linear-gradient(90deg,transparent,var(--gold),transparent);
}

.kpu-logo{
  width:96px;
  height:auto;
}

.kpu-body{
  padding:26px 26px 32px;
  background:#fff;
}

.kpu-title{
  color:var(--maroon);
  font-weight:800;
  font-size:1.1rem;
  margin-bottom:18px;
  line-height:1.4;
}

.kpu-tag{
  display:inline-block;
  background:var(--maroon);
  color:#fff;
  padding:7px 20px;
  font-size:.75rem;
  font-weight:700;
  border-radius:999px;
  text-transform:capitalize;
}

.empty-state{
  color:#777;
  font-size:.95rem;
}
</style>
</head>

<body>

<?php include 'header.php'; ?>
<div style="height:82px;"></div>

<main>
  <div class="container text-center pt-5">

    <h2 class="page-title">MATERI SINAU DEMOKRASI</h2>
    <p class="page-subtitle">
      Pelajari konsep, tahapan, dan nilai-nilai demokrasi dan kepemiluan melalui materi yang disusun ringkas dan mudah dipahami.
    </p>

    <form method="GET" id="filterForm" class="row justify-content-center g-3 mb-5">
      <div class="col-md-5">
        <div class="input-group shadow-sm">
          <span class="input-group-text bg-white">
            <i class="bi bi-search"></i>
          </span>
          <input
            type="text"
            name="search"
            class="form-control"
            placeholder="Cari judul materi..."
            value="<?= htmlspecialchars($search); ?>"
            oninput="debouncedSubmit()"
          >
        </div>
      </div>

      <div class="col-md-3">
        <select
          name="bagian"
          class="form-select shadow-sm"
          onchange="document.getElementById('filterForm').submit()"
        >
          <option value="">Semua Subbagian</option>
          <?php foreach ($daftarBagian as $b): ?>
            <option value="<?= htmlspecialchars($b); ?>" <?= $bagian === $b ? 'selected' : ''; ?>>
              <?= htmlspecialchars($b); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <?php if (count($materi) === 0): ?>
      <div class="empty-state">
        <?php if ($bagian !== ''): ?>
          Belum ada materi pada subbagian <b><?= htmlspecialchars($bagian); ?></b>.
        <?php else: ?>
          Materi tidak ditemukan.
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="row g-4 justify-content-center">
        <?php foreach ($materi as $m): ?>
          <div class="col-lg-4 col-md-6">
            <a href="materi_viewer.php?id=<?= (int)$m['id']; ?>" class="text-decoration-none">
              <div class="kpu-card">
                <div class="kpu-header">
                  <img src="Asset/LogoKPU.png" class="kpu-logo" alt="KPU">
                </div>
                <div class="kpu-body text-start">
                  <div class="kpu-title">
                    <?= htmlspecialchars($m['judul']); ?>
                  </div>
                  <span class="kpu-tag">
                    <?= htmlspecialchars($m['bagian']); ?>
                  </span>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php include 'footer.php'; ?>

<script>
let typingTimer;
const typingDelay = 600;

function debouncedSubmit(){
  clearTimeout(typingTimer);
  typingTimer = setTimeout(() => {
    document.getElementById('filterForm').submit();
  }, typingDelay);
}
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
