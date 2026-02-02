-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 02, 2026 at 07:46 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sinau_pemilu`
--

-- --------------------------------------------------------

--
-- Table structure for table `kuis_paket`
--

CREATE TABLE `kuis_paket` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `bagian` varchar(255) DEFAULT NULL,
  `input_mode` enum('csv','manual') NOT NULL DEFAULT 'csv',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kuis_paket`
--

INSERT INTO `kuis_paket` (`id`, `judul`, `bagian`, `input_mode`, `created_at`) VALUES
(1, 'grom', NULL, 'csv', '2026-01-26 02:36:20'),
(2, 'Dasar 😗', NULL, 'csv', '2026-01-26 02:52:10'),
(5, 'Pemilu', NULL, 'manual', '2026-01-27 14:57:37'),
(8, 'Pemiluu', NULL, 'csv', '2026-01-30 12:58:03'),
(9, 'Tes', 'Keuangan', 'manual', '2026-02-02 04:44:43');

-- --------------------------------------------------------

--
-- Table structure for table `kuis_soal`
--

CREATE TABLE `kuis_soal` (
  `id` int(11) NOT NULL,
  `paket_id` int(11) NOT NULL,
  `nomor` int(11) NOT NULL,
  `pertanyaan` text NOT NULL,
  `opsi_a` varchar(255) NOT NULL,
  `opsi_b` varchar(255) NOT NULL,
  `opsi_c` varchar(255) NOT NULL,
  `opsi_d` varchar(255) NOT NULL,
  `jawaban` enum('A','B','C','D') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kuis_soal`
--

INSERT INTO `kuis_soal` (`id`, `paket_id`, `nomor`, `pertanyaan`, `opsi_a`, `opsi_b`, `opsi_c`, `opsi_d`, `jawaban`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'apa itu essay?', 'gon', 'gun', 'gen', 'gum', 'B', '2026-01-26 02:36:20', NULL),
(4, 1, 2, 'Pemilu adalah...', 'pemilihan umum', 'pake nanya', 'yaaa', 'okee', 'A', '2026-01-26 02:47:45', NULL),
(6, 2, 1, 'Apa kepanjangan dari KPU?', 'Komisi Pemilihan Umum', 'Komisi Peraturan Umum', 'Komite Pemilihan Umum', 'Kantor Pemilihan Umum', 'A', '2026-01-26 02:52:10', NULL),
(7, 2, 2, 'Pemilu di Indonesia dilaksanakan setiap berapa tahun?', '3 tahun', '4 tahun', '5 tahun', '6 tahun', 'C', '2026-01-26 02:52:10', NULL),
(8, 2, 3, 'Contoh hak warga negara dalam pemilu adalah...', 'Membayar pajak', 'Memilih dalam pemilu', 'Mengurus SIM', 'Menjaga kebersihan', 'B', '2026-01-26 02:52:10', NULL),
(9, 2, 4, 'Apa tujuan utama pemilu?', 'Menentukan pajak daerah', 'Memilih pemimpin secara demokratis', 'Mengatur lalu lintas', 'Membuat aturan sekolah', 'B', '2026-01-26 02:52:10', NULL),
(10, 2, 5, 'Salah satu asas pemilu adalah...', 'Rahasia', 'Komersial', 'Monopoli', 'Diskriminatif', 'A', '2026-01-26 02:52:10', NULL),
(32, 5, 11, 'apapapa', 'sad', 'fjej', 'sfdnf', 'd sandhe', 'B', '2026-01-27 14:57:37', NULL),
(35, 8, 1, 'Apa saja tahapan utama dalam pelaksanaan Pemilu di Indonesia?', 'Pendaftaran, kampanye, pemungutan suara, penghitungan suara', 'Pendaftaran, verifikasi, pemungutan suara, pasca-pemilu', 'Pendaftaran, pemungutan suara, hitung suara, penetapan hasil', 'Kampanye, penghitungan suara, pelantikan', 'A', '2026-01-30 12:58:03', NULL),
(36, 8, 2, 'Siapakah yang berperan sebagai penyelenggara Pemilu di Indonesia?', 'Komisi Pemilihan Umum (KPU)', 'Badan Pengawas Pemilu (Bawaslu)', 'Presiden', 'Dewan Perwakilan Rakyat (DPR)', 'A', '2026-01-30 12:58:03', NULL),
(37, 8, 3, 'Apa yang dimaksud dengan Pemilu Pra-Pemilu?', 'Proses yang dilakukan setelah pemungutan suara', 'Proses pengorganisasian partai politik', 'Persiapan yang dilakukan sebelum pemilu, termasuk pendaftaran pemilih', 'Pemeriksaan hasil pemilu', 'C', '2026-01-30 12:58:03', NULL),
(38, 8, 4, 'Apa tujuan dari penyusunan arsip dalam pemilu?', 'Menyimpan hasil perhitungan suara untuk referensi masa depan', 'Menyimpan data pemilih dan hasil pemilu untuk kepentingan pengarsipan', 'Mengontrol kampanye calon', 'Semua benar', 'B', '2026-01-30 12:58:03', NULL),
(39, 8, 5, 'Siapa yang bertanggung jawab atas pengawasan Pemilu?', 'Komisi Pemilihan Umum', 'Badang Pengawas Pemilu', 'Dewan Perwakilan Rakyat', 'Kementerian Dalam Negeri', 'B', '2026-01-30 12:58:03', NULL),
(40, 8, 6, 'Bagaimana cara KPU memastikan pemilu berjalan dengan adil dan transparan?', 'Menggunakan sistem e-voting', 'Melibatkan masyarakat dalam proses pengawasan', 'Memilih calon pemimpin secara acak', 'Menjaga kerahasiaan suara pemilih', 'B', '2026-01-30 12:58:03', NULL),
(41, 8, 7, 'Apa yang dimaksud dengan Pemilu Pasca-Pemilu?', 'Semua proses yang dilakukan setelah pemungutan suara', 'Pemeriksaan hasil pemilu dan pengumuman pemenang', 'Kampanye kandidat', 'Pendaftaran partai politik', 'B', '2026-01-30 12:58:03', NULL),
(42, 8, 8, 'Alat apa yang digunakan untuk melaksanakan Pemilu di Indonesia?', 'Surat suara dan kotak suara', 'Alat hitung cepat', 'Sistem e-voting', 'Semua benar', 'A', '2026-01-30 12:58:03', NULL),
(43, 8, 9, 'Apa yang dilakukan dalam tahap kampanye Pemilu?', 'Menyebarkan informasi mengenai calon', 'Proses verifikasi suara', 'Pemungutan suara', 'Penghitungan hasil pemilu', 'A', '2026-01-30 12:58:03', NULL),
(44, 8, 10, 'Apa fungsi utama dari Undang-Undang Pemilu?', 'Menetapkan jadwal Pemilu', 'Menjamin hak suara warga negara', 'Membatasi jumlah partai politik', 'Semua benar', 'D', '2026-01-30 12:58:03', NULL),
(45, 8, 11, 'Siapa yang memutuskan hasil Pemilu dalam sistem hukum di Indonesia?', 'Komisi Pemilihan Umum', 'Badang Pengawas Pemilu', 'Dewan Perwakilan Rakyat', 'Presiden', 'B', '2026-01-30 12:58:03', NULL),
(46, 8, 12, 'Apakah yang dimaksud dengan \"penghitungan suara terbuka\"?', 'Penghitungan suara dilakukan secara rahasia', 'Setiap pemilih diperbolehkan mengetahui hasil suara pemilih lainnya', 'Suara dihitung secara terbuka dan dapat disaksikan oleh publik', 'Penghitungan suara hanya dilakukan oleh petugas', 'C', '2026-01-30 12:58:03', NULL),
(47, 8, 13, 'Apa tujuan dari verifikasi pemilih dalam Pemilu?', 'Untuk memastikan pemilih terdaftar sesuai dengan data KTP', 'Untuk mencegah kecurangan pemilu', 'Untuk menghitung jumlah pemilih', 'Semua benar', 'D', '2026-01-30 12:58:03', NULL),
(48, 8, 14, 'Bagaimana Pemilu di Indonesia dilaksanakan agar sesuai dengan prinsip demokrasi?', 'Proses dilakukan dengan transparansi penuh', 'Semua warga negara yang memenuhi syarat memiliki hak suara yang sama', 'Hasil pemilu diputuskan berdasarkan suara terbanyak', 'Semua benar', 'D', '2026-01-30 12:58:03', NULL),
(49, 8, 15, 'Apa yang dimaksud dengan \"hasil Pemilu yang sah\"?', 'Hasil yang disetujui oleh partai politik', 'Hasil yang telah diverifikasi dan disetujui oleh KPU dan Mahkamah Konstitusi', 'Hasil yang diumumkan oleh Presiden', 'Hasil yang dipilih berdasarkan suara terbanyak', 'B', '2026-01-30 12:58:03', NULL),
(50, 9, 1, 'ayam go?', 'reng', 'food', 'jek', 'public?', 'D', '2026-02-02 04:44:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `materi`
--

CREATE TABLE `materi` (
  `id` int(11) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `bagian` enum('Keuangan','Umum dan Logistik','Teknis Penyelenggara Pemilu, Partisipasi Hubungan Masyarakat','Hukum dan SDM','Perencanaan','Data dan Informasi') NOT NULL,
  `tipe` enum('pdf','jpg') NOT NULL,
  `jumlah_slide` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materi`
--

INSERT INTO `materi` (`id`, `judul`, `bagian`, `tipe`, `jumlah_slide`, `created_at`) VALUES
(9, 'TES', 'Umum dan Logistik', 'pdf', 21, '2026-01-25 20:23:24');

-- --------------------------------------------------------

--
-- Table structure for table `materi_media`
--

CREATE TABLE `materi_media` (
  `id` int(11) NOT NULL,
  `materi_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materi_media`
--

INSERT INTO `materi_media` (`id`, `materi_id`, `file_path`, `sort_order`, `created_at`) VALUES
(2, 9, 'materi_20260125_212324_3a6a86c361a8.pdf', 0, '2026-01-25 20:23:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `kuis_paket`
--
ALTER TABLE `kuis_paket`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kuis_soal`
--
ALTER TABLE `kuis_soal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_paket_nomor` (`paket_id`,`nomor`);

--
-- Indexes for table `materi`
--
ALTER TABLE `materi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materi_media`
--
ALTER TABLE `materi_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_media_materi` (`materi_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `kuis_paket`
--
ALTER TABLE `kuis_paket`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `kuis_soal`
--
ALTER TABLE `kuis_soal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `materi`
--
ALTER TABLE `materi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `materi_media`
--
ALTER TABLE `materi_media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `kuis_soal`
--
ALTER TABLE `kuis_soal`
  ADD CONSTRAINT `fk_soal_paket` FOREIGN KEY (`paket_id`) REFERENCES `kuis_paket` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `materi_media`
--
ALTER TABLE `materi_media`
  ADD CONSTRAINT `fk_media_materi` FOREIGN KEY (`materi_id`) REFERENCES `materi` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
