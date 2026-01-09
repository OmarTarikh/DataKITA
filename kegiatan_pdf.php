<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Akses ditolak');
}

require 'vendor/autoload.php';

/* ================= AMBIL DATA KEGIATAN ================= */
$stmt = $koneksi->prepare("
    SELECT
        id_kegiatan,
        judul,
        deskripsi,
        tanggal,
        waktu_mulai,
        waktu_selesai,
        tempat,
        expired_at,
        created_at
    FROM kegiatan_masyarakat
    ORDER BY tanggal DESC
");
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= LOAD DOMPDF ================= */
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

/* ================= HTML PDF ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Kegiatan Masyarakat</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #000;
        }
        h2 {
            text-align: center;
            margin-bottom: 5px;
        }
        p.subtitle {
            text-align: center;
            margin-top: 0;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background-color: #4E73DF;
            color: #fff;
            padding: 6px;
            border: 1px solid #000;
            font-size: 11px;
        }
        td {
            padding: 6px;
            border: 1px solid #000;
            vertical-align: top;
        }
        .center {
            text-align: center;
        }
        .footer {
            position: fixed;
            bottom: -30px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>
<body>

<h2>LAPORAN KEGIATAN MASYARAKAT</h2>
<p class="subtitle">Sistem DataKITA</p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Judul</th>
            <th>Deskripsi</th>
            <th>Tanggal</th>
            <th>Waktu</th>
            <th>Tempat</th>
            <th>Expired</th>
            <th>Dibuat</th>
        </tr>
    </thead>
    <tbody>
';

/* ================= ISI DATA ================= */
if (count($data) > 0) {
    foreach ($data as $row) {

        $waktu = '-';
        if (!empty($row['waktu_mulai']) && !empty($row['waktu_selesai'])) {
            $waktu = substr($row['waktu_mulai'], 0, 5) . ' - ' . substr($row['waktu_selesai'], 0, 5);
        }

        $html .= '
        <tr>
            <td class="center">'.htmlspecialchars($row['id_kegiatan']).'</td>
            <td>'.htmlspecialchars($row['judul']).'</td>
            <td>'.htmlspecialchars($row['deskripsi']).'</td>
            <td class="center">'.date('d-m-Y', strtotime($row['tanggal'])).'</td>
            <td class="center">'.$waktu.'</td>
            <td>'.htmlspecialchars($row['tempat'] ?? '-').'</td>
            <td class="center">'.(
                $row['expired_at']
                ? date('d-m-Y H:i', strtotime($row['expired_at']))
                : '-'
            ).'</td>
            <td class="center">'.date('d-m-Y H:i', strtotime($row['created_at'])).'</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td colspan="8" class="center">Tidak ada data kegiatan</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div class="footer">
    Dicetak pada '.date('d F Y H:i').' | DataKITA
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
    'Laporan_Kegiatan_Masyarakat.pdf',
    ['Attachment' => false]
);
