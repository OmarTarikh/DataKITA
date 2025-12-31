<?php
require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ================= VALIDASI ================= */
if (!isset($_GET['nik']) || empty($_GET['nik'])) {
    die('NIK tidak ditemukan');
}

$nik = $_GET['nik'];

/* ================= AMBIL DATA ================= */
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
    WHERE w.NIK = ?
");
$stmt->execute([$nik]);
$w = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$w) {
    die('Data warga tidak ditemukan');
}

/* ================= DOMPDF ================= */
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

/* ================= FOTO KTP ================= */
$fotoKtpHtml = '';
if (!empty($w['Dokumen_ktp']) && file_exists('img/ktp/'.$w['Dokumen_ktp'])) {
    $imgData = base64_encode(file_get_contents('img/ktp/'.$w['Dokumen_ktp']));
    $fotoKtpHtml = '
        <img src="data:image/jpeg;base64,'.$imgData.'"
            style="
                max-width:350px;
                border-radius:10px;
                border:2px solid #d1d3e2;
                margin-top:8px;
            ">
    ';
} else {
    $fotoKtpHtml = '<div class="text-muted">Dokumen KTP belum diunggah</div>';
}

/* ================= HTML ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<!-- Bootstrap (struktur saja, inline CSS tetap utama) -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Custom CSS -->
<link rel="stylesheet" href="css/style.css">

<style>
    body {
        font-family: DejaVu Sans;
        font-size: 12px;
        background: #f8f9fc;
    }

    .modal-header-sim {
        background-color:#4E73DF;
        color:#fff;
        padding:14px 18px;
        font-weight:700;
        font-size:15px;
        border-radius:12px 12px 0 0;
        text-align:center;
    }

    .card-sim {
        background:#fff;
        border:1px solid #d1d3e2;
        border-radius:0 0 12px 12px;
        padding:18px;
    }

    label {
        font-weight:600;
        color:#6c757d;
        font-size:11px;
        margin-bottom:3px;
    }

    .field {
        background:#e9ecef;
        padding:7px 14px;
        border-radius:20px;
        font-size:12px;
        margin-bottom:10px;
    }

    .row {
        display:flex;
        gap:12px;
    }

    .col-6 {
        width:50%;
    }

    .center {
        text-align:center;
        margin-top:15px;
    }
</style>
</head>

<body>

<div class="modal-header-sim">
    DETAIL DATA WARGA
</div>

<div class="card-sim">

    <div class="row">
        <div class="col-6">
            <label>NIK</label>
            <div class="field">'.$w['NIK'].'</div>
        </div>
        <div class="col-6">
            <label>Nama Lengkap</label>
            <div class="field">'.$w['Nama'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Tempat Lahir</label>
            <div class="field">'.$w['Tempat_lahir'].'</div>
        </div>
        <div class="col-6">
            <label>Tanggal Lahir</label>
            <div class="field">'.$w['Tanggal_lahir'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Jenis Kelamin</label>
            <div class="field">'.$w['Jenis_kelamin'].'</div>
        </div>
        <div class="col-6">
            <label>Agama</label>
            <div class="field">'.$w['Agama'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Pendidikan</label>
            <div class="field">'.$w['Pendidikan'].'</div>
        </div>
        <div class="col-6">
            <label>Pekerjaan</label>
            <div class="field">'.$w['Pekerjaan'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Status Perkawinan</label>
            <div class="field">'.$w['Status_perkawinan'].'</div>
        </div>
        <div class="col-6">
            <label>No KK</label>
            <div class="field">'.$w['No_kk'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Alamat</label>
            <div class="field">'.$w['Alamat'].'</div>
        </div>
        <div class="col-6">
            <label>RT / RW</label>
            <div class="field">'.$w['RT'].' / '.$w['RW'].'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Kelurahan</label>
            <div class="field">'.$w['Kelurahan'].'</div>
        </div>
        <div class="col-6">
            <label>Kecamatan</label>
            <div class="field">'.$w['Kecamatan'].'</div>
        </div>
    </div>

    <div class="center">
        <label>Foto KTP</label><br>
        '.$fotoKtpHtml.'
    </div>

</div>

</body>
</html>
';

/* ================= RENDER PDF ================= */
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream(
    'Warga_'.$w['NIK'].'.pdf',
    ['Attachment' => false]
);
exit;
