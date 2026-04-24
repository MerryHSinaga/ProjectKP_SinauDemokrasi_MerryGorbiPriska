<?php
declare(strict_types=1);
session_start();

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true
) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['timeout'], $_SESSION['last_activity'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

if (time() - (int)$_SESSION['last_activity'] > (int)$_SESSION['timeout']) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$_SESSION['last_activity'] = time();

require_once 'db.php';

$UPLOAD_DIR = __DIR__ . "/uploads/materi";
$UPLOAD_DIR_THUMB = __DIR__ . "/uploads/thumbnails";
$UPLOAD_DIR = rtrim($UPLOAD_DIR, "/");
$UPLOAD_DIR_THUMB = rtrim($UPLOAD_DIR_THUMB, "/");

if (!is_dir($UPLOAD_DIR)) {
    @mkdir($UPLOAD_DIR, 0775, true);
}
if (!is_dir($UPLOAD_DIR_THUMB)) {
    @mkdir($UPLOAD_DIR_THUMB, 0775, true);
}

$BAGIAN_OPTIONS = [
  'Keuangan, Umum dan Logistik',
  'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
  'Perencanaan, Data dan Informasi'
];
$DEFAULT_BAGIAN = 'Keuangan, Umum dan Logistik';

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function column_exists(string $table, string $column): bool {
    $st = db()->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

function index_exists(string $table, string $indexName): bool {
    $st = db()->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $st->execute([$table, $indexName]);
    return (int)$st->fetchColumn() > 0;
}

function safe_name(string $prefix, string $ext): string {
    $ext = strtolower($ext);
    return $prefix . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(6)) . "." . $ext;
}

function count_pdf_pages(string $pdfPath): int {
    $content = @file_get_contents($pdfPath);
    if (is_string($content) && $content !== "") {
        $n = preg_match_all("/\/Type\s*\/Page\b/", $content);
        if ($n > 0) return (int)$n;
    }
    return 1;
}

function validate_judul_or_throw(string $judul): void {
    $judul = trim($judul);
    $len = mb_strlen($judul, 'UTF-8');
    if ($judul === '') throw new RuntimeException("Judul wajib diisi.");
    if ($len > 45) throw new RuntimeException("Judul maksimal 45 karakter (termasuk spasi).");
    if (!preg_match('/^[A-Za-z0-9\.\,\:\?\s]+$/', $judul)) {
        throw new RuntimeException("Judul hanya boleh berisi huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).");
    }
}

function validate_url_or_throw(string $url, string $label): string {
    $url = trim($url);
    if ($url === '') throw new RuntimeException($label . " wajib diisi.");
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException($label . " tidak valid.");
    }
    return $url;
}

function upload_error_to_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_OK => "",
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Ukuran file terlalu besar.",
        UPLOAD_ERR_PARTIAL => "Upload gagal (file terunggah sebagian).",
        UPLOAD_ERR_NO_FILE => "Silakan pilih file.",
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
        "Silakan pilih file.",
        "Upload gagal.",
        "Upload gagal (file terunggah sebagian).",
        "Upload gagal (folder sementara tidak tersedia).",
        "Upload gagal (gagal menyimpan file).",
        "Upload gagal (diblokir ekstensi oleh server).",
        "Tipe file harus PDF.",
        "File tidak valid (bukan PDF).",
        "Gagal menyimpan file.",
        "ID tidak valid.",
        "Materi tidak ditemukan.",
        "Bagian wajib dipilih.",
        "URL Link wajib diisi.",
        "URL Link tidak valid.",
        "Thumbnail hanya boleh JPG, JPEG, PNG, atau WEBP.",
        "Ukuran thumbnail maksimal 2MB.",
        "Upload thumbnail gagal.",
        "Gagal menyimpan thumbnail.",
        "File video wajib diupload.",
        "Tipe file video tidak didukung.",
        "Ukuran video maksimal 50MB.",
        "Judul materi sudah digunakan. Silakan pakai judul lain.",
    ];

    if (in_array($msg, $allowed, true)) return $msg;
    if (stripos($msg, "uq_materi_judul") !== false || stripos($msg, "duplicate entry") !== false) {
        return "Judul materi sudah digunakan. Silakan pakai judul lain.";
    }
    if (stripos($msg, "SQLSTATE") !== false) return null;
    return $msg ?: null;
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
        return [false,"","Ukuran file terlalu besar."];
    }

    $ext = strtolower(pathinfo((string)($file["name"] ?? ""), PATHINFO_EXTENSION));
    if ($ext !== "pdf") return [false,"","Tipe file harus PDF."];

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

    $name = safe_name("materi", "pdf");
    $path = rtrim($destDir,"/") . "/" . $name;

    if (!move_uploaded_file((string)$file["tmp_name"], $path)) return [false,"","Gagal menyimpan file."];
    return [true,$name,""];
}

