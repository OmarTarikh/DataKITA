<?php
require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ================= AMBIL DATA WARGA ================= */
$stmt = $koneksi->query("
    SELECT 
        w.NIK,
        w.Nama,
        w.Tempat_lahir,
        w.Tanggal_lahir,
        w.Jenis_kelamin,
        w.Agama,
        w.Pendidikan,
        w.Pekerjaan,
        w.Status_perkawinan,
        w.No_kk,
        k.RT,
        k.RW,
        k.Kelurahan,
        k.Kecamatan
    FROM Warga w
    LEFT JOIN Keluarga k ON k.No_kk = w.No_kk
    ORDER BY w.Nama ASC
");

$dataWarga = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$dataWarga) {
    die('Data warga kosong');
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

<!-- Bootstrap -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

<!-- Custom CSS -->
<link rel="stylesheet" href="css/style.css">

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
    <h4>DAFTAR DATA WARGA</h4>
</div>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>NIK</th>
    <th>Nama</th>
    <th>Tempat / Tgl Lahir</th>
    <th>JK</th>
    <th>Agama</th>
    <th>Pendidikan</th>
    <th>Pekerjaan</th>
    <th>Status</th>
    <th>No KK</th>
    <th>RT</th>
    <th>RW</th>
    <th>Kelurahan</th>
    <th>Kecamatan</th>
</tr>
</thead>
<tbody>
';

$no = 1;
foreach ($dataWarga as $w) {
    $html .= '
    <tr>
        <td class="text-center">'.$no++.'</td>
        <td>'.$w['NIK'].'</td>
        <td>'.$w['Nama'].'</td>
        <td>'.$w['Tempat_lahir'].', '.$w['Tanggal_lahir'].'</td>
        <td class="text-center">'.$w['Jenis_kelamin'].'</td>
        <td>'.$w['Agama'].'</td>
        <td>'.$w['Pendidikan'].'</td>
        <td>'.$w['Pekerjaan'].'</td>
        <td>'.$w['Status_perkawinan'].'</td>
        <td>'.$w['No_kk'].'</td>
        <td class="text-center">'.$w['RT'].'</td>
        <td class="text-center">'.$w['RW'].'</td>
        <td>'.$w['Kelurahan'].'</td>
        <td>'.$w['Kecamatan'].'</td>
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
    'Daftar_Data_Warga.pdf',
    ['Attachment' => false]
);
exit;
