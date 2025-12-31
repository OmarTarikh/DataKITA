<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    header("Location: index.php");
    exit;
}

$allowed = ['jpg', 'jpeg', 'png', 'webp'];
$ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    header("Location: index.php");
    exit;
}

$folder = 'img/profile/';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

/* Nama file aman & unik */
$filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
$target = $folder . $filename;

/* Pindahkan file */
if (move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {

    /* Simpan ke database */
    $stmt = $koneksi->prepare("
        UPDATE User 
        SET Foto_profil = ? 
        WHERE Id_user = ?
    ");
    $stmt->execute([$filename, $user_id]);
}

header("Location: index.php");
exit;
