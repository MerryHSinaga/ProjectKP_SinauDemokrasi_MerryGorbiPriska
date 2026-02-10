<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION["admin"])) {
  header("Location: login_admin.php");
  exit;
}

require_once 'db.php';

if (!isset($UPLOAD_DIR) || !is_string($UPLOAD_DIR) || trim($UPLOAD_DIR) === "") {
  $UPLOAD_DIR = __DIR__ . "/uploads/materi";
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
  'Teknis Penyelenggara Pemilu',
  'Partisipasi Hubungan Masyarakat',
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
      'Teknis Penyelenggara Pemilu',
      'Partisipasi Hubungan Masyarakat',
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
  if ($len > 45) throw new RuntimeException("Judul maksimal 45 karakter (termasuk spasi).");
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
    "Subbagian wajib dipilih.",
  ];

  if (in_array($msg, $allowed, true)) return $msg;
  if (stripos($msg, "SQLSTATE") !== false) return null;
  return null;
}

function upload_one_pdf(array $file, int $maxBytes, string $destDir): array {
  if (!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) return [false,"","File wajib diupload."];

  $errCode = (int)($file["error"] ?? UPLOAD_ERR_OK);
  if ($errCode !== UPLOAD_ERR_OK) {
    $msg = upload_error_to_message($errCode);
    return [false,"", $msg !== "" ? $msg : "Upload gagal."];
  }

  if ((int)($file["size"] ?? 0) > $maxBytes) return [false,"","Ukuran file terlalu besar (maks 500KB)."];

  $ext = strtolower(pathinfo((string)($file["name"] ?? ""), PATHINFO_EXTENSION));
  if ($ext !== "pdf") return [false,"","Tipe file tidak sesuai (wajib PDF)."];

  $mime = "";
  try {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file((string)$file["tmp_name"]);
  } catch (Throwable $e) {
    $mime = "";
  }

  $allowedMimes = ["application/pdf","application/x-pdf","application/octet-stream"];

  if (!is_pdf_signature((string)$file["tmp_name"])) return [false,"","File tidak valid (bukan PDF)."];
  if ($mime !== "" && !in_array($mime, $allowedMimes, true)) return [false,"","File tidak valid (bukan PDF)."];

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
$shouldRedirect = false;

try {
  if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = (string)($_POST["action"] ?? "");
    $lastAction = $action;

    if ($action === "logout") {
      $_SESSION = [];
      if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], (bool)$p["secure"], (bool)$p["httponly"]);
      }
      session_destroy();
      header("Location: login_admin.php");
      exit;
    }

    if ($action === "add" || $action === "edit") {
      $judul  = trim((string)($_POST["judul"] ?? ""));
      $bagian = trim((string)($_POST["bagian"] ?? ""));
      $mode   = (string)($_POST["mode"] ?? "pdf");

      if ($judul === "") throw new RuntimeException("Judul wajib diisi.");
      validate_judul_or_throw($judul);
      if ($mode !== "pdf") throw new RuntimeException("Materi hanya boleh dalam bentuk PDF.");

      if ($bagian === "" || !in_array($bagian, $GLOBALS['BAGIAN_OPTIONS'], true)) $bagian = $GLOBALS['DEFAULT_BAGIAN'];

      db()->beginTransaction();

      if ($action === "add") {
        db()->prepare("INSERT INTO materi (judul, bagian, tipe, jumlah_slide) VALUES (?, ?, 'pdf', 0)")->execute([$judul, $bagian]);
        $materiId = (int)db()->lastInsertId();
      } else {
        $materiId = (int)($_POST["id"] ?? 0);
        if ($materiId <= 0) throw new RuntimeException("ID tidak valid.");

        $st = db()->prepare("SELECT tipe FROM materi WHERE id=?");
        $st->execute([$materiId]);
        $row = $st->fetch();
        if (!$row) throw new RuntimeException("Materi tidak ditemukan.");
        if ((string)$row["tipe"] !== "pdf") throw new RuntimeException("Materi hanya boleh dalam bentuk PDF.");

        db()->prepare("UPDATE materi SET judul=?, bagian=? WHERE id=?")->execute([$judul, $bagian, $materiId]);
      }

      $hasNewPdf = isset($_FILES["pdf"]) && ((int)($_FILES["pdf"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
      $shouldReplaceMedia = ($action === "add") || $hasNewPdf;

      if ($shouldReplaceMedia) {
        if (!$hasNewPdf) throw new RuntimeException("Silakan pilih file PDF.");

        remove_media_files($materiId);

        [$ok,$fn,$err] = upload_one_pdf($_FILES["pdf"] ?? [], 500*1024, $UPLOAD_DIR);
        if (!$ok) throw new RuntimeException($err);

        $pages = count_pdf_pages($UPLOAD_DIR . "/" . $fn);

        db()->prepare("INSERT INTO materi_media (materi_id, file_path, sort_order) VALUES (?, ?, 0)")->execute([$materiId, $fn]);
        db()->prepare("UPDATE materi SET jumlah_slide=? WHERE id=?")->execute([$pages, $materiId]);
      }

      db()->commit();

      $toast = ["type"=>"success","msg"=> ($action === "add" ? "Berhasil menambahkan materi: " : "Berhasil memperbarui materi: ") . $judul];
      $shouldRedirect = true;
    }

    if ($action === "delete") {
      $id = (int)($_POST["id"] ?? 0);
      if ($id <= 0) throw new RuntimeException("ID tidak valid.");

      remove_media_files($id);
      db()->prepare("DELETE FROM materi WHERE id=?")->execute([$id]);

      $toast = ["type"=>"success","msg"=>"Materi berhasil dihapus."];
      $shouldRedirect = true;
    }
  }
} catch (Throwable $e) {
  if (db()->inTransaction()) db()->rollBack();

  $friendly = friendly_error_message($e->getMessage());

  if ($lastAction === "edit") {
    $toast = ["type"=>"danger","msg"=> $friendly ? ("Gagal memperbarui materi. " . $friendly) : "Gagal memperbarui materi. Silakan coba lagi."];
  } elseif ($lastAction === "delete") {
    $toast = ["type"=>"danger","msg"=> $friendly ? ("Gagal menghapus materi. " . $friendly) : "Gagal menghapus materi. Silakan coba lagi."];
  } else {
    $toast = ["type"=>"danger","msg"=> $friendly ? ("Gagal menambahkan materi. " . $friendly) : "Gagal menambahkan materi. Silakan coba lagi."];
  }

  if ($lastAction !== "logout" && $_SERVER["REQUEST_METHOD"] === "POST") $shouldRedirect = true;
}

if ($shouldRedirect && $toast["type"]) {
  $_SESSION["flash_toast"] = $toast;
  header("Location: " . ($_SERVER["PHP_SELF"] ?? "admin_materi.php"));
  exit;
}

if (!$toast["type"] && isset($_SESSION["flash_toast"]) && is_array($_SESSION["flash_toast"])) {
  $t = $_SESSION["flash_toast"];
  unset($_SESSION["flash_toast"]);
  if (isset($t["type"], $t["msg"])) $toast = ["type" => (string)$t["type"], "msg" => (string)$t["msg"]];
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

<?php
$site_title = 'Admin | Daftar Materi';
include 'identitas.php';
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

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
      --navbar-h:90px;
    }

    html{overflow-y:scroll; scrollbar-gutter: stable;}

    body{
      margin:0;
      font-family:'Inter',system-ui,-apple-system,sans-serif;
      background:var(--bg);
      min-height:100vh;
      display:flex;
      flex-direction:column;
    }

    body.modal-open{
      overflow:hidden !important;
      padding-right:0 !important;
    }
    .modal, .navbar-kpu{ padding-right:0 !important; }

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
      gap:14px;
      position:relative;
    }

    .btn-back{
      position:absolute;
      left:-40px;
      top:50%;
      transform:translateY(-50%);
      width:42px;height:42px;
      border-radius:12px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      color:#fff;
      text-decoration:none;
      background:transparent;
      transition:transform .2s ease, filter .2s ease, background .2s ease;
      z-index:2;
    }

    .btn-back:hover{
      filter:brightness(1.05);
      transform:translateY(-50%) translateY(-1px);
    }

    .btn-back i{
      font-size:22px;
      line-height:1;
    }

    .brand{
      display:flex;
      align-items:center;
      gap:8px;
      text-decoration:none;
      flex-shrink:0;
    }

    .brand img{height:36px}

    .brand-text{color:#fff;line-height:1.15;}
    .brand-text strong{font-size:.95rem;font-weight:700;}
    .brand-text span{font-size:.85rem;font-weight:400;}

    .nav-menu{
      display:flex;
      gap:26px;
      align-items:center;
    }

    .btn-logout{
      border:0;
      background:transparent;
      color:#fff;
      font-weight:600;
      font-size:.85rem;
      letter-spacing:.5px;
      padding:0;
      position:relative;
      white-space:nowrap;
    }

    .btn-logout::after{
      content:"";
      position:absolute;
      left:0;bottom:-6px;
      width:0;height:3px;
      background:var(--gold);
      transition:.3s;
    }

    .btn-logout:hover::after{width:100%}

    .page{
      max-width:1200px;
      margin:0 auto;
      width:100%;
      padding:calc(var(--navbar-h) + 60px) 20px 40px;
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

    .table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch;}
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
    .judul-note{font-style:italic;font-weight:300;font-size:12px;color:#333;margin-bottom:8px;}

    .input-pill{
      border:2px solid #111;border-radius:999px;
      padding:10px 18px;font-size:13px;outline:none;width:min(520px,100%);
      background:#fff;
    }

    .select-wrap{
      position:relative;
      width:min(520px, 100%);
    }

    .select-wrap select.input-pill{
      width:100%;
      padding-right:52px;
      border-radius:999px;
      appearance:none;
      -webkit-appearance:none;
      -moz-appearance:none;
      background-image:none;
    }

    .select-wrap::after{
      content:"";
      position:absolute;
      top:50%;
      right:22px;
      width:10px;
      height:10px;
      border-right:2px solid #111;
      border-bottom:2px solid #111;
      transform:translateY(-60%) rotate(45deg);
      pointer-events:none;
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
    .btn-save{border:0;background:var(--maroon);color:#fff;font-weight:800;font-size:14px;padding:12px 44px;border-radius:14px;}

    .btn-edit-pdf{
      border:0;background:var(--maroon);color:#fff;font-weight:800;font-size:12px;
      padding:10px 14px;border-radius:999px;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;
      box-shadow:0 10px 18px rgba(0,0,0,.15);
      transition:transform .15s ease, filter .15s ease;
    }
    .btn-edit-pdf:hover{filter:brightness(.95);transform:translateY(1px);}
    .btn-edit-pdf:active{transform:translateY(2px);}

    .pdf-preview-header{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .pdf-preview-title{font-weight:800;font-size:14px;margin:0;line-height:1.2;}
    .pdf-preview-subtitle{font-style:italic;font-size:12px;margin-top:4px;margin-bottom:0;}

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
      box-shadow:0 18px 34px rgba(0,0,0,.25);
    }

    .btn-modal-action{
      border:0;
      border-radius:20px;
      padding:6px 22px;
      font-weight:600;
      background:var(--maroon);
      color:#fff;
      min-width:120px;
    }
    .btn-modal-cancel{
      border:0;
      border-radius:20px;
      padding:6px 22px;
      font-weight:600;
      background:#e9e9e9;
      color:#111;
      min-width:120px;
    }

    .popup-title{
      font-weight:900;
      font-size:16px;
      margin:0 0 8px 0;
      color:#111;
    }
    .popup-message{
      font-size:13px;
      color:#333;
      margin:0 0 18px 0;
      line-height:1.45;
      white-space:pre-wrap;
    }
    .popup-actions{
      display:flex;
      gap:10px;
      justify-content:center;
      flex-wrap:wrap;
      margin-top:6px;
    }

    @media (max-width: 992px){
      .modal-content-custom{width:min(360px, 92vw);}
    }

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
      .btn-back{width:42px;height:42px;border-radius:12px;}
      .btn-back i{font-size:22px;line-height:1;}
    }
  </style>
</head>
<body>

<nav class="navbar-kpu">
  <div class="inner">
    <a href="admin.php" class="btn-back" id="btnBack" aria-label="Kembali">
      <i class="bi bi-arrow-left"></i>
    </a>

    <a href="admin.php" class="brand">
      <img src="Asset/LogoKPU.png" alt="KPU">
      <div class="brand-text">
        <strong>KPU</strong><br>
        <span>DIY</span>
      </div>
    </a>

    <div class="nav-menu">
      <form method="post" id="logoutFormDesktop" class="m-0">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn-logout" id="btnLogoutDesktop">LOGOUT</button>
      </form>
    </div>
  </div>
</nav>

<main class="page">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3" style="max-width:980px;margin:0 auto;">
    <div>
      <h1 class="title">Daftar Materi</h1>
      <div class="subtitle">Klik tombol edit untuk memperbarui file, judul, atau Subbagian materi.</div>
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
        <div class="text col-bagian">SUBBAGIAN</div>
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
            <form method="post" class="form-delete">
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

<div class="modal-overlay" id="popupOverlay" aria-hidden="true">
  <div class="modal-content-custom" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
    <p class="popup-title" id="popupTitle">Konfirmasi</p>
    <p class="popup-message" id="popupMessage">Pesan</p>
    <div class="popup-actions" id="popupActions"></div>
  </div>
</div>

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
        <input class="input-pill" name="judul" id="judulInput" type="text" placeholder="Tuliskan judul materi di sini..." maxlength="45" required>

        <div class="label-plain mt-3">Subbagian</div>
        <div class="select-wrap">
          <select class="input-pill" name="bagian" id="bagianInput" required>
            <?php foreach ($BAGIAN_OPTIONS as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= $opt === $DEFAULT_BAGIAN ? "selected" : "" ?>>
                <?= htmlspecialchars($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div id="inputMateriMeta">
          <div class="mt-3" style="font-weight:800;font-size:14px;">Input Materi</div>
          <div style="font-style:italic;font-size:12px;">(PDF) max. 500Kb</div>
        </div>

        <input id="pdfPicker" name="pdf" type="file" accept="application/pdf" class="d-none">

        <div class="dropzone" id="dropzone">
          <div>
            <div class="dz-icon"><i class="bi bi-file-earmark-pdf"></i></div>
            <div class="dz-text" id="dzText">Klik atau seret file PDF ke sini</div>
          </div>
        </div>

        <div class="mt-3" id="pdfPreviewWrap" style="display:none;">
          <div class="pdf-preview-header">
            <div>
              <p class="pdf-preview-title">Preview PDF</p>
              <p class="pdf-preview-subtitle">(tampilan cepat untuk admin)</p>
            </div>

            <button type="button" class="btn-edit-pdf" id="btnEditPdf" style="display:none;">
              <i class="bi bi-pencil-square"></i>
              <span>Edit PDF</span>
            </button>
          </div>

          <div style="margin-top:10px;border:1px solid #e6e6e6;border-radius:16px;overflow:hidden;">
            <iframe id="pdfPreviewFrame" src="" style="width:100%;height:420px;border:0;background:#fff;" title="Preview PDF"></iframe>
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
  const popupOverlay = document.getElementById('popupOverlay');
  const popupTitle   = document.getElementById('popupTitle');
  const popupMessage = document.getElementById('popupMessage');
  const popupActions = document.getElementById('popupActions');

  let popupLocked = false;

  function closePopup(){
    popupOverlay.style.display = "none";
    popupOverlay.setAttribute("aria-hidden","true");
    popupActions.innerHTML = '';
    popupLocked = false;
  }

  function openPopup({ title="Konfirmasi", message="", okText="OK", cancelText="", onOk=null, onCancel=null }){
    if (popupLocked) return;
    popupLocked = true;

    popupTitle.textContent = title;
    popupMessage.textContent = message;
    popupActions.innerHTML = '';

    if (cancelText) {
      const btnCancel = document.createElement('button');
      btnCancel.type = "button";
      btnCancel.className = "btn-modal-cancel";
      btnCancel.textContent = cancelText;
      btnCancel.addEventListener('click', () => {
        closePopup();
        if (typeof onCancel === "function") onCancel();
      });
      popupActions.appendChild(btnCancel);
    }

    const btnOk = document.createElement('button');
    btnOk.type = "button";
    btnOk.className = "btn-modal-action";
    btnOk.textContent = okText;
    btnOk.addEventListener('click', () => {
      closePopup();
      if (typeof onOk === "function") onOk();
    });
    popupActions.appendChild(btnOk);

    popupOverlay.style.display = "flex";
    popupOverlay.setAttribute("aria-hidden","false");
  }

  function showError(message) {
    openPopup({ title: "Terjadi Kesalahan", message, okText: "OK" });
  }

  function showConfirm({ title="Konfirmasi", message="", okText="Ya", cancelText="Batal", onOk, onCancel }) {
    openPopup({ title, message, okText, cancelText, onOk, onCancel });
  }

  popupOverlay.addEventListener('click', (e) => {
    if (e.target !== popupOverlay) return;
    const hasCancel = popupActions.querySelector('.btn-modal-cancel');
    if (!hasCancel) closePopup();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== "Escape") return;
    if (popupOverlay.style.display !== "flex") return;
    const hasCancel = popupActions.querySelector('.btn-modal-cancel');
    if (!hasCancel) closePopup();
  });

  const logoutFormDesktop = document.getElementById('logoutFormDesktop');
  const btnLogoutDesktop  = document.getElementById('btnLogoutDesktop');

  if (logoutFormDesktop && btnLogoutDesktop) {
    btnLogoutDesktop.addEventListener('click', (e) => {
      e.preventDefault();
      openPopup({
        title: "Konfirmasi",
        message: "Yakin ingin logout?",
        okText: "Logout",
        cancelText: "Batal",
        onOk: () => logoutFormDesktop.submit()
      });
    });
  }

  const btnBack = document.getElementById('btnBack');

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
  const btnEditPdf = document.getElementById('btnEditPdf');

  const UPLOADS_PUBLIC_BASE = "uploads/materi";

  const materiForm = document.getElementById('materiForm');
  const materiModal = new bootstrap.Modal(materiModalEl, { backdrop: true, keyboard: true });

  let currentAction = "add";
  let objectUrl = null;

  let isDirty = false;
  let bypassCloseConfirm = false;
  let bypassSaveConfirm = false;

  let initialSnapshot = null;

  function resetDirty(){
    isDirty = false;
    bypassCloseConfirm = false;
    bypassSaveConfirm = false;
  }

  function makeSnapshot(){
    return JSON.stringify({
      action: actionInput.value,
      id: idInput.value,
      judul: (judulInput.value || "").trim(),
      bagian: bagianInput.value || "",
      hasFile: (pdfPicker.files && pdfPicker.files.length > 0)
    });
  }

  function setInitialSnapshot(){
    initialSnapshot = makeSnapshot();
    resetDirty();
  }

  function recomputeDirty(){
    if (!initialSnapshot) return;
    isDirty = (makeSnapshot() !== initialSnapshot);
  }

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
    if(!re.test(judul)) return "Judul hanya boleh berisi huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).";
    return "";
  }

  function setModeAdd(){
    dropzone.style.display = "flex";
    if (inputMateriMeta) inputMateriMeta.style.display = "block";
    dzText.textContent = "Klik atau seret file PDF ke sini";
    btnEditPdf.style.display = "none";
  }

  function setModeEdit(){
    dropzone.style.display = "none";
    if (inputMateriMeta) inputMateriMeta.style.display = "none";
    btnEditPdf.style.display = "inline-flex";
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

  if (btnBack) {
    btnBack.addEventListener('click', (e) => {
      e.preventDefault();

      if (popupOverlay.style.display === "flex") return;

      const modalOpen = materiModalEl.classList.contains('show');

      if (!modalOpen || !isDirty) {
        window.location.href = 'admin.php';
        return;
      }

      showConfirm({
        title: "Konfirmasi",
        message: "Perubahan belum disimpan, yakin ingin kembali?",
        okText: "Kembali",
        cancelText: "Batal",
        onOk: () => {
          isDirty = false;
          window.location.href = 'admin.php';
        }
      });
    });
  }

  btnEditPdf.addEventListener('click', () => pdfPicker.click());

  btnOpenAdd.addEventListener('click', () => {
    currentAction = "add";
    modalTitle.textContent = "Materi Baru";
    resetModal();
    setModeAdd();
    materiModal.show();
    setTimeout(setInitialSnapshot, 0);
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
      setTimeout(setInitialSnapshot, 0);
    });
  });

  function handlePdfSelect(file){
    const msg = validatePdfFile(file);
    if(msg){ showError(msg); return; }
    setFileToInput(file);

    if (currentAction === "add") dzText.textContent = `File dipilih: ${file.name}`;

    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);
    showPreview(objectUrl);

    recomputeDirty();
  }

  pdfPicker.addEventListener('change', () => {
    const f = (pdfPicker.files || [])[0];
    if(f) handlePdfSelect(f);
    recomputeDirty();
  });

  dropzone.addEventListener('click', () => pdfPicker.click());

  dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
  });

  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));

  dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    const f = (e.dataTransfer.files || [])[0];
    if(f) handlePdfSelect(f);
    recomputeDirty();
  });

  judulInput.addEventListener('input', () => recomputeDirty());
  bagianInput.addEventListener('change', () => recomputeDirty());

  materiModalEl.addEventListener('hide.bs.modal', (e) => {
    if (popupOverlay.style.display === "flex") return;

    if (isDirty && !bypassCloseConfirm) {
      e.preventDefault();
      showConfirm({
        title: "Konfirmasi",
        message: "Perubahan belum disimpan, yakin ingin keluar?",
        okText: "Keluar",
        cancelText: "Batal",
        onOk: () => {
          bypassCloseConfirm = true;
          materiModal.hide();
        }
      });
    }
  });

  materiModalEl.addEventListener('hidden.bs.modal', () => {
    clearPreview();
    resetDirty();
    initialSnapshot = null;
  });

  materiForm.addEventListener("submit", (e) => {
    const judulMsg = validateJudul(judulInput.value);
    if(judulMsg){ e.preventDefault(); showError(judulMsg); return; }

    const hasPdf = (pdfPicker.files && pdfPicker.files.length > 0);
    if(currentAction === "add" && !hasPdf){ e.preventDefault(); showError("Wajib upload file PDF."); return; }

    if(hasPdf){
      const msg = validatePdfFile(pdfPicker.files[0]);
      if(msg){ e.preventDefault(); showError(msg); return; }
    }

    if(!bagianInput.value){ e.preventDefault(); showError("Subbagian wajib dipilih."); return; }

    if (bypassSaveConfirm) return;

    e.preventDefault();
    showConfirm({
      title: "Konfirmasi",
      message: "Yakin ingin disimpan?",
      okText: "Simpan",
      cancelText: "Batal",
      onOk: () => {
        bypassSaveConfirm = true;
        materiForm.requestSubmit ? materiForm.requestSubmit() : materiForm.submit();
      }
    });
  });

  document.querySelectorAll('.form-delete').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      showConfirm({
        title: "Konfirmasi",
        message: "Yakin ingin menghapus materi ini?",
        okText: "Hapus",
        cancelText: "Batal",
        onOk: () => form.submit()
      });
    });
  });
})();
</script>

</body>
</html>