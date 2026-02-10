<?php
declare(strict_types=1);
session_start();
require_once 'db.php';
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
        :root { --maroon:#700D09; }

        body {
            margin:0;
            padding-top:82px;
            font-family:'Inter',sans-serif;
            background:#eef1ff;
        }

        .hero-section { background:var(--maroon); overflow:hidden; }
        .carousel-inner { aspect-ratio:1600/366; }
        .hero-image { width:100%; height:100%; object-fit:cover; }

        .section-title { color:var(--maroon); font-weight:800; }

.info-box{
  background:#fff;
  padding:24px 28px;
  border-radius:14px;
  max-width:730px;
  margin:auto;
  box-shadow:
    0 4px 14px rgba(0,0,0,.08),
    0 -4px 0 rgba(112, 13, 9, 0.85),
    0 -8px 18px rgba(244, 58, 48, 0.25);
}


        .alur-card {
            background:var(--maroon);
            color:#fff;
            padding:24px 16px;
            border-radius:14px;
            text-align:center;
            transition:.3s;
            height:100%;
            box-shadow: 0 8px 18px rgba(0,0,0,.18);
        }

        .alur-card:hover {
            transform:translateY(-5px);
            box-shadow:
                0 0 0 2px rgba(244,196,48,.85),
                0 0 18px rgba(244,196,48,.6),
                0 12px 28px rgba(0,0,0,.25);
        }

        .feature-card {
            background:#fff;
            padding:26px;
            border-radius:16px;
            text-align:center;
            transition:.3s;
            box-shadow:0 4px 14px rgba(0,0,0,.08);
        }

        .feature-card:hover {
            transform:translateY(-5px);
            box-shadow:0 10px 25px rgba(0,0,0,.2);
            box-shadow:
        }

        .btn-maroon {
            background:var(--maroon);
            color:#fff;
            border-radius:18px;
            font-size:.9rem;
            padding:6px 18px;
        }

        .btn-maroon:hover { 
            background: #fff;; 
            color: var(--maroon);
            box-shadow:
            0 0 0 2px rgba(112,13,9,.85),
            0 10px 25px rgba(0,0,0,.12);    
        }

        footer {
            background:var(--maroon);
            color:#fff;
            text-align:center;
            padding:32px 0;
            margin-top:60px;
        }
    </style>

<?php
$site_title = 'SI-NAU Demokrasi | KPU DIY';
include 'identitas.php';
?>

</head>

<body>

<?php include 'header.php'; ?>

<section class="hero-section">
    <div id="heroCarousel" class="carousel slide carousel-fade"
         data-bs-ride="carousel" data-bs-interval="4000">
        <div class="carousel-inner">
            <?php
            $covers = glob('Asset/cover/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            $active = 'active';
            foreach ($covers as $c) {
                echo '
                <div class="carousel-item '.$active.'">
                    <img src="'.$c.'" class="hero-image">
                </div>';
                $active = '';
            }
            ?>
        </div>
    </div>
</section>

<section class="py-5 text-center">
    <div class="info-box fs-5 mt-4">
        SI-NAU Demokrasi merupakan media edukasi demokrasi dan kepemiluan berbasis website
        untuk membantu masyarakat memahami proses dan nilai-nilai demokrasi serta kepemiluan
        secara sederhana dan interaktif.
    </div>
</section>

<section class="container py-5">
    <h3 class="section-title text-center mb-5 fs-1">Alur Pelaksanaan</h3>
    <div class="row g-4">
        <div class="col-md-3"><div class="alur-card"><div class="h-100 d-flex flex-column justify-content-center align-items-center"><i class="bi bi-book fs-1 mb-2"></i><p class="mb-0">Pilih dan Baca Materi</p></div></div></div>
        <div class="col-md-3"><div class="alur-card"><div class="h-100 d-flex flex-column justify-content-center align-items-center"><i class="bi bi-ui-checks fs-1 mb-2"></i><p class="mb-0">Ikuti Kuis yang Tersedia</p></div></div></div>
        <div class="col-md-3"><div class="alur-card"><div class="h-100 d-flex flex-column justify-content-center align-items-center"><i class="bi bi-bar-chart fs-1 mb-2"></i><p class="mb-0">Lihat Hasil Kuis</p></div></div></div>
        <div class="col-md-3"><div class="alur-card"><div class="h-100 d-flex flex-column justify-content-center align-items-center"><i class="bi bi-award fs-1 mb-2"></i><p class="mb-0">Unduh Sertifikat</p></div></div></div>
    </div>
</section>

<section class="container py-5 text-center">
    <h3 class="section-title mb-4">Ayo, belajar Demokrasi dan Kepemiluan Lebih Dekat!</h3>
    <div class="row justify-content-center g-4">
        <div class="col-md-4"><div class="feature-card"><h5 class="fw-bold">Materi</h5><p class="text-muted small">Materi tentang demokrasi dan kepemiluan terstruktur.</p><a class="btn btn-maroon" href="daftar_materi.php">Baca</a></div></div>
        <div class="col-md-4"><div class="feature-card"><h5 class="fw-bold">Kuis</h5><p class="text-muted small">Uji pemahaman demokrasi dan kepemiluan Anda.</p><a class="btn btn-maroon" href="daftar_kuis.php">Coba</a></div></div>
    </div>
</section>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
