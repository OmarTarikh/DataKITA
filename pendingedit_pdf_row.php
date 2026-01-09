<?php
require 'koneksi.php';
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/* ================= VALIDASI ================= */
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID pending tidak ditemukan');
}

$id_pending = $_GET['id'];

/* ================= AMBIL DATA PENDING (1 ROW) ================= */
$stmt = $koneksi->prepare("
    SELECT 
        dp.*,

        -- fallback warga
        w.Nama AS nama_warga,
        w.Dokumen_ktp AS dokumen_ktp_db,

        -- fallback keluarga
        k.Kepala_keluarga AS kepala_keluarga_db,
        k.Alamat AS alamat_db,
        k.RT AS rt_db,
        k.RW AS rw_db,
        k.Kelurahan AS kelurahan_db,
        k.Kecamatan AS kecamatan_db,
        k.Dokumen_kk AS dokumen_kk_db

    FROM data_pending dp
    LEFT JOIN warga w 
        ON dp.nik COLLATE utf8mb4_general_ci 
        = w.NIK COLLATE utf8mb4_general_ci
    LEFT JOIN keluarga k 
        ON (
            dp.no_kk COLLATE utf8mb4_general_ci 
            = k.No_kk COLLATE utf8mb4_general_ci
            OR w.No_kk COLLATE utf8mb4_general_ci 
            = k.No_kk COLLATE utf8mb4_general_ci
        )
    WHERE dp.id_pending = ?
");
$stmt->execute([$id_pending]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die('Data pending tidak ditemukan');
}

/* ================= PRIORITAS DATA ================= */
$identitas = $p['tipe_data'] === 'warga'
    ? $p['nik']
    : $p['no_kk'];

$namaTampil = $p['tipe_data'] === 'warga'
    ? ($p['nama'] ?: $p['nama_warga'])
    : ($p['kepala_keluarga'] ?: $p['kepala_keluarga_db']);

$alamat    = $p['alamat']     ?: $p['alamat_db'];
$rt        = $p['rt']         ?: $p['rt_db'];
$rw        = $p['rw']         ?: $p['rw_db'];
$kelurahan = $p['kelurahan']  ?: $p['kelurahan_db'];
$kecamatan = $p['kecamatan'] ?: $p['kecamatan_db'];
$catatan   = $p['catatan']    ?: '-';

/* ================= DOKUMEN ================= */
$dokumenHtml = '<div class="text-muted">Dokumen pendukung belum tersedia</div>';

if (!empty($p['dokumen_ktp']) && file_exists('img/ktp/'.$p['dokumen_ktp'])) {
    $img = base64_encode(file_get_contents('img/ktp/'.$p['dokumen_ktp']));
    $dokumenHtml = '<img src="data:image/jpeg;base64,'.$img.'" style="max-width:350px;border-radius:10px;border:2px solid #d1d3e2;">';
}
elseif (!empty($p['dokumen_kk']) && file_exists('img/kk/'.$p['dokumen_kk'])) {
    $img = base64_encode(file_get_contents('img/kk/'.$p['dokumen_kk']));
    $dokumenHtml = '<img src="data:image/jpeg;base64,'.$img.'" style="max-width:350px;border-radius:10px;border:2px solid #d1d3e2;">';
}
elseif (!empty($p['dokumen_ktp_db']) && file_exists('img/ktp/'.$p['dokumen_ktp_db'])) {
    $img = base64_encode(file_get_contents('img/ktp/'.$p['dokumen_ktp_db']));
    $dokumenHtml = '<img src="data:image/jpeg;base64,'.$img.'" style="max-width:350px;border-radius:10px;border:2px solid #d1d3e2;">';
}
elseif (!empty($p['dokumen_kk_db']) && file_exists('img/kk/'.$p['dokumen_kk_db'])) {
    $img = base64_encode(file_get_contents('img/kk/'.$p['dokumen_kk_db']));
    $dokumenHtml = '<img src="data:image/jpeg;base64,'.$img.'" style="max-width:350px;border-radius:10px;border:2px solid #d1d3e2;">';
}

/* ================= DOMPDF ================= */
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

/* ================= HTML (DESIGN TETAP) ================= */
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<style>
    body { font-family: DejaVu Sans; font-size:12px; background:#f8f9fc; }
    .modal-header-sim {
        background:#4E73DF;color:#fff;padding:14px;
        font-weight:700;font-size:15px;
        border-radius:12px 12px 0 0;text-align:center;
    }
    .card-sim {
        background:#fff;border:1px solid #d1d3e2;
        border-radius:0 0 12px 12px;padding:18px;
    }
    label { font-weight:600;color:#6c757d;font-size:11px; }
    .field {
        background:#e9ecef;padding:7px 14px;
        border-radius:20px;font-size:12px;margin-bottom:10px;
    }
    .row { display:flex; gap:12px; }
    .col-6 { width:50%; }
    .center { text-align:center;margin-top:15px; }
</style>
</head>
<body>

<div class="modal-header-sim">
    DETAIL DATA PENGAJUAN EDIT
</div>

<div class="card-sim">

    <div class="row">
        <div class="col-6">
            <label>'.($p['tipe_data']==='warga'?'NIK':'No KK').'</label>
            <div class="field">'.$identitas.'</div>
        </div>
        <div class="col-6">
            <label>Nama</label>
            <div class="field">'.$namaTampil.'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Alamat</label>
            <div class="field">'.$alamat.'</div>
        </div>
        <div class="col-6">
            <label>RT / RW</label>
            <div class="field">'.$rt.' / '.$rw.'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-6">
            <label>Kelurahan</label>
            <div class="field">'.$kelurahan.'</div>
        </div>
        <div class="col-6">
            <label>Kecamatan</label>
            <div class="field">'.$kecamatan.'</div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <label>Catatan</label>
            <div class="field">'.$catatan.'</div>
        </div>
    </div>

    <div class="center">
        <label>Dokumen Pendukung</label><br>
        '.$dokumenHtml.'
    </div>

</div>

</body>
</html>
';

/* ================= RENDER ================= */
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream(
    'Pending_Edit_'.$id_pending.'.pdf',
    ['Attachment' => false]
);
exit;
