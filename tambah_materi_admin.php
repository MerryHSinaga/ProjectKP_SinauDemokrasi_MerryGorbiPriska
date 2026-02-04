<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION["admin"])) {
  header("Location: login_admin.php");
  exit;
}

require_once 'db.php';

if (!isset($UPLOAD_DIR) || !is_string($UPLOAD_DIR) || trim($UPLOAD_DIR) === "") {
  $UPLOAD_DIR = __DIR__ . "/uploads";
}
$UPLOAD_DIR = rtrim($UPLOAD_DIR, "/");

if (!is_dir($UPLOAD_DIR)) {
  @mkdir($UPLOAD_DIR, 0775, true);
}
if (!is_dir($UPLOAD_DIR) || !is_writable($UPLOAD_DIR)) {
}

$BAGIAN_OPTIONS = [
  'Keuangan',
  'Umum dan Logistik',
  'Teknis Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat',
  'Hukum dan SDM',
  'Perencanaan',
  'Data dan Informasi'
];
$DEFAULT_BAGIAN = 'Umum dan Logistik';

try {
  db()->exec("
    ALTER TABLE materi
    ADD COLUMN bagian ENUM(
      'Keuangan',
      'Umum dan Logistik',
      'Teknis Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat',
      'Hukum dan SDM',
      'Perencanaan',
      'Data dan Informasi'
    ) NOT NULL DEFAULT 'Umum dan Logistik'
    AFTER judul
  ");
} catch (Throwable $e) {
}

function safe_name(string $ext): string {
  $ext = strtolower($ext);
  return "materi_" . date("Ymd_His") . "_" . bin2hex(random_bytes(6)) . "." . $ext;
}

function count_pdf_pages(string $pdfPath): int {
  $pdfinfo = @shell_exec("pdfinfo " . escapeshellarg($pdfPath) . " 2>/dev/null");
  if (is_string($pdfinfo) && $pdfinfo !== "" && preg_match('/Pages:\s+(\d+)/i', $pdfinfo, $m)) {
    return (int)$m[1];
  }

  $content = @file_get_contents($pdfPath);
  if (is_string($content) && $content !== "") {
    $n = preg_match_all("/\/Type\s*\/Page\b/", $content);
    if ($n > 0) return (int)$n;
  }
  return 1;
}

function validate_judul_or_throw(string $judul): void {
  $len = mb_strlen($judul, 'UTF-8');
  if ($len > 45) {
    throw new RuntimeException("Judul maksimal 45 karakter (termasuk spasi).");
  }

  if (!preg_match('/^[A-Za-z0-9\.\,\:\?\s]+$/', $judul)) {
    throw new RuntimeException("Judul hanya boleh berisi huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).");
  }
}

function upload_error_to_message(int $code): string {
  return match ($code) {
    UPLOAD_ERR_OK => "",
    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Ukuran file terlalu besar (maks 500KB).",
    UPLOAD_ERR_PARTIAL => "Upload gagal (file terunggah sebagian).",
    UPLOAD_ERR_NO_FILE => "Silakan pilih file PDF.",
    UPLOAD_ERR_NO_TMP_DIR => "Upload gagal (folder sementara tidak tersedia).",
    UPLOAD_ERR_CANT_WRITE => "Upload gagal (gagal menyimpan file).",
    UPLOAD_ERR_EXTENSION => "Upload gagal (diblokir ekstensi oleh server).",
    default => "Upload gagal.",
  };
}

function is_pdf_signature(string $tmpPath): bool {
  $fh = @fopen($tmpPath, 'rb');
  if (!$fh) return false;
  $head = (string)@fread($fh, 5);
  @fclose($fh);
  return str_starts_with($head, "%PDF-");
}

function friendly_error_message(string $msg): ?string {
  $allowed = [
    "Judul wajib diisi.",
    "Judul maksimal 45 karakter (termasuk spasi).",
    "Judul hanya boleh berisi huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).",
    "Materi hanya boleh dalam bentuk PDF.",
    "Silakan pilih file PDF.",
    "File wajib diupload.",
    "Upload gagal.",
    "Upload gagal (file terunggah sebagian).",
    "Upload gagal (folder sementara tidak tersedia).",
    "Upload gagal (gagal menyimpan file).",
    "Upload gagal (diblokir ekstensi oleh server).",
    "Ukuran file terlalu besar (maks 500KB).",
    "Tipe file tidak sesuai (wajib PDF).",
    "File tidak valid (bukan PDF).",
    "Gagal menyimpan file.",
    "ID tidak valid.",
    "Materi tidak ditemukan.",
    "Wajib upload file PDF.",
  ];

  if (in_array($msg, $allowed, true)) return $msg;
  if (stripos($msg, "SQLSTATE") !== false) return null;
  return null;
}

function upload_one_pdf(array $file, int $maxBytes, string $destDir): array {
  if (!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) {
    return [false,"","File wajib diupload."];
  }

  $errCode = (int)($file["error"] ?? UPLOAD_ERR_OK);
  if ($errCode !== UPLOAD_ERR_OK) {
    $msg = upload_error_to_message($errCode);
    return [false,"", $msg !== "" ? $msg : "Upload gagal."];
  }

  if ((int)($file["size"] ?? 0) > $maxBytes) {
    return [false,"","Ukuran file terlalu besar (maks 500KB)."];
  }

  $ext = strtolower(pathinfo((string)($file["name"] ?? ""), PATHINFO_EXTENSION));
  if ($ext !== "pdf") return [false,"","Tipe file tidak sesuai (wajib PDF)."];

  $mime = "";
  try {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file["tmp_name"]);
  } catch (Throwable $e) {
    $mime = "";
  }

  $allowedMimes = [
    "application/pdf",
    "application/x-pdf",
    "application/octet-stream",
  ];

  if (!is_pdf_signature((string)$file["tmp_name"])) {
    return [false,"","File tidak valid (bukan PDF)."];
  }

  if ($mime !== "" && !in_array($mime, $allowedMimes, true)) {
    return [false,"","File tidak valid (bukan PDF)."];
  }

  $name = safe_name("pdf");
  $path = rtrim($destDir,"/") . "/" . $name;

  if (!move_uploaded_file((string)$file["tmp_name"], $path)) return [false,"","Gagal menyimpan file."];
  return [true,$name,""];
}

