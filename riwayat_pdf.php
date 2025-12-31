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
use Dompdf\Options;

/* ================= AMBIL DATA RIWAYAT ================= */
$data = $koneksi->query("
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
    ORDER BY r.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (!$data) {
    die('Data riwayat administrasi kosong');
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

<style>
    body {
        font-family: DejaVu Sans;
        font-size: 10px;
        background: #f8f9fc;
    }

    .header {
        background: #4E73DF;
        color: #fff;
        padding: 14px;
        border-radius: 8px;
        margin-bottom: 14px;
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

    .badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 9px;
        font-weight: 700;
        color: #fff;
        display: inline-block;
        text-transform: uppercase;
    }

    .tambah { background: #1cc88a; }
    .ubah { background: #36b9cc; }
    .hapus { background: #e74a3b; }
    .verifikasi { background: #f6c23e; }

    .footer {
        margin-top: 14px;
        font-size: 9px;
        text-align: right;
        color: #6c757d;
    }
</style>
</head>

<body>

<div class="header">
    <h4>RIWAYAT ADMINISTRASI</h4>
</div>

<table>
<thead>
<tr>
    <th>No</th>
    <th>Jenis Data</th>
    <th>ID Data</th>
    <th>Aksi</th>
    <th>Keterangan</th>
    <th>Dilakukan Oleh</th>
    <th>Tanggal</th>
</tr>
</thead>
<tbody>
';

$no = 1;
foreach ($data as $d) {

    $aksiClass = strtolower($d['aksi']);

    $html .= '
    <tr>
        <td class="text-center">'.$no++.'</td>
        <td class="text-center">'.ucfirst($d['jenis_data']).'</td>
        <td>'.$d['id_data'].'</td>
        <td class="text-center">
            <span class="badge '.$aksiClass.'">
                '.$d['aksi'].'
            </span>
        </td>
        <td>'.($d['keterangan'] ?? '-').'</td>
        <td class="text-center">'.($d['Nama_user'] ?? 'System').'</td>
        <td class="text-center">'.date('d-m-Y H:i', strtotime($d['created_at'])).'</td>
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
    'Riwayat_Administrasi.pdf',
    ['Attachment' => false]
);
exit;
