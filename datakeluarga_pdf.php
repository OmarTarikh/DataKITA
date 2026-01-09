<?php
session_start();

/* ================= PROTEKSI USER ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$user_id = $_SESSION['user_id'];

/* ================= AMBIL DATA KELUARGA + WARGA ================= */
$stmt = $koneksi->prepare("
    SELECT 
        k.No_kk,
        k.Kepala_keluarga,
        k.Alamat,
        k.RT,
        k.RW,
        k.Kelurahan,
        k.Kecamatan,
        k.status,
        GROUP_CONCAT(w.Nama SEPARATOR ', ') AS anggota
    FROM keluarga k
    LEFT JOIN warga w 
        ON w.No_kk = k.No_kk 
        AND w.status = 'terverifikasi'
    WHERE k.Id_user = ?
    GROUP BY k.No_kk
    ORDER BY k.RT ASC, k.RW ASC
");
$stmt->execute([$user_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$data) {
    die('Data keluarga kosong');
}

/* ================= DOMPDF ================= */
$options = new Options();
$options->set('isRemoteEnabled', true);
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
        font-family: DejaVu Sans;
        font-size: 10px;
        background: #f8f9fc;
    }

    .header {
        background: #4E73DF;
        color: #fff;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .header h4 {
        margin: 0;
        font-weight: 700;
        text-align: center;
        letter-spacing: 1px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }

    th {
        background: #4E73DF;
        color: #fff;
        text-align: center;
        font-weight: 700;
        padding: 6px;
        border: 1px solid #dee2e6;
    }

    td {
        padding: 6px;
        border: 1px solid #dee2e6;
        vertical-align: middle;
    }

    tbody tr:nth-child(even) {
        background: #f2f6ff;
    }

    .text-center {
        text-align: center;
    }

    .badge-status {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 9px;
        font-weight: 700;
        color: #fff;
        display: inline-block;
    }

    .pending {
        background: #f6c23e;
    }

    .terverifikasi {
        background: #1cc88a;
    }

    .footer {
        margin-top: 15px;
        font-size: 9px;
        text-align: right;
        color: #6c757d;
    }
</style>
</head>

<body>

<div class="header">
    <h4>DATA KELUARGA & ANGGOTA</h4>
</div>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>No KK</th>
    <th>Kepala Keluarga</th>
    <th>Alamat</th>
    <th>RT</th>
    <th>RW</th>
    <th>Kelurahan</th>
    <th>Kecamatan</th>
    <th>Anggota Keluarga</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
';

$no = 1;
foreach ($data as $d) {

    $statusClass = $d['status'] === 'terverifikasi'
        ? 'terverifikasi'
        : 'pending';

    $html .= '
    <tr>
        <td class="text-center">'.$no++.'</td>
        <td>'.$d['No_kk'].'</td>
        <td>'.$d['Kepala_keluarga'].'</td>
        <td>'.$d['Alamat'].'</td>
        <td class="text-center">'.$d['RT'].'</td>
        <td class="text-center">'.$d['RW'].'</td>
        <td>'.$d['Kelurahan'].'</td>
        <td>'.$d['Kecamatan'].'</td>
        <td>'.($d['anggota'] ?: '-').'</td>
        <td class="text-center">
            <span class="badge-status '.$statusClass.'">
                '.ucfirst($d['status']).'
            </span>
        </td>
    </tr>';
}

$html .= '
</tbody>
</table>

<div class="footer">
    Dicetak pada: '.date('d-m-Y H:i').' | DataKITA
</div>

</body>
</html>
';

/* ================= RENDER PDF ================= */
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

/* ================= OUTPUT ================= */
$dompdf->stream(
    'Data_Keluarga_User.pdf',
    ['Attachment' => false]
);
exit;
