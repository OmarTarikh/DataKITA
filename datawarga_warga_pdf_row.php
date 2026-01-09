<?php
session_start();

/* PROTEKSI USER */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* VALIDASI */
if (!isset($_GET['nik'])) {
    die("NIK tidak ditemukan.");
}

$user_id = $_SESSION['user_id'];
$nik     = $_GET['nik'];

/* ================= DATA WARGA ================= */
$stmt = $koneksi->prepare("
    SELECT 
        w.*,
        k.Alamat,
        k.RT,
        k.RW,
        k.Kelurahan,
        k.Kecamatan
    FROM Warga w
    LEFT JOIN Keluarga k ON k.No_kk = w.No_kk
    WHERE w.NIK = ? AND w.Id_user = ?
");
$stmt->execute([$nik, $user_id]);
$warga = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$warga) {
    die("Data warga tidak ditemukan atau bukan milik Anda.");
}

/* ================= FOTO KTP (TANPA GD) ================= */
/* Placeholder visual agar layout & look tetap konsisten */
$fotoKtpHtml = '
<div class="doc-placeholder">
    <div class="doc-text">
        Foto KTP<br>
        <span>(Tidak ditampilkan pada PDF)</span>
    </div>
</div>
';

/* ================= DOMPDF OPTIONS ================= */
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

/* ================= HTML ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<style>
body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 12px;
    background-color: #f8f9fc;
}

.header {
    background-color: #4E73DF;
    color: #fff;
    padding: 14px;
    border-radius: 12px 12px 0 0;
    text-align: center;
    font-weight: 700;
    font-size: 15px;
}

.content {
    background: #ffffff;
    padding: 18px;
    border-radius: 0 0 12px 12px;
    border: 1px solid #e3e6f0;
}

label {
    font-weight: 600;
    color: #6c757d;
    margin-bottom: 4px;
}

.readonly {
    background-color: #e9ecef;
    border-radius: 50px;
    padding: 6px 12px;
    border: 1px solid #ced4da;
    font-size: 12px;
}

/* Placeholder pengganti gambar (TETAP RAPI & SERASI) */
.doc-placeholder {
    width: 360px;
    height: 220px;
    border: 2px dashed #d1d3e2;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #f8f9fc;
    margin-top: 10px;
}

.doc-text {
    text-align: center;
    font-size: 12px;
    color: #6c757d;
    font-weight: 600;
}

.doc-text span {
    font-size: 10px;
    font-weight: 400;
}

.footer {
    margin-top: 12px;
    font-size: 10px;
    color: #6c757d;
    text-align: right;
}
</style>
</head>

<body>

<div class="header">
    DETAIL DATA WARGA
</div>

<div class="content">

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>NIK</label>
            <div class="readonly">'.$warga['NIK'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Nama Lengkap</label>
            <div class="readonly">'.$warga['Nama'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Tempat Lahir</label>
            <div class="readonly">'.$warga['Tempat_lahir'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Tanggal Lahir</label>
            <div class="readonly">'.$warga['Tanggal_lahir'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Jenis Kelamin</label>
            <div class="readonly">'.$warga['Jenis_kelamin'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Agama</label>
            <div class="readonly">'.$warga['Agama'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Pendidikan</label>
            <div class="readonly">'.$warga['Pendidikan'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Pekerjaan</label>
            <div class="readonly">'.$warga['Pekerjaan'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Status Perkawinan</label>
            <div class="readonly">'.$warga['Status_perkawinan'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>No KK</label>
            <div class="readonly">'.$warga['No_kk'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Alamat</label>
            <div class="readonly">'.$warga['Alamat'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>RT / RW</label>
            <div class="readonly">'.$warga['RT'].' / '.$warga['RW'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Kelurahan</label>
            <div class="readonly">'.$warga['Kelurahan'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Kecamatan</label>
            <div class="readonly">'.$warga['Kecamatan'].'</div>
        </div>
    </div>

    <div class="text-center mt-3">
        <label>Foto KTP</label><br>
        '.$fotoKtpHtml.'
    </div>

    <div class="footer">
        Dicetak pada: '.date('d-m-Y H:i').' | DataKITA
    </div>

</div>

</body>
</html>
';

/* ================= PDF ================= */
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("warga_{$warga['NIK']}.pdf", ["Attachment" => false]);
exit;