function upload_one_video(array $file, int $maxBytes, string $destDir): array {
    if (!isset($file["tmp_name"]) || !is_uploaded_file($file["tmp_name"])) {
        return [false,"","File video wajib diupload."];
    }

    $errCode = (int)($file["error"] ?? UPLOAD_ERR_OK);
    if ($errCode !== UPLOAD_ERR_OK) {
        $msg = upload_error_to_message($errCode);
        return [false,"", $msg !== "" ? $msg : "Upload gagal."];
    }

    if ((int)($file["size"] ?? 0) > $maxBytes) {
        return [false,"","Ukuran video maksimal 50MB."];
    }

    $ext = strtolower(pathinfo((string)($file["name"] ?? ""), PATHINFO_EXTENSION));
    $allowedExt = ['mp4','mov','webm','mkv'];
    if (!in_array($ext, $allowedExt, true)) {
        return [false,"","Tipe file video tidak didukung."];
    }

    $name = safe_name("materi_video", $ext);
    $path = rtrim($destDir,"/") . "/" . $name;

    if (!move_uploaded_file((string)$file["tmp_name"], $path)) {
        return [false,"","Gagal menyimpan file."];
    }

    return [true,$name,""];
}

function upload_thumbnail(?array $file): ?string {
    global $UPLOAD_DIR_THUMB;

    if (!$file || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $err = (int)($file['error'] ?? UPLOAD_ERR_OK);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload thumbnail gagal.");
    }

    if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException("Ukuran thumbnail maksimal 2MB.");
    }

    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        throw new RuntimeException("Thumbnail hanya boleh JPG, JPEG, PNG, atau WEBP.");
    }

    $name = safe_name('thumb_materi', $ext);
    $dest = $UPLOAD_DIR_THUMB . "/" . $name;

    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
        throw new RuntimeException("Gagal menyimpan thumbnail.");
    }

    return $name;
}

