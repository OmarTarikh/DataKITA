<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ================= USER DATA ================= */
$userStmt = $koneksi->prepare("
    SELECT 
        u.Nama_user,
        u.Email,
        u.Foto_profil,
        w.NIK,
        w.Tanggal_lahir,
        w.Tempat_lahir,
        k.Alamat
    FROM User u
    LEFT JOIN Warga w ON w.Id_user = u.Id_user
    LEFT JOIN Keluarga k ON k.No_kk = w.No_kk
    WHERE u.Id_user = ?
");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$fotoProfil = $user['Foto_profil'] ?: 'default.png';

/* ================= DATA KELUARGA (DARI WARGA USER) ================= */
$keluargaStmt = $koneksi->prepare("
    SELECT DISTINCT
        k.No_kk,
        k.Kepala_keluarga,
        k.Alamat,
        k.RT,
        k.RW,
        k.Kelurahan,
        k.Kecamatan,
        k.Dokumen_kk,
        k.status
    FROM keluarga k
    INNER JOIN warga w ON w.No_kk = k.No_kk
    WHERE w.Id_user = ?
    ORDER BY k.RT ASC
");
$keluargaStmt->execute([$user_id]);
$dataKeluarga = $keluargaStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DATA WARGA (SEMUA ANGGOTA 1 KK) ================= */

/* 1. Ambil No_kk milik user login */
$kkStmt = $koneksi->prepare("
    SELECT DISTINCT No_kk
    FROM warga
    WHERE Id_user = ?
    LIMIT 1
");
$kkStmt->execute([$user_id]);
$no_kk_user = $kkStmt->fetchColumn();

/* 2. Jika user belum terhubung ke KK */
if (!$no_kk_user) {
    $dataWarga = [];
} else {

    /* 3. Ambil SEMUA warga dalam KK tersebut */
    $wargaStmt = $koneksi->prepare("
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
            w.Dokumen_ktp
        FROM warga w
        WHERE 
            w.status = 'terverifikasi'
            AND w.No_kk = ?
        ORDER BY w.Nama ASC
    ");

    $wargaStmt->execute([$no_kk_user]);
    $dataWarga = $wargaStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= DETAIL WARGA ================= */
$detailWarga = null;

if (isset($_GET['view'])) {
    $nik = $_GET['view'];

    $detailStmt = $koneksi->prepare("
        SELECT 
            w.NIK, w.Nama, w.Tempat_lahir, w.Tanggal_lahir,
            w.Jenis_kelamin, w.Agama, w.Pendidikan, w.Pekerjaan,
            w.Status_perkawinan, w.No_kk, w.Dokumen_ktp
        FROM Warga w
        WHERE w.NIK = ? AND w.Id_user = ?
    ");
    $detailStmt->execute([$nik, $user_id]);
    $detailWarga = $detailStmt->fetch(PDO::FETCH_ASSOC);
}

/* ================= TAMBAH DATA WARGA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_warga') {

    $nik              = $_POST['nik'];
    $nama             = $_POST['nama'];
    $tempat_lahir     = $_POST['tempat_lahir'];
    $tanggal_lahir    = $_POST['tanggal_lahir'];
    $jenis_kelamin    = $_POST['jenis_kelamin'];
    $agama            = $_POST['agama'];
    $pendidikan       = $_POST['pendidikan'];
    $pekerjaan        = $_POST['pekerjaan'];
    $status_perkawinan= $_POST['status_perkawinan'];

    /* Ambil NO_KK milik user */
    $kkStmt = $koneksi->prepare("SELECT No_kk FROM Keluarga WHERE Id_user = ? LIMIT 1");
    $kkStmt->execute([$user_id]);
    $kk = $kkStmt->fetchColumn();

    if (!$kk) {
        die('No KK tidak ditemukan untuk user ini');
    }

    /* Upload KTP */
    $namaFileKtp = null;
    if (!empty($_FILES['dokumen_ktp']['name'])) {
        $ext = pathinfo($_FILES['dokumen_ktp']['name'], PATHINFO_EXTENSION);
        $namaFileKtp = 'ktp_'.$nik.'.'.$ext;
        move_uploaded_file($_FILES['dokumen_ktp']['tmp_name'], 'img/ktp/'.$namaFileKtp);
    }

    /* INSERT KE TABEL WARGA */
    $insert = $koneksi->prepare("
        INSERT INTO Warga (
            NIK, Nama, Tempat_lahir, Tanggal_lahir,
            Jenis_kelamin, Agama, Pendidikan, Pekerjaan,
            Status_perkawinan, No_kk, Dokumen_ktp,
            Id_user, status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");

    $insert->execute([
        $nik,
        $nama,
        $tempat_lahir,
        $tanggal_lahir,
        $jenis_kelamin,
        $agama,
        $pendidikan,
        $pekerjaan,
        $status_perkawinan,
        $kk,
        $namaFileKtp,
        $user_id,
        'pending'
    ]);

    header("Location: datawarga.php?success=1");
    exit;
}

/* ================= AJUKAN EDIT DATA WARGA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_warga') {

    $id_user = $_SESSION['user_id'];

    /* DATA BARU */
    $nik               = $_POST['nik'];
    $nama              = $_POST['nama'];
    $tempat_lahir      = $_POST['tempat_lahir'];
    $tanggal_lahir     = $_POST['tanggal_lahir'];
    $jenis_kelamin     = $_POST['jenis_kelamin'];
    $agama             = $_POST['agama'];
    $pendidikan        = $_POST['pendidikan'];
    $pekerjaan         = $_POST['pekerjaan'];
    $status_perkawinan = $_POST['status_perkawinan'];
    $no_kk             = $_POST['no_kk'];
    $catatan           = $_POST['catatan'];

    /* ===== UPLOAD KTP (OPSIONAL) ===== */
    $dokumen_ktp = null;
    if (!empty($_FILES['dokumen_ktp']['name'])) {

        $folderKtp = 'img/ktp/';
        if (!is_dir($folderKtp)) mkdir($folderKtp, 0777, true);

        $ext = strtolower(pathinfo($_FILES['dokumen_ktp']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) die('Format KTP tidak valid');

        $dokumen_ktp = 'ktp_'.$nik.'_'.time().'.'.$ext;
        move_uploaded_file($_FILES['dokumen_ktp']['tmp_name'], $folderKtp.$dokumen_ktp);
    }

    /* ===== UPLOAD KK (WAJIB) ===== */
    if (empty($_FILES['dokumen_kk']['name'])) {
        die('Dokumen KK wajib diunggah');
    }

    $folderKK = 'img/kk/';
    if (!is_dir($folderKK)) mkdir($folderKK, 0777, true);

    $ext = strtolower(pathinfo($_FILES['dokumen_kk']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'])) die('Format KK tidak valid');

    $dokumen_kk = 'kk_'.$nik.'_'.time().'.'.$ext;
    move_uploaded_file($_FILES['dokumen_kk']['tmp_name'], $folderKK.$dokumen_kk);

    /* ===== SIMPAN KE data_pending ===== */
    $stmt = $koneksi->prepare("
        INSERT INTO data_pending (
            tipe_data, aksi, id_user,
            nik, no_kk,
            nama, tempat_lahir, tanggal_lahir,
            jenis_kelamin, agama, pendidikan,
            pekerjaan, status_perkawinan,
            dokumen_ktp, dokumen_kk,
            catatan, status
        ) VALUES (
            'warga','edit',?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pending'
        )
    ");

    $stmt->execute([
        $id_user,
        $nik, $no_kk,
        $nama, $tempat_lahir, $tanggal_lahir,
        $jenis_kelamin, $agama, $pendidikan,
        $pekerjaan, $status_perkawinan,
        $dokumen_ktp, $dokumen_kk,
        $catatan
    ]);

    header("Location: datawarga.php?edit=success");
    exit;
}

/* EDIT KELUARGA */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit_keluarga') {

    $id_user = $_SESSION['user_id'];

    $no_kk            = $_POST['no_kk'];
    $kepala_keluarga  = $_POST['kepala_keluarga'];
    $alamat           = $_POST['alamat'];
    $rt               = $_POST['rt'];
    $rw               = $_POST['rw'];
    $kelurahan        = $_POST['kelurahan'];
    $kecamatan        = $_POST['kecamatan'];
    $catatan          = $_POST['catatan'];

    /* ================= UPLOAD DOKUMEN KK (WAJIB) ================= */
    if (empty($_FILES['dokumen_kk']['name'])) {
        die('Dokumen KK wajib diunggah');
    }

    $folderKK = 'img/kk/';
    if (!is_dir($folderKK)) {
        mkdir($folderKK, 0777, true);
    }

    $ext = strtolower(pathinfo($_FILES['dokumen_kk']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
        die('Format dokumen KK harus JPG / PNG');
    }

    $dokumen_kk = 'kk_' . $no_kk . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['dokumen_kk']['tmp_name'], $folderKK . $dokumen_kk);

    /* ================= INSERT KE DATA_PENDING ================= */
$stmt = $koneksi->prepare("
    INSERT INTO data_pending (
        tipe_data, aksi, id_user,
        no_kk, kepala_keluarga, alamat,
        rt, rw, kelurahan, kecamatan,
        dokumen_kk, catatan, status
    ) VALUES (
        'keluarga',
        'edit',
        :id_user,
        :no_kk,
        :kepala_keluarga,
        :alamat,
        :rt,
        :rw,
        :kelurahan,
        :kecamatan,
        :dokumen_kk,
        :catatan,
        'pending'
    )
");

$stmt->execute([
    ':id_user'          => $id_user,
    ':no_kk'            => $no_kk,
    ':kepala_keluarga'  => $kepala_keluarga,
    ':alamat'           => $alamat,
    ':rt'               => $rt,
    ':rw'               => $rw,
    ':kelurahan'        => $kelurahan,
    ':kecamatan'        => $kecamatan,
    ':dokumen_kk'       => $dokumen_kk,
    ':catatan'          => $catatan
]);

    header("Location: datawarga.php?edit_keluarga=success");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Halaman Data Warga</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@3.0.0/dist/iconify-icon.min.js"></script>

</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a href="indexwarga.php" class="sidebar-brand d-flex align-items-center justify-content-center">
                <div class="sidebar-brand-icon">
                    <img src="img/Logo_DataKITA(3).png" style="width: 100px; height: auto;">
                </div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item">
                <a class="nav-link" href="indexwarga.php">
                <iconify-icon icon="material-symbols:dashboard"></iconify-icon>
                <span>Dashboard</span></a>
            </li>

            <hr class="sidebar-divider">

            <li class="nav-item">
                <a class="nav-link" href="datawarga.php">
                    <iconify-icon icon="mdi:table"></iconify-icon>
                    <span>Tabel Data</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <!-- Sidebar Toggler (Sidebar) -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>

        </ul>
        <!-- End of Sidebar -->

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const currentPage = window.location.pathname.split('/').pop();

                const navLinks = document.querySelectorAll('.sidebar .nav-link');

                navLinks.forEach(link => {
                    const linkPage = link.getAttribute('href');

                    if (linkPage === currentPage || (linkPage === '' && currentPage === 'index.html')) {
                    document.querySelectorAll('.sidebar .nav-item').forEach(item => item.classList.remove('active'));
                    link.closest('.nav-item').classList.add('active');
                    }
                });
            });
        </script>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                    <!-- Sidebar Toggle (Topbar) -->
                    <form class="form-inline">
                        <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                            <i class="fa fa-bars"></i>
                        </button>
                    </form>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <div class="topbar-divider d-none d-sm-block"></div>

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                <?= htmlspecialchars($user['Nama_user']) ?>
                                </span>
                                <img class="img-profile rounded-circle"
                                    src="img/profile/<?= htmlspecialchars($user['Foto_profil'] ?? 'default.png') ?>">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#profileModal">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                    <!-- Profile Modal -->
                    <div class="modal fade" id="profileModal" tabindex="-1" role="dialog" aria-labelledby="profileModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content" style="border-radius:18px; overflow:hidden; max-width:950px; margin:auto;">

                                <!-- Header -->
                                <div class="modal-header d-flex justify-content-between align-items-center"
                                    style="background-color:#4E73DF; border:none; border-top-left-radius:18px; border-top-right-radius:18px;">
                                    <h5 class="modal-title text-white font-weight-bold mb-0">Profil Pengguna</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:1;">
                                    <span aria-hidden="true" style="font-size:1.5rem;">&times;</span>
                                    </button>
                                </div>

                                <!-- Body -->
                                <div class="modal-body bg-white" style="padding:2rem 3rem;">
                                    <div class="row align-items-center mb-4">
                                        <div class="col-md-2 text-center">
                                        <form action="upload_foto.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">

                                                <div class="position-relative d-inline-block" style="cursor:pointer;"
                                                    onclick="document.getElementById('fotoInput').click();">

                                                    <img src="img/profile/<?= htmlspecialchars($user['Foto_profil'] ?? 'default.png') ?>"
                                                        class="rounded-circle profile-photo"
                                                        style="width:90px;height:90px;object-fit:cover;">

                                                    <div class="overlay d-flex flex-column justify-content-center align-items-center"
                                                        style="position:absolute; top:0; left:0; width:100%; height:100%; border-radius:50%;
                                                            background-color:rgba(0,0,0,0.4); color:white; opacity:0; transition:0.3s;">
                                                        <iconify-icon icon="mdi:pencil" style="font-size:20px;"></iconify-icon>
                                                        <small style="font-size:0.75rem;">Tambah Foto</small>
                                                    </div>
                                                </div>

                                            <!-- INPUT FILE TERSEMBUNYI -->
                                            <input type="file"
                                                id="fotoInput"
                                                name="foto"
                                                accept="image/*"
                                                style="display:none"
                                                onchange="this.form.submit()">
                                        </form>
                                        </div>
                                        <div class="col-md-10">
                                            <h5 class="font-weight-bold mb-1 text-dark">
                                                <?= htmlspecialchars($user['Nama_user']) ?>
                                            </h5>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 pr-4">
                                            <div class="form-group">
                                                <label style="font-weight:500; color:#6c757d;">Nama</label>
                                                <h5 class="font-weight-bold mb-1 text-dark">
                                                    <?= htmlspecialchars($user['Nama_user']) ?>
                                                </h5>
                                            </div>
                                            <div class="form-group">
                                            <label style="font-weight:500; color:#6c757d;">Nomor Induk Kependudukan</label>
                                                <input type="text" class="form-control rounded-pill"
                                                value="<?= htmlspecialchars($user['NIK'] ?? '-') ?>"
                                                readonly>
                                            </div>
                                            <div class="form-group">
                                            <label style="font-weight:500; color:#6c757d;">Tanggal Lahir</label>
                                                <input type="text" class="form-control rounded-pill"
                                                value="<?= $user['Tanggal_lahir'] 
                                                    ? date('d F Y', strtotime($user['Tanggal_lahir'])) 
                                                    : '-' ?>"
                                                readonly>
                                            </div>
                                            <div class="form-group">
                                            <label style="font-weight:500; color:#6c757d;">Tempat Lahir</label>
                                                <input type="text" class="form-control rounded-pill"
                                                value="<?= htmlspecialchars($user['Tempat_lahir'] ?? '-') ?>"
                                                readonly>
                                            </div>
                                        </div>

                                    <div class="col-md-6 pl-4">
                                        <div class="form-group">
                                        <label style="font-weight:500; color:#6c757d;">Email</label>
                                            <input type="text" class="form-control rounded-pill"
                                            value="<?= htmlspecialchars($user['Email']) ?>"
                                            readonly>
                                        </div>

                                        <div class="form-group">
                                        <label style="font-weight:500; color:#6c757d;">Alamat</label>
                                            <textarea class="form-control" rows="4" readonly>
                                            <?= htmlspecialchars($user['Alamat'] ?? '-') ?>
                                            </textarea>
                                        </div>
                                    </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const passwordInput = document.getElementById('profilePassword');

                            // Profile photo hover effect
                            const profileContainer = document.querySelector('.position-relative.d-inline-block');
                            const overlay = profileContainer.querySelector('.overlay');
                            const photo = profileContainer.querySelector('.profile-photo');

                            profileContainer.addEventListener('mouseenter', () => {
                            overlay.style.opacity = '1';
                            photo.style.filter = 'brightness(75%)';
                            });
                            profileContainer.addEventListener('mouseleave', () => {
                            overlay.style.opacity = '0';
                            photo.style.filter = 'brightness(100%)';
                            });
                        });
                    </script>

                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">

                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">ANGGOTA KELUARGA</h6>
                    <div class="d-flex align-items-center" style="gap:10px;">
                        <a href="#" class="btn btn-info d-inline-flex align-items-center justify-content-center" 
                        data-toggle="modal" data-target="#addWargaModal"
                        style="border:none; border-radius:10px; font-weight:700; font-size:13px;">
                        <iconify-icon icon="ic:round-plus" style="font-size:20px; margin-right:6px;"></iconify-icon>
                        AJUKAN TAMBAH DATA
                        </a>

                        <a href="datawarga_keluarga_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
                        style="border:none; border-radius:10px; font-weight:700; font-size:13px;">
                        <iconify-icon icon="mingcute:pdf-fill" style="font-size:18px; margin-right:6px;"></iconify-icon>
                        CETAK PDF
                        </a>
                    </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead style="background-color: #4E73DF; color: #fff;">
                                    <tr>
                                        <th>NIK</th>
                                        <th>NAMA</th>
                                        <th>TEMPAT LAHIR</th>
                                        <th>TGL. LAHIR</th>
                                        <th>JENIS KELAMIN</th>
                                        <th>AGAMA</th>
                                        <th>PENDIDIKAN</th>
                                        <th>PEKERJAAN</th>
                                        <th>STATUS PERKAWINAN</th>
                                        <th>NO KK</th>
                                        <th>OPSI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($dataWarga)): ?>
                                    <?php foreach ($dataWarga as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['NIK']) ?></td>
                                            <td><?= htmlspecialchars($row['Nama']) ?></td>
                                            <td><?= htmlspecialchars($row['Tempat_lahir']) ?></td>
                                            <td><?= htmlspecialchars($row['Tanggal_lahir']) ?></td>
                                            <td><?= htmlspecialchars($row['Jenis_kelamin']) ?></td>
                                            <td><?= htmlspecialchars($row['Agama']) ?></td>
                                            <td><?= htmlspecialchars($row['Pendidikan']) ?></td>
                                            <td><?= htmlspecialchars($row['Pekerjaan']) ?></td>
                                            <td><?= htmlspecialchars($row['Status_perkawinan']) ?></td>
                                            <td><?= htmlspecialchars($row['No_kk']) ?></td>
                                            <td class="text-center">
                                                <div style="display: flex; justify-content: center; align-items: center; gap: 6px;">
                                                    <!-- View Button -->
                                                    <button class="btn btn-sm btn-secondary d-flex align-items-center justify-content-center" 
                                                        title="Lihat" data-toggle="modal" data-target="#showWargaModal<?= $row['NIK'] ?>"
                                                        style="width: 28px; height: 28px; border-radius: 6px;">
                                                        <iconify-icon icon="mdi:eye" style="font-size:16px;"></iconify-icon>
                                                    </button>

                                                    <!-- Download PDF Button -->
                                                    <a href="datawarga_warga_pdf_row.php?nik=<?= $row['NIK'] ?>"
                                                        target="_blank"
                                                        class="btn btn-sm btn-info d-flex align-items-center justify-content-center"
                                                        title="Unduh PDF"
                                                        style="width: 28px; height: 28px; border-radius: 6px;">
                                                        <iconify-icon icon="mingcute:pdf-fill" style="font-size:18px;"></iconify-icon>
                                                    </a>

                                                    <!-- Edit Button -->
                                                    <button class="btn btn-sm btn-warning d-flex align-items-center justify-content-center" 
                                                        title="Edit" data-toggle="modal" data-target="#editWargaModal<?= $row['NIK'] ?>"
                                                        style="width: 28px; height: 28px; border-radius: 6px;">
                                                        <iconify-icon icon="mdi:pencil" style="font-size:16px;"></iconify-icon>
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <button class="btn btn-sm btn-danger d-flex align-items-center justify-content-center" title="Hapus" data-toggle="modal" data-target="#deleteWargaModal<?= $row['NIK'] ?>" style="width: 28px; height: 28px; border-radius: 6px;">
                                                        <iconify-icon icon="mdi:trash-can" style="font-size:16px;"></iconify-icon>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Show warga modal -->
                                        <div class="modal fade" id="showWargaModal<?= $row['NIK'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                            <div class="modal-dialog modal-lg" role="document">
                                                <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                <!-- Header -->
                                                <div class="modal-header d-flex justify-content-between align-items-center"
                                                    style="background-color:#4E73DF; border:none;">
                                                    <h5 class="modal-title text-white font-weight-bold mb-0">Detail Data Warga</h5>
                                                    <button type="button" class="close text-white" data-dismiss="modal">
                                                        <span style="font-size:1.5rem;">&times;</span>
                                                    </button>
                                                </div>

                                                <!-- Body -->
                                                <div class="modal-body bg-white" style="padding:1.5rem;">
                                                    <form>

                                                    <!-- Row 1 -->
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>NIK</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['NIK']) ?>" readonly>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Nama</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Nama']) ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Row 2 -->
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>Tempat Lahir</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Tempat_lahir']) ?>" readonly>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Tanggal Lahir</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Tanggal_lahir']) ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Row 3 -->
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>Jenis Kelamin</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Jenis_kelamin']) ?>" readonly>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Agama</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Agama']) ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Row 4 -->
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>Pendidikan</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Pendidikan']) ?>" readonly>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>Pekerjaan</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Pekerjaan']) ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- Row 5 -->
                                                    <div class="form-row">
                                                        <div class="form-group col-md-6">
                                                            <label>Status Perkawinan</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['Status_perkawinan']) ?>" readonly>
                                                        </div>
                                                        <div class="form-group col-md-6">
                                                            <label>No KK</label>
                                                            <input type="text" class="form-control rounded-pill"
                                                                value="<?= htmlspecialchars($row['No_kk']) ?>" readonly>
                                                        </div>
                                                    </div>

                                                    <!-- FOTO KTP -->
                                                    <div class="form-group text-center mt-3">
                                                        <label>Foto KTP</label><br>
                                                        <?php if (!empty($row['Dokumen_ktp'])): ?>
                                                            <img src="img/ktp/<?= htmlspecialchars($row['Dokumen_ktp']) ?>"
                                                                style="max-width:200px; border-radius:10px;
                                                                        border:2px solid #d1d3e2; box-shadow:0 0 6px rgba(0,0,0,0.1);">
                                                        <?php else: ?>
                                                            <p class="text-muted" style="font-size:0.85rem;">Tidak ada foto KTP</p>
                                                        <?php endif; ?>
                                                    </div>

                                                    </form>
                                                </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Ajukan Perubahan Data Warga Modal -->
                                        <div class="modal fade" id="editWargaModal<?= $row['NIK'] ?>" tabindex="-1" role="dialog">
                                            <div class="modal-dialog modal-lg" role="document">
                                            <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                            <!-- Header -->
                                            <div class="modal-header d-flex justify-content-between align-items-center"
                                                style="background-color:#4E73DF; border:none;">
                                                <h5 class="modal-title text-white font-weight-bold mb-0">
                                                    Ajukan Perubahan Data Warga
                                                </h5>
                                                <button type="button" class="close text-white" data-dismiss="modal">
                                                    <span style="font-size:1.5rem;">&times;</span>
                                                </button>
                                            </div>

                                            <!-- Body -->
                                            <div class="modal-body bg-white" style="padding:1.5rem;">

                                            <form method="POST" action="" enctype="multipart/form-data">

                                            <input type="hidden" name="action" value="edit_warga">
                                            <input type="hidden" name="nik" value="<?= $row['NIK'] ?>">

                                            <div class="alert alert-info" style="font-size:0.85rem; border-radius:10px;">
                                                Perubahan data akan <strong>diverifikasi oleh admin</strong> sebelum ditampilkan kembali.
                                            </div>

                                            <!-- Row 1 -->
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>NIK</label>
                                                    <input type="text" class="form-control rounded-pill"
                                                        value="<?= htmlspecialchars($row['NIK']) ?>" readonly>
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Nama</label>
                                                    <input type="text" name="nama" class="form-control rounded-pill"
                                                        value="<?= htmlspecialchars($row['Nama']) ?>" required>
                                                </div>
                                            </div>

                                            <!-- Row 2 -->
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Tempat Lahir</label>
                                                    <input type="text" name="tempat_lahir"
                                                        class="form-control rounded-pill"
                                                        value="<?= htmlspecialchars($row['Tempat_lahir']) ?>">
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Tanggal Lahir</label>
                                                    <input type="date" name="tanggal_lahir"
                                                        class="form-control rounded-pill"
                                                        value="<?= $row['Tanggal_lahir'] ?>">
                                                </div>
                                            </div>

                                            <!-- Row 3 -->
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Jenis Kelamin</label>
                                                    <select name="jenis_kelamin"
                                                        class="form-control rounded-pill" required>
                                                        <option value="Laki-laki"
                                                            <?= $row['Jenis_kelamin']=='Laki-laki'?'selected':'' ?>>
                                                            Laki-laki
                                                        </option>
                                                        <option value="Perempuan"
                                                            <?= $row['Jenis_kelamin']=='Perempuan'?'selected':'' ?>>
                                                            Perempuan
                                                        </option>
                                                    </select>
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Agama</label>
                                                    <select name="agama"
                                                        class="form-control rounded-pill" required>
                                                        <?php
                                                        $agamaList = ['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu'];
                                                        foreach ($agamaList as $a): ?>
                                                            <option value="<?= $a ?>"
                                                                <?= $row['Agama']==$a?'selected':'' ?>>
                                                                <?= $a ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Row 4 -->
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Pendidikan</label>
                                                    <select name="pendidikan"
                                                        class="form-control rounded-pill" required>

                                                        <?php
                                                        $listPendidikan = [
                                                            'BELUM ADA','TK','SD','SMP','SMA','D3','S1','S2','S3'
                                                        ];

                                                        foreach ($listPendidikan as $p): ?>

                                                            <option value="<?= $p ?>"
                                                                <?= $row['Pendidikan']===$p?'selected':'' ?>>
                                                                <?= $p ?>
                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Pekerjaan</label>
                                                    <input type="text" name="pekerjaan"
                                                        class="form-control rounded-pill"
                                                        value="<?= htmlspecialchars($row['Pekerjaan']) ?>">
                                                </div>
                                            </div>

                                            <!-- Row 5 -->
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Status Perkawinan</label>
                                                    <select name="status_perkawinan"
                                                        class="form-control rounded-pill" required>

                                                        <option value="Belum Menikah"
                                                            <?= $row['Status_perkawinan']=='Belum Menikah'?'selected':'' ?>>
                                                            Belum Menikah
                                                        </option>

                                                        <option value="Menikah"
                                                            <?= $row['Status_perkawinan']=='Menikah'?'selected':'' ?>>
                                                            Menikah
                                                        </option>

                                                        <option value="Cerai"
                                                            <?= $row['Status_perkawinan']=='Cerai'?'selected':'' ?>>
                                                            Cerai
                                                        </option>

                                                    </select>
                                                </div>

                                                <div class="form-group col-md-6">
                                                    <label>Upload Foto KTP (Jika Punya)</label>
                                                    <input type="file" name="dokumen_ktp"
                                                        class="form-control rounded-pill">
                                                </div>
                                            </div>

                                            <!-- ============ TAMBAHAN BARU SESUAI PERMINTAANMU ============ -->

                                            <div class="form-group mt-3">
                                                <label style="font-weight:500; color:#6c757d;">Catatan Perubahan</label>
                                                <textarea name="catatan"
                                                    class="form-control"
                                                    rows="3"
                                                    placeholder="Jelaskan alasan perubahan data..."
                                                    style="font-size:0.9rem; border-radius:10px; padding:0.6rem 1rem; resize:none;"></textarea>
                                            </div>

                                            <div class="form-group">
                                                <label style="font-weight:500; color:#6c757d;">Upload Dokumen KK Pendukung</label>
                                                <input type="file" name="dokumen_kk"
                                                    class="form-control mt-1"
                                                    style="font-size:0.9rem; border-radius:10px; padding:0.45rem 1rem;">
                                            </div>

                                            <!-- Footer -->
                                            <div class="modal-footer d-flex justify-content-between align-items-center">
                                                <button class="btn btn-secondary"
                                                    type="button"
                                                    data-dismiss="modal"
                                                    style="border-radius:10px;">
                                                    Batal
                                                </button>

                                                <button class="btn btn-primary font-weight-bold"
                                                    type="submit"
                                                    style="border-radius:10px;">
                                                    AJUKAN PERUBAHAN
                                                </button>
                                            </div>

                                            </form>

                                            </div>

                                            </div>
                                            </div>
                                        </div>

                                        <!-- Ajukan Penghapusan Data Warga Modal -->
                                        <div class="modal fade" 
                                            id="deleteWargaModal<?= $row['NIK'] ?>" 
                                            tabindex="-1" 
                                            role="dialog" 
                                            aria-labelledby="deleteWargaModalLabel<?= $row['NIK'] ?>" 
                                            aria-hidden="true">

                                            <div class="modal-dialog" role="document">

                                                <div class="modal-content" style="border-radius:12px; overflow:hidden;">

                                                    <!-- Header -->
                                                    <div class="modal-header" style="background-color:#dc3545; border:none;">
                                                        <h5 class="modal-title text-white font-weight-bold" 
                                                            id="deleteWargaModalLabel<?= $row['NIK'] ?>">
                                                            Ajukan Penghapusan Data Warga
                                                        </h5>

                                                        <button class="close text-white" 
                                                                type="button" 
                                                                data-dismiss="modal" 
                                                                aria-label="Close" 
                                                                style="opacity:1;">
                                                            <span aria-hidden="true">×</span>
                                                        </button>
                                                    </div>

                                                    <!-- Body -->
                                                    <div class="modal-body text-gray-700" style="font-size:0.95rem;">

                                                        <p class="mb-3">
                                                            Ajukan permohonan untuk 
                                                            <strong class="text-danger">menghapus data warga</strong> dari sistem.<br>
                                                            Mohon isi alasan penghapusan dan lampirkan 
                                                            <strong>Foto KK</strong> sebagai bukti pendukung.
                                                        </p>

                                                        <form method="POST" 
                                                            action="request_hapus.php" 
                                                            enctype="multipart/form-data">

                                                            <!-- Parameter Penting -->
                                                            <input type="hidden" name="action" value="request_hapus_warga">
                                                            <input type="hidden" name="tipe_data" value="warga">
                                                            <input type="hidden" name="aksi" value="hapus">

                                                            <!-- kirim NIK yang ingin dihapus -->
                                                            <input type="hidden" name="nik" value="<?= $row['NIK'] ?>">

                                                            <!-- Field: Catatan -->
                                                            <div class="form-group">
                                                                <label style="font-weight:500; color:#6c757d;">
                                                                    Alasan Penghapusan
                                                                </label>

                                                                <textarea name="catatan" 
                                                                        class="form-control" 
                                                                        rows="3"
                                                                        placeholder="Tuliskan alasan penghapusan data ini..."
                                                                        style="font-size:0.9rem; border-radius:10px; padding:0.6rem 1rem; resize:none;"
                                                                        required></textarea>
                                                            </div>

                                                            <!-- Field: Upload Foto KK -->
                                                            <div class="form-group">
                                                                <label style="font-weight:500; color:#6c757d;">
                                                                    Upload Foto KK Pendukung
                                                                </label>

                                                                <input type="file" 
                                                                    name="foto_kk" 
                                                                    class="form-control mt-1"
                                                                    style="font-size:0.9rem; border-radius:10px; padding:0.45rem 1rem;"
                                                                    required>

                                                                <small class="text-muted" style="font-size:0.75rem;">
                                                                    Unggah foto KK (format JPG/PNG, maks. 2MB)
                                                                </small>
                                                            </div>

                                                            <!-- Footer -->
                                                            <div class="modal-footer d-flex justify-content-between align-items-center">

                                                                <button class="btn btn-secondary" 
                                                                        type="button" 
                                                                        data-dismiss="modal"
                                                                        style="border-radius:8px; font-weight:600;">
                                                                    Batal
                                                                </button>

                                                                <button class="btn btn-danger font-weight-bold" 
                                                                        type="submit"
                                                                        style="border-radius:8px;">
                                                                    Ajukan Penghapusan
                                                                </button>

                                                            </div>

                                                        </form>

                                                    </div>

                                                </div>

                                            </div>
                                        </div>

                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">
                                            Tidak ada data warga
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>                
        </div>

                <div class="card shadow mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">DATA KELUARGA</h6>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="keluargaTable" width="100%" cellspacing="0">
                                <thead style="background-color: #4E73DF; color: #fff;">
                                    <tr>
                                    <th>NOMOR KARTU KELUARGA</th>
                                    <th>KEPALA KELUARGA</th>
                                    <th>ALAMAT</th>
                                    <th>RT</th>
                                    <th>RW</th>
                                    <th>KELURAHAN</th>
                                    <th>KECAMATAN</th>
                                    <th>OPSI</th>
                                    </tr>
                                </thead>
                                    <tbody>
                                    <?php if (empty($dataKeluarga)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Belum ada data keluarga
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dataKeluarga as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['No_kk']) ?></td>
                                                <td><?= htmlspecialchars($row['Kepala_keluarga']) ?></td>
                                                <td><?= htmlspecialchars($row['Alamat']) ?></td>
                                                <td><?= htmlspecialchars($row['RT']) ?></td>
                                                <td><?= htmlspecialchars($row['RW']) ?></td>
                                                <td><?= htmlspecialchars($row['Kelurahan']) ?></td>
                                                <td><?= htmlspecialchars($row['Kecamatan']) ?></td>
                                                <td class="text-center">
                                                    <div style="display: flex; justify-content: center; align-items: center; gap: 6px;">
                                                        <!-- View Button -->
                                                            <button class="btn btn-sm btn-secondary d-flex align-items-center justify-content-center" title="Lihat" data-toggle="modal" data-target="#showKeluargaModal<?= $row['No_kk'] ?>" style="width: 28px; height: 28px; border-radius: 6px;">
                                                                <iconify-icon icon="mdi:eye" style="font-size:16px;"></iconify-icon>
                                                            </button>

                                                        <!-- Edit Button -->
                                                            <button class="btn btn-sm btn-warning d-flex align-items-center justify-content-center"
                                                                title="Edit"
                                                                data-toggle="modal"
                                                                data-target="#editKeluargaModal<?= $row['No_kk'] ?>"
                                                                style="width: 28px; height: 28px; border-radius: 6px;">
                                                                <iconify-icon icon="mdi:pencil" style="font-size:16px;"></iconify-icon>
                                                            </button>
                                                        <!-- PDF Button -->
                                                            <a href="datakeluarga_pdf.php?id=<?= $row['No_kk'] ?>" class="btn btn-sm btn-danger d-flex align-items-center justify-content-center" 
                                                                title="Unduh PDF"
                                                                style="width: 28px; height: 28px; border-radius: 6px;">
                                                                <iconify-icon icon="mingcute:pdf-fill" style="font-size:18px;"></iconify-icon>
                                                            </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Lihat Keluarga Modal -->
                                            <div class="modal fade"
                                                id="showKeluargaModal<?= $row['No_kk'] ?>"
                                                tabindex="-1"
                                                role="dialog"
                                                aria-labelledby="showKeluargaModalLabel<?= $row['No_kk'] ?>"
                                                aria-hidden="true">

                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header d-flex justify-content-between align-items-center"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold mb-0">
                                                                Detail Data Keluarga
                                                            </h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal" style="opacity:1;">
                                                                <span style="font-size:1.5rem;">&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body bg-white" style="padding:1.5rem; max-height:80vh; overflow-y:auto;">
                                                            <form>

                                                                <!-- Row 1 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label style="font-weight:500; color:#6c757d;">Nomor Kartu Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['No_kk']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                    <div class="form-group col-md-6">
                                                                        <label style="font-weight:500; color:#6c757d;">Kepala Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['Kepala_keluarga']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                </div>

                                                                <!-- Row 2 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-12">
                                                                        <label style="font-weight:500; color:#6c757d;">Alamat</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['Alamat']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                </div>

                                                                <!-- Row 3 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-3">
                                                                        <label style="font-weight:500; color:#6c757d;">RT</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['RT']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                    <div class="form-group col-md-3">
                                                                        <label style="font-weight:500; color:#6c757d;">RW</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['RW']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                    <div class="form-group col-md-3">
                                                                        <label style="font-weight:500; color:#6c757d;">Kelurahan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['Kelurahan']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                    <div class="form-group col-md-3">
                                                                        <label style="font-weight:500; color:#6c757d;">Kecamatan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['Kecamatan']) ?>"
                                                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                                                    </div>
                                                                </div>

                                                                <!-- Row 4: Dokumen KK -->
                                                                <div class="form-group text-center mt-3 mb-4">
                                                                    <label style="font-weight:500; color:#6c757d;">Dokumen KK</label><br>

                                                                    <?php if (!empty($row['Dokumen_kk'])): ?>
                                                                        <img src="img/kk/<?= htmlspecialchars($row['Dokumen_kk']) ?>"
                                                                            alt="Dokumen KK"
                                                                            style="max-width:400px; height:auto; border-radius:10px;
                                                                            border:2px solid #d1d3e2;
                                                                            box-shadow:0 0 6px rgba(0,0,0,0.1); margin-top:8px;">
                                                                    <?php else: ?>
                                                                        <p class="text-muted mt-2">Dokumen KK belum diunggah</p>
                                                                    <?php endif; ?>
                                                                </div>

                                                            </form>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Ajukan Perubahan Data Keluarga Modal -->
                                            <div class="modal fade"
                                                id="editKeluargaModal<?= $row['No_kk'] ?>"
                                                tabindex="-1"
                                                role="dialog"
                                                aria-hidden="true">

                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold">
                                                                Ajukan Perubahan Data Keluarga
                                                            </h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body bg-white" style="padding:1.5rem;">
                                                            <form method="POST"
                                                                action=""
                                                                enctype="multipart/form-data">

                                                                <!-- CONTROL -->
                                                                <input type="hidden" name="action" value="edit_keluarga">
                                                                <input type="hidden" name="tipe_data" value="keluarga">
                                                                <input type="hidden" name="aksi" value="edit">
                                                                <input type="hidden" name="id_user" value="<?= $_SESSION['user_id'] ?>">
                                                                <input type="hidden" name="no_kk_lama" value="<?= $row['No_kk'] ?>">

                                                                <div class="alert alert-info" style="font-size:0.85rem;">
                                                                    Silakan ubah data keluarga di bawah ini.
                                                                    Perubahan akan <strong>menunggu persetujuan admin</strong>.
                                                                </div>

                                                                <!-- NO KK -->
                                                                <div class="form-group">
                                                                    <label>Nomor Kartu Keluarga</label>
                                                                    <input type="text"
                                                                        name="no_kk"
                                                                        class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($row['No_kk']) ?>"
                                                                        required>
                                                                </div>

                                                                <!-- Kepala Keluarga -->
                                                                <div class="form-group">
                                                                    <label>Kepala Keluarga</label>
                                                                    <input type="text"
                                                                        name="kepala_keluarga"
                                                                        class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($row['Kepala_keluarga']) ?>"
                                                                        required>
                                                                </div>

                                                                <!-- Alamat -->
                                                                <div class="form-group">
                                                                    <label>Alamat</label>
                                                                    <input type="text"
                                                                        name="alamat"
                                                                        class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($row['Alamat']) ?>"
                                                                        required>
                                                                </div>

                                                                <!-- RT RW -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>RT</label>
                                                                        <input type="text"
                                                                            name="rt"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['RT']) ?>"
                                                                            required>
                                                                    </div>
                                                                    <div class="form-group col-md-6">
                                                                        <label>RW</label>
                                                                        <input type="text"
                                                                            name="rw"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['RW']) ?>"
                                                                            required>
                                                                    </div>
                                                                </div>

                                                                <!-- Kelurahan & Kecamatan -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Kelurahan</label>
                                                                        <input type="text"
                                                                            name="kelurahan"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['Kelurahan']) ?>"
                                                                            required>
                                                                    </div>
                                                                    <div class="form-group col-md-6">
                                                                        <label>Kecamatan</label>
                                                                        <input type="text"
                                                                            name="kecamatan"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($row['Kecamatan']) ?>"
                                                                            required>
                                                                    </div>
                                                                </div>

                                                                <!-- Catatan -->
                                                                <div class="form-group">
                                                                    <label>Alasan Pengajuan</label>
                                                                    <textarea name="catatan"
                                                                        class="form-control"
                                                                        rows="3"
                                                                        placeholder="Tuliskan alasan perubahan data..."
                                                                        required></textarea>
                                                                </div>

                                                                <!-- Upload KK WAJIB -->
                                                                <div class="form-group">
                                                                    <label>Upload Foto KK (Wajib)</label>
                                                                    <input type="file"
                                                                        name="dokumen_kk"
                                                                        class="form-control"
                                                                        accept=".jpg,.jpeg,.png"
                                                                        required>
                                                                    <small class="text-muted">Format JPG / PNG</small>
                                                                </div>

                                                                <!-- Submit -->
                                                                <div class="text-right mt-3">
                                                                    <button type="submit"
                                                                        class="btn btn-primary font-weight-bold">
                                                                        AJUKAN PERUBAHAN
                                                                    </button>
                                                                </div>

                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                </div>
                 

                <!-- Tambah Data Warga -->
                <div class="modal fade" id="addWargaModal" tabindex="-1" role="dialog" aria-labelledby="addWargaModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content" style="border-radius: 18px; overflow: hidden;">

                    <!-- Header -->
                    <div class="modal-header" style="background-color: #4E73DF; border: none;">
                        <h5 class="modal-title text-white font-weight-bold">Ajukan Tambah Data Warga</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1;">
                        <span aria-hidden="true" style="font-size: 1.6rem;">&times;</span>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body bg-white" style="padding: 1.5rem;">
                        <form method="POST" enctype="multipart/form-data">

                        <!-- action flag -->
                        <input type="hidden" name="action" value="add_warga">

                        <!-- Row 1 -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">NIK</label>
                            <input type="text" name="nik" class="form-control rounded-pill"
                                placeholder="Masukkan NIK" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            </div>

                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Nama</label>
                            <input type="text" name="nama" class="form-control rounded-pill"
                                placeholder="Masukkan Nama Lengkap" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            </div>
                        </div>

                        <!-- Row 2 -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="form-control rounded-pill"
                                placeholder="Masukkan Tempat Lahir" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            </div>

                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control rounded-pill"
                                required style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            </div>
                        </div>

                        <!-- Row 3 -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-control rounded-pill" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                            </div>

                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Agama</label>
                            <select name="agama" class="form-control rounded-pill" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                                <option value="">Pilih Agama</option>
                                <option>Islam</option>
                                <option>Kristen</option>
                                <option>Katolik</option>
                                <option>Hindu</option>
                                <option>Buddha</option>
                                <option>Konghucu</option>
                            </select>
                            </div>
                        </div>

                        <!-- Row 4 -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Pendidikan</label>
                            <select name="pendidikan" class="form-control rounded-pill" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                                <option value="">Pilih Pendidikan</option>
                                <option>BELUM ADA</option>
                                <option>TK</option>
                                <option>SD</option>
                                <option>SMP</option>
                                <option>SMA</option>
                                <option>D3</option>
                                <option>S1</option>
                                <option>S2</option>
                                <option>S3</option>
                            </select>
                            </div>

                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Pekerjaan</label>
                            <input type="text" name="pekerjaan" class="form-control rounded-pill"
                                placeholder="Masukkan Pekerjaan" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            </div>
                        </div>

                        <!-- Row 5 -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Status Perkawinan</label>
                            <select name="status_perkawinan" class="form-control rounded-pill" required
                                style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                                <option value="">Pilih Status</option>
                                <option>Belum Menikah</option>
                                <option>Menikah</option>
                                <option>Cerai</option>
                            </select>
                            </div>
                            <div class="form-group col-md-6">
                            <label style="font-weight: 500; color: #6c757d;">Foto KTP</label>
                            <input type="file" name="dokumen_ktp" class="form-control-file mt-1"
                                accept="image/*" style="font-size: 0.9rem;">
                            <small class="text-muted" style="font-size: 0.75rem;">
                                Unggah foto KTP dalam format JPG/PNG
                            </small>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="form-row mt-3">
                            <div class="form-group col-md-12 d-flex justify-content-start">
                                <button type="reset"
                                    class="btn btn-danger font-weight-bold mr-2"
                                    style="border-radius: 10px; font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                                    KOSONGKAN
                                </button>
                            <button type="submit" class="btn btn-primary font-weight-bold"
                                style="border-radius: 10px; font-size: 0.9rem; padding: 0.5rem 1.4rem;">
                                UNGGAH DATA
                            </button>
                            </div>
                        </div>

                        </form>
                    </div>

                    </div>
                </div>
                </div>

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; DataKITA 2025</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="js/sb-admin-2.min.js"></script>

    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <script src="js/demo/datatables-demo.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

        const urlParams = new URLSearchParams(window.location.search);

        if (urlParams.has('success')) {
            Swal.fire({
                icon: 'success',
                title: 'Data Berhasil Dikirim',
                text: 'Data Anda berhasil dikirim ke sistem. Mohon tunggu proses verifikasi dari admin agar data dapat ditampilkan kembali.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#4E73DF'
            });
        }

        if (urlParams.get('edit') === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Perubahan Berhasil Dikirim',
                text: 'Pengajuan perubahan data Anda berhasil terkirim. Silakan tunggu verifikasi admin sebelum perubahan diterapkan.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#4E73DF'
            });
        }

        // ========== POPUP SETELAH PENGAJUAN HAPUS ==========
        if (urlParams.get('hapus') === 'pending') {
            Swal.fire({
                icon: 'info',
                title: 'Pengajuan Hapus Terkirim',
                text: 'Permohonan penghapusan data Anda berhasil terkirim. Silakan tunggu persetujuan dari admin.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc3545'
            });
        }

        if (urlParams.get('edit_keluarga') === 'success') {
        Swal.fire({
            icon: 'success',
            title: 'Perubahan Berhasil Dikirim',
            text: 'Pengajuan perubahan data keluarga berhasil terkirim. Silakan tunggu verifikasi admin.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#4E73DF'
        });
        }

        // Opsional: bersihkan URL setelah popup
        if (urlParams.has('hapus') || urlParams.has('edit') || urlParams.has('success')) {
            window.history.replaceState({}, document.title, window.location.pathname);
        }

    });
    </script>

</body>

</html>