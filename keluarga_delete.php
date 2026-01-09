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

/* ================= TRANSACTION ================= */
$koneksi->beginTransaction();

try {

    /* ================= AMBIL DATA KELUARGA ================= */
    $stmt = $koneksi->prepare("
        SELECT Kepala_keluarga, Dokumen_kk
        FROM Keluarga
        WHERE No_kk = ?
    ");
    $stmt->execute([$no_kk]);
    $keluarga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$keluarga) {
        throw new Exception("Data keluarga tidak ditemukan");
    }

    /* ================= AMBIL DATA WARGA ================= */
    $stmt = $koneksi->prepare("
        SELECT Dokumen_ktp
        FROM Warga
        WHERE No_kk = ?
    ");
    $stmt->execute([$no_kk]);
    $wargaList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* ================= HAPUS FILE KTP ================= */
    foreach ($wargaList as $w) {
        if (!empty($w['Dokumen_ktp'])) {
            $fileKtp = 'img/ktp/' . $w['Dokumen_ktp'];
            if (file_exists($fileKtp)) {
                unlink($fileKtp);
            }
        }
    }

    /* ================= HAPUS FILE KK ================= */
    if (!empty($keluarga['Dokumen_kk'])) {
        $fileKk = 'img/kk/' . $keluarga['Dokumen_kk'];
        if (file_exists($fileKk)) {
            unlink($fileKk);
        }
    }

    /* ================= HAPUS WARGA ================= */
    $stmt = $koneksi->prepare("DELETE FROM Warga WHERE No_kk = ?");
    $stmt->execute([$no_kk]);

    /* ================= HAPUS KELUARGA ================= */
    $stmt = $koneksi->prepare("DELETE FROM Keluarga WHERE No_kk = ?");
    $stmt->execute([$no_kk]);

    /* ================= RIWAYAT ================= */
    $keterangan = "Data keluarga No KK {$no_kk} (kepala keluarga {$keluarga['Kepala_keluarga']}) beserta seluruh anggota dihapus";

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

    /* ================= COMMIT ================= */
    $koneksi->commit();

    header("Location: keluarga.php");
    exit;

} catch (Exception $e) {

    $koneksi->rollBack();
    die("Gagal menghapus keluarga: " . $e->getMessage());
}
