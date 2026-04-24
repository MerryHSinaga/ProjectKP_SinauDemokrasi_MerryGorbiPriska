<?php
session_start();
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: daftar_kuis.php");
    exit;
}

$kuis_id = (int)$_GET['id'];

$stmt = db()->prepare("SELECT id, judul, bagian FROM kuis_paket WHERE id = ?");
$stmt->execute([$kuis_id]);
$paket = $stmt->fetch();

if (!$paket) {
    header("Location: daftar_kuis.php");
    exit;
}

$_SESSION['flow_step']     = 'BIODATA_OK';
$_SESSION['kuis_id']       = $kuis_id;
$_SESSION['materi_bagian'] = $paket['bagian'] ?? $paket['judul'];

if (!isset($_SESSION['nama'])) {
    $_SESSION['nama'] = $_SESSION['username'] ?? 'Guest';
}

header("Location: user_kuis.php");
exit;