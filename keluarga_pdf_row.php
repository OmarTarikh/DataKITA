<?php
session_start();

/* PROTEKSI */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;

/* VALIDASI */
if (!isset($_GET['id'])) {
    die("No KK tidak ditemukan.");
}

$no_kk = $_GET['id'];

/* ================= DATA KELUARGA ================= */
$stmt = $koneksi->prepare("
    SELECT 
        No_kk,
        Kepala_keluarga,
        Alamat,
        RT,
        RW,
        Kelurahan,
        Kecamatan,
        Dokumen_kk
    FROM Keluarga
    WHERE No_kk = ?
");
$stmt->execute([$no_kk]);
$keluarga = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$keluarga) {
    die("Data keluarga tidak ditemukan.");
}

/* ================= DATA ANGGOTA ================= */
$anggotaStmt = $koneksi->prepare("
    SELECT NIK, Nama
    FROM Warga
    WHERE No_kk = ?
    ORDER BY Nama ASC
");
$anggotaStmt->execute([$no_kk]);
$anggota = $anggotaStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= HTML ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<!-- Bootstrap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Custom CSS -->
<link rel="stylesheet" href="css/style.css">

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

    .form-group label {
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 4px;
    }

    .readonly {
        background-color: #e9ecef;
        border-radius: 50px;
        padding: 6px 12px;
        border: 1px solid #ced4da;
    }

    .section-box {
        border: 1px solid #d1d3e2;
        border-radius: 10px;
        padding: 12px 15px;
        background-color: #f8f9fc;
        margin-top: 8px;
    }

    .anggota-header {
        font-weight: 700;
        color: #4E73DF;
        font-size: 12px;
        display: flex;
        justify-content: space-between;
    }

    .anggota-item {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        margin-top: 4px;
    }

    .doc-img {
        max-width: 380px;
        border-radius: 10px;
        border: 2px solid #d1d3e2;
        margin-top: 10px;
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
    DETAIL DATA KELUARGA
</div>

<div class="content">

    <!-- ROW 1 -->
    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Nomor Kartu Keluarga</label>
            <div class="readonly">'.$keluarga['No_kk'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Kepala Keluarga</label>
            <div class="readonly">'.$keluarga['Kepala_keluarga'].'</div>
        </div>
    </div>

    <!-- ROW 2 -->
    <div class="row">
        <div class="col-md-12 mb-2">
            <label>Alamat</label>
            <div class="readonly">'.$keluarga['Alamat'].'</div>
        </div>
    </div>

    <!-- ROW 3 -->
    <div class="row">
        <div class="col-md-6 mb-2">
            <label>RT</label>
            <div class="readonly">'.$keluarga['RT'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>RW</label>
            <div class="readonly">'.$keluarga['RW'].'</div>
        </div>
    </div>

    <!-- ROW 4 -->
    <div class="row">
        <div class="col-md-6 mb-2">
            <label>Kelurahan</label>
            <div class="readonly">'.$keluarga['Kelurahan'].'</div>
        </div>
        <div class="col-md-6 mb-2">
            <label>Kecamatan</label>
            <div class="readonly">'.$keluarga['Kecamatan'].'</div>
        </div>
    </div>

    <!-- ANGGOTA -->
    <div class="form-group mt-3">
        <label>Anggota Keluarga</label>
        <div class="section-box">
            <div class="anggota-header">
                <span>NIK</span>
                <span>Nama</span>
            </div>
            <hr style="margin:6px 0;">
';

if (count($anggota) > 0) {
    foreach ($anggota as $a) {
        $html .= '
            <div class="anggota-item">
                <span>'.$a['NIK'].'</span>
                <span>'.$a['Nama'].'</span>
            </div>
        ';
    }
} else {
    $html .= '
        <div class="text-center text-muted" style="font-size:11px;">
            Tidak ada anggota keluarga
        </div>
    ';
}

$html .= '
        </div>
    </div>

    <!-- DOKUMEN KK -->
    <div class="form-group text-center mt-3">
        <label>Dokumen KK</label><br>
';

if (!empty($keluarga['Dokumen_kk']) && file_exists('img/kk/'.$keluarga['Dokumen_kk'])) {
    $img = base64_encode(file_get_contents('img/kk/'.$keluarga['Dokumen_kk']));
    $html .= '<img src="data:image/jpeg;base64,'.$img.'" class="doc-img">';
} else {
    $html .= '<div class="text-muted" style="font-size:11px;">Belum ada dokumen</div>';
}

$html .= '
    </div>

    <div class="footer">
        Dicetak pada: '.date('d-m-Y H:i').' | DataKITA
    </div>

</div>

</body>
</html>
';

/* ================= PDF ================= */
$pdf = new Dompdf();
$pdf->loadHtml($html);
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdf->stream("keluarga_{$keluarga['No_kk']}.pdf", ["Attachment" => false]);
exit;
