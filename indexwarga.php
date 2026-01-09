<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['welcome_shown'])) {
    $_SESSION['welcome_shown'] = true;
    $showWelcome = true;
} else {
    $showWelcome = false;
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

/* ================= RT & RW USER LOGIN ================= */
$rtRwStmt = $koneksi->prepare("
    SELECT k.RT, k.RW
    FROM Keluarga k
    JOIN Warga w ON w.No_kk = k.No_kk
    WHERE w.Id_user = ?
    LIMIT 1
");
$rtRwStmt->execute([$user_id]);
$lokasi = $rtRwStmt->fetch(PDO::FETCH_ASSOC);

$rtUser = $lokasi['RT'] ?? null;
$rwUser = $lokasi['RW'] ?? null;

/* ================= JUMLAH WARGA RT (TERVERIFIKASI) ================= */
$jumlahWargaRT = 0;

if ($rtUser && $rwUser) {
    $stmtRT = $koneksi->prepare("
        SELECT COUNT(*) 
        FROM Warga w
        JOIN Keluarga k ON w.No_kk = k.No_kk
        WHERE 
            k.RT = ? 
            AND k.RW = ?
            AND w.status = 'terverifikasi'
    ");
    $stmtRT->execute([$rtUser, $rwUser]);
    $jumlahWargaRT = $stmtRT->fetchColumn();
}

/* ================= JUMLAH WARGA RW (TERVERIFIKASI) ================= */
$jumlahWargaRW = 0;

if ($rwUser) {
    $stmtRW = $koneksi->prepare("
        SELECT COUNT(*) 
        FROM Warga w
        JOIN Keluarga k ON w.No_kk = k.No_kk
        WHERE 
            k.RW = ?
            AND w.status = 'terverifikasi'
    ");
    $stmtRW->execute([$rwUser]);
    $jumlahWargaRW = $stmtRW->fetchColumn();
}



/* ================= DATA KELUARGA ================= */
$keluargaStmt = $koneksi->query("
    SELECT 
        No_kk,
        Kepala_keluarga,
        Alamat,
        RT,
        RW,
        Kelurahan,
        Kecamatan,
        Dokumen_kk,
        Id_user,
        status
    FROM Keluarga
    ORDER BY RT ASC
");
$dataKeluarga = $keluargaStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= KEGIATAN MASYARAKAT ================= */
$kegiatanStmt = $koneksi->prepare("
    SELECT 
        judul,
        deskripsi,
        tanggal,
        waktu_mulai,
        waktu_selesai,
        tempat
    FROM kegiatan_masyarakat
    ORDER BY tanggal DESC
    LIMIT 10
");
$kegiatanStmt->execute();
$kegiatanList = $kegiatanStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= KOTAK SARAN SUBMIT ================= */
$successSaran = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_saran'])) {

    $isi_saran = trim($_POST['isi_saran']);
    $id_user   = $_SESSION['user_id'];

    if ($isi_saran !== '') {
        $stmt = $koneksi->prepare("
            INSERT INTO kotak_saran (id_user, isi_saran, status)
            VALUES (?, ?, 'baru')
        ");
        $stmt->execute([$id_user, $isi_saran]);

        $successSaran = true;

        // 🔒 cegah resubmit
        header("Location: indexwarga.php?saran=success");
        exit;
    }
}

/* ================= NOTIFIKASI WARGA ================= */
$notifStmt = $koneksi->prepare("
    SELECT id_notifikasi, pesan, created_at
    FROM notifikasi_warga
    WHERE 
        (id_user IS NULL OR id_user = ?)
        AND expired_at >= NOW()
    ORDER BY created_at DESC
    LIMIT 10
");
$notifStmt->execute([$user_id]);
$notifList = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Dashboard</title>

    <link rel="stylesheet" href="css/style.css">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@3.0.0/dist/iconify-icon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>


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

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                        <!-- Left Statistic Cards -->
                        <div class="col-xl-3 col-md-12 mb-4">

                            <!-- Jumlah Keluarga RT 1 -->
                            <div class="card shadow mb-3" style="border-left: 4px solid #4E73DF; border-radius: 15px; height: 120px;">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">JUMLAH WARGA RT <?= htmlspecialchars($rtUser) ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">  <?= $jumlahWargaRT ?></div>
                                </div>
                                <div>
                                <svg xmlns="http://www.w3.org/2000/svg" width="40px" height="40px" viewBox="0 0 24 24">
                                    <path fill="#DDDFEB" d="M16 17v2H2v-2s0-4 7-4s7 4 7 4m-3.5-9.5A3.5 3.5 0 1 0 9 11a3.5 3.5 0 0 0 3.5-3.5m3.44 5.5A5.32 5.32 0 0 1 18 17v2h4v-2s0-3.63-6.06-4M15 4a3.4 3.4 0 0 0-1.93.59a5 5 0 0 1 0 5.82A3.4 3.4 0 0 0 15 11a3.5 3.5 0 0 0 0-7"/>
                                </svg>
                                </div>
                            </div>
                            </div>

                            <!-- Jumlah Warga RT 1 -->
                            <div class="card shadow mb-3" style="border-left: 4px solid #1cc88a; border-radius: 15px; height: 120px;">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <div>
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">JUMLAH WARGA RW <?= htmlspecialchars($rwUser) ?></div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $jumlahWargaRW ?></div>
                                </div>
                                <div>
                                <svg xmlns="http://www.w3.org/2000/svg" width="40px" height="40px" viewBox="0 0 24 24">
                                    <path fill="#DDDFEB" d="M12 4a4 4 0 0 1 4 4a4 4 0 0 1-4 4a4 4 0 0 1-4-4a4 4 0 0 1 4-4m0 10c4.42 0 8 1.79 8 4v2H4v-2c0-2.21 3.58-4 8-4"/>
                                </svg>
                                </div>
                            </div>
                            </div>

                        </div>

                        <!-- Right Leader Profile Cards -->
                        <div class="col-xl-9 col-md-12">

                            <div class="row">

                            <!-- Ketua RT 1 -->
                            <div class="col-md-6 mb-4">
                                <div class="card shadow d-flex flex-row align-items-center p-3" 
                                    style="border-left: 6px solid #e74a3b; border-radius: 18px; height: 255px;">
                                <div class="col-6">
                                    <h5 class="font-weight-bold mb-2" style="color:#e74a3b;">KETUA RT 1</h5>
                                    <p style="margin:0;"><strong style="color: #e74a3b;">Nama:</strong><br>Adit Maulana</p>
                                    <p style="margin:0;"><strong style="color: #e74a3b;">Kontak:</strong><br>+62 81234567891<br>Adit123@gmail.com</p>
                                    <p style="margin:0;"><strong style="color: #e74a3b;">Alamat:</strong><br>Jl. Alhamdulillah no 2</p>
                                </div>
                                <div class="col-6 ">
                                    <img src="img/glachio-lindro.png" alt="Ketua RT 1" 
                                        style="width: 224px; height:auto; ">
                                </div>
                                </div>
                            </div>

                            <!-- Ketua RW 1 -->
                            <div class="col-md-6 mb-4">
                                <div class="card shadow d-flex flex-row align-items-center p-3" 
                                    style="border-left: 6px solid #36b9cc; border-radius: 18px; height: 255px;">
                                <div class="col-6">
                                    <h5 class="font-weight-bold mb-2" style="color:#36b9cc;">KETUA RW 1</h5>
                                    <p style="margin:0;"><strong style="color: #36b9cc;">Nama:</strong><br>Glacio Lindro</p>
                                    <p style="margin:0;"><strong style="color: #36b9cc;">Kontak:</strong><br>+62 87769696969<br>Cio1212@gmail.com</p>
                                    <p style="margin:0;"><strong style="color: #36b9cc;">Alamat:</strong><br>Jl. Masyallah no 10</p>
                                </div>
                                <div class="col-6 ">
                                    <img src="img/glachio-lindro.png" alt="Ketua RW 1" 
                                        style="width: 224px; height:auto; ">
                                </div>
                                </div>
                            </div>

                            </div>
                        </div>

                    </div>

                    <!-- Content Row -->

                    <div class="row">

                        <!-- INFORMASI KEGIATAN MASYARAKAT -->
                        <div class="col-xl-4 col-md-12 mb-4">
                            <div class="card shadow h-100" style="border-radius: 15px;">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between" 
                                    style="background: transparent; border: none;">
                                    <h6 class="m-0 font-weight-bold text-primary">INFORMASI KEGIATAN MASYARAKAT</h6>
                                </div>

                                <div class="card-body" 
                                    style="max-height: 300px; overflow-y: auto; padding-top: 0.5rem;">

                                    <?php if (empty($kegiatanList)): ?>
                                        <div class="text-muted small text-center">
                                            Belum ada kegiatan masyarakat
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($kegiatanList as $i => $kegiatan): ?>
                                        <?php
                                            // WARNA BORDER BERGANTIAN (SAMA SEPERTI CONTOH)
                                            $colors = ['#e74a3b', '#36b9cc', '#1cc88a'];
                                            $textColors = ['text-danger', 'text-info', 'text-success'];
                                            $colorIndex = $i % 3;

                                            $tanggal = date('M d', strtotime($kegiatan['tanggal']));
                                            $tanggalFull = date('l, d F Y', strtotime($kegiatan['tanggal']));
                                        ?>

                                        <div class="d-flex mb-3">
                                            <div class="mr-3 text-muted small font-weight-bold">
                                                <?= $tanggal ?>
                                            </div>

                                            <div class="p-2 flex-grow-1 rounded-lg" 
                                                style="background-color: #f8f9fc; border-left: 4px solid <?= $colors[$colorIndex] ?>;">
                                                
                                                <div class="font-weight-bold <?= $textColors[$colorIndex] ?>">
                                                    <?= htmlspecialchars($kegiatan['judul']) ?>
                                                </div>

                                                <div class="small mt-1">
                                                    <strong>TANGGAL:</strong> <?= $tanggalFull ?><br>
                                                    <?php if (!empty($kegiatan['waktu_mulai']) && !empty($kegiatan['waktu_selesai'])): ?>
                                                        <strong>WAKTU:</strong>
                                                        <?= htmlspecialchars($kegiatan['waktu_mulai']) ?>
                                                        -
                                                        <?= htmlspecialchars($kegiatan['waktu_selesai']) ?><br>
                                                    <?php endif; ?>
                                                    <?php if (!empty($kegiatan['tempat'])): ?>
                                                        <strong>TEMPAT:</strong> <?= htmlspecialchars($kegiatan['tempat']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                    <?php endforeach; ?>

                                </div>
                            </div>
                        </div>

                        <!-- KOTAK SARAN -->
                        <div class="col-xl-4 col-md-12 mb-4">
                            <div class="card shadow h-100" style="border-radius: 15px;">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between" 
                                    style="background: transparent; border: none;">
                                    <h6 class="m-0 font-weight-bold text-primary">KOTAK SARAN</h6>
                                </div>

                                <div class="card-body d-flex flex-column justify-content-between">

                                    <form method="POST">
                                        <div class="p-3 text-white bg-gradient-primary my-2 mx-2" style="border-radius: 10px;">
                                            <p class="mb-2" style="font-size: 0.95rem;">
                                                Kami terbuka untuk pendapat Anda demi lingkungan yang lebih baik.
                                            </p>

                                            <textarea 
                                                name="isi_saran"
                                                class="form-control mb-3"
                                                rows="3"
                                                placeholder="Ketik disini..."
                                                style="border-radius: 15px; resize: none; height: 150px; font-size: 0.9rem;"
                                                required
                                            ></textarea>

                                            <button 
                                                type="submit"
                                                name="kirim_saran"
                                                class="btn btn-success font-weight-bold"
                                                style="border-radius: 10px; font-size: 0.85rem;">
                                                KIRIM SARAN
                                            </button>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>

                        <!-- NOTIFIKASI WARGA -->
                        <div class="col-xl-4 col-md-12 mb-4">
                            <div class="card shadow h-100" style="border-radius: 15px;">
                                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between" 
                                    style="background: transparent; border: none;">
                                    
                                    <h6 class="m-0 font-weight-bold text-primary">NOTIFIKASI WARGA</h6>

                                    <!-- TOMBOL TAMPILKAN SEMUA (AWALNYA HIDDEN) -->
                                    <a href="javascript:void(0)"
                                    id="btnShowAllNotif"
                                    onclick="tampilkanSemuaNotif()"
                                    class="text-primary small font-weight-bold"
                                    style="display:none;">
                                    Tampilkan semua
                                    </a>
                                </div>

                                <div class="card-body" 
                                    style="max-height: 300px; overflow-y: auto; padding-top: 0.5rem;">

                                    <?php if (empty($notifList)): ?>
                                        <div class="text-muted small text-center">
                                            Belum ada notifikasi
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($notifList as $i => $notif): ?>
                                        <?php
                                            $colors = ['#36b9cc', '#1cc88a', '#4e73df'];
                                            $color  = $colors[$i % count($colors)];
                                        ?>

                                        <div class="p-3 mb-3 rounded-lg notif-item"
                                            style="background-color: #eaf2ff; border-left: 4px solid <?= $color ?>;"
                                            id="notif-<?= $notif['id_notifikasi'] ?>">

                                            <p class="small mb-2">
                                                “<?= htmlspecialchars($notif['pesan']) ?>”
                                            </p>

                                            <!-- TANGGAL + HAPUS SEJAJAR -->
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-primary font-weight-bold small">
                                                    <?= date('d M Y H:i', strtotime($notif['created_at'])) ?>
                                                </span>

                                                <a href="javascript:void(0)"
                                                onclick="hapusNotif('notif-<?= $notif['id_notifikasi'] ?>')"
                                                class="text-danger small font-weight-bold">
                                                    Sembunyikan notifikasi
                                                </a>
                                            </div>
                                        </div>

                                    <?php endforeach; ?>

                                </div>
                            </div>
                        </div>

                    </div>

                </div>
                <!-- /.container-fluid -->

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

    
    <script>
        function hapusNotif(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.transition = 'opacity 0.3s ease';
                el.style.opacity = '0';
                setTimeout(() => el.style.display = 'none', 300);
            }
        }
    </script>

    <script>
        let totalNotif = document.querySelectorAll('.notif-item').length;
        let hiddenNotif = 0;

        function hapusNotif(id) {
            const el = document.getElementById(id);
            if (el && el.style.display !== 'none') {
                el.style.transition = 'opacity 0.3s ease';
                el.style.opacity = '0';

                setTimeout(() => {
                    el.style.display = 'none';
                    hiddenNotif++;

                    // munculkan tombol "Tampilkan semua"
                    if (hiddenNotif > 0) {
                        document.getElementById('btnShowAllNotif').style.display = 'inline';
                    }
                }, 300);
            }
        }

        function tampilkanSemuaNotif() {
            const items = document.querySelectorAll('.notif-item');

            items.forEach(item => {
                item.style.display = 'block';
                item.style.opacity = '1';
            });

            hiddenNotif = 0;
            document.getElementById('btnShowAllNotif').style.display = 'none';
        }
    </script>


    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/sb-admin-2.min.js"></script>
    <?php if ($showWelcome): ?>
<script>
Swal.fire({
    title: 'SELAMAT DATANG',
    html: `
        <div style="font-family:'Nunito', sans-serif;">
            <img src="img/LOGO_DataKITA.png" 
                 style="width:120px; margin-bottom:15px;">
            
            <h6 style="color:#6c757d; font-weight:700; letter-spacing:1px;">
                DI APLIKASI
            </h6>

            <h4 margin-bottom:10px;">
                <span style="color:#4e73df; font-weight:700;">Data</span>
                <span style=" font-weight:800; color:#e74a3b;">K</span>
                <span style=" font-weight:800; color:#1cc88a;">I</span>
                <span style=" font-weight:800; color:#f6c23e;">T</span>
                <span style=" font-weight:800; color:#36b9cc;">A</span>
            </h4>

            <p style="font-size:14px; color:#6c757d;">
                Kelola dan pantau data dengan mudah,<br>
                cepat, dan transparan.
            </p>
        </div>
    `,
    background: '#ffffff',
    confirmButtonText: 'MULAI',
    confirmButtonColor: '#4e73df',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showClass: {
        popup: 'animate__animated animate__zoomIn animate__faster'
    },
    hideClass: {
        popup: 'animate__animated animate__fadeOut animate__faster'
    }
});
</script>
<?php endif; ?>

    <?php if ($successSaran): ?>
    <script>
    Swal.fire({
        icon: 'success',
        title: 'Saran Berhasil Dikirim',
        text: 'Terima kasih atas partisipasi Anda. Saran yang Anda kirim akan segera kami tinjau dan tindak lanjuti demi lingkungan yang lebih baik.',
        confirmButtonText: 'Baik',
        confirmButtonColor: '#1cc88a'
    });
    </script>
    <?php endif; ?>

</body>

</html>