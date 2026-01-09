<?php
session_start();
require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ================= PROTEKSI LOGIN ================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    die('Akses ditolak');
}

$user_id = $_SESSION['user_id'];

/* ================= AMBIL DATA WARGA BERDASARKAN KELUARGA ================= */
$stmt = $koneksi->prepare("
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
    INNER JOIN Keluarga k 
        ON k.No_kk = w.No_kk
    WHERE w.No_kk IN (
        SELECT No_kk 
        FROM Warga 
        WHERE Id_user = ?
    )
    AND w.status = 'terverifikasi'
    ORDER BY w.Nama ASC
");
$stmt->execute([$user_id]);
$dataWarga = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$dataWarga) {
    die('Data warga kosong');
}

/* ================= DOMPDF ================= */
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

/* ================= HTML PDF ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
    body { font-family: DejaVu Sans; font-size: 10px; }
    .header {
        background:#4E73DF;
        color:#fff;
        padding:12px;
        border-radius:8px;
        margin-bottom:12px;
        text-align:center;
        font-weight:700;
    }
    table {
        width:100%;
        border-collapse:collapse;
    }
    th {
        background:#4E73DF;
        color:#fff;
        padding:6px;
        text-align:center;
        border:1px solid #dee2e6;
    }
    td {
        padding:6px;
        border:1px solid #dee2e6;
    }
    tr:nth-child(even) { background:#f2f6ff; }
    .footer {
        margin-top:12px;
        font-size:9px;
        text-align:right;
        color:#6c757d;
    }
</style>
</head>
<body>

<div class="header">DAFTAR DATA WARGA</div>

<table>
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
        <td style="text-align:center">'.$no++.'</td>
        <td>'.$w['NIK'].'</td>
        <td>'.$w['Nama'].'</td>
        <td>'.$w['Tempat_lahir'].', '.$w['Tanggal_lahir'].'</td>
        <td style="text-align:center">'.$w['Jenis_kelamin'].'</td>
        <td>'.$w['Agama'].'</td>
        <td>'.$w['Pendidikan'].'</td>
        <td>'.$w['Pekerjaan'].'</td>
        <td>'.$w['Status_perkawinan'].'</td>
        <td>'.$w['No_kk'].'</td>
        <td style="text-align:center">'.$w['RT'].'</td>
        <td style="text-align:center">'.$w['RW'].'</td>
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
    'Data_Warga_'.$user_id.'.pdf',
    ['Attachment' => false]
);
exit;
