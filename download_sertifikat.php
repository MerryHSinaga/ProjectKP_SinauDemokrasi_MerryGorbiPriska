<?php
declare(strict_types=1);

session_start();
require_once 'fpdf.php';
require_once 'db.php';

if (
    !isset($_SESSION['user_logged_in']) ||
    $_SESSION['user_logged_in'] !== true ||
    !isset($_SESSION['user_id'])
) {
    exit('Akses ditolak. Silakan login terlebih dahulu.');
}

$userId = (int)$_SESSION['user_id'];
$aktivitasId = (int)($_GET['id'] ?? 0);
if ($aktivitasId <= 0) {
    exit('ID aktivitas kuis tidak valid.');
}

function tanggalIndoLengkap(string $date): string
{
    $hari = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];

    $bulan = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
    ];

    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }

    return $hari[date('l', $ts)] . ', ' . date('d', $ts) . ' ' . $bulan[date('m', $ts)] . ' ' . date('Y', $ts);
}

function safeFilePart(string $text): string
{
    $text = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($text));
    $text = trim((string)$text, '_');
    return $text !== '' ? $text : 'peserta';
}

function updateSertifikatPath(int $aktivitasId, string $sertifikatPath): void
{
    $st = db()->prepare("UPDATE user_aktivitas_kuis SET sertifikat_path = ? WHERE id = ?");
    $st->execute([$sertifikatPath, $aktivitasId]);
}

$stmt = db()->prepare("
    SELECT 
        u.nama AS nama_lengkap,
        a.id,
        a.user_id,
        a.kuis_id,
        a.judul_kuis,
        a.skor,
        a.lulus,
        a.created_at,
        a.sertifikat_path
    FROM user_aktivitas_kuis a
    INNER JOIN users u ON u.id = a.user_id
    WHERE a.id = ? AND a.user_id = ?
    LIMIT 1
");
$stmt->execute([$aktivitasId, $userId]);
$aktivitas = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$aktivitas) {
    exit('Data aktivitas kuis tidak ditemukan.');
}

$skorAsli = (int)round((float)($aktivitas['skor'] ?? 0));
$lulus = (int)($aktivitas['lulus'] ?? 0) === 1 || $skorAsli >= 55;
if (!$lulus) {
    exit('Sertifikat hanya tersedia untuk peserta yang lulus.');
}

$namaRaw      = (string)($aktivitas['nama_lengkap'] ?? 'Peserta');
$materiUjiRaw = (string)($aktivitas['judul_kuis'] ?? 'Kuis');
$tanggalRaw   = (string)($aktivitas['created_at'] ?? date('Y-m-d H:i:s'));
$tanggal      = tanggalIndoLengkap($tanggalRaw);

$_SESSION['nama'] = $namaRaw;
$_SESSION['materi'] = $materiUjiRaw;
$_SESSION['tanggal'] = $tanggalRaw;
$_SESSION['skor'] = $skorAsli;
$_SESSION['aktivitas_kuis_id'] = (int)$aktivitas['id'];
$_SESSION['kuis_id'] = isset($aktivitas['kuis_id']) ? (int)$aktivitas['kuis_id'] : null;

$nama      = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $namaRaw);
$materiUji = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $materiUjiRaw);

if ($skorAsli >= 85) {
    $predikat = 'Baik Sekali';
} elseif ($skorAsli >= 70) {
    $predikat = 'Baik';
} elseif ($skorAsli >= 55) {
    $predikat = 'Cukup';
} else {
    $predikat = 'Perlu Belajar Lagi';
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();
$pdf->Image('Asset/sertifikat-bg.png', 0, 0, 297, 210);
$pdf->SetTextColor(255,255,255);

$pdf->SetFont('Times', 'B', 40);
$pdf->SetXY(0, 46);
$pdf->Cell(297, 16, 'SERTIFIKAT', 0, 1, 'C');

$pdf->SetFont('Times', '', 16);
$pdf->SetXY(0, 65);
$pdf->Cell(297, 10, 'Diberikan kepada:', 0, 1, 'C');

$pdf->SetFont('Times', 'B', 30);
$pdf->SetXY(20, 80);
$pdf->Cell(257, 14, strtoupper((string)$nama), 0, 1, 'C');

$pdf->SetFont('Times', '', 16);
$pdf->SetXY(0, 100);
$pdf->Cell(
    297,
    10,
    'Atas partisipasinya dalam uji pemahaman SINAU DEMOKRASI yang dilaksanakan pada:',
    0,
    1,
    'C'
);

$xLabel = 96;
$y = 116;
$h = 9;

$pdf->SetXY($xLabel, $y);
$pdf->Cell(40, $h, 'Hari, Tanggal', 0, 0);
$pdf->Cell(5, $h, ':', 0, 0);
$pdf->Cell(120, $h, $tanggal, 0, 1);

$pdf->SetXY($xLabel, $y + $h);
$pdf->Cell(40, $h, 'Materi Uji', 0, 0);
$pdf->Cell(5, $h, ':', 0, 0);
$pdf->MultiCell(120, $h, (string)$materiUji, 0, 'L');

$pdf->SetXY($xLabel, $y + ($h * 2));
$pdf->Cell(40, $h, 'Nilai', 0, 0);
$pdf->Cell(5, $h, ':', 0, 0);
$pdf->Cell(120, $h, $skorAsli . ' (' . $predikat . ')', 0, 1);

$pdf->SetXY(0, 150);
$pdf->MultiCell(
    297,
    9,
    "Semoga pembelajaran ini dapat meningkatkan pemahaman\n" .
    "tentang pemilu dan partisipasi dalam demokrasi.",
    0,
    'C'
);

$pdfBinary = $pdf->Output('S');
$folder = __DIR__ . '/uploads/sertifikat';
if (!is_dir($folder)) {
    @mkdir($folder, 0775, true);
}

if (is_dir($folder) && is_writable($folder)) {
    $filename = 'sertifikat_' . safeFilePart($namaRaw) . '_' . date('Ymd_His') . '.pdf';
    $absolutePath = $folder . '/' . $filename;

    if (file_put_contents($absolutePath, $pdfBinary) !== false) {
        $relativePath = 'uploads/sertifikat/' . $filename;
        $_SESSION['sertifikat_path'] = $relativePath;
        try {
            updateSertifikatPath($aktivitasId, $relativePath);
        } catch (Throwable $e) {
        }
    }
}

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="Sertifikat_SI_NAU_DEMOKRASI.pdf"');
header('Content-Transfer-Encoding: binary');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfBinary;
exit;
