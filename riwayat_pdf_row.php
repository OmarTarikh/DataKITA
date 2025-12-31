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
    die("ID riwayat tidak ditemukan");
}

$id = $_GET['id'];

$stmt = $koneksi->prepare("
    SELECT 
        r.id,
        r.jenis_data,
        r.id_data,
        r.aksi,
        r.keterangan,
        r.created_at,
        u.Nama_user
    FROM riwayat_administrasi r
    LEFT JOIN User u ON u.Id_user = r.dilakukan_oleh
    WHERE r.id = ?
");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("Data tidak ditemukan");
}

/* ================= HTML ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

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

    .label {
        font-weight: 600;
        color: #6c757d;
        margin-bottom: 3px;
    }

    .value {
        background-color: #e9ecef;
        border-radius: 50px;
        padding: 6px 14px;
        border: 1px solid #ced4da;
        margin-bottom: 10px;
    }

    .footer {
        margin-top: 15px;
        font-size: 10px;
        color: #6c757d;
        text-align: right;
    }
</style>
</head>

<body>

<div class="header">
    DETAIL RIWAYAT ADMINISTRASI
</div>

<div class="content">

    <div>
        <div class="label">Jenis Data</div>
        <div class="value">'.strtoupper($data['jenis_data']).'</div>
    </div>

    <div>
        <div class="label">ID Data (No KK / NIK)</div>
        <div class="value">'.$data['id_data'].'</div>
    </div>

    <div>
        <div class="label">Aksi</div>
        <div class="value">'.strtoupper($data['aksi']).'</div>
    </div>

    <div>
        <div class="label">Keterangan</div>
        <div class="value">'.($data['keterangan'] ?? '-').'</div>
    </div>

    <div>
        <div class="label">Dilakukan Oleh</div>
        <div class="value">'.($data['admin'] ?? 'System').'</div>
    </div>

    <div>
        <div class="label">Tanggal</div>
        <div class="value">'.date('d-m-Y H:i', strtotime($data['created_at'])).'</div>
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
$pdf->stream("riwayat_administrasi_{$data['id']}.pdf", ["Attachment" => false]);
exit;
