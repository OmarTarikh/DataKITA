<?php
session_start();
require 'koneksi.php';

/* === PROTEKSI LOGIN === */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_POST['nik'])) {
    header("Location: warga.php");
    exit;
}

$nik     = $_POST['nik'];
$id_user = $_SESSION['user_id'];

/* === AMBIL DATA WARGA (untuk riwayat & file) === */
$stmt = $koneksi->prepare("
    SELECT NIK, Nama, Dokumen_ktp 
    FROM Warga 
    WHERE NIK = ?
");
$stmt->execute([$nik]);
$warga = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$warga) {
    header("Location: warga.php");
    exit;
}

/* === HAPUS FILE KTP JIKA ADA === */
if (!empty($warga['Dokumen_ktp'])) {
    $file = 'img/ktp/' . $warga['Dokumen_ktp'];
    if (file_exists($file)) {
        unlink($file);
    }
}

/* === DELETE DATA WARGA === */
$stmt = $koneksi->prepare(
    "DELETE FROM Warga WHERE NIK = ?"
);
$stmt->execute([$nik]);

/* === SIMPAN RIWAYAT ADMINISTRASI === */
$keterangan = "Data warga atas nama {$warga['Nama']} (NIK: {$nik} telah dihapus";

$stmt = $koneksi->prepare("
    INSERT INTO riwayat_administrasi
    (jenis_data, id_data, aksi, keterangan, dilakukan_oleh, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");

$stmt->execute([
    'warga',          // jenis_data
    $nik,             // id_data
    'hapus',          // aksi
    $keterangan,      // keterangan
    $id_user          // dilakukan_oleh
]);

header("Location: warga.php");
exit;
