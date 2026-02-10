<?php
session_start();
require_once 'fpdf.php';

//session check
if (
    empty($_SESSION['nama']) ||
    empty($_SESSION['tanggal']) ||
    empty($_SESSION['materi']) ||
    !isset($_SESSION['skor'])
) {
    exit;
}

//tanggal
function tanggalIndoLengkap($date)
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
    return $hari[date('l', $ts)].', '.date('d', $ts).' '.$bulan[date('m', $ts)].' '.date('Y', $ts);
}


$nama      = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $_SESSION['nama']);
$materiUji = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $_SESSION['materi']);
$skor      = (int) $_SESSION['skor'];
$tanggal   = tanggalIndoLengkap($_SESSION['tanggal']);

//Predikat
if ($skor >= 85) {
    $predikat = 'Baik Sekali';
} elseif ($skor >= 70) {
    $predikat = 'Baik';
} elseif ($skor >= 55) {
    $predikat = 'Cukup';
} else {
    $predikat = 'Perlu Belajar Lagi';
}

//Set pdf A4
$pdf = new FPDF('L', 'mm', 'A4');
$pdf->AddPage();

// Background
$pdf->Image('Asset/sertifikat-bg.png', 0, 0, 297, 210);


$pdf->SetTextColor(255,255,255);

// Judul
$pdf->SetFont('Times', 'B', 40);
$pdf->SetXY(0, 46);
$pdf->Cell(297, 16, 'SERTIFIKAT', 0, 1, 'C');

$pdf->SetFont('Times', '', 16);
$pdf->SetXY(0, 65);
$pdf->Cell(297, 10, 'Diberikan kepada:', 0, 1, 'C');


$pdf->SetFont('Times', 'B', 30);
$pdf->SetXY(20, 80);
$pdf->Cell(257, 14, strtoupper($nama), 0, 1, 'C');

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

$pdf->SetFont('Times', '', 16);

$pdf->SetXY($xLabel, $y);
$pdf->Cell(40, $h, 'Hari, Tanggal', 0, 0);
$pdf->Cell(5,  $h, ':', 0, 0);
$pdf->Cell(120, $h, $tanggal, 0, 1);

$pdf->SetXY($xLabel, $y + $h);
$pdf->Cell(40, $h, 'Materi Uji', 0, 0);
$pdf->Cell(5,  $h, ':', 0, 0);
$pdf->MultiCell(120, $h, $materiUji, 0, 'L');

$pdf->SetXY($xLabel, $y + ($h * 2));
$pdf->Cell(40, $h, 'Nilai', 0, 0);
$pdf->Cell(5,  $h, ':', 0, 0);
$pdf->Cell(120, $h, "$skor ($predikat)", 0, 1);


$pdf->SetXY(0, 150);
$pdf->MultiCell(
    297,
    9,
    "Semoga pembelajaran ini dapat meningkatkan pemahaman\n".
    "tentang pemilu dan partisipasi dalam demokrasi.",
    0,
    'C'
);

if (ob_get_length()) {
    ob_clean();
}

$pdf->Output('D', 'Sertifikat_SI_NAU_DEMOKRASI.pdf');
exit;
