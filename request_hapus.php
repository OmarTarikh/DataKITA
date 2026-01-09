<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

/* ================== VALIDASI REQUEST ================== */
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    ($_POST['action'] ?? '') !== 'request_hapus_warga'
) {
    header("Location: datawarga.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$tipe_data = 'warga';
$aksi      = 'hapus';
$nik       = $_POST['nik'] ?? null;
$catatan   = $_POST['catatan'] ?? null;

if (!$nik) {
    die('NIK tidak valid');
}

/* ================== UPLOAD FOTO KK (WAJIB) ================== */
if (!isset($_FILES['foto_kk']) || $_FILES['foto_kk']['error'] !== UPLOAD_ERR_OK) {
    die('Foto KK wajib diunggah');
}

$folderKK = 'img/kk/';
if (!is_dir($folderKK)) {
    mkdir($folderKK, 0777, true);
}

$ext = strtolower(pathinfo($_FILES['foto_kk']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
    die('Format file KK tidak didukung');
}

$namaFileKK = 'kk_' . $nik . '_' . time() . '.' . $ext;

move_uploaded_file(
    $_FILES['foto_kk']['tmp_name'],
    $folderKK . $namaFileKK
);

/* ================== SIMPAN KE data_pending ================== */
$stmt = $koneksi->prepare("
    INSERT INTO data_pending
    (
        tipe_data,
        aksi,
        id_user,
        nik,
        dokumen_kk,
        catatan,
        status
    )
    VALUES (?,?,?,?,?,?,?)
");

$stmt->execute([
    $tipe_data,
    $aksi,
    $user_id,
    $nik,
    $namaFileKK,
    $catatan,
    'pending'
]);

/* ================== REDIRECT ================== */
header("Location: datawarga.php?hapus=pending");
exit;
