<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Akses ditolak');
}

require 'vendor/autoload.php';

/* ================= AMBIL DATA NOTIFIKASI WARGA ================= */
$stmt = $koneksi->prepare("
    SELECT
        n.id_notifikasi,
        n.id_user,
        u.Nama_user,
        n.pesan,
        n.expired_at,
        n.created_at
    FROM notifikasi_warga n
    LEFT JOIN user u ON u.Id_user = n.id_user
    ORDER BY n.created_at DESC
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
    <title>Laporan Notifikasi Warga</title>
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

<h2>LAPORAN NOTIFIKASI WARGA</h2>
<p class="subtitle">Sistem DataKITA</p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Tujuan</th>
            <th>Pesan</th>
            <th>Expired</th>
            <th>Dibuat</th>
        </tr>
    </thead>
    <tbody>
';

/* ================= ISI DATA ================= */
if (count($data) > 0) {
    foreach ($data as $row) {

        $tujuan = $row['id_user']
            ? htmlspecialchars($row['id_user'].' - '.$row['Nama_user'])
            : 'Semua Warga';

        $html .= '
        <tr>
            <td class="center">'.htmlspecialchars($row['id_notifikasi']).'</td>
            <td class="center">'.$tujuan.'</td>
            <td>'.htmlspecialchars($row['pesan']).'</td>
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
            <td colspan="5" class="center">Tidak ada data notifikasi</td>
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
    'Laporan_Notifikasi_Warga.pdf',
    ['Attachment' => false]
);