function remove_media_files(int $materiId): void {
  global $UPLOAD_DIR;

  $st = db()->prepare("SELECT file_path FROM materi_media WHERE materi_id=?");
  $st->execute([$materiId]);

  foreach ($st->fetchAll() as $f) {
    $pdf = (string)$f["file_path"];
    $p = $UPLOAD_DIR . "/" . $pdf;
    if (is_file($p)) @unlink($p);
  }

  db()->prepare("DELETE FROM materi_media WHERE materi_id=?")->execute([$materiId]);
}

$toast = ["type"=>"", "msg"=>""];
$lastAction = "";

try {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    $lastAction = $action;

    if ($action === "add" || $action === "edit") {
      $judul  = trim((string)($_POST["judul"] ?? ""));
      $bagian = trim((string)($_POST["bagian"] ?? ""));
      $mode   = (string)($_POST["mode"] ?? "pdf");

      if ($judul === "") throw new RuntimeException("Judul wajib diisi.");
      validate_judul_or_throw($judul);

      if ($mode !== "pdf") throw new RuntimeException("Materi hanya boleh dalam bentuk PDF.");

      if ($bagian === "" || !in_array($bagian, $GLOBALS['BAGIAN_OPTIONS'], true)) {
        $bagian = $GLOBALS['DEFAULT_BAGIAN'];
      }

      db()->beginTransaction();

      if ($action === "add") {
        db()->prepare("INSERT INTO materi (judul, bagian, tipe, jumlah_slide) VALUES (?, ?, 'pdf', 0)")
          ->execute([$judul, $bagian]);
        $materiId = (int)db()->lastInsertId();
      } else {
        $materiId = (int)($_POST["id"] ?? 0);
        if ($materiId <= 0) throw new RuntimeException("ID tidak valid.");

        $st = db()->prepare("SELECT tipe FROM materi WHERE id=?");
        $st->execute([$materiId]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException("Materi tidak ditemukan.");
        if ((string)$row["tipe"] !== "pdf") throw new RuntimeException("Materi hanya boleh dalam bentuk PDF.");

        db()->prepare("UPDATE materi SET judul=?, bagian=? WHERE id=?")
          ->execute([$judul, $bagian, $materiId]);
      }

      $hasNewPdf = isset($_FILES["pdf"]) && ((int)($_FILES["pdf"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
      $shouldReplaceMedia = ($action === "add") || $hasNewPdf;

      if ($shouldReplaceMedia) {
        if (!$hasNewPdf) throw new RuntimeException("Silakan pilih file PDF.");

        remove_media_files($materiId);

        [$ok,$fn,$err] = upload_one_pdf($_FILES["pdf"] ?? [], 500*1024, $UPLOAD_DIR);
        if (!$ok) throw new RuntimeException($err);

        $pages = count_pdf_pages($UPLOAD_DIR . "/" . $fn);

        db()->prepare("INSERT INTO materi_media (materi_id, file_path, sort_order) VALUES (?, ?, 0)")
          ->execute([$materiId, $fn]);

        db()->prepare("UPDATE materi SET jumlah_slide=? WHERE id=?")
          ->execute([$pages, $materiId]);
      }

      db()->commit();

      if ($action === "add") $toast = ["type"=>"success","msg"=>"Berhasil menambahkan materi: " . $judul];
      else $toast = ["type"=>"success","msg"=>"Berhasil memperbarui materi: " . $judul];
    }

    if ($action === "delete") {
      $id = (int)($_POST["id"] ?? 0);
      if ($id <= 0) throw new RuntimeException("ID tidak valid.");

      remove_media_files($id);
      db()->prepare("DELETE FROM materi WHERE id=?")->execute([$id]);

      $toast = ["type"=>"success","msg"=>"Materi berhasil dihapus."];
    }
  }
} catch (Throwable $e) {
  if (db()->inTransaction()) db()->rollBack();

  $friendly = friendly_error_message($e->getMessage());

  if ($lastAction === "edit") {
    $toast = ["type"=>"danger","msg"=> $friendly ? ("Gagal memperbarui materi. " . $friendly) : "Gagal memperbarui materi. Silakan coba lagi."];
  } else {
    $toast = ["type"=>"danger","msg"=> $friendly ? ("Gagal menambahkan materi. " . $friendly) : "Gagal menambahkan materi. Silakan coba lagi."];
  }
}

$rows = db()->query("SELECT * FROM materi ORDER BY id DESC")->fetchAll();

$mediaByMateri = [];
$st = db()->query("SELECT materi_id, file_path, sort_order FROM materi_media ORDER BY materi_id DESC, sort_order ASC, id ASC");
foreach ($st->fetchAll() as $m) {
  $mid = (int)$m["materi_id"];
  $mediaByMateri[$mid] ??= [];
  $mediaByMateri[$mid][] = $m["file_path"];
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Daftar Materi | DIY</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --maroon:#700D09;
      --bg:#E9EDFF;
      --header-gray:#d9d9d9;
      --row-line:#e6e6e6;
      --shadow:0 14px 22px rgba(0,0,0,.18);
      --gold:#f4c430;
    }

    body{
      margin:0;
      font-family:'Inter';
      background:var(--bg);
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }

    .bg-maroon{background:var(--maroon)!important}
    .navbar{padding:20px 0;border-bottom:1px solid rgba(0,0,0,.15);}

    .nav-link{color:#fff !important;font-weight:500;}
    .nav-hover{position:relative;padding-bottom:6px;}
    .nav-hover::after{
      content:"";
      position:absolute;
      left:0;
      bottom:0;
      width:0;
      height:3px;
      background:var(--gold);
      transition:0.3s ease;
    }
    .nav-hover:hover::after,
    .nav-active::after{width:100%;}

    .page{
      max-width:1200px;
      margin:0 auto;
      width:100%;
      padding:140px 20px 40px;
      flex:1;
    }

    .title{font-weight:900;font-size:48px;margin:0;color:#111;line-height:1.05;}
    .subtitle{margin-top:10px;color:#333;font-size:14px;font-style:italic;}

    .btn-add{
      border:0;background:var(--maroon);color:#fff;
      font-weight:600;font-size:14px;
      padding:12px 34px;border-radius:999px;
      display:inline-flex;align-items:center;gap:10px;white-space:nowrap;
      box-shadow:0 10px 18px rgba(0,0,0,.18);
      transition:transform .2s ease, filter .2s ease;
      margin-top:18px;
    }
    .btn-add:hover{filter:brightness(.92);transform:translateY(1px);}
    .btn-add:active{transform:translateY(2px);}

    .table-wrap{
      margin-top:44px;background:#fff;border-radius:26px;overflow:hidden;
      box-shadow:var(--shadow);max-width:980px;margin-left:auto;margin-right:auto;
    }

    .table-scroll{
      overflow-x:auto;
      -webkit-overflow-scrolling:touch;
    }

    .table-grid{min-width:980px;}

    .table-head{
      background:var(--header-gray);
      padding:18px 34px;
      display:grid;
      grid-template-columns:90px 1fr 320px 180px 90px;
      align-items:center;
      font-weight:900;font-size:20px;color:#111;
    }
    .table-row{
      padding:18px 34px;
      display:grid;
      grid-template-columns:90px 1fr 320px 180px 90px;
      align-items:center;
      border-top:1px solid var(--row-line);
      font-size:16px;color:#111;
    }

    .col-judul{ padding-right:0.3cm; }
    .col-bagian{ padding-left:0.3cm; }

    .cell-center{text-align:center;}

    .icon-btn{
      border:0;background:transparent;padding:0;cursor:pointer;
      display:inline-flex;align-items:center;justify-content:center;
      width:44px;height:44px;border-radius:12px;
      transition:background .15s ease, transform .15s ease;
    }
    .icon-btn:hover{background:rgba(112,13,9,.08);transform:translateY(-1px);}
    .icon-edit,.icon-trash{color:var(--maroon);font-size:22px;}

    .label-plain{font-weight:600;font-size:14px;color:#111;margin-bottom:8px;}
    .judul-note{
      font-style:italic;
      font-weight:300;
      font-size:12px;
      color:#333;
      margin-bottom:8px;
    }

    .input-pill{
      border:2px solid #111;border-radius:999px;
      padding:10px 18px;font-size:13px;outline:none;width:min(520px,100%);
      background:#fff;
    }
    select.input-pill{
      appearance:auto;
      -webkit-appearance:auto;
      -moz-appearance:auto;
    }

    .modal-dialog{ max-width:680px; }
    .modal-content{
      border:0;border-radius:28px;overflow:hidden;
      box-shadow:0 30px 60px rgba(0,0,0,.30);
    }
    .modal-header-custom{
      background:var(--maroon);
      padding:22px 28px 16px;
      position:relative;
    }
    .modal-title-custom{margin:0;color:#fff;font-weight:800;font-size:28px;line-height:1.05;}
    .modal-subtitle-custom{margin-top:6px;color:rgba(255,255,255,.85);font-style:italic;font-size:13px;}
    .modal-close-x{
      position:absolute;top:16px;right:18px;width:44px;height:44px;border-radius:12px;
      border:0;background:transparent;color:#fff;font-size:30px;display:flex;
      align-items:center;justify-content:center;opacity:.95;
    }

    .modal-body{
      padding:22px 28px 26px;
      background:#fff;
      max-height:70vh;
      overflow:auto;
    }

    .dropzone{
      margin-top:12px;height:150px;border-radius:18px;background:#d9d9d9;border:2px dashed rgba(112,13,9,.25);
      display:flex;align-items:center;justify-content:center;text-align:center;cursor:pointer;
      padding:12px;
    }
    .dropzone.dragover{outline:3px solid rgba(112,13,9,.35);}
    .dropzone .dz-icon{font-size:42px;color:#fff;}
    .dropzone .dz-text{color:#fff;font-size:13px;font-weight:800;word-break:break-word;}

    .actions-row{display:flex;justify-content:flex-end;margin-top:16px;gap:10px;flex-wrap:wrap;}
    .btn-save{
      border:0;background:var(--maroon);color:#fff;font-weight:800;font-size:14px;
      padding:12px 44px;border-radius:14px;
    }

    .btn-back{
      width:42px;height:42px;border-radius:12px;
      display:inline-flex;align-items:center;justify-content:center;
      color:#fff;
      text-decoration:none;
      transition:transform .15s ease, filter .15s ease;
    }
    .btn-back:hover{filter:brightness(1.05);transform:translateY(-1px);}
    .btn-back i{font-size:22px;line-height:1;}

    .pdf-edit-row{
      display:flex;
      align-items:center;
      justify-content:flex-end;
      gap:12px;
      margin-top:12px;
    }
    .btn-edit-pdf{
      border:0;
      background:var(--maroon);
      color:#fff;
      font-weight:800;
      font-size:12px;
      padding:10px 14px;
      border-radius:999px;
      display:inline-flex;
      align-items:center;
      gap:8px;
      white-space:nowrap;
      box-shadow:0 10px 18px rgba(0,0,0,.15);
      transition:transform .15s ease, filter .15s ease;
    }
    .btn-edit-pdf:hover{filter:brightness(.95);transform:translateY(1px);}
    .btn-edit-pdf:active{transform:translateY(2px);}

    @media (max-width: 576px){
      body{font-size:13px;}
      .title{font-size:32px;}
      .subtitle{font-size:12px;}
      .btn-add{font-size:12px;padding:10px 18px;margin-top:10px;}
      .table-head{font-size:16px;padding:14px 16px;}
      .table-row{font-size:14px;padding:14px 16px;}
      .icon-btn{width:40px;height:40px;}
      .icon-edit,.icon-trash{font-size:20px;}
      .modal-header-custom{padding:18px 18px 14px;}
      .modal-title-custom{font-size:22px;}
      .modal-subtitle-custom{font-size:12px;}
      .modal-body{padding:14px 14px 16px;}
      .label-plain{font-size:13px;}
      .judul-note{font-size:11px;}
      .input-pill{font-size:13px;padding:9px 14px;}
      .btn-save{font-size:12px;padding:10px 18px;border-radius:12px;}
      .dropzone{height:140px;}
      .dropzone .dz-icon{font-size:40px;}
      .dropzone .dz-text{font-size:12px;}
      .table-grid{min-width:980px;}
    }
  </style>
</head>
<body>

<nav class="navbar navbar-dark bg-maroon fixed-top">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center gap-2">
      <a class="btn-back" href="javascript:history.back()" aria-label="Kembali" title="Kembali">
        <i class="bi bi-arrow-left"></i>
      </a>

      <a class="navbar-brand d-flex align-items-center gap-2" href="admin.php">
        <img src="Asset/LogoKPU.png" width="40" height="40" alt="KPU">
        <span class="lh-sm text-white fs-6">
          <strong>KPU</strong><br>DIY
        </span>
      </a>
    </div>

    <ul class="navbar-nav flex-row gap-5 align-items-center">
      <li class="nav-item">
        <a class="nav-link nav-hover" href="login_admin.php">LOGOUT</a>
      </li>
    </ul>
  </div>
</nav>

<main class="page">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3" style="max-width:980px;margin:0 auto;">
    <div>
      <h1 class="title">Daftar Materi</h1>
      <div class="subtitle">Klik tombol edit untuk memperbarui file, judul, atau bagian materi.</div>
    </div>

    <button class="btn-add" type="button" id="btnOpenAdd">
      <span>+ Tambah Materi</span>
    </button>
  </div>

  <?php if ($toast["type"]): ?>
    <div class="alert alert-<?= htmlspecialchars($toast["type"]) ?> mt-4"
         style="border-radius:16px;font-weight:800;max-width:980px;margin-left:auto;margin-right:auto;">
      <?= htmlspecialchars($toast["msg"]) ?>
    </div>
  <?php endif; ?>

  <section class="table-wrap">
    <div class="table-scroll">
      <div class="table-head table-grid">
        <div></div>
        <div class="text col-judul">JUDUL MATERI</div>
        <div class="text col-bagian">BAGIAN</div>
        <div class="text-center">JUMLAH SLIDE</div>
        <div></div>
      </div>

      <?php foreach ($rows as $r): ?>
        <?php
          $rid = (int)$r["id"];
          $media = $mediaByMateri[$rid] ?? [];
          $pdfFile = $media[0] ?? "";
          $bagian = (string)($r["bagian"] ?? $DEFAULT_BAGIAN);
        ?>
        <div class="table-row table-grid">
          <div class="cell-center">
            <button class="icon-btn btn-edit"
                    type="button"
                    data-id="<?= $rid ?>"
                    data-judul="<?= htmlspecialchars((string)$r["judul"]) ?>"
                    data-bagian="<?= htmlspecialchars($bagian) ?>"
                    data-pdf="<?= htmlspecialchars($pdfFile) ?>">
              <i class="bi bi-pencil-fill icon-edit"></i>
            </button>
          </div>

          <div class="col-judul"><?= htmlspecialchars((string)$r["judul"]) ?></div>
          <div class="col-bagian"><?= htmlspecialchars($bagian) ?></div>
          <div class="cell-center"><?= (int)$r["jumlah_slide"] ?></div>

          <div class="cell-center">
            <form method="post" onsubmit="return confirm('Yakin hapus materi ini?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $rid ?>">
              <button class="icon-btn" type="submit" title="Hapus">
                <i class="bi bi-trash3-fill icon-trash"></i>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>

      <div style="height:14px;background:#fff"></div>
    </div>
  </section>
</main>

<div class="modal fade" id="materiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="materiForm" method="post" enctype="multipart/form-data">
      <div class="modal-header-custom">
        <button type="button" class="modal-close-x" data-bs-dismiss="modal" aria-label="Close">&times;</button>
        <div class="modal-title-custom" id="modalTitle">Materi Baru</div>
        <div class="modal-subtitle-custom">Upload materi hanya dalam bentuk PDF</div>
      </div>

      <div class="modal-body">
        <input type="hidden" name="action" id="actionInput" value="add">
        <input type="hidden" name="id" id="idInput" value="">
        <input type="hidden" name="mode" id="modeInput" value="pdf">

        <div class="judul-note">
          Aturan judul: maksimal 45 karakter (termasuk spasi). Hanya boleh huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).
        </div>

        <div class="label-plain">Judul Materi</div>
        <input
          class="input-pill"
          name="judul"
          id="judulInput"
          type="text"
          placeholder="Tuliskan judul materi di sini..."
          maxlength="45"
          required
        >

        <div class="label-plain mt-3">Bagian</div>
        <select class="input-pill" name="bagian" id="bagianInput" required>
          <?php foreach ($BAGIAN_OPTIONS as $opt): ?>
            <option value="<?= htmlspecialchars($opt) ?>" <?= $opt === $DEFAULT_BAGIAN ? "selected" : "" ?>>
              <?= htmlspecialchars($opt) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <div id="inputMateriMeta">
          <div class="mt-3" style="font-weight:800;font-size:14px;">Input Materi</div>
          <div style="font-style:italic;font-size:12px;">(PDF) max. 500Kb</div>
        </div>

        <input id="pdfPicker" name="pdf" type="file" accept="application/pdf" class="d-none">

        <div class="pdf-edit-row" id="pdfEditRow" style="display:none;">
          <button type="button" class="btn-edit-pdf" id="btnEditPdf">
            <i class="bi bi-pencil-square"></i>
            <span>Edit PDF</span>
          </button>
        </div>

        <div class="dropzone" id="dropzone">
          <div>
            <div class="dz-icon"><i class="bi bi-file-earmark-pdf"></i></div>
            <div class="dz-text" id="dzText">Klik atau seret file PDF ke sini</div>
          </div>
        </div>

        <div class="mt-3" id="pdfPreviewWrap" style="display:none;">
          <div style="font-weight:800;font-size:14px;">Preview PDF</div>
          <div style="font-style:italic;font-size:12px;">(tampilan cepat untuk admin)</div>

          <div style="margin-top:10px;border:1px solid #e6e6e6;border-radius:16px;overflow:hidden;">
            <iframe
              id="pdfPreviewFrame"
              src=""
              style="width:100%;height:420px;border:0;background:#fff;"
              title="Preview PDF"
            ></iframe>
          </div>
        </div>

        <div class="actions-row">
          <button class="btn-save" type="submit" id="btnSave">Simpan</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const materiModalEl = document.getElementById('materiModal');
  const btnOpenAdd = document.getElementById('btnOpenAdd');
  const modalTitle = document.getElementById('modalTitle');
  const actionInput = document.getElementById('actionInput');
  const idInput = document.getElementById('idInput');
  const judulInput = document.getElementById('judulInput');
  const bagianInput = document.getElementById('bagianInput');

  const inputMateriMeta = document.getElementById('inputMateriMeta');

  const pdfPicker = document.getElementById('pdfPicker');
  const dropzone = document.getElementById('dropzone');
  const dzText = document.getElementById('dzText');

  const pdfPreviewWrap = document.getElementById('pdfPreviewWrap');
  const pdfPreviewFrame = document.getElementById('pdfPreviewFrame');

  const pdfEditRow = document.getElementById('pdfEditRow');
  const btnEditPdf = document.getElementById('btnEditPdf');

  const UPLOADS_PUBLIC_BASE = "uploads";

  const materiForm = document.getElementById('materiForm');
  const materiModal = new bootstrap.Modal(materiModalEl, { backdrop: true, keyboard: true });

  let currentAction = "add";
  let objectUrl = null;

  function setFileToInput(file){
    const dt = new DataTransfer();
    dt.items.add(file);
    pdfPicker.files = dt.files;
  }

  function clearPreview(){
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
    pdfPreviewFrame.src = "";
    pdfPreviewWrap.style.display = "none";
  }

  function showPreview(url){
    pdfPreviewFrame.src = url;
    pdfPreviewWrap.style.display = "block";
  }

  function validatePdfFile(file){
    if(!file) return "File tidak ditemukan.";

    const name = (file.name || "").toLowerCase();
    const nameOk = name.endsWith(".pdf");

    const allowedTypes = ["application/pdf","application/x-pdf","application/octet-stream"];
    const type = (file.type || "").toLowerCase();
    const typeOk = (type === "" || allowedTypes.includes(type));

    if(!nameOk && !typeOk) return "Tipe file harus PDF.";
    if(file.size > 500 * 1024) return "Ukuran file terlalu besar (maks 500KB).";
    return "";
  }

  function validateJudul(val){
    const judul = (val || "").trim();

    if(judul.length === 0) return "Judul wajib diisi.";
    if(judul.length > 45) return "Judul maksimal 45 karakter (termasuk spasi).";

    const re = /^[A-Za-z0-9\.\,\:\?\s]+$/;
    if(!re.test(judul)){
      return "Judul hanya boleh berisi huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).";
    }
    return "";
  }

  function setModeAdd(){
   
    dropzone.style.display = "flex";
    pdfEditRow.style.display = "none";
    if (inputMateriMeta) inputMateriMeta.style.display = "block";
    dzText.textContent = "Klik atau seret file PDF ke sini";
  }

  function setModeEdit(){
    dropzone.style.display = "none";
    pdfEditRow.style.display = "flex";
    if (inputMateriMeta) inputMateriMeta.style.display = "none";
  }

  function resetModal(){
    judulInput.value = "";
    bagianInput.selectedIndex = 0;
    actionInput.value = "add";
    idInput.value = "";
    pdfPicker.value = "";
    clearPreview();
    setModeAdd();
  }

  btnEditPdf.addEventListener('click', () => {
    pdfPicker.click();
  });

  btnOpenAdd.addEventListener('click', () => {
    currentAction = "add";
    modalTitle.textContent = "Materi Baru";
    resetModal();
    setModeAdd();
    materiModal.show();
  });

  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
      currentAction = "edit";
      modalTitle.textContent = "Edit Materi";

      resetModal();
      actionInput.value = "edit";
      idInput.value = btn.dataset.id || "";
      judulInput.value = btn.dataset.judul || "";

      const bagian = btn.dataset.bagian || "";
      if (bagian) bagianInput.value = bagian;

      const existingPdf = btn.dataset.pdf || "";
      setModeEdit();

      if (existingPdf) {
        const url = `${UPLOADS_PUBLIC_BASE}/${encodeURIComponent(existingPdf)}?v=${Date.now()}`;
        showPreview(url);
      } else {
        clearPreview();
      }

      materiModal.show();
    });
  });

  function handlePdfSelect(file){
    const msg = validatePdfFile(file);
    if(msg){ alert(msg); return; }
    setFileToInput(file);

    if (currentAction === "add") {
      dzText.textContent = `File dipilih: ${file.name}`;
    }
    
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    showPreview(objectUrl);
  }

  pdfPicker.addEventListener('change', () => {
    const f = (pdfPicker.files || [])[0];
    if(f) handlePdfSelect(f);
  });

  dropzone.addEventListener('click', () => {
    pdfPicker.click();
  });

  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
  });

  dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
  });

  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    const f = (e.dataTransfer.files || [])[0];
    if(f) handlePdfSelect(f);
  });

  materiForm.addEventListener("submit", (e) => {
    const judulMsg = validateJudul(judulInput.value);
    if(judulMsg){
      e.preventDefault();
      alert(judulMsg);
      return;
    }

    const hasPdf = (pdfPicker.files && pdfPicker.files.length > 0);

    if(currentAction === "add" && !hasPdf){
      e.preventDefault();
      alert("Wajib upload file PDF.");
      return;
    }

    if(hasPdf){
      const msg = validatePdfFile(pdfPicker.files[0]);
      if(msg){
        e.preventDefault();
        alert(msg);
        return;
      }
    }

    if(!bagianInput.value){
      e.preventDefault();
      alert("Bagian wajib dipilih.");
      return;
    }
  });

  materiModalEl.addEventListener('hidden.bs.modal', () => {
    clearPreview();
  });

})();
</script>

<?php include 'footer.php'; ?>
</body>
</html>
