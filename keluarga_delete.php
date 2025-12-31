<?php
session_start();
require 'koneksi.php';

/* ================= PROTEKSI ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: keluarga.php");
    exit;
}

$no_kk   = $_GET['id'];
$id_user = $_SESSION['user_id'];

/* ================= AMBIL DATA KELUARGA ================= */
$stmt = $koneksi->prepare("
    SELECT Kepala_keluarga, Dokumen_kk
    FROM Keluarga
    WHERE No_kk = ?
");
$stmt->execute([$no_kk]);
$keluarga = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$keluarga) {
    header("Location: keluarga.php");
    exit;
}

/* ================= HAPUS FILE KK (JIKA ADA) ================= */
if (!empty($keluarga['Dokumen_kk'])) {
    $file = 'img/kk/' . $keluarga['Dokumen_kk'];
    if (file_exists($file)) {
        unlink($file);
    }
}

/* ================= HAPUS DATA KELUARGA ================= */
$stmt = $koneksi->prepare("DELETE FROM Keluarga WHERE No_kk = ?");
$stmt->execute([$no_kk]);

/* ================= SIMPAN RIWAYAT ADMINISTRASI ================= */
$keterangan = "Data keluarga (No kk: {$no_kk} kepala keluarga {$keluarga['Kepala_keluarga']} dihapus";

$stmt = $koneksi->prepare("
    INSERT INTO riwayat_administrasi
    (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
    VALUES (?,?,?,?,?)
");
$stmt->execute([
    'keluarga',
    $no_kk,
    'hapus',
    $keterangan,
    $id_user
]);

/* ================= REDIRECT ================= */
header("Location: keluarga.php");
exit;
