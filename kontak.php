<?php
declare(strict_types=1);
session_start();
$activePage = 'kontak';
?>

<?php
$site_title = 'SI-NAU Demokrasi | Kontak';
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
  --bg:#eef1ff;
}

body{
  margin:0;
  font-family:'Inter',sans-serif;
  background:var(--bg);
  min-height:100vh;
}

.content-wrapper{
  padding-top:120px;
  padding-bottom:80px;
}

.contact-card{
  max-width:720px;
  margin:auto;
  background:#fff;
  padding:36px 34px;
  border-radius:18px;
  box-shadow:0 6px 22px rgba(0,0,0,.1);
}

.contact-title{
  color:var(--maroon);
  font-weight:800;
  margin-bottom:28px;
}

.contact-item{
  display:flex;
  align-items:center;
  gap:14px;
  font-size:15px;
  margin-bottom:18px;
}

.contact-item i{
  font-size:22px;
  color:var(--maroon);
}

.contact-item a{
  color:inherit;
}

.btn-back{
  display:inline-flex;
  align-items:center;
  gap:8px;
  background:var(--maroon);
  color:#fff;
  font-size:14px;
  font-weight:600;
  padding:8px 20px;
  border-radius:999px;
  text-decoration:none;
  margin-top:24px;
  box-shadow:0 6px 10px rgba(0,0,0,.15);
  transition:.25s;
}

.btn-back:hover{
  background:#5b0a07;
  color:#fff;
}

@media(max-width:576px){
  .contact-card{
    padding:28px 22px;
  }
}
</style>
</head>

<body>

<?php include 'header.php'; ?>

<main class="content-wrapper">
  <div class="container">
    <div class="contact-card">

      <h2 class="contact-title">Kontak Kami</h2>

      <div class="contact-item">
        <a href="https://maps.app.goo.gl/fVTgKMD9LTj3JsmRA"
           target="_blank" rel="noopener">
          <i class="bi bi-geo-alt-fill"></i>
        </a>
        <span>Komisi Pemilihan Umum Daerah Istimewa Yogyakarta</span>
      </div>

      <div class="contact-item">
        <a href="mailto:diy@kpu.go.id">
          <i class="bi bi-envelope-fill"></i>
        </a>
        <span>diy@kpu.go.id</span>
      </div>

      <div class="contact-item">
        <a href="tel:+62274558006">
          <i class="bi bi-telephone-fill"></i>
        </a>
        <span>(0274) 558006</span>
      </div>

      <div class="contact-item">
        <a href="https://wa.me/6281911301775"
           target="_blank" rel="noopener">
          <i class="bi bi-whatsapp"></i>
        </a>
        <span>0819 1130 1775</span>
      </div>

      <div class="contact-item">
        <a href="https://diy.kpu.go.id"
           target="_blank" rel="noopener">
          <i class="bi bi-globe2"></i>
        </a>
        <span>https://diy.kpu.go.id</span>
      </div>

      <a href="dashboard.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>
        Kembali
      </a>

    </div>
  </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
