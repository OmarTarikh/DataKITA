-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 09 Jan 2026 pada 06.28
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `datakita`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `data_pending`
--

CREATE TABLE `data_pending` (
  `id_pending` int(10) UNSIGNED NOT NULL,
  `tipe_data` enum('warga','keluarga') NOT NULL,
  `aksi` enum('edit','hapus') NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `nik` varchar(30) DEFAULT NULL,
  `no_kk` varchar(30) DEFAULT NULL,
  `nama` varchar(255) DEFAULT NULL,
  `tempat_lahir` varchar(255) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `jenis_kelamin` varchar(50) DEFAULT NULL,
  `agama` varchar(50) DEFAULT NULL,
  `pendidikan` varchar(100) DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `status_perkawinan` varchar(50) DEFAULT NULL,
  `kepala_keluarga` varchar(255) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `rt` varchar(10) DEFAULT NULL,
  `rw` varchar(10) DEFAULT NULL,
  `kelurahan` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `dokumen_ktp` varchar(255) DEFAULT NULL,
  `dokumen_kk` varchar(255) DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `status` enum('pending','terverifikasi','ditolak') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `data_pending`
--

INSERT INTO `data_pending` (`id_pending`, `tipe_data`, `aksi`, `id_user`, `nik`, `no_kk`, `nama`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `agama`, `pendidikan`, `pekerjaan`, `status_perkawinan`, `kepala_keluarga`, `alamat`, `rt`, `rw`, `kelurahan`, `kecamatan`, `dokumen_ktp`, `dokumen_kk`, `catatan`, `status`, `created_at`, `reviewed_at`) VALUES
(27, 'warga', 'edit', 22, '545454545454545', NULL, 'John cenas', 'Batam', '1978-02-14', 'Laki-laki', 'Katolik', 'S2', 'Developer', 'Belum Menikah', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'kk_545454545454545_1767889466.jpg', 'test', 'pending', '2026-01-08 16:24:26', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kegiatan_masyarakat`
--

CREATE TABLE `kegiatan_masyarakat` (
  `id_kegiatan` int(10) UNSIGNED NOT NULL,
  `judul` varchar(150) NOT NULL,
  `deskripsi` text NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_mulai` time DEFAULT NULL,
  `waktu_selesai` time DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `tempat` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kegiatan_masyarakat`
--

INSERT INTO `kegiatan_masyarakat` (`id_kegiatan`, `judul`, `deskripsi`, `tanggal`, `waktu_mulai`, `waktu_selesai`, `expired_at`, `tempat`, `created_at`) VALUES
(1, 'GOTONG ROYONG MINGGUAN', 'Kegiatan kerja bakti rutin membersihkan lingkungan dan selokan.', '2025-10-12', NULL, NULL, NULL, 'Halaman Pos Ronda RT 05', '2026-01-03 15:15:04'),
(2, 'RAPAT RUTIN BULANAN', 'Rapat koordinasi warga membahas keamanan dan kebersihan lingkungan.', '2025-10-15', NULL, NULL, NULL, 'Balai Warga RW 02', '2026-01-03 15:15:04'),
(3, 'KERJA BAKTI TAMAN', 'Penataan dan perawatan taman lingkungan bersama warga.', '2025-10-20', NULL, NULL, NULL, 'Taman Lingkungan RW 02', '2026-01-03 15:15:04'),
(4, 'SOSIALISASI KEAMANAN LINGKUNGAN', 'Sosialisasi sistem keamanan lingkungan dan ronda malam.', '2025-10-25', '15:58:00', '15:56:00', NULL, 'Aula Serbaguna RW 02', '2026-01-03 15:15:04');

-- --------------------------------------------------------

--
-- Struktur dari tabel `keluarga`
--

CREATE TABLE `keluarga` (
  `No_kk` varchar(30) NOT NULL,
  `Kepala_keluarga` varchar(255) NOT NULL,
  `Alamat` text DEFAULT NULL,
  `RT` varchar(10) DEFAULT NULL,
  `RW` varchar(10) DEFAULT NULL,
  `Kelurahan` varchar(100) DEFAULT NULL,
  `Kecamatan` varchar(100) DEFAULT NULL,
  `Dokumen_kk` varchar(255) DEFAULT NULL,
  `Id_user` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','terverifikasi') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `keluarga`
--

INSERT INTO `keluarga` (`No_kk`, `Kepala_keluarga`, `Alamat`, `RT`, `RW`, `Kelurahan`, `Kecamatan`, `Dokumen_kk`, `Id_user`, `status`) VALUES
('123124125414123124', 'Adit Maulana', 'Jl. Thailand', '03', '04', 'Biru', 'Muda', 'kk_123124125414123124_1767864332.jpg', 18, 'terverifikasi'),
('217111000001', 'Budi Santoso', 'Jl. Mawar No. 1', '01', '02', 'Baloi', 'Batam Kota', 'kk_2171112601010003_1767881483.jpg', 15, 'terverifikasi'),
('217111000002', 'Siti Aminah', 'Jl. Melati No. 5', '02', '02', 'Baloi', 'Batam Kota', 'kk_2171112601020002_1767880126.jpg', 16, 'terverifikasi'),
('217111000003', 'Andi Pratama', 'Jl. Kenanga No. 9', '03', '01', 'Baloi', 'Batam Kota', 'kk_2171112601030002_1767877392.jpg', 17, 'terverifikasi'),
('7676766767676767', 'John cena', 'Jl. Bing Chiling', '02', '02', 'Wuhan', 'cina', 'kk_695fcfe7b1a41.png', 21, 'terverifikasi'),
('845345349583520', 'Glachio Lindro', 'Jl. Malaysia', '03', '04', 'Durian', 'Runtuh', 'kk_69590b2118aab.jpg', 19, 'pending');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kotak_saran`
--

CREATE TABLE `kotak_saran` (
  `id_saran` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED NOT NULL,
  `isi_saran` text NOT NULL,
  `status` enum('baru','dibaca','ditindaklanjuti') DEFAULT 'baru',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kotak_saran`
--

INSERT INTO `kotak_saran` (`id_saran`, `id_user`, `isi_saran`, `status`, `created_at`) VALUES
(2, 18, 'Jalan aspal di jl. thailand rusak, mohon segera di perbaiki', 'baru', '2026-01-03 15:18:32'),
(7, 18, 'test test', 'baru', '2026-01-03 15:23:07'),
(9, 18, 'test 2', 'baru', '2026-01-03 15:23:10'),
(13, 18, 'dadafasf', 'baru', '2026-01-03 15:23:53'),
(23, 22, 'test', 'baru', '2026-01-09 05:01:33'),
(25, 22, 'ale keren', 'baru', '2026-01-09 05:05:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi_warga`
--

CREATE TABLE `notifikasi_warga` (
  `id_notifikasi` int(10) UNSIGNED NOT NULL,
  `id_user` int(10) UNSIGNED DEFAULT NULL,
  `pesan` text NOT NULL,
  `expired_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `notifikasi_warga`
--

INSERT INTO `notifikasi_warga` (`id_notifikasi`, `id_user`, `pesan`, `expired_at`, `created_at`) VALUES
(1, NULL, 'Bagi seluruh warga yang kami hormati, diinformasikan bahwa akan dilaksanakan kerja bakti lingkungan pada hari Minggu. Partisipasi seluruh warga sangat diharapkan demi menjaga kebersihan dan kenyamanan bersama.', '2026-01-10 23:03:30', '2026-01-03 16:03:30'),
(2, 18, 'Data keluarga Anda telah diverifikasi. Terima kasih telah melengkapi data kependudukan.', '2026-02-02 23:03:43', '2026-01-03 16:03:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_administrasi`
--

CREATE TABLE `riwayat_administrasi` (
  `id` int(11) NOT NULL,
  `jenis_data` enum('keluarga','warga','dashboard') NOT NULL,
  `id_data` varchar(50) NOT NULL,
  `aksi` enum('tambah','ubah','hapus','verifikasi') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `dilakukan_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `riwayat_administrasi`
--

INSERT INTO `riwayat_administrasi` (`id`, `jenis_data`, `id_data`, `aksi`, `keterangan`, `dilakukan_oleh`, `created_at`) VALUES
(1, 'keluarga', '2345453234663234', 'verifikasi', 'Status keluarga diubah menjadi pending', 4, '2025-12-31 19:05:50'),
(3, 'keluarga', '217111000042242', 'tambah', 'Data keluarga baru ditambahkan', 4, '2025-12-31 19:32:19'),
(4, 'keluarga', '217111000001', 'verifikasi', 'Status keluarga diubah menjadi terverifikasi', 4, '2025-12-31 19:33:08'),
(5, 'keluarga', '217111000042242', 'hapus', 'Data keluarga atas nama adssdasdadfsda dihapus', 4, '2025-12-31 19:34:38'),
(6, 'warga', '12343412321421', 'tambah', 'Data warga baru ditambahkan', 4, '2025-12-31 19:52:27'),
(7, 'warga', '12343412321421', 'hapus', 'Data warga atas nama jono (NIK: 12343412321421) telah dihapus', 4, '2025-12-31 19:52:41'),
(8, 'keluarga', '2345453234663234', 'hapus', 'Data keluarga (No kk: 2345453234663234 kepala keluarga Omar Tarikh dihapus', 4, '2025-12-31 19:56:14'),
(9, 'keluarga', '123124125414123124', 'verifikasi', 'Status keluarga diubah menjadi terverifikasi', 4, '2026-01-03 12:28:24'),
(10, 'warga', '12345678954321', 'hapus', 'Data warga atas nama Omar Tarikh (NIK: 12345678954321 telah dihapus', 4, '2026-01-03 12:58:40'),
(14, 'keluarga', '217111000002', 'verifikasi', 'Status keluarga dan seluruh anggota diubah menjadi terverifikasi', 4, '2026-01-03 13:05:34'),
(15, 'keluarga', '217111000003', 'verifikasi', 'Status keluarga dan seluruh anggota diubah menjadi terverifikasi', 4, '2026-01-03 13:40:33'),
(16, 'keluarga', '217111000003', 'verifikasi', 'Status keluarga dan seluruh anggota diubah menjadi pending', 4, '2026-01-03 13:41:23'),
(17, 'warga', '3245363245435', 'hapus', 'Data warga atas nama dfsgdhngdsfd (NIK: 3245363245435 telah dihapus', 4, '2026-01-08 07:04:26'),
(19, 'warga', '123213421421421', 'verifikasi', 'Status warga diubah menjadi pending', 4, '2026-01-08 07:12:19'),
(20, 'warga', '123213421421421', 'verifikasi', 'Status warga diubah menjadi terverifikasi', 4, '2026-01-08 07:12:36'),
(21, 'warga', '234567897876543', 'tambah', 'Data warga baru ditambahkan', 4, '2026-01-08 10:51:32'),
(22, 'warga', '234567898765456789', 'tambah', 'Data warga baru ditambahkan', 4, '2026-01-08 10:52:11'),
(23, 'keluarga', '123124125414123124', 'ubah', 'Perubahan data keluarga dengan No KK 123124125414123124 telah diverifikasi admin', 4, '2026-01-08 13:46:47'),
(24, 'warga', '2171112601010003', 'ubah', 'Perubahan data warga dengan NIK 2171112601010003 telah diverifikasi admin', 4, '2026-01-08 14:01:00'),
(25, 'warga', '2171112601010002', 'hapus', 'Pengajuan edit data warga dengan NIK 2171112601010002 dihapus oleh admin', 4, '2026-01-08 14:10:21'),
(26, 'warga', '2171112601010003', 'ubah', 'Perubahan data warga dengan NIK 2171112601010003 telah diverifikasi admin', 4, '2026-01-08 14:10:31'),
(27, 'warga', '2171112601010003', 'hapus', 'Penghapusan data warga dengan NIK 2171112601010003 telah diverifikasi admin', 4, '2026-01-08 14:25:15'),
(28, 'warga', '2171112601010002', 'hapus', 'Pengajuan hapus data warga dengan NIK 2171112601010002 dibatalkan / dihapus oleh admin', 4, '2026-01-08 14:27:08'),
(29, 'keluarga', '7676766767676767', 'verifikasi', 'Status keluarga dan seluruh anggota diubah menjadi terverifikasi', 4, '2026-01-08 16:06:10'),
(30, 'keluarga', '345678765434567898754345678', 'tambah', 'Data keluarga baru ditambahkan', 4, '2026-01-08 17:30:36'),
(31, 'keluarga', '3468765324578980898', 'tambah', 'Data keluarga dan kepala keluarga ditambahkan', 4, '2026-01-08 17:36:05'),
(32, 'keluarga', '345678765434567898754345678', 'hapus', 'Data keluarga (No kk: 345678765434567898754345678 kepala keluarga brurururururu dihapus', 4, '2026-01-08 17:36:44'),
(33, 'keluarga', '3468765324578980898', 'hapus', 'Data keluarga (No kk: 3468765324578980898 kepala keluarga ggggggggggg dihapus', 4, '2026-01-08 17:36:48'),
(34, 'warga', '75684643', 'hapus', 'Data warga atas nama ggggggggggg (NIK: 75684643 telah dihapus', 4, '2026-01-08 17:37:04'),
(35, 'keluarga', '45465787965435467', 'tambah', 'Data keluarga dan kepala keluarga ditambahkan', 4, '2026-01-08 17:39:29'),
(36, 'keluarga', '45465787965435467', 'hapus', 'Data keluarga No KK 45465787965435467 (kepala keluarga ssssssssssss) beserta seluruh anggota dihapus', 4, '2026-01-08 17:39:36'),
(37, 'warga', '6787986532134567879', 'ubah', 'Data warga diperbarui', 4, '2026-01-08 17:40:24'),
(43, 'dashboard', 'test_1', 'tambah', 'TEST', 1, '2026-01-09 04:51:34'),
(44, 'dashboard', 'kegiatan_4', 'ubah', 'Mengubah kegiatan masyarakat', 4, '2026-01-09 04:52:30'),
(45, 'dashboard', 'saran_15', 'hapus', 'Menghapus data kotak saran', 4, '2026-01-09 04:55:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `user`
--

CREATE TABLE `user` (
  `Id_user` int(10) UNSIGNED NOT NULL,
  `Nama_user` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Foto_profil` varchar(255) DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `Role` enum('admin','user') DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `user`
--

INSERT INTO `user` (`Id_user`, `Nama_user`, `Email`, `Foto_profil`, `Password`, `Role`) VALUES
(3, 'Aleser Tarikh Omar', 'aleser187@gmail.com', 'default.png', '$2y$10$opNPH0m5awlEQXjy2Va/xeZb5qTJVuJjeVD4bye/VbiN3RtMtFEfu', 'user'),
(4, 'Admin', 'admin@gmail.com', 'user_4_1766748071.jpg', '12345', 'admin'),
(14, 'Admin 2', 'admin2@gmail.com', 'default.png', '12345', 'admin'),
(15, 'Budi Santoso', 'budi@gmail.com', 'default.png', '12345', 'user'),
(16, 'Siti Aminah', 'siti@gmail.com', 'default.png', '12345', 'user'),
(17, 'Andi Pratama', 'andi@gmail.com', 'default.png', '12345', 'user'),
(18, 'Adit Maulana', 'aditadit@gmail.com', NULL, '$2y$10$ni1RA6hqOYhb1r1.AcGhV.H0twzgmUYe7E3OMuINw/s0Lz3EaoJn.', 'user'),
(19, 'Glachio lindro', 'lindro@gmail.com', NULL, '$2y$10$f7t2GEc7xZ7j9vmzQtg22uM0nVsXBRZZFnrtykRu7HpOX/aZmsmtu', 'user'),
(20, 'boo seunkwan', 'boo@gmail.com', NULL, '$2y$10$jblLflgZ1Mi4CoTmwMAFn.7zJ3pxA1mH2kIpEsykXgzuV.TVNPI0a', 'user'),
(21, 'johnny', 'jon@gmail.com', NULL, '$2y$10$eEKN2YkNaFZdaXce608ZHei5.55r8hpKGjAlGCQ2ESQpCXGGKGk5O', 'user'),
(22, 'Kim Mingyu', 'kiming@gmail.com', NULL, '$2y$10$YrpM.XnkE1phHCWITfC1uuW.AwtjVOTgq/DyxO6K4eICcukc.HzJ2', 'user');

-- --------------------------------------------------------

--
-- Struktur dari tabel `warga`
--

CREATE TABLE `warga` (
  `NIK` varchar(30) NOT NULL,
  `Nama` varchar(255) NOT NULL,
  `Tempat_lahir` varchar(255) DEFAULT NULL,
  `Tanggal_lahir` date DEFAULT NULL,
  `Jenis_kelamin` varchar(50) DEFAULT NULL,
  `Agama` varchar(50) DEFAULT NULL,
  `Pendidikan` varchar(100) DEFAULT NULL,
  `Pekerjaan` varchar(100) DEFAULT NULL,
  `Status_perkawinan` varchar(50) DEFAULT NULL,
  `No_kk` varchar(30) DEFAULT NULL,
  `Dokumen_ktp` varchar(255) DEFAULT NULL,
  `Id_user` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','terverifikasi') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `warga`
--

INSERT INTO `warga` (`NIK`, `Nama`, `Tempat_lahir`, `Tanggal_lahir`, `Jenis_kelamin`, `Agama`, `Pendidikan`, `Pekerjaan`, `Status_perkawinan`, `No_kk`, `Dokumen_ktp`, `Id_user`, `status`) VALUES
('123356546676345252', 'Adit Maulana', 'Batam', '2007-06-03', 'Laki-laki', 'Kristen', 'S2', 'Developer', 'Cerai', '123124125414123124', 'ktp_695907e1a5c03.jpg', 18, 'terverifikasi'),
('12345678954388', 'Adit Santotso', 'Batam', '2025-08-01', 'Laki-laki', 'Islam', 'D3', 'Developer', 'Kawin', '217111000001', 'ktp_12345678954388_1767164869.jpg', 4, 'terverifikasi'),
('2171112601010001', 'Budi Santoso', 'Batam', '1985-05-10', 'Laki-laki', 'Islam', 'S1', 'Karyawan', 'Kawin', '217111000001', 'ktp_2171112601010001_1767195838.jpg', 15, 'terverifikasi'),
('2171112601010002', 'Ani Santoso', 'Batam', '1988-03-12', 'Perempuan', 'Islam', 'SMA', 'Ibu Rumah Tangga', 'Kawin', '217111000001', 'ktp_2171112601010002_1767196093.jpg', 15, 'terverifikasi'),
('2171112601020001', 'Siti Aminah', 'Medan', '1990-09-15', 'Perempuan', 'Islam', 'D3', 'Wiraswasta', 'Kawin', '217111000002', 'siti.jpg', 16, 'terverifikasi'),
('2171112601030001', 'Andi Pratama', 'Padang', '1982-01-08', 'Laki-laki', 'Islam', 'S1', 'Pegawai Negeri', 'Kawin', '217111000003', 'andi.jpg', 17, 'terverifikasi'),
('545454545454545', 'John cena', 'Batam', '1978-02-14', 'Laki-laki', 'Katolik', 'S2', 'Developer', 'Belum Menikah', '7676766767676767', 'ktp_695fcfe7b22ae.jpg', 21, 'terverifikasi'),
('6787986532134567879', 'Dinar Lindro', 'Batam', '2019-07-03', 'Laki-laki', 'Islam', 'SD', 'Pelajar', 'Kawin', '845345349583520', 'ktp_6787986532134567879_1767894024.jpg', 19, 'pending'),
('756324423567897', 'Glachio Lindro', 'Batam', '1976-07-15', 'Laki-laki', 'Islam', 'S1', 'Manager', 'Cerai', '845345349583520', 'ktp_69590b21192b6.jpg', 19, 'pending'),
('98989898989889', 'fcgvbjkladfadf', 'asdsfsadsad', '2026-01-06', 'Laki-laki', 'Katolik', 'TK', 'sdsdfsdfs', 'Belum Menikah', '7676766767676767', 'ktp_695fd538e37ba.jpeg', 22, 'terverifikasi');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `data_pending`
--
ALTER TABLE `data_pending`
  ADD PRIMARY KEY (`id_pending`),
  ADD KEY `id_user` (`id_user`);

--
-- Indeks untuk tabel `kegiatan_masyarakat`
--
ALTER TABLE `kegiatan_masyarakat`
  ADD PRIMARY KEY (`id_kegiatan`);

--
-- Indeks untuk tabel `keluarga`
--
ALTER TABLE `keluarga`
  ADD PRIMARY KEY (`No_kk`),
  ADD KEY `fk_keluarga_user` (`Id_user`);

--
-- Indeks untuk tabel `kotak_saran`
--
ALTER TABLE `kotak_saran`
  ADD PRIMARY KEY (`id_saran`),
  ADD KEY `fk_saran_user` (`id_user`);

--
-- Indeks untuk tabel `notifikasi_warga`
--
ALTER TABLE `notifikasi_warga`
  ADD PRIMARY KEY (`id_notifikasi`),
  ADD KEY `fk_notif_user` (`id_user`);

--
-- Indeks untuk tabel `riwayat_administrasi`
--
ALTER TABLE `riwayat_administrasi`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`Id_user`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indeks untuk tabel `warga`
--
ALTER TABLE `warga`
  ADD PRIMARY KEY (`NIK`),
  ADD KEY `No_kk` (`No_kk`),
  ADD KEY `fk_warga_user` (`Id_user`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `data_pending`
--
ALTER TABLE `data_pending`
  MODIFY `id_pending` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `kegiatan_masyarakat`
--
ALTER TABLE `kegiatan_masyarakat`
  MODIFY `id_kegiatan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `kotak_saran`
--
ALTER TABLE `kotak_saran`
  MODIFY `id_saran` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `notifikasi_warga`
--
ALTER TABLE `notifikasi_warga`
  MODIFY `id_notifikasi` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `riwayat_administrasi`
--
ALTER TABLE `riwayat_administrasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT untuk tabel `user`
--
ALTER TABLE `user`
  MODIFY `Id_user` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `data_pending`
--
ALTER TABLE `data_pending`
  ADD CONSTRAINT `data_pending_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `user` (`Id_user`);

--
-- Ketidakleluasaan untuk tabel `keluarga`
--
ALTER TABLE `keluarga`
  ADD CONSTRAINT `fk_keluarga_user` FOREIGN KEY (`Id_user`) REFERENCES `user` (`Id_user`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `kotak_saran`
--
ALTER TABLE `kotak_saran`
  ADD CONSTRAINT `fk_saran_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`Id_user`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi_warga`
--
ALTER TABLE `notifikasi_warga`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`Id_user`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `warga`
--
ALTER TABLE `warga`
  ADD CONSTRAINT `fk_warga_keluarga` FOREIGN KEY (`No_kk`) REFERENCES `keluarga` (`No_kk`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_warga_user` FOREIGN KEY (`Id_user`) REFERENCES `user` (`Id_user`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