function ensure_tables(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS materi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(255) NOT NULL,
            bagian ENUM(
              'Keuangan, Umum dan Logistik',
              'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
              'Perencanaan, Data dan Informasi'
            ) NOT NULL DEFAULT 'Keuangan, Umum dan Logistik',
            tipe ENUM('pdf','video','link') NOT NULL DEFAULT 'pdf',
            jumlah_slide INT NOT NULL DEFAULT 0,
            thumbnail VARCHAR(255) DEFAULT NULL,
            content_url TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!column_exists('materi', 'bagian')) {
        @db()->exec("
            ALTER TABLE materi
            ADD COLUMN bagian ENUM(
              'Keuangan, Umum dan Logistik',
              'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
              'Perencanaan, Data dan Informasi'
            ) NOT NULL DEFAULT 'Keuangan, Umum dan Logistik'
            AFTER judul
        ");
    }

    @db()->exec("
        ALTER TABLE materi
        MODIFY COLUMN bagian ENUM(
          'Keuangan, Umum dan Logistik',
          'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
          'Perencanaan, Data dan Informasi'
        ) NOT NULL DEFAULT 'Keuangan, Umum dan Logistik'
    ");

    if (!column_exists('materi', 'tipe')) {
        @db()->exec("ALTER TABLE materi ADD COLUMN tipe ENUM('pdf','video','link') NOT NULL DEFAULT 'pdf' AFTER bagian");
    }

    if (!column_exists('materi', 'thumbnail')) {
        if (column_exists('materi', 'thumbnail_path')) {
            @db()->exec("ALTER TABLE materi CHANGE COLUMN thumbnail_path thumbnail VARCHAR(255) DEFAULT NULL");
        } else {
            @db()->exec("ALTER TABLE materi ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER jumlah_slide");
        }
    }

    if (!column_exists('materi', 'content_url')) {
        @db()->exec("ALTER TABLE materi ADD COLUMN content_url TEXT DEFAULT NULL AFTER thumbnail");
    }

    if (!index_exists('materi', 'uq_materi_judul')) {
        @db()->exec("ALTER TABLE materi ADD UNIQUE KEY uq_materi_judul (judul)");
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS materi_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            materi_id INT NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_materi_media FOREIGN KEY (materi_id) REFERENCES materi(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function remove_media_files(int $materiId): void {
    global $UPLOAD_DIR;

    $st = db()->prepare("SELECT file_path FROM materi_media WHERE materi_id=?");
    $st->execute([$materiId]);

    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $file = (string)$f["file_path"];
        $p = $UPLOAD_DIR . "/" . $file;
        if (is_file($p)) @unlink($p);
    }

    db()->prepare("DELETE FROM materi_media WHERE materi_id=?")->execute([$materiId]);
}

ensure_tables();

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
            header("Location: login.php");
            exit;
        }

        if ($action === "add" || $action === "edit") {
            $judul  = trim((string)($_POST["judul"] ?? ""));
            $bagian = trim((string)($_POST["bagian"] ?? ""));
            $mode   = (string)($_POST["mode"] ?? "pdf");
            $contentUrl = trim((string)($_POST["content_url"] ?? ""));

            if ($judul === "") throw new RuntimeException("Judul wajib diisi.");
            validate_judul_or_throw($judul);

            if (!in_array($mode, ['pdf','video','link'], true)) {
                throw new RuntimeException("Tipe materi tidak valid.");
            }

            if ($bagian === "" || !in_array($bagian, $GLOBALS['BAGIAN_OPTIONS'], true)) {
                throw new RuntimeException("Bagian wajib dipilih.");
            }

            $newThumb = upload_thumbnail($_FILES["thumbnail"] ?? null);

            db()->beginTransaction();

            if ($action === "add") {
                db()->prepare("
                    INSERT INTO materi (judul, bagian, tipe, jumlah_slide, thumbnail, content_url)
                    VALUES (?, ?, ?, 0, ?, NULL)
                ")->execute([$judul, $bagian, $mode, $newThumb]);
                $materiId = (int)db()->lastInsertId();
            } else {
                $materiId = (int)($_POST["id"] ?? 0);
                if ($materiId <= 0) throw new RuntimeException("ID tidak valid.");

                $st = db()->prepare("SELECT * FROM materi WHERE id=?");
                $st->execute([$materiId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new RuntimeException("Materi tidak ditemukan.");

                $thumbFinal = $newThumb ?: (string)($row["thumbnail"] ?? "");

                db()->prepare("
                    UPDATE materi
                    SET judul=?, bagian=?, tipe=?, thumbnail=?
                    WHERE id=?
                ")->execute([$judul, $bagian, $mode, $thumbFinal, $materiId]);
            }

            if ($mode === "pdf") {
                $hasNewPdf = isset($_FILES["pdf"]) && ((int)($_FILES["pdf"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
                $shouldReplaceMedia = ($action === "add") || $hasNewPdf;

                if ($shouldReplaceMedia) {
                    if (!$hasNewPdf) throw new RuntimeException("Silakan pilih file.");

                    remove_media_files($materiId);

                    [$ok,$fn,$err] = upload_one_pdf($_FILES["pdf"] ?? [], 10*1024*1024, $UPLOAD_DIR);
                    if (!$ok) throw new RuntimeException($err);

                    $pages = count_pdf_pages($UPLOAD_DIR . "/" . $fn);

                    db()->prepare("INSERT INTO materi_media (materi_id, file_path, sort_order) VALUES (?, ?, 0)")
                        ->execute([$materiId, $fn]);

                    db()->prepare("UPDATE materi SET jumlah_slide=?, content_url=NULL WHERE id=?")
                        ->execute([$pages, $materiId]);
                } else {
                    db()->prepare("UPDATE materi SET content_url=NULL WHERE id=?")->execute([$materiId]);
                }
            } elseif ($mode === "video") {
                $hasNewVideo = isset($_FILES["video"]) && ((int)($_FILES["video"]["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
                $shouldReplaceVideo = ($action === "add") || $hasNewVideo;

                if ($shouldReplaceVideo) {
                    if (!$hasNewVideo) throw new RuntimeException("File video wajib diupload.");

                    remove_media_files($materiId);

                    [$ok,$fn,$err] = upload_one_video($_FILES["video"] ?? [], 50*1024*1024, $UPLOAD_DIR);
                    if (!$ok) throw new RuntimeException($err);

                    db()->prepare("INSERT INTO materi_media (materi_id, file_path, sort_order) VALUES (?, ?, 0)")
                        ->execute([$materiId, $fn]);

                    db()->prepare("UPDATE materi SET jumlah_slide=0, content_url=NULL WHERE id=?")
                        ->execute([$materiId]);
                } else {
                    db()->prepare("UPDATE materi SET content_url=NULL WHERE id=?")->execute([$materiId]);
                }
            } elseif ($mode === "link") {
                $finalUrl = validate_url_or_throw($contentUrl, "URL Link");
                remove_media_files($materiId);
                db()->prepare("UPDATE materi SET jumlah_slide=0, content_url=? WHERE id=?")
                    ->execute([$finalUrl, $materiId]);
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
    header("Location: " . ($_SERVER["PHP_SELF"] ?? "tambah_materi_admin.php"));
    exit;
}

if (!$toast["type"] && isset($_SESSION["flash_toast"]) && is_array($_SESSION["flash_toast"])) {
    $t = $_SESSION["flash_toast"];
    unset($_SESSION["flash_toast"]);
    if (isset($t["type"], $t["msg"])) $toast = ["type" => (string)$t["type"], "msg" => (string)$t["msg"]];
}

$rows = db()->query("SELECT * FROM materi ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$mediaByMateri = [];
$st = db()->query("SELECT materi_id, file_path, sort_order FROM materi_media ORDER BY materi_id DESC, sort_order ASC, id ASC");
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
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
  padding:18px 24px;
  display:grid;
  grid-template-columns:90px 1fr 240px 140px 120px 90px;
  align-items:center;
  font-weight:900;font-size:18px;color:#111;
}
.table-row{
  padding:18px 24px;
  display:grid;
  grid-template-columns:90px 1fr 240px 140px 120px 90px;
  align-items:center;
  border-top:1px solid var(--row-line);
  font-size:15px;color:#111;
}

.cell-center{text-align:center;}

.type-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:8px 12px;
  border-radius:999px;
  background:rgba(112,13,9,.08);
  color:var(--maroon);
  font-weight:800;
  font-size:12px;
  border:1px solid rgba(112,13,9,.18);
}

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

.file-pill-wrap{
  width:min(520px,100%);
}

.file-pill-input{
  display:none;
}

.file-pill{
  width:100%;
  min-height:46px;
  border:2px solid #111;
  border-radius:999px;
  padding:8px 14px;
  background:#fff;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  cursor:pointer;
}

.file-pill-btn{
  background:var(--maroon);
  color:#fff;
  border-radius:999px;
  padding:7px 14px;
  font-size:12px;
  font-weight:800;
  line-height:1;
  white-space:nowrap;
}

.file-pill-name{
  flex:1;
  min-width:0;
  color:#333;
  font-size:13px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
}

.thumb-preview{
  margin-top:12px;
  width:160px;
  height:110px;
  border-radius:16px;
  overflow:hidden;
  background:#ececec;
  display:flex;
  align-items:center;
  justify-content:center;
  color:#999;
  border:1px solid #ddd;
}
.thumb-preview img{
  width:100%;
  height:100%;
  object-fit:cover;
}

.mode-switch{
  width:240px;background:#d9d9d9;border-radius:999px;padding:6px;display:flex;gap:6px;user-select:none;
}
.mode-pill{
  flex:1;border-radius:999px;padding:8px 0;text-align:center;font-weight:900;cursor:pointer;color:#fff;font-size:13px;
}
.mode-pill.inactive{opacity:.55;background:transparent;color:#fff;}
.mode-pill.active{background:var(--maroon);}

.modal-dialog{ max-width:760px; }
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
  max-height:76vh;
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

.url-box{
  border:2px solid #111;
  border-radius:18px;
  padding:12px 14px;
  font-size:14px;
  outline:none;
  width:100%;
  min-height:90px;
  resize:vertical;
}

.file-preview-wrap{
  margin-top:12px;
}

.actions-row{display:flex;justify-content:flex-end;margin-top:16px;gap:10px;flex-wrap:wrap;}
.btn-save{border:0;background:var(--maroon);color:#fff;font-weight:800;font-size:14px;padding:12px 44px;border-radius:14px;}

.btn-edit-file{
  border:0;background:var(--maroon);color:#fff;font-weight:800;font-size:12px;
  padding:10px 14px;border-radius:999px;display:inline-flex;align-items:center;gap:8px;white-space:nowrap;
  box-shadow:0 10px 18px rgba(0,0,0,.15);
  transition:transform .15s ease, filter .15s ease;
}
.btn-edit-file:hover{filter:brightness(.95);transform:translateY(1px);}
.btn-edit-file:active{transform:translateY(2px);}

.preview-header{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.preview-title{font-weight:800;font-size:14px;margin:0;line-height:1.2;}
.preview-subtitle{font-style:italic;font-size:12px;margin-top:4px;margin-bottom:0;}

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

@media (max-width: 576px){
  .title{font-size:32px;}
  .subtitle{font-size:12px;}
  .btn-add{font-size:12px;padding:10px 18px;margin-top:10px;}
  .table-grid{min-width:980px;}
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
      <div class="subtitle">Klik tombol edit untuk memperbarui file, thumbnail, judul, tipe, atau bagian materi.</div>
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
        <div class="text">JUDUL MATERI</div>
        <div class="text">BAGIAN</div>
        <div class="text-center">TIPE</div>
        <div class="text-center">JUMLAH</div>
        <div></div>
      </div>

      <?php foreach ($rows as $r): ?>
        <?php
          $rid = (int)$r["id"];
          $media = $mediaByMateri[$rid] ?? [];
          $file = $media[0] ?? "";
          $bagian = (string)($r["bagian"] ?? $DEFAULT_BAGIAN);
          $thumbnail = (string)($r["thumbnail"] ?? "");
          $tipe = (string)($r["tipe"] ?? "pdf");
          $contentUrl = (string)($r["content_url"] ?? "");
        ?>
        <div class="table-row table-grid">
          <div class="cell-center">
            <button class="icon-btn btn-edit"
                    type="button"
                    data-id="<?= $rid ?>"
                    data-judul="<?= htmlspecialchars((string)$r["judul"]) ?>"
                    data-bagian="<?= htmlspecialchars($bagian) ?>"
                    data-file="<?= htmlspecialchars($file) ?>"
                    data-tipe="<?= htmlspecialchars($tipe) ?>"
                    data-thumbnail="<?= htmlspecialchars($thumbnail) ?>"
                    data-content-url="<?= htmlspecialchars($contentUrl) ?>">
              <i class="bi bi-pencil-fill icon-edit"></i>
            </button>
          </div>

          <div><?= htmlspecialchars((string)$r["judul"]) ?></div>
          <div><?= htmlspecialchars($bagian) ?></div>
          <div class="cell-center">
            <span class="type-badge">
              <?php if ($tipe === 'pdf'): ?><i class="bi bi-file-earmark-pdf"></i><?php endif; ?>
              <?php if ($tipe === 'video'): ?><i class="bi bi-play-circle"></i><?php endif; ?>
              <?php if ($tipe === 'link'): ?><i class="bi bi-link-45deg"></i><?php endif; ?>
              <?= strtoupper(htmlspecialchars($tipe)) ?>
            </span>
          </div>
          <div class="cell-center">
            <?= $tipe === 'pdf' ? (int)$r["jumlah_slide"] . " slide" : "-" ?>
          </div>

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
        <div class="modal-subtitle-custom">Materi bisa berupa PDF, file video, atau link</div>
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

        <div class="label-plain mt-3">Bagian</div>
        <div class="select-wrap">
          <select class="input-pill" name="bagian" id="bagianInput" required>
            <?php foreach ($BAGIAN_OPTIONS as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= $opt === $DEFAULT_BAGIAN ? "selected" : "" ?>>
                <?= htmlspecialchars($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="label-plain mt-3">Thumbnail Materi (opsional)</div>
        <div class="file-pill-wrap">
          <input type="file" name="thumbnail" id="thumbnailInput" class="file-pill-input" accept=".jpg,.jpeg,.png,.webp">
          <label class="file-pill" for="thumbnailInput">
            <span class="file-pill-btn">Pilih File</span>
            <span class="file-pill-name" id="thumbnailName">Belum ada file dipilih</span>
          </label>
        </div>
        <div id="thumbPreview" class="thumb-preview" style="display:none;">
          <img id="thumbPreviewImg" src="" alt="Preview Thumbnail">
        </div>

        <div class="label-plain mt-3">Jenis Materi</div>
        <div class="mode-switch" id="modeSwitch">
          <div class="mode-pill active" data-mode="pdf">PDF</div>
          <div class="mode-pill inactive" data-mode="video">Video</div>
          <div class="mode-pill inactive" data-mode="link">Link</div>
        </div>

        <div id="pdfArea">
          <div class="label-plain mt-3">Input Materi PDF</div>
          <div class="text-muted" style="font-size:12px;">File PDF</div>

          <input id="pdfPicker" name="pdf" type="file" accept="application/pdf" class="d-none">

          <div class="dropzone" id="dropzonePdf">
            <div>
              <div class="dz-icon"><i class="bi bi-file-earmark-pdf"></i></div>
              <div class="dz-text" id="pdfText">Klik atau seret file PDF ke sini</div>
            </div>
          </div>

          <div class="file-preview-wrap" id="pdfPreviewWrap" style="display:none;">
            <div class="preview-header">
              <div>
                <p class="preview-title">Preview PDF</p>
                <p class="preview-subtitle">(tampilan cepat untuk admin)</p>
              </div>

              <button type="button" class="btn-edit-file" id="btnEditPdf" style="display:none;">
                <i class="bi bi-pencil-square"></i>
                <span>Edit PDF</span>
              </button>
            </div>

            <div style="margin-top:10px;border:1px solid #e6e6e6;border-radius:16px;overflow:hidden;">
              <iframe id="pdfPreviewFrame" src="" style="width:100%;height:420px;border:0;background:#fff;" title="Preview PDF"></iframe>
            </div>
          </div>
        </div>

        <div id="videoArea" style="display:none;">
          <div class="label-plain mt-3">Input File Video</div>
          <div class="text-muted" style="font-size:12px;">Format: mp4, mov, webm, mkv. Maksimal 50MB.</div>

          <input id="videoPicker" name="video" type="file" accept="video/mp4,video/quicktime,video/webm,.mkv" class="d-none">

          <div class="dropzone" id="dropzoneVideo">
            <div>
              <div class="dz-icon"><i class="bi bi-play-circle"></i></div>
              <div class="dz-text" id="videoText">Klik atau seret file video ke sini</div>
            </div>
          </div>

          <div class="file-preview-wrap" id="videoPreviewWrap" style="display:none;">
            <div class="preview-header">
              <div>
                <p class="preview-title">Preview Video</p>
                <p class="preview-subtitle">(tampilan cepat untuk admin)</p>
              </div>

              <button type="button" class="btn-edit-file" id="btnEditVideo" style="display:none;">
                <i class="bi bi-pencil-square"></i>
                <span>Edit Video</span>
              </button>
            </div>

            <div style="margin-top:10px;border:1px solid #e6e6e6;border-radius:16px;overflow:hidden;padding:10px;background:#fff;">
              <video id="videoPreview" controls style="width:100%;max-height:360px;background:#000;"></video>
            </div>
          </div>
        </div>

        <div id="urlArea" style="display:none;">
          <div class="label-plain mt-3" id="urlLabel">URL Link</div>
          <textarea class="url-box" name="content_url" id="contentUrlInput" placeholder="https://..."></textarea>
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
  const thumbnailInput = document.getElementById('thumbnailInput');
  const thumbnailName = document.getElementById('thumbnailName');
  const thumbPreview = document.getElementById('thumbPreview');
  const thumbPreviewImg = document.getElementById('thumbPreviewImg');

  const modeInput = document.getElementById('modeInput');
  const modeSwitch = document.getElementById('modeSwitch');

  const pdfArea = document.getElementById('pdfArea');
  const videoArea = document.getElementById('videoArea');
  const urlArea = document.getElementById('urlArea');
  const urlLabel = document.getElementById('urlLabel');
  const contentUrlInput = document.getElementById('contentUrlInput');

  const pdfPicker = document.getElementById('pdfPicker');
  const dropzonePdf = document.getElementById('dropzonePdf');
  const pdfText = document.getElementById('pdfText');
  const pdfPreviewWrap = document.getElementById('pdfPreviewWrap');
  const pdfPreviewFrame = document.getElementById('pdfPreviewFrame');
  const btnEditPdf = document.getElementById('btnEditPdf');

  const videoPicker = document.getElementById('videoPicker');
  const dropzoneVideo = document.getElementById('dropzoneVideo');
  const videoText = document.getElementById('videoText');
  const videoPreviewWrap = document.getElementById('videoPreviewWrap');
  const videoPreview = document.getElementById('videoPreview');
  const btnEditVideo = document.getElementById('btnEditVideo');

  const UPLOADS_PUBLIC_BASE = "uploads/materi";

  const materiForm = document.getElementById('materiForm');
  const materiModal = new bootstrap.Modal(materiModalEl, { backdrop: true, keyboard: true });

  let currentAction = "add";
  let objectUrlPdf = null;
  let objectUrlVideo = null;
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
      mode: modeInput.value || "pdf",
      contentUrl: (contentUrlInput.value || "").trim(),
      hasPdf: (pdfPicker.files && pdfPicker.files.length > 0),
      hasVideo: (videoPicker.files && videoPicker.files.length > 0),
      hasThumb: (thumbnailInput.files && thumbnailInput.files.length > 0)
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

  function setFileToInput(inputEl, file){
    const dt = new DataTransfer();
    dt.items.add(file);
    inputEl.files = dt.files;
  }

  function clearPdfPreview(){
    if (objectUrlPdf) {
      URL.revokeObjectURL(objectUrlPdf);
      objectUrlPdf = null;
    }
    pdfPreviewFrame.src = "";
    pdfPreviewWrap.style.display = "none";
  }

  function clearVideoPreview(){
    if (objectUrlVideo) {
      URL.revokeObjectURL(objectUrlVideo);
      objectUrlVideo = null;
    }
    videoPreview.src = "";
    videoPreviewWrap.style.display = "none";
  }

  function showPdfPreview(url){
    pdfPreviewFrame.src = url;
    pdfPreviewWrap.style.display = "block";
  }

  function showVideoPreview(url){
    videoPreview.src = url;
    videoPreviewWrap.style.display = "block";
  }

  function showThumb(src){
    if (!src) {
      thumbPreview.style.display = "none";
      thumbPreviewImg.src = "";
      return;
    }
    thumbPreview.style.display = "flex";
    thumbPreviewImg.src = src;
  }

  function validatePdfFile(file){
    if(!file) return "File tidak ditemukan.";

    const name = (file.name || "").toLowerCase();
    const nameOk = name.endsWith(".pdf");

    const allowedTypes = ["application/pdf","application/x-pdf","application/octet-stream"];
    const type = (file.type || "").toLowerCase();
    const typeOk = (type === "" || allowedTypes.includes(type));

    if(!nameOk && !typeOk) return "Tipe file harus PDF.";
    if(file.size > 10 * 1024 * 1024) return "Ukuran file terlalu besar.";
    return "";
  }

  function validateVideoFile(file){
    if(!file) return "File video wajib diupload.";
    const name = (file.name || "").toLowerCase();
    const allowed = [".mp4",".mov",".webm",".mkv"];
    const ok = allowed.some(ext => name.endsWith(ext));
    if(!ok) return "Tipe file video tidak didukung.";
    if(file.size > 50 * 1024 * 1024) return "Ukuran video maksimal 50MB.";
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

  function setTypeMode(mode){
    modeInput.value = mode;

    modeSwitch.querySelectorAll(".mode-pill").forEach(p=>{
      const on = p.dataset.mode === mode;
      p.classList.toggle("active", on);
      p.classList.toggle("inactive", !on);
    });

    pdfArea.style.display = mode === "pdf" ? "block" : "none";
    videoArea.style.display = mode === "video" ? "block" : "none";
    urlArea.style.display = mode === "link" ? "block" : "none";
    urlLabel.textContent = "URL Link";
  }

  function setModeAdd(){
    dropzonePdf.style.display = "flex";
    dropzoneVideo.style.display = "flex";
    pdfText.textContent = "Klik atau seret file PDF ke sini";
    videoText.textContent = "Klik atau seret file video ke sini";
    btnEditPdf.style.display = "none";
    btnEditVideo.style.display = "none";
  }

  function setModeEdit(){
    dropzonePdf.style.display = "none";
    dropzoneVideo.style.display = "none";
    btnEditPdf.style.display = "inline-flex";
    btnEditVideo.style.display = "inline-flex";
  }

  function resetModal(){
    judulInput.value = "";
    bagianInput.selectedIndex = 0;
    actionInput.value = "add";
    idInput.value = "";
    pdfPicker.value = "";
    videoPicker.value = "";
    contentUrlInput.value = "";
    thumbnailInput.value = "";
    thumbnailName.textContent = "Belum ada file dipilih";
    showThumb("");
    clearPdfPreview();
    clearVideoPreview();
    setModeAdd();
    setTypeMode("pdf");
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
  btnEditVideo.addEventListener('click', () => videoPicker.click());

  btnOpenAdd.addEventListener('click', () => {
    currentAction = "add";
    modalTitle.textContent = "Materi Baru";
    resetModal();
    setModeAdd();
    materiModal.show();
    setTimeout(setInitialSnapshot, 0);
  });

  modeSwitch.addEventListener("click", (e)=>{
    const pill = e.target.closest(".mode-pill");
    if(!pill) return;
    setTypeMode(pill.dataset.mode);
    recomputeDirty();
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

      const tipe = btn.dataset.tipe || "pdf";
      const existingFile = btn.dataset.file || "";
      const contentUrl = btn.dataset.contentUrl || "";
      const thumb = btn.dataset.thumbnail || "";

      setTypeMode(tipe);

      if (thumb) {
        thumbnailName.textContent = thumb;
        showThumb("uploads/thumbnails/" + thumb);
      }

      if (tipe === "pdf") {
        setModeEdit();
        if (existingFile) {
          const url = `${UPLOADS_PUBLIC_BASE}/${encodeURIComponent(existingFile)}?v=${Date.now()}`;
          showPdfPreview(url);
        } else {
          clearPdfPreview();
        }
      } else if (tipe === "video") {
        setModeEdit();
        if (existingFile) {
          const url = `${UPLOADS_PUBLIC_BASE}/${encodeURIComponent(existingFile)}?v=${Date.now()}`;
          showVideoPreview(url);
        } else {
          clearVideoPreview();
        }
      } else {
        clearPdfPreview();
        clearVideoPreview();
        contentUrlInput.value = contentUrl;
      }

      materiModal.show();
      setTimeout(setInitialSnapshot, 0);
    });
  });

  function handlePdfSelect(file){
    const msg = validatePdfFile(file);
    if(msg){ showError(msg); return; }
    setFileToInput(pdfPicker, file);

    if (currentAction === "add") pdfText.textContent = `File dipilih: ${file.name}`;

    if (objectUrlPdf) URL.revokeObjectURL(objectUrlPdf);
    objectUrlPdf = URL.createObjectURL(file);
    showPdfPreview(objectUrlPdf);

    recomputeDirty();
  }

  function handleVideoSelect(file){
    const msg = validateVideoFile(file);
    if(msg){ showError(msg); return; }
    setFileToInput(videoPicker, file);

    if (currentAction === "add") videoText.textContent = `File dipilih: ${file.name}`;

    if (objectUrlVideo) URL.revokeObjectURL(objectUrlVideo);
    objectUrlVideo = URL.createObjectURL(file);
    showVideoPreview(objectUrlVideo);

    recomputeDirty();
  }

  pdfPicker.addEventListener('change', () => {
    const f = (pdfPicker.files || [])[0];
    if(f) handlePdfSelect(f);
    recomputeDirty();
  });

  videoPicker.addEventListener('change', () => {
    const f = (videoPicker.files || [])[0];
    if(f) handleVideoSelect(f);
    recomputeDirty();
  });

  thumbnailInput.addEventListener('change', ()=>{
    if (thumbnailInput.files && thumbnailInput.files[0]) {
      const file = thumbnailInput.files[0];
      thumbnailName.textContent = file.name;
      const reader = new FileReader();
      reader.onload = e => showThumb(e.target.result);
      reader.readAsDataURL(file);
    } else {
      thumbnailName.textContent = "Belum ada file dipilih";
      showThumb("");
    }
    recomputeDirty();
  });

  dropzonePdf.addEventListener('click', () => pdfPicker.click());
  dropzonePdf.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzonePdf.classList.add('dragover');
  });
  dropzonePdf.addEventListener('dragleave', () => dropzonePdf.classList.remove('dragover'));
  dropzonePdf.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzonePdf.classList.remove('dragover');
    const f = (e.dataTransfer.files || [])[0];
    if(f) handlePdfSelect(f);
    recomputeDirty();
  });

  dropzoneVideo.addEventListener('click', () => videoPicker.click());
  dropzoneVideo.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzoneVideo.classList.add('dragover');
  });
  dropzoneVideo.addEventListener('dragleave', () => dropzoneVideo.classList.remove('dragover'));
  dropzoneVideo.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzoneVideo.classList.remove('dragover');
    const f = (e.dataTransfer.files || [])[0];
    if(f) handleVideoSelect(f);
    recomputeDirty();
  });

  judulInput.addEventListener('input', () => recomputeDirty());
  bagianInput.addEventListener('change', () => recomputeDirty());
  contentUrlInput.addEventListener('input', () => recomputeDirty());

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
    clearPdfPreview();
    clearVideoPreview();
    resetDirty();
    initialSnapshot = null;
  });

  materiForm.addEventListener("submit", (e) => {
    const judulMsg = validateJudul(judulInput.value);
    if(judulMsg){
      e.preventDefault();
      showError(judulMsg);
      return;
    }

    if(!bagianInput.value){
      e.preventDefault();
      showError("Bagian wajib dipilih.");
      return;
    }

    const mode = modeInput.value || "pdf";
    const hasPdf = (pdfPicker.files && pdfPicker.files.length > 0);
    const hasVideo = (videoPicker.files && videoPicker.files.length > 0);

    if (mode === "pdf") {
      if(currentAction === "add" && !hasPdf){
        e.preventDefault();
        showError("File wajib diupload.");
        return;
      }

      if(hasPdf){
        const msg = validatePdfFile(pdfPicker.files[0]);
        if(msg){
          e.preventDefault();
          showError(msg);
          return;
        }
      }
    }

    if (mode === "video") {
      if(currentAction === "add" && !hasVideo){
        e.preventDefault();
        showError("File video wajib diupload.");
        return;
      }

      if(hasVideo){
        const msg = validateVideoFile(videoPicker.files[0]);
        if(msg){
          e.preventDefault();
          showError(msg);
          return;
        }
      }
    }

    if (mode === "link") {
      const url = (contentUrlInput.value || "").trim();
      if (!url) {
        e.preventDefault();
        showError("URL Link wajib diisi.");
        return;
      }
    }

    if (bypassSaveConfirm) {
      const btn = document.getElementById("btnSave");
      btn.disabled = true;
      btn.innerHTML = "Menyimpan...";
      btn.style.opacity = "0.7";
      btn.style.pointerEvents = "none";
      return;
    }

    e.preventDefault();

    showConfirm({
      title: "Konfirmasi",
      message: "Yakin ingin disimpan?",
      okText: "Simpan",
      cancelText: "Batal",
      onOk: () => {
        bypassSaveConfirm = true;

        const btn = document.getElementById("btnSave");
        btn.disabled = true;
        btn.innerHTML = "Menyimpan...";
        btn.style.opacity = "0.7";
        btn.style.pointerEvents = "none";

        materiForm.requestSubmit
          ? materiForm.requestSubmit()
          : materiForm.submit();
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