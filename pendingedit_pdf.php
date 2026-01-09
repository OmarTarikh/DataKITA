<?php
require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ================= AMBIL DATA PENDING EDIT ================= */
$stmt = $koneksi->query("
    SELECT 
        dp.id_pending,
        dp.tipe_data,
        dp.id_user,
        dp.nik,
        dp.no_kk,
        dp.nama,
        dp.kepala_keluarga,
        dp.alamat,
        dp.rt,
        dp.rw,
        dp.kelurahan,
        dp.kecamatan,
        dp.catatan,
        dp.status,
        dp.created_at,

        -- fallback data warga
        w.Nama AS nama_warga,

        -- fallback data keluarga
        k.Kepala_keluarga AS kepala_keluarga_db,
        k.Alamat AS alamat_db,
        k.RT AS rt_db,
        k.RW AS rw_db,
        k.Kelurahan AS kelurahan_db,
        k.Kecamatan AS kecamatan_db

    FROM data_pending dp

    LEFT JOIN warga w 
        ON dp.nik COLLATE utf8mb4_general_ci
        = w.NIK COLLATE utf8mb4_general_ci

    LEFT JOIN keluarga k 
        ON (
            dp.no_kk COLLATE utf8mb4_general_ci
            = k.No_kk COLLATE utf8mb4_general_ci
            OR
            w.No_kk COLLATE utf8mb4_general_ci
            = k.No_kk COLLATE utf8mb4_general_ci
        )

    WHERE dp.aksi = 'edit'
    ORDER BY dp.created_at DESC
");

$dataPending = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$dataPending) {
    die('Data pending edit kosong');
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
    <h4>DAFTAR PENGAJUAN EDIT DATA</h4>
</div>

<table class="table table-bordered table-sm">
<thead>
<tr>
    <th>No</th>
    <th>Tipe</th>
    <th>NIK / No KK</th>
    <th>Nama / Kepala Keluarga</th>
    <th>Alamat</th>
    <th>RT</th>
    <th>RW</th>
    <th>Kelurahan</th>
    <th>Kecamatan</th>
    <th>ID User</th>
    <th>Status</th>
</tr>
</thead>
<tbody>
';

$no = 1;
foreach ($dataPending as $p) {

    // ===== PRIORITAS DATA =====
    $namaTampil = $p['tipe_data'] === 'warga'
        ? ($p['nama'] ?: $p['nama_warga'])
        : ($p['kepala_keluarga'] ?: $p['kepala_keluarga_db']);

    $alamat   = $p['alamat']   ?: $p['alamat_db'];
    $rt       = $p['rt']       ?: $p['rt_db'];
    $rw       = $p['rw']       ?: $p['rw_db'];
    $kelurahan= $p['kelurahan']?: $p['kelurahan_db'];
    $kecamatan= $p['kecamatan']?: $p['kecamatan_db'];

    $identitas = $p['tipe_data'] === 'warga'
        ? $p['nik']
        : $p['no_kk'];

    $html .= '
    <tr>
        <td class="text-center">'.$no++.'</td>
        <td class="text-center">'.ucfirst($p['tipe_data']).'</td>
        <td>'.$identitas.'</td>
        <td>'.$namaTampil.'</td>
        <td>'.$alamat.'</td>
        <td class="text-center">'.$rt.'</td>
        <td class="text-center">'.$rw.'</td>
        <td>'.$kelurahan.'</td>
        <td>'.$kecamatan.'</td>
        <td class="text-center">'.$p['id_user'].'</td>
        <td class="text-center">'.ucfirst($p['status']).'</td>
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
    'Data_Pending_Edit.pdf',
    ['Attachment' => false]
);
exit;
