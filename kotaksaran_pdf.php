<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Akses ditolak');
}

require 'koneksi.php';
require 'vendor/autoload.php';
/* ================= AMBIL DATA KOTAK SARAN ================= */
$stmt = $koneksi->prepare("
    SELECT 
        ks.id_saran,
        u.Nama_user,
        ks.isi_saran,
        ks.id_user,
        ks.created_at,
        k.RT,
        k.RW,
        ks.status
    FROM kotak_saran ks
    LEFT JOIN user u ON u.Id_user = ks.id_user
    LEFT JOIN warga w ON w.Id_user = u.Id_user
    LEFT JOIN keluarga k ON k.No_kk = w.No_kk
    ORDER BY ks.created_at DESC
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
    <title>Laporan Kotak Saran</title>
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
        .status-baru {
            background: #f6c23e;
            color: #000;
            font-weight: bold;
            text-align: center;
        }
        .status-dibaca {
            background: #36b9cc;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }
        .status-ditindaklanjuti {
            background: #1cc88a;
            color: #fff;
            font-weight: bold;
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

<h2>LAPORAN KOTAK SARAN</h2>
<p class="subtitle">Sistem DataKITA</p>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nama User</th>
            <th>Isi Saran</th>
            <th>ID User</th>
            <th>Tanggal</th>
            <th>RT</th>
            <th>RW</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($data) > 0) {
    foreach ($data as $row) {

        $statusClass = 'status-baru';
        if ($row['status'] === 'dibaca') $statusClass = 'status-dibaca';
        if ($row['status'] === 'ditindaklanjuti') $statusClass = 'status-ditindaklanjuti';

        $html .= '
        <tr>
            <td class="center">'.htmlspecialchars($row['id_saran']).'</td>
            <td>'.htmlspecialchars($row['Nama_user'] ?? '-').'</td>
            <td>'.htmlspecialchars($row['isi_saran']).'</td>
            <td class="center">'.htmlspecialchars($row['id_user']).'</td>
            <td class="center">'.date('d-m-Y H:i', strtotime($row['created_at'])).'</td>
            <td class="center">'.($row['RT'] ?? '-').'</td>
            <td class="center">'.($row['RW'] ?? '-').'</td>
            <td class="'.$statusClass.'">'.ucfirst($row['status']).'</td>
        </tr>';
    }
} else {
    $html .= '
        <tr>
            <td colspan="8" class="center">Tidak ada data saran</td>
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
    'Laporan_Kotak_Saran.pdf',
    ['Attachment' => false]
);
