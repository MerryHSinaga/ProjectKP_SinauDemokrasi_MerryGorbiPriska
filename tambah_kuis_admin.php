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

$activePage = 'kuis';
$self = (string)($_SERVER['PHP_SELF'] ?? 'tambah_kuis_admin.php');
$UPLOAD_DIR_THUMB = __DIR__ . "/uploads/thumbnails";

if (!is_dir($UPLOAD_DIR_THUMB)) {
    @mkdir($UPLOAD_DIR_THUMB, 0775, true);
}

const BAGIAN_ENUM = [
    'Keuangan, Umum dan Logistik',
    'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
    'Perencanaan, Data dan Informasi',
];

const MAX_SOAL = 100;
const DEFAULT_SOAL = 15;

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function do_logout_and_redirect(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $p["path"],
            $p["domain"],
            (bool)$p["secure"],
            (bool)$p["httponly"]
        );
    }
    session_destroy();
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && (string)($_POST["action"] ?? "") === "logout") {
    do_logout_and_redirect();
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

function validate_bagian(?string $bagian): ?string {
    $bagian = $bagian === null ? null : trim($bagian);
    if ($bagian === null || $bagian === "") return null;
    return in_array($bagian, BAGIAN_ENUM, true) ? $bagian : null;
}

function validate_judul(string $judul): string {
    $judul = trim($judul);
    if ($judul === "") {
        throw new RuntimeException("Judul kuis wajib diisi.");
    }
    if (mb_strlen($judul, "UTF-8") > 45) {
        throw new RuntimeException("Judul terlalu panjang. Maksimal 45 karakter (termasuk spasi).");
    }
    if (!preg_match('/^[\p{L}\p{N} \.\,\:\?]+$/u', $judul)) {
        throw new RuntimeException("Judul hanya boleh berisi huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).");
    }
    return $judul;
}

function judul_ellipsis(string $text, int $max = 40): string {
    $text = trim($text);
    if ($text === "") return "";
    if (mb_strlen($text, "UTF-8") <= $max) return $text;
    return mb_substr($text, 0, $max, "UTF-8") . "...";
}

function friendly_error_message(string $msg): string {
    $m = trim($msg);
    $lower = strtolower($m);

    if (str_contains($lower, "uq_kuis_paket_judul") || str_contains($lower, "duplicate entry")) {
        return "Judul kuis sudah digunakan. Silakan pakai judul lain.";
    }

    if (
        str_contains($lower, "sqlstate") ||
        str_contains($lower, "pdo") ||
        str_contains($lower, "syntax") ||
        str_contains($lower, "foreign key")
    ) {
        return "Terjadi kendala saat menyimpan data. Silakan coba lagi.";
    }

    return $m !== "" ? $m : "Gagal memproses permintaan. Silakan coba lagi.";
}

function safe_name(string $prefix, string $ext): string {
    return $prefix . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . "." . strtolower($ext);
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

    $name = safe_name('thumb_kuis', $ext);
    $dest = $UPLOAD_DIR_THUMB . "/" . $name;

    if (!move_uploaded_file((string)$file['tmp_name'], $dest)) {
        throw new RuntimeException("Gagal menyimpan thumbnail.");
    }

    return $name;
}

function ensure_tables(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS kuis_paket (
            id INT AUTO_INCREMENT PRIMARY KEY,
            judul VARCHAR(255) NOT NULL,
            input_mode ENUM('csv','manual') NOT NULL DEFAULT 'csv',
            bagian ENUM(
              'Keuangan, Umum dan Logistik',
              'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
              'Perencanaan, Data dan Informasi'
            ) DEFAULT NULL,
            thumbnail VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!column_exists('kuis_paket', 'input_mode')) {
        @db()->exec("ALTER TABLE kuis_paket ADD COLUMN input_mode ENUM('csv','manual') NOT NULL DEFAULT 'csv' AFTER judul");
    }

    if (!column_exists('kuis_paket', 'bagian')) {
        @db()->exec("
          ALTER TABLE kuis_paket
          ADD COLUMN bagian ENUM(
            'Keuangan, Umum dan Logistik',
            'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
            'Perencanaan, Data dan Informasi'
          ) DEFAULT NULL
          AFTER input_mode
      ");
    }
    
    @db()->exec("
    UPDATE kuis_paket
    SET bagian = 'Keuangan, Umum dan Logistik'
    WHERE bagian IN ('Keuangan', 'Umum dan Logistik')
    ");

    @db()->exec("
        UPDATE kuis_paket
        SET bagian = 'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM'
        WHERE bagian IN ('Teknis Penyelenggara Pemilu', 'Partisipasi Hubungan Masyarakat', 'Hukum dan SDM')
    ");

    @db()->exec("
        UPDATE kuis_paket
        SET bagian = 'Perencanaan, Data dan Informasi'
        WHERE bagian IN ('Perencanaan', 'Data dan Informasi')
    ");

    @db()->exec("
        ALTER TABLE kuis_paket
        MODIFY COLUMN bagian ENUM(
          'Keuangan, Umum dan Logistik',
          'Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM',
          'Perencanaan, Data dan Informasi'
        ) DEFAULT NULL
    ");

    if (!column_exists('kuis_paket', 'thumbnail')) {
        @db()->exec("ALTER TABLE kuis_paket ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER bagian");
    }

    if (!index_exists('kuis_paket', 'uq_kuis_paket_judul')) {
        @db()->exec("ALTER TABLE kuis_paket ADD UNIQUE KEY uq_kuis_paket_judul (judul)");
    }

    db()->exec("
        CREATE TABLE IF NOT EXISTS kuis_soal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            paket_id INT NOT NULL,
            nomor INT NOT NULL,
            pertanyaan TEXT NOT NULL,
            opsi_a VARCHAR(255) NOT NULL,
            opsi_b VARCHAR(255) NOT NULL,
            opsi_c VARCHAR(255) NOT NULL,
            opsi_d VARCHAR(255) NOT NULL,
            jawaban ENUM('A','B','C','D') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_paket_nomor (paket_id, nomor),
            CONSTRAINT fk_soal_paket FOREIGN KEY (paket_id) REFERENCES kuis_paket(id)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function paket_save(int $id, string $judul, string $mode, ?string $bagian, ?string $thumbnail = null): int {
    $judul = validate_judul($judul);
    $mode = strtolower(trim($mode));
    $bagian = validate_bagian($bagian);
    if (!in_array($mode, ["csv","manual"], true)) $mode = "csv";
    if ($bagian === null) throw new RuntimeException("Bagian wajib dipilih.");

    if ($id <= 0) {
        $st = db()->prepare("INSERT INTO kuis_paket (judul, input_mode, bagian, thumbnail) VALUES (?, ?, ?, ?)");
        $st->execute([$judul, $mode, $bagian, $thumbnail]);
        return (int)db()->lastInsertId();
    }

    if ($thumbnail !== null) {
        $st = db()->prepare("UPDATE kuis_paket SET judul=?, input_mode=?, bagian=?, thumbnail=? WHERE id=?");
        $st->execute([$judul, $mode, $bagian, $thumbnail, $id]);
    } else {
        $st = db()->prepare("UPDATE kuis_paket SET judul=?, input_mode=?, bagian=? WHERE id=?");
        $st->execute([$judul, $mode, $bagian, $id]);
    }

    return $id;
}

function paket_update_meta(int $id, string $judul, ?string $bagian, ?string $thumbnail = null): int {
    if ($id <= 0) throw new RuntimeException("Data paket tidak valid.");
    $judul = validate_judul($judul);
    $bagian = validate_bagian($bagian);
    if ($bagian === null) throw new RuntimeException("Bagian wajib dipilih.");

    if ($thumbnail !== null) {
        $st = db()->prepare("UPDATE kuis_paket SET judul=?, bagian=?, thumbnail=? WHERE id=?");
        $st->execute([$judul, $bagian, $thumbnail, $id]);
    } else {
        $st = db()->prepare("UPDATE kuis_paket SET judul=?, bagian=? WHERE id=?");
        $st->execute([$judul, $bagian, $id]);
    }

    return $id;
}

function soal_upsert(
    int $paketId,
    int $nomor,
    string $pertanyaan,
    string $a,
    string $b,
    string $c,
    string $d,
    string $jawaban
): void {
    if ($paketId <= 0) throw new RuntimeException("Data paket tidak valid.");

    if ($nomor < 1 || $nomor > MAX_SOAL) {
        throw new RuntimeException("Nomor soal harus 1 sampai " . MAX_SOAL . ".");
    }

    $pertanyaan = trim($pertanyaan);
    $a = trim($a); $b = trim($b); $c = trim($c); $d = trim($d);
    $jawaban = strtoupper(trim($jawaban));

    if ($pertanyaan === "" && $a === "" && $b === "" && $c === "" && $d === "" && $jawaban === "") return;

    if ($pertanyaan === "" || $a === "" || $b === "" || $c === "" || $d === "") {
        throw new RuntimeException("Nomor {$nomor}: Pertanyaan dan semua pilihan (A–D) harus diisi.");
    }
    if (!in_array($jawaban, ["A","B","C","D"], true)) {
        throw new RuntimeException("Nomor {$nomor}: Kunci jawaban harus A, B, C, atau D.");
    }

    $sql = "
        INSERT INTO kuis_soal (paket_id, nomor, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
          pertanyaan=VALUES(pertanyaan),
          opsi_a=VALUES(opsi_a),
          opsi_b=VALUES(opsi_b),
          opsi_c=VALUES(opsi_c),
          opsi_d=VALUES(opsi_d),
          jawaban=VALUES(jawaban)
    ";
    $st = db()->prepare($sql);
    $st->execute([$paketId, $nomor, $pertanyaan, $a, $b, $c, $d, $jawaban]);
}

function csv_parse_valid_rows(string $tmpPath): array {
    $fh = fopen($tmpPath, "r");
    if (!$fh) throw new RuntimeException("File CSV tidak bisa dibaca. Silakan coba upload ulang.");

    $rows = [];
    $line = 0;

    try {
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            $row = array_map(static fn($v) => is_string($v) ? trim($v) : "", $row);

            $allEmpty = true;
            foreach ($row as $v) {
                if ((string)$v !== "") { $allEmpty = false; break; }
            }
            if ($allEmpty) continue;

            if (count($row) < 7) {
                throw new RuntimeException("Format CSV tidak sesuai. Pastikan ada 7 kolom: nomor, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban.");
            }

            if ($line === 1 && !ctype_digit((string)$row[0])) continue;

            if (!ctype_digit((string)$row[0])) {
                throw new RuntimeException("Format CSV tidak sesuai. Kolom nomor harus angka.");
            }

            $nomor = (int)$row[0];
            $pertanyaan = (string)$row[1];
            $a = (string)$row[2];
            $b = (string)$row[3];
            $c = (string)$row[4];
            $d = (string)$row[5];
            $jawaban = strtoupper((string)$row[6]);

            if ($nomor < 1 || $nomor > MAX_SOAL) {
                throw new RuntimeException("Nomor soal pada CSV harus 1 sampai " . MAX_SOAL . ".");
            }
            if (trim($pertanyaan) === "" || trim($a) === "" || trim($b) === "" || trim($c) === "" || trim($d) === "") {
                throw new RuntimeException("Ada soal pada CSV yang belum lengkap. Pastikan pertanyaan dan opsi A–D terisi.");
            }
            if (!in_array($jawaban, ["A","B","C","D"], true)) {
                throw new RuntimeException("Kunci jawaban pada CSV harus A, B, C, atau D.");
            }

            $rows[] = [
                "nomor" => $nomor,
                "pertanyaan" => $pertanyaan,
                "a" => $a,
                "b" => $b,
                "c" => $c,
                "d" => $d,
                "jawaban" => $jawaban,
                "line" => $line,
            ];
        }
    } finally {
        fclose($fh);
    }

    if (count($rows) === 0) {
        throw new RuntimeException("CSV tidak berisi soal. Pastikan minimal ada 1 soal.");
    }

    return $rows;
}

ensure_tables();

if (isset($_GET["download"]) && $_GET["download"] === "template_csv") {
    $filename = "template_kuis.csv";
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $out = fopen("php://output", "w");
    if ($out === false) exit;

    fputcsv($out, ["nomor","pertanyaan","opsi_a","opsi_b","opsi_c","opsi_d","jawaban"]);
    fclose($out);
    exit;
}

if (isset($_GET["ajax"]) && $_GET["ajax"] === "paket_detail") {
    header("Content-Type: application/json; charset=utf-8");
    $id = (int)($_GET["id"] ?? 0);
    if ($id <= 0) { echo json_encode(["ok"=>false]); exit; }

    $p = db()->prepare("SELECT id, judul, input_mode, bagian, thumbnail FROM kuis_paket WHERE id=?");
    $p->execute([$id]);
    $paket = $p->fetch(PDO::FETCH_ASSOC);
    if (!$paket) { echo json_encode(["ok"=>false]); exit; }

    $soal = db()->prepare("
        SELECT nomor, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban
        FROM kuis_soal WHERE paket_id=? ORDER BY nomor ASC
    ");
    $soal->execute([$id]);
    $rows = $soal->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "ok"=>true,
        "paket"=>[
            "id" => (int)$paket["id"],
            "judul" => (string)$paket["judul"],
            "input_mode" => (string)$paket["input_mode"],
            "bagian" => $paket["bagian"] !== null ? (string)$paket["bagian"] : "",
            "thumbnail" => $paket["thumbnail"] !== null ? (string)$paket["thumbnail"] : "",
        ],
        "soal"=>$rows
    ]);
    exit;
}

$toast = ["type"=>"", "msg"=>""];

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = (string)($_POST["action"] ?? "");

        switch ($action) {
            case "paket_delete": {
                $id = (int)($_POST["paket_id"] ?? 0);
                if ($id <= 0) throw new RuntimeException("Data paket tidak valid.");
                db()->prepare("DELETE FROM kuis_paket WHERE id=?")->execute([$id]);
                $toast = ["type"=>"success","msg"=>"Paket kuis berhasil dihapus."];
                break;
            }

            case "paket_update_meta": {
                $paketId = (int)($_POST["paket_id"] ?? 0);
                $judulPaket = (string)($_POST["judul_paket"] ?? "");
                $bagian = (string)($_POST["bagian"] ?? "");
                $thumbnail = upload_thumbnail($_FILES["thumbnail"] ?? null);

                db()->beginTransaction();
                paket_update_meta($paketId, $judulPaket, $bagian, $thumbnail);
                db()->commit();

                $toast = ["type"=>"success","msg"=>"Perubahan berhasil disimpan."];
                break;
            }

            case "soal_save_bulk": {
                $paketId = (int)($_POST["paket_id"] ?? 0);
                $judulPaket = (string)($_POST["judul_paket"] ?? "");
                $bagian = (string)($_POST["bagian"] ?? "");
                $bulkJson = (string)($_POST["bulk_json"] ?? "");
                $thumbnail = upload_thumbnail($_FILES["thumbnail"] ?? null);

                if ($bulkJson === "") throw new RuntimeException("Data soal masih kosong.");

                $bulk = json_decode($bulkJson, true);
                if (!is_array($bulk)) throw new RuntimeException("Data soal tidak terbaca. Silakan coba lagi.");

                db()->beginTransaction();
                $paketId = paket_save($paketId, $judulPaket, "manual", $bagian, $thumbnail);

                $saved = 0;
                foreach ($bulk as $noStr => $d) {
                    $no = (int)$noStr;
                    if (!is_array($d)) continue;

                    soal_upsert(
                        $paketId,
                        $no,
                        (string)($d["pertanyaan"] ?? ""),
                        (string)($d["a"] ?? ""),
                        (string)($d["b"] ?? ""),
                        (string)($d["c"] ?? ""),
                        (string)($d["d"] ?? ""),
                        (string)($d["jawaban"] ?? "")
                    );

                    if (trim((string)($d["pertanyaan"] ?? "")) !== "") $saved++;
                }

                db()->commit();
                $toast = ["type"=>"success","msg"=>"Soal berhasil disimpan. Total terisi: {$saved} (maks " . MAX_SOAL . ")."];
                break;
            }

            case "csv_import": {
                $paketId = (int)($_POST["paket_id"] ?? 0);
                $judulPaket = (string)($_POST["judul_paket"] ?? "");
                $bagian = (string)($_POST["bagian"] ?? "");
                $thumbnail = upload_thumbnail($_FILES["thumbnail"] ?? null);

                if (!isset($_FILES["csv"]) || !is_uploaded_file($_FILES["csv"]["tmp_name"])) {
                    throw new RuntimeException("File CSV wajib diupload.");
                }

                $parsedRows = csv_parse_valid_rows($_FILES["csv"]["tmp_name"]);

                db()->beginTransaction();
                $paketId = paket_save($paketId, $judulPaket, "csv", $bagian, $thumbnail);

                $saved = 0;
                foreach ($parsedRows as $r) {
                    soal_upsert(
                        $paketId,
                        (int)$r["nomor"],
                        (string)$r["pertanyaan"],
                        (string)$r["a"],
                        (string)$r["b"],
                        (string)$r["c"],
                        (string)$r["d"],
                        (string)$r["jawaban"]
                    );
                    $saved++;
                }

                db()->commit();
                $toast = ["type"=>"success","msg"=>"Import CSV berhasil. Total soal: {$saved} (maks " . MAX_SOAL . ")."];
                break;
            }

            case "logout":
                do_logout_and_redirect();
                break;
        }
    }
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $toast = ["type"=>"danger","msg"=>friendly_error_message($e->getMessage())];
}

$paket = db()->query("
    SELECT p.id, p.judul, p.input_mode, p.bagian, p.thumbnail,
           (SELECT COUNT(*) FROM kuis_soal s WHERE s.paket_id=p.id) AS jumlah_soal
    FROM kuis_paket p
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$site_title = 'Admin | Daftar Soal';
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
  --gold:#f4c430;
  --navbar-h:90px;
  --header-gray:#d9d9d9;
  --row-line:#e6e6e6;
  --shadow:0 14px 22px rgba(0,0,0,.18);
}
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
.btn-back i{font-size:22px;line-height:1;}
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
.nav-menu{display:flex;gap:26px;align-items:center;}
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
  font-weight:700;font-size:14px;
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
  background:var(--header-gray);padding:18px 24px;
  display:grid;
  grid-template-columns:90px 1fr 280px 220px 90px;
  align-items:center;
  font-weight:900;font-size:18px;color:#111;
}
.table-row{
  padding:18px 24px;display:grid;
  grid-template-columns:90px 1fr 280px 220px 90px;
  align-items:center;border-top:1px solid var(--row-line);font-size:15px;color:#111;
}
.cell-center{text-align:center;}
.icon-btn{
  border:0;background:transparent;padding:0;cursor:pointer;
  display:inline-flex;align-items:center;justify-content:center;
  width:44px;height:44px;border-radius:12px;
  transition:background .15s ease, transform .15s ease;
}
.icon-btn:hover{background:rgba(112,13,9,.08);transform:translateY(-1px);}
.icon-edit,.icon-trash{color:var(--maroon);font-size:22px;}
.modal-content{border:0;border-radius:28px;overflow:hidden;box-shadow:0 30px 60px rgba(0,0,0,.28);}
.modal-header-custom{background:var(--maroon);padding:22px 28px 16px;position:relative;}
.modal-title-custom{margin:0;color:#fff;font-weight:900;font-size:34px;line-height:1.05;}
.modal-subtitle-custom{margin-top:6px;color:rgba(255,255,255,.85);font-style:italic;font-size:13px;}
.modal-close-x{
  position:absolute;top:16px;right:18px;width:44px;height:44px;border-radius:12px;border:0;
  background:transparent;color:#fff;font-size:30px;display:flex;align-items:center;justify-content:center;
  opacity:.95;transition:opacity .15s ease, transform .15s ease;
}
.modal-close-x:hover{opacity:1;transform:scale(1.03);}
.modal-body{
  padding:18px 24px 22px;
  background:#fff;
  max-height:calc(100vh - 240px);
  overflow:auto;
}
.pill-input{
  border:2px solid #111;
  border-radius:999px;
  padding:10px 16px;
  font-size:14px;
  outline:none;
  width:100%;
  background:#fff;
}
.select-wrap{position:relative;width:100%;}
.select-wrap select.pill-input{
  width:100%;
  padding-right:52px;
  border-radius:999px;
  appearance:none;
  -webkit-appearance:none;
  -moz-appearance:none;
  background-image:none;
  background:#fff;
  line-height:1.2;
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
.top-form-grid{
  display:grid;
  grid-template-columns:minmax(0,1fr) auto;
  gap:18px 24px;
  align-items:end;
}
.top-form-grid .full-row{
  grid-column:1 / -1;
}
.mode-switch-wrap{
  display:flex;
  flex-direction:column;
  justify-content:flex-end;
  min-width:180px;
}
.mode-label{
  visibility:hidden;
  user-select:none;
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
textarea.big{
  border:2px solid #111;border-radius:18px;padding:12px 14px;font-size:14px;outline:none;width:100%;
  min-height:130px;resize:vertical;
}
.mode-switch{
  width:180px;
  background:#d9d9d9;
  border-radius:999px;
  padding:6px;
  display:flex;
  gap:6px;
  user-select:none;
}
.mode-pill{flex:1;border-radius:999px;padding:8px 0;text-align:center;font-weight:900;cursor:pointer;color:#fff;font-size:13px;}
.mode-pill.inactive{opacity:.55;background:transparent;color:#fff;}
.mode-pill.active{background:var(--maroon);}
.numbers{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;}
.num-btn{width:40px;height:40px;border-radius:999px;border:2px solid #111;background:#fff;font-weight:900;cursor:pointer;}
.num-btn.active{background:#e9edff;}
.num-btn.filled{border-color:var(--maroon);}
.num-add{
  width:40px;
  height:40px;
  border-radius:999px;
  border:2px dashed var(--maroon);
  background:#fff;
  color:var(--maroon);
  font-weight:900;
  font-size:22px;
  line-height:1;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  transition:transform .15s ease, background .15s ease;
}

.num-add:hover{
  background:#f7ebeb;
  transform:translateY(-1px);
}
.dropzone{
  margin-top:14px;height:170px;border-radius:18px;background:#d9d9d9;border:2px dashed rgba(112,13,9,.25);
  display:flex;align-items:center;justify-content:center;text-align:center;cursor:pointer;
  padding:14px;
}
.dropzone.dragover{outline:3px solid rgba(112,13,9,.35);}
.dropzone .dz-icon{font-size:50px;color:#fff;}
.dropzone .dz-text{color:#fff;font-size:14px;font-weight:800;word-break:break-word;}
.ans-grid{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;}
.ans-item{display:flex;align-items:center;gap:8px;border:2px solid #111;border-radius:999px;padding:10px 14px;cursor:pointer;user-select:none;}
.ans-item input{accent-color: var(--maroon); transform:scale(1.05);}
.ans-item.active{border-color:var(--maroon); background:#f3e9e9;}
.actions{display:flex;justify-content:flex-end;gap:12px;margin-top:16px;flex-wrap:wrap;}
.btn-save{border:0;background:var(--maroon);color:#fff;font-weight:900;font-size:14px;padding:12px 34px;border-radius:14px;}
.btn-outline{border:2px solid #111;background:#fff;color:#111;font-weight:800;font-size:14px;padding:12px 34px;border-radius:14px;}
.tpl-link{
  display:inline-flex;align-items:center;gap:8px;
  font-weight:900;font-size:12px;
  color:#333;text-decoration:none;
  padding:6px 10px;border-radius:999px;
  background:#f3f3f3;border:1px solid rgba(0,0,0,.12);
  transition:transform .15s ease, filter .15s ease;
  white-space:nowrap;
}
.tpl-link:hover{filter:brightness(.98);transform:translateY(-1px);}
.info-max{
  margin-top:8px;font-size:12px;font-weight:900;color:#700D09;
  background:rgba(112,13,9,.08);
  border:1px solid rgba(112,13,9,.18);
  padding:8px 10px;border-radius:12px;
  display:inline-flex;align-items:center;gap:8px;
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
.popup-title{font-weight:900;font-size:16px;margin:0 0 8px 0;color:#111;}
.popup-message{font-size:13px;color:#333;margin:0 0 18px 0;line-height:1.45;white-space:pre-wrap;}
.popup-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:6px;}
@media (max-width: 768px){
  .top-form-grid{
    grid-template-columns:1fr;
  }
  .mode-switch-wrap{
    min-width:unset;
  }
  .mode-label{
    display:none;
  }
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
    <a href="admin.php" class="btn-back" aria-label="Kembali">
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
      <h1 class="title">Daftar Soal</h1>
      <div class="subtitle">Klik tombol edit untuk memperbarui kuis, thumbnail, dan soal.</div>
    </div>

    <button class="btn-add" type="button" id="btnOpenAdd">
      <span>+ Tambah Soal</span>
    </button>
  </div>

  <?php if ($toast["type"] && $toast["type"] !== "danger"): ?>
    <div class="alert alert-<?= htmlspecialchars($toast["type"]) ?> mt-4"
        style="border-radius:16px;font-weight:800;max-width:980px;margin-left:auto;margin-right:auto;">
      <?= htmlspecialchars($toast["msg"]) ?>
    </div>
  <?php endif; ?>

  <section class="table-wrap">
    <div class="table-scroll">
      <div class="table-head table-grid">
        <div></div>
        <div class="text">JUDUL KUIS</div>
        <div class="text">BAGIAN</div>
        <div class="text-center">JUMLAH SOAL</div>
        <div></div>
      </div>

      <?php foreach ($paket as $p): ?>
        <?php
          $judulFull = (string)$p["judul"];
          $judulShow = judul_ellipsis($judulFull, 40);
          $bagianVal = (string)($p["bagian"] ?? "");
          $thumbnail = (string)($p["thumbnail"] ?? "");
        ?>
        <div class="table-row table-grid">
          <div class="cell-center">
            <button class="icon-btn btn-edit" type="button"
                    data-id="<?= (int)$p["id"] ?>"
                    data-judul="<?= htmlspecialchars($judulFull) ?>"
                    data-mode="<?= htmlspecialchars((string)$p["input_mode"]) ?>"
                    data-bagian="<?= htmlspecialchars($bagianVal) ?>"
                    data-thumbnail="<?= htmlspecialchars($thumbnail) ?>">
              <i class="bi bi-pencil-fill icon-edit"></i>
            </button>
          </div>

          <div title="<?= htmlspecialchars($judulFull) ?>">
            <?= htmlspecialchars($judulShow) ?>
          </div>

          <div title="<?= htmlspecialchars($bagianVal) ?>">
            <?= htmlspecialchars($bagianVal !== "" ? $bagianVal : "-") ?>
          </div>

          <div class="cell-center"><?= (int)$p["jumlah_soal"] ?></div>

          <div class="cell-center">
            <form method="post" class="form-delete-paket">
              <input type="hidden" name="action" value="paket_delete">
              <input type="hidden" name="paket_id" value="<?= (int)$p["id"] ?>">
              <button class="icon-btn btn-delete" type="submit" title="Hapus">
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

<div class="modal fade" id="kuisModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <form class="modal-content" id="kuisForm" method="post" enctype="multipart/form-data">
      <div class="modal-header-custom">
        <button type="button" class="modal-close-x" data-bs-dismiss="modal" aria-label="Close">&times;</button>
        <div class="modal-title-custom" id="modalTitle">Input Kuis</div>
        <div class="modal-subtitle-custom">Lengkapi formulir di bawah ini</div>
      </div>

      <div class="modal-body">
        <input type="hidden" name="action" id="actionInput" value="csv_import">
        <input type="hidden" name="paket_id" id="paketIdInput" value="">
        <input type="hidden" name="bulk_json" id="bulkJsonInput" value="">

        <div class="top-form-grid">
          <div class="full-row">
            <label class="fw-bold mb-2" style="font-size:14px;">Judul Kuis</label>
            <div class="text-muted fst-italic fw-light" style="font-size:12px;margin-top:-6px;margin-bottom:8px;">
              Maksimal 45 karakter (termasuk spasi). Hanya boleh huruf, angka, spasi, titik (.), koma (,), titik dua (:), dan tanda tanya (?).
            </div>
            <input class="pill-input" type="text" name="judul_paket" id="judulPaketInput" placeholder="Tuliskan Judul Kuis di sini..." required maxlength="45">
          </div>

          <div>
            <label class="fw-bold mb-2" style="font-size:14px;">Bagian</label>
            <div class="select-wrap">
              <select class="pill-input" id="bagianInput" name="bagian" required>
              <option value="">-- Pilih Bagian --</option>
              <option value="Keuangan, Umum dan Logistik">Keuangan, Umum dan Logistik</option>
              <option value="Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM">Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat, Hubungan dan SDM</option>
              <option value="Perencanaan, Data dan Informasi">Perencanaan, Data dan Informasi</option>
            </select>
            </div>
          </div>

          <div class="mode-switch-wrap">
            <label class="fw-bold mb-2 mode-label" style="font-size:14px;">Mode Input</label>
            <div class="mode-switch" id="modeSwitch">
              <div class="mode-pill active" data-mode="csv">CSV</div>
              <div class="mode-pill inactive" data-mode="manual">Manual</div>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <label class="fw-bold mb-2" style="font-size:14px;">Thumbnail Kuis (opsional)</label>
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
        </div>

        <div id="csvArea" class="mt-4">
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <label class="fw-bold mb-2" style="font-size:14px;">Input Kuis</label>
            <a class="tpl-link" href="<?= htmlspecialchars($self, ENT_QUOTES, 'UTF-8') ?>?download=template_csv" target="_blank" rel="noopener">
              <i class="bi bi-download"></i> Unduh Template CSV
            </a>
          </div>

          <div class="info-max">
            <i class="bi bi-exclamation-circle"></i> Awal tampil 15 soal. Klik tombol + untuk menambah nomor, maksimal <?= MAX_SOAL ?> soal.
          </div>

          <input type="file" name="csv" id="csvInput" accept=".csv,text/csv" class="d-none">
          <div class="dropzone" id="csvDrop">
            <div>
              <div class="dz-icon"><i class="bi bi-filetype-csv"></i></div>
              <div class="dz-text">Klik atau seret file CSV ke sini</div>
              <div class="dz-text" id="csvName" style="font-size:12px;opacity:.85;"></div>
            </div>
          </div>
          <div class="text-muted mt-2" style="font-size:12px;">
            Kolom wajib: <b>nomor, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, jawaban(A/B/C/D)</b>
          </div>
        </div>

        <div id="manualArea" class="mt-4" style="display:none;">
          <label class="fw-bold mb-2" style="font-size:14px;">Input Kuis</label>

          <div class="info-max">
            <i class="bi bi-exclamation-circle"></i> Maksimal <?= MAX_SOAL ?> soal (nomor 1–<?= MAX_SOAL ?>)
          </div>

          <div class="numbers" id="numbers"></div>
          <input type="hidden" id="nomorActive" value="1">

          <div class="mt-3">
            <label class="fw-bold mb-2" style="font-size:14px;">Pertanyaan</label>
            <textarea class="big" id="pertanyaanInput" placeholder="Tuliskan Pertanyaan di sini..."></textarea>
          </div>

          <div class="row g-3 mt-1">
            <div class="col-md-6">
              <label class="fw-bold mb-2" style="font-size:14px;">Pilihan A</label>
              <input class="pill-input" id="opsiA" placeholder="Jawaban A...">
            </div>
            <div class="col-md-6">
              <label class="fw-bold mb-2" style="font-size:14px;">Pilihan B</label>
              <input class="pill-input" id="opsiB" placeholder="Jawaban B...">
            </div>
            <div class="col-md-6">
              <label class="fw-bold mb-2" style="font-size:14px;">Pilihan C</label>
              <input class="pill-input" id="opsiC" placeholder="Jawaban C...">
            </div>
            <div class="col-md-6">
              <label class="fw-bold mb-2" style="font-size:14px;">Pilihan D</label>
              <input class="pill-input" id="opsiD" placeholder="Jawaban D...">
            </div>
          </div>

          <div class="mt-3">
            <label class="fw-bold mb-2" style="font-size:14px;">Kunci Jawaban Benar</label>
            <div class="ans-grid" id="ansGrid">
              <label class="ans-item" data-val="A"><input type="radio" name="jawaban_radio" value="A"> <span>A</span></label>
              <label class="ans-item" data-val="B"><input type="radio" name="jawaban_radio" value="B"> <span>B</span></label>
              <label class="ans-item" data-val="C"><input type="radio" name="jawaban_radio" value="C"> <span>C</span></label>
              <label class="ans-item" data-val="D"><input type="radio" name="jawaban_radio" value="D"> <span>D</span></label>
            </div>
          </div>
        </div>

        <div class="actions">
          <button class="btn-outline" type="button" data-bs-dismiss="modal">Batalkan</button>
          <button class="btn-save" type="submit">Simpan</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="popupOverlay" aria-hidden="true">
  <div class="modal-content-custom" role="dialog" aria-modal="true" aria-labelledby="popupTitle">
    <p class="popup-title" id="popupTitle">Konfirmasi</p>
    <p class="popup-message" id="popupMessage">Pesan</p>
    <div class="popup-actions" id="popupActions"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function(){
  const SELF = <?= json_encode($self, JSON_UNESCAPED_UNICODE) ?>;
  const MAX_SOAL = <?= MAX_SOAL ?>;
  const DEFAULT_SOAL = <?= DEFAULT_SOAL ?>;

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

  const modalEl = document.getElementById("kuisModal");
  const modal = new bootstrap.Modal(modalEl, { backdrop: true, keyboard: true });

  const btnOpenAdd = document.getElementById("btnOpenAdd");
  const modalTitle = document.getElementById("modalTitle");

  const actionInput     = document.getElementById("actionInput");
  const paketIdInput    = document.getElementById("paketIdInput");
  const judulPaketInput = document.getElementById("judulPaketInput");
  const bagianInput     = document.getElementById("bagianInput");
  const bulkJsonInput   = document.getElementById("bulkJsonInput");
  const thumbnailInput  = document.getElementById("thumbnailInput");
  const thumbnailName   = document.getElementById("thumbnailName");
  const thumbPreview    = document.getElementById("thumbPreview");
  const thumbPreviewImg = document.getElementById("thumbPreviewImg");

  const modeSwitch = document.getElementById("modeSwitch");
  const csvArea    = document.getElementById("csvArea");
  const manualArea = document.getElementById("manualArea");

  const csvInput = document.getElementById("csvInput");
  const csvDrop  = document.getElementById("csvDrop");
  const csvName  = document.getElementById("csvName");

  const numbers     = document.getElementById("numbers");
  const nomorActive = document.getElementById("nomorActive");

  const pertanyaanInput = document.getElementById("pertanyaanInput");
  const opsiA = document.getElementById("opsiA");
  const opsiB = document.getElementById("opsiB");
  const opsiC = document.getElementById("opsiC");
  const opsiD = document.getElementById("opsiD");
  const ansGrid = document.getElementById("ansGrid");

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

  document.querySelectorAll(".form-delete-paket").forEach((form) => {
    form.addEventListener("submit", (e) => {
      e.preventDefault();
      showConfirm({
        title: "Konfirmasi Penghapusan",
        message: "Apakah Anda yakin ingin menghapus soal ini?",
        okText: "Hapus",
        cancelText: "Batal",
        onOk: () => form.submit()
      });
    });
  });

  let isDirty = false;
  let forceClose = false;
  let allowSubmit = false;

  function markDirty(){ isDirty = true; }
  function clearDirty(){ isDirty = false; }

  function showThumb(src){
    if (!src) {
      thumbPreview.style.display = "none";
      thumbPreviewImg.src = "";
      return;
    }
    thumbPreview.style.display = "flex";
    thumbPreviewImg.src = src;
  }

  modalEl.addEventListener("hide.bs.modal", (e) => {
    if (forceClose) return;
    if (!isDirty) return;

    e.preventDefault();
    showConfirm({
      title: "Perhatian",
      message: "Perubahan belum disimpan, apakah Anda yakin ingin keluar?",
      okText: "Keluar",
      cancelText: "Batal",
      onOk: () => {
        forceClose = true;
        modal.hide();
        setTimeout(() => { forceClose = false; }, 0);
      }
    });
  });

  let currentMode = "csv";
  let cacheSoal = {};
  let visibleSoalCount = DEFAULT_SOAL;

  function setMode(mode){
    currentMode = mode;
    modeSwitch.querySelectorAll(".mode-pill").forEach(p=>{
      const on = p.dataset.mode === mode;
      p.classList.toggle("active", on);
      p.classList.toggle("inactive", !on);
    });
    csvArea.style.display = (mode === "csv") ? "block" : "none";
    manualArea.style.display = (mode === "manual") ? "block" : "none";
  }

  function clearJawabanRadio(){
    ansGrid.querySelectorAll("input[type=radio]").forEach(r => r.checked = false);
    ansGrid.querySelectorAll(".ans-item").forEach(x => x.classList.remove("active"));
  }

  function setJawabanRadio(val){
    clearJawabanRadio();
    const r = ansGrid.querySelector(`input[type=radio][value="${val}"]`);
    if(r) r.checked = true;
    const lab = ansGrid.querySelector(`.ans-item[data-val="${val}"]`);
    if(lab) lab.classList.add("active");
  }

  function getJawabanVal(){
    const r = ansGrid.querySelector("input[type=radio]:checked");
    return r ? r.value : "";
  }

  function saveDraft(){
    const no = parseInt(nomorActive.value,10);
    cacheSoal[no] = {
      pertanyaan: pertanyaanInput.value || "",
      a: opsiA.value || "",
      b: opsiB.value || "",
      c: opsiC.value || "",
      d: opsiD.value || "",
      jawaban: getJawabanVal() || ""
    };
  }

  function loadDraft(no){
    const d = cacheSoal[no] || {pertanyaan:"",a:"",b:"",c:"",d:"",jawaban:""};
    pertanyaanInput.value = d.pertanyaan;
    opsiA.value = d.a; opsiB.value = d.b; opsiC.value = d.c; opsiD.value = d.d;
    if(d.jawaban) setJawabanRadio(d.jawaban); else clearJawabanRadio();
  }

  function getHighestFilledNumber(){
  let highest = 0;
  Object.keys(cacheSoal).forEach(key => {
    const no = parseInt(key, 10);
    const item = cacheSoal[no];
    if (
      item &&
      (
        (item.pertanyaan || "").trim() !== "" ||
        (item.a || "").trim() !== "" ||
        (item.b || "").trim() !== "" ||
        (item.c || "").trim() !== "" ||
        (item.d || "").trim() !== "" ||
        (item.jawaban || "").trim() !== ""
      )
    ) {
      if (no > highest) highest = no;
    }
  });
  return highest;
}

function normalizeVisibleSoalCount(){
  const highestFilled = getHighestFilledNumber();
  const activeNo = parseInt(nomorActive.value || "1", 10);
  visibleSoalCount = Math.max(DEFAULT_SOAL, highestFilled, activeNo, Math.min(visibleSoalCount, MAX_SOAL));
  if (visibleSoalCount > MAX_SOAL) visibleSoalCount = MAX_SOAL;
}

function buildNumbers(){
  numbers.innerHTML = "";
  normalizeVisibleSoalCount();

  const activeNo = parseInt(nomorActive.value,10);

  for(let i=1;i<=visibleSoalCount;i++){
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "num-btn";
    btn.textContent = i;

    if(i === activeNo) btn.classList.add("active");
    if(cacheSoal[i] && (cacheSoal[i].pertanyaan || "").trim() !== "") btn.classList.add("filled");

    btn.addEventListener("click", ()=>{
      saveDraft();
      nomorActive.value = String(i);
      loadDraft(i);
      buildNumbers();
      markDirty();
    });

    numbers.appendChild(btn);
  }

  if (visibleSoalCount < MAX_SOAL) {
    const addBtn = document.createElement("button");
    addBtn.type = "button";
    addBtn.className = "num-add";
    addBtn.textContent = "+";
    addBtn.title = "Tambah nomor soal";

    addBtn.addEventListener("click", () => {
      saveDraft();
      visibleSoalCount++;
      nomorActive.value = String(visibleSoalCount);
      loadDraft(visibleSoalCount);
      buildNumbers();
      markDirty();
    });

    numbers.appendChild(addBtn);
  }
}

  modeSwitch.addEventListener("click", (e)=>{
    const pill = e.target.closest(".mode-pill");
    if(!pill) return;

    if (currentMode === "manual") {
      saveDraft();
      buildNumbers();
    }

    setMode(pill.dataset.mode);

    if (pill.dataset.mode === "manual") {
      const no = parseInt(nomorActive.value || "1", 10);
      loadDraft(no);
      buildNumbers();
    }

    markDirty();
  });

  ansGrid.addEventListener("click", (e)=>{
    const lab = e.target.closest(".ans-item");
    if(!lab) return;
    setJawabanRadio(lab.dataset.val);
    markDirty();
  });

  function resetForm(){
  paketIdInput.value = "";
  judulPaketInput.value = "";
  bagianInput.value = "";
  bulkJsonInput.value = "";
  cacheSoal = {};
  visibleSoalCount = DEFAULT_SOAL;
  nomorActive.value = "1";

    pertanyaanInput.value = "";
    opsiA.value=""; opsiB.value=""; opsiC.value=""; opsiD.value="";
    clearJawabanRadio();

    csvInput.value = "";
    csvName.textContent = "";
    thumbnailInput.value = "";
    thumbnailName.textContent = "Belum ada file dipilih";
    showThumb("");

    buildNumbers();
    setMode("csv");

    clearDirty();
    allowSubmit = false;
  }

  [judulPaketInput, pertanyaanInput, opsiA, opsiB, opsiC, opsiD].forEach(el => el.addEventListener("input", markDirty));
  bagianInput.addEventListener("change", markDirty);

  thumbnailInput.addEventListener("change", ()=>{
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
    markDirty();
  });

  csvDrop.addEventListener("click", ()=> csvInput.click());
  csvDrop.addEventListener("dragover", (e)=>{ e.preventDefault(); csvDrop.classList.add("dragover"); });
  csvDrop.addEventListener("dragleave", ()=> csvDrop.classList.remove("dragover"));
  csvDrop.addEventListener("drop", (e)=>{
    e.preventDefault();
    csvDrop.classList.remove("dragover");
    if(e.dataTransfer.files && e.dataTransfer.files[0]){
      csvInput.files = e.dataTransfer.files;
      csvName.textContent = e.dataTransfer.files[0].name;
      markDirty();
    }
  });
  csvInput.addEventListener("change", ()=>{
    if(csvInput.files && csvInput.files[0]) {
      csvName.textContent = csvInput.files[0].name;
      markDirty();
    } else {
      csvName.textContent = "";
    }
  });

  btnOpenAdd.addEventListener("click", ()=>{
    resetForm();
    modalTitle.textContent = "Input Kuis";
    modal.show();
  });

  document.querySelectorAll(".btn-edit").forEach(btn=>{
    btn.addEventListener("click", async ()=>{
      resetForm();
      modalTitle.textContent = "Edit Kuis";

      paketIdInput.value = btn.dataset.id || "";
      judulPaketInput.value = btn.dataset.judul || "";
      bagianInput.value = btn.dataset.bagian || "";

      if (btn.dataset.thumbnail) {
        thumbnailName.textContent = btn.dataset.thumbnail;
        showThumb("uploads/thumbnails/" + btn.dataset.thumbnail);
      }

      try{
        const res = await fetch(`${SELF}?ajax=paket_detail&id=${encodeURIComponent(paketIdInput.value)}`, { cache: "no-store" });
        const json = await res.json();

        if(json.ok){
          if(json.paket && typeof json.paket.bagian === "string") {
            bagianInput.value = json.paket.bagian || "";
          }

          if (json.paket && json.paket.thumbnail) {
            thumbnailName.textContent = json.paket.thumbnail;
            showThumb("uploads/thumbnails/" + json.paket.thumbnail);
          }

          cacheSoal = {};
            (json.soal || []).forEach(s=>{
              const no = parseInt(s.nomor,10);
              cacheSoal[no] = {
                pertanyaan: s.pertanyaan || "",
                a: s.opsi_a || "",
                b: s.opsi_b || "",
                c: s.opsi_c || "",
                d: s.opsi_d || "",
                jawaban: s.jawaban || ""
              };
            });

            visibleSoalCount = Math.max(DEFAULT_SOAL, (json.soal || []).length);

            nomorActive.value = "1";
            loadDraft(1);
            buildNumbers();

          const m = (json.paket && (json.paket.input_mode === "manual" || json.paket.input_mode === "csv")) ? json.paket.input_mode : "csv";
          setMode(m);

          if (m === "manual") {
            loadDraft(1);
            buildNumbers();
          }

          clearDirty();
        } else {
          showError("Data kuis tidak ditemukan.");
        }
      } catch(e) {
        showError("Gagal memuat data kuis. Coba refresh halaman.");
      }

      modal.show();
    });
  });

  function isJudulValid(judul){
    const t = (judul || "").trim();
    if(t.length === 0) return false;
    if(t.length > 45) return false;
    const re = /^[A-Za-z0-9 .,:\?]+$/;
    return re.test(t);
  }

  const formEl = document.getElementById("kuisForm");
  formEl.addEventListener("submit", (e)=>{
    if (allowSubmit) return;
    e.preventDefault();

    const judul = judulPaketInput.value || "";
    if(!isJudulValid(judul)){
      showError(
        "Judul tidak sesuai aturan.\n" +
        "- Maksimal 45 karakter (termasuk spasi)\n" +
        "- Hanya boleh: huruf, angka, spasi, titik (.), koma (,), titik dua (:), tanda tanya (?)"
      );
      return;
    }

    const isEdit = (paketIdInput.value || "").trim() !== "";

    if(currentMode === "csv"){
      const hasFile = (csvInput.files && csvInput.files.length > 0);
      if(hasFile){
        actionInput.value = "csv_import";
      } else {
        if(isEdit){
          actionInput.value = "paket_update_meta";
        } else {
          showError("File CSV wajib diupload.");
          return;
        }
      }
    } else {
      saveDraft();
      actionInput.value = "soal_save_bulk";
      bulkJsonInput.value = JSON.stringify(cacheSoal);
    }

    showConfirm({
      title: "Konfirmasi",
      message: "Apakah Anda yakin ingin menyimpan perubahan ini?",
      okText: "Simpan",
      cancelText: "Batal",
      onOk: () => {
        allowSubmit = true;
        clearDirty();
        formEl.submit();
      }
    });
  });

  resetForm();

  <?php if ($toast["type"] === "danger"): ?>
    window.addEventListener("DOMContentLoaded", () => {
      showError(<?= json_encode((string)$toast["msg"], JSON_UNESCAPED_UNICODE) ?>);
    });
  <?php endif; ?>
})();
</script>

</body>
</html>
