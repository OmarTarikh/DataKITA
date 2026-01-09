<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

date_default_timezone_set('Asia/Jakarta');
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

/* ================= DATA USER UNTUK NOTIFIKASI ================= */
$userListStmt = $koneksi->prepare("
    SELECT Id_user, Nama_user
    FROM user
    ORDER BY Nama_user ASC
");
$userListStmt->execute();
$userList = $userListStmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ===============================
 * AUTO DELETE DATA EXPIRED
 * ===============================
 */
function autoDeleteExpired(PDO $koneksi)
{
    $tables = [
        'kegiatan_masyarakat',
        'notifikasi_warga'
    ];

    foreach ($tables as $table) {
        $sql = "
            DELETE FROM {$table}
            WHERE expired_at IS NOT NULL
            AND expired_at <= NOW()
        ";

        $stmt = $koneksi->prepare($sql);
        $stmt->execute();
    }
}
autoDeleteExpired($koneksi);

/* ================= TAMBAH KEGIATAN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_kegiatan'])) {

    $judul         = $_POST['judul'];
    $deskripsi     = $_POST['deskripsi'];
    $tanggal       = $_POST['tanggal'];
    $waktu_mulai   = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $tempat        = $_POST['tempat'];

    $expired_at = !empty($_POST['expired_at'])
        ? $_POST['expired_at']
        : $tanggal . ' 23:59:59';

    $stmt = $koneksi->prepare("
        INSERT INTO kegiatan_masyarakat
        (judul, deskripsi, tanggal, waktu_mulai, waktu_selesai, tempat, expired_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $judul,
        $deskripsi,
        $tanggal,
        $waktu_mulai,
        $waktu_selesai,
        $tempat,
        $expired_at
    ]);

    $id_kegiatan = $koneksi->lastInsertId();

    /* === RIWAYAT ADMINISTRASI === */
    $riwayat = $koneksi->prepare("
        INSERT INTO riwayat_administrasi
        (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
        VALUES (?, ?, ?, ?, ?)
    ");
    $riwayat->execute([
        'dashboard',
        'kegiatan_' . $id_kegiatan,
        'tambah',
        'Menambahkan kegiatan masyarakat',
        $_SESSION['user_id']
    ]);

    header("Location: dashboardwarga.php?tambah_kegiatan=success");
    exit;
}

/* ================= DATA KEGIATAN ================= */
$kegiatanStmt = $koneksi->prepare("
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
$kegiatanStmt->execute();
$kegiatanWarga = $kegiatanStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= UPDATE KEGIATAN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kegiatan'])) {

    $id_kegiatan   = $_POST['id_kegiatan'];
    $judul         = $_POST['judul'];
    $deskripsi     = $_POST['deskripsi'];
    $tanggal       = $_POST['tanggal'];
    $waktu_mulai   = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $tempat        = $_POST['tempat'];
    $expired_at    = !empty($_POST['expired_at']) ? $_POST['expired_at'] : null;

    $stmt = $koneksi->prepare("
        UPDATE kegiatan_masyarakat SET
            judul = ?,
            deskripsi = ?,
            tanggal = ?,
            waktu_mulai = ?,
            waktu_selesai = ?,
            tempat = ?,
            expired_at = ?
        WHERE id_kegiatan = ?
    ");
    $stmt->execute([
        $judul,
        $deskripsi,
        $tanggal,
        $waktu_mulai,
        $waktu_selesai,
        $tempat,
        $expired_at,
        $id_kegiatan
    ]);

    /* === RIWAYAT === */
    $riwayat = $koneksi->prepare("
        INSERT INTO riwayat_administrasi
        (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
        VALUES (?, ?, ?, ?, ?)
    ");
    $riwayat->execute([
        'dashboard',
        'kegiatan_' . $id_kegiatan,
        'ubah',
        'Mengubah kegiatan masyarakat',
        $_SESSION['user_id']
    ]);

    header("Location: dashboardwarga.php?edit=success");
    exit;
}

/* ================= HAPUS KEGIATAN MASYARAKAT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_kegiatan'])) {

    $id_kegiatan = $_POST['id_kegiatan'];

    if (!empty($id_kegiatan)) {

        /* === HAPUS DATA KEGIATAN === */
        $stmt = $koneksi->prepare("
            DELETE FROM kegiatan_masyarakat
            WHERE id_kegiatan = ?
        ");
        $stmt->execute([$id_kegiatan]);

        /* === OPTIONAL: RIWAYAT ADMINISTRASI === */
        $riwayat = $koneksi->prepare("
            INSERT INTO riwayat_administrasi
            (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
        $riwayat->execute([
            'dashboard',
            'kegiatan_' . $id_kegiatan,
            'hapus',
            'Menghapus kegiatan masyarakat',
            $_SESSION['user_id']
        ]);

        /* === REDIRECT (ANTI DOUBLE SUBMIT) === */
        header("Location: dashboardwarga.php?hapus_kegiatan=success");
        exit;
    }
}


/* ================= KOTAK SARAN ================= */
$saranStmt = $koneksi->prepare("
    SELECT 
        ks.id_saran,
        ks.id_user,
        ks.isi_saran,
        ks.status,
        ks.created_at,
        u.Nama_user,
        k.RT,
        k.RW
    FROM kotak_saran ks
    LEFT JOIN user u ON u.Id_user = ks.id_user
    LEFT JOIN warga w ON w.Id_user = ks.id_user
    LEFT JOIN keluarga k ON k.No_kk = w.No_kk
    ORDER BY ks.created_at DESC
");
$saranStmt->execute();
$kotakSaran = $saranStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= HAPUS SARAN ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_saran'])) {

    $id_saran = $_POST['id_saran'];

    if (!empty($id_saran)) {

        /* === HAPUS DATA SARAN === */
        $stmt = $koneksi->prepare("DELETE FROM kotak_saran WHERE id_saran = ?");
        $stmt->execute([$id_saran]);

        /* === RIWAYAT ADMINISTRASI === */
        $riwayat = $koneksi->prepare("
            INSERT INTO riwayat_administrasi
            (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
        $riwayat->execute([
            'dashboard',
            'saran_' . $id_saran,
            'hapus',
            'Menghapus data kotak saran',
            $_SESSION['user_id']
        ]);

        /* === REDIRECT === */
        header("Location: dashboardwarga.php?hapus=success");
        exit;
    }
}

/* ================= DATA NOTIFIKASI WARGA ================= */
$notifStmt = $koneksi->prepare("
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
$notifStmt->execute();
$notifikasiWarga = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= TAMBAH NOTIFIKASI WARGA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_notifikasi'])) {

    $id_user    = $_POST['id_user'] !== '' ? $_POST['id_user'] : null;
    $pesan      = $_POST['pesan'];
    $expired_at = !empty($_POST['expired_at'])
        ? $_POST['expired_at']
        : date('Y-m-d 23:59:59');

    $stmt = $koneksi->prepare("
        INSERT INTO notifikasi_warga
        (id_user, pesan, expired_at)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$id_user, $pesan, $expired_at]);

    $id_notifikasi = $koneksi->lastInsertId();

    /* === RIWAYAT === */
        $riwayat = $koneksi->prepare("
            INSERT INTO riwayat_administrasi
            (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
    $riwayat->execute([
        'dashboard',
        'notifikasi_' . $id_notifikasi,
        'tambah',
        'Menambahkan notifikasi warga',
        $_SESSION['user_id']
    ]);

    header("Location: dashboardwarga.php?tambah_notifikasi=success");
    exit;
}

/* ================= UPDATE NOTIFIKASI WARGA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifikasi'])) {

    $id_notifikasi = $_POST['id_notifikasi'];
    $id_user       = $_POST['id_user']; // boleh NULL
    $pesan         = $_POST['pesan'];
    $expired_at    = !empty($_POST['expired_at']) ? $_POST['expired_at'] : null;

    if (empty($id_notifikasi) || empty($pesan)) {
        die('Data tidak valid');
    }

    $stmt = $koneksi->prepare("
        UPDATE notifikasi_warga SET
            id_user    = ?,
            pesan      = ?,
            expired_at = ?
        WHERE id_notifikasi = ?
    ");

    $stmt->execute([
        $id_user !== '' ? $id_user : null, // NULL = semua warga
        $pesan,
        $expired_at,
        $id_notifikasi
    ]);

    /* === OPTIONAL: RIWAYAT ADMINISTRASI === */
        $riwayat = $koneksi->prepare("
            INSERT INTO riwayat_administrasi
            (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
        $riwayat->execute([
            'dashboard',
            'notifikasi_' . $id_notifikasi,
            'ubah',
            'Mengubah notifikasi warga',
            $_SESSION['user_id']
        ]);

    /* === REDIRECT (ANTI DOUBLE SUBMIT) === */
    header("Location: dashboardwarga.php?edit_notifikasi=success");
    exit;
}

/* ================= HAPUS NOTIFIKASI WARGA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_notifikasi'])) {

    $id_notifikasi = $_POST['id_notifikasi'];

    if (!empty($id_notifikasi)) {

        /* === HAPUS DATA NOTIFIKASI === */
        $stmt = $koneksi->prepare("
            DELETE FROM notifikasi_warga
            WHERE id_notifikasi = ?
        ");
        $stmt->execute([$id_notifikasi]);

        /* === OPTIONAL: RIWAYAT ADMINISTRASI === */
        $riwayat = $koneksi->prepare("
            INSERT INTO riwayat_administrasi
            (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
            VALUES (?, ?, ?, ?, ?)
        ");
        $riwayat->execute([
            'dashboard',
            'notifikasi_' . $id_notifikasi,
            'hapus',
            'Menghapus notifikasi warga',
            $_SESSION['user_id']
        ]);
        /* === REDIRECT (ANTI DOUBLE SUBMIT) === */
        header("Location: dashboardwarga.php?hapus_notifikasi=success");
        exit;
    }
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

    <title>Halaman Dashboard Warga</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="vendor/datatables/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style2.css">
    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@3.0.0/dist/iconify-icon.min.js"></script>

</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="index.php">
                <div class="sidebar-brand-icon">
                    <img src="img/Logo_DataKITA(3).png" style="width: 100px; height: auto;">
                </div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-0">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                <iconify-icon icon="material-symbols:dashboard"></iconify-icon>
                <span>Dashboard</span></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="dashboardwarga.php">
                <iconify-icon icon="ep:list"></iconify-icon>
                <span>Dashboard Warga</span></a>
            </li>


            <hr class="sidebar-divider">

            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages"
                    aria-expanded="true" aria-controls="collapsePages">
                    <iconify-icon icon="mdi:table"></iconify-icon>
                    <span>Tabel</span></a>
                </a>
                <div id="collapsePages" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="warga.php">Warga</a>
                        <a class="collapse-item" href="keluarga.php">Keluarga</a>
                    </div>
                </div>
            </li>



            <li class="nav-item">
                <a class="nav-link" href="riwayat.php">
                    <iconify-icon icon="material-symbols:history"></iconify-icon>
                    <span>Riwayat</span>
                </a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">

            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-toggle="collapse" data-target="#collapsePages2"
                    aria-expanded="true" aria-controls="collapsePages">
                    <iconify-icon icon="lucide:folder-sync"></iconify-icon>
                    <span>Pengajuan Perubahan</span></a>
                </a>
                <div id="collapsePages2" class="collapse" aria-labelledby="headingPages" data-parent="#accordionSidebar">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="pendingedit.php">Edit</a>
                        <a class="collapse-item" href="pendinghapus.php">Hapus</a>
                    </div>
                </div>
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
                            <h6 class="m-0 font-weight-bold text-primary">KOTAK SARAN</h6>
                            <div class="d-flex align-items-center" style="gap:10px;">

                                <a href="kotaksaran_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
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
                                            <th>ID SARAN</th>
                                            <th>NAMA USER</th>
                                            <th>ISI SARAN</th>
                                            <th>ID USER</th>
                                            <th>TANGGAL</th>
                                            <th >RT</th>
                                            <th >RW</th>
                                            <th>STATUS</th>
                                            <th>OPSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($kotakSaran) > 0): ?>
                                            <?php foreach ($kotakSaran as $k): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($k['id_saran']) ?></td>
                                                    <td><?= htmlspecialchars($k['Nama_user'] ?? 'Anonim') ?></td>
                                                    <td><?= htmlspecialchars($k['isi_saran']) ?></td>
                                                    <td><?= htmlspecialchars($k['id_user']) ?></td>
                                                    <td><?= htmlspecialchars($k['created_at']) ?></td>

                                                    <!-- TD HIDDEN (RT & RW) -->
                                                    <td><?= htmlspecialchars($k['RT'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($k['RW'] ?? '-') ?></td>

                                                    <!-- STATUS -->
                                                    <td>
                                                        <form method="POST" action="update_status_saran.php">
                                                            <input type="hidden" name="id_saran" value="<?= $k['id_saran'] ?>">
                                                            <input type="hidden" name="status" id="status<?= $k['id_saran'] ?>">

                                                            <a class="btn btn-sm nav-link dropdown-toggle 
                                                                <?= $k['status'] === 'ditindaklanjuti'
                                                                    ? 'btn-success'
                                                                    : ($k['status'] === 'dibaca'
                                                                        ? 'btn-info'
                                                                        : 'btn-warning') ?>"
                                                                id="statusDropdown<?= $k['id_saran'] ?>" 
                                                                href="#"
                                                                role="button"
                                                                data-toggle="dropdown"
                                                                aria-haspopup="true"
                                                                aria-expanded="false"
                                                                style="min-width:80px; font-weight:600;">
                                                                <?= ucfirst($k['status']) ?>
                                                            </a>

                                                            <div class="dropdown-menu dropdown-menu-right animated--fade-in"
                                                                aria-labelledby="statusDropdown<?= $k['id_saran'] ?>">

                                                                <a href="#"
                                                                class="dropdown-item text-warning font-weight-bold"
                                                                onclick="
                                                                    document.getElementById('status<?= $k['id_saran'] ?>').value='baru';
                                                                    this.closest('form').submit();
                                                                ">
                                                                    <span class="badge badge-warning">&nbsp;</span>
                                                                    Baru
                                                                </a>

                                                                <div class="dropdown-divider"></div>

                                                                <a href="#"
                                                                class="dropdown-item text-info font-weight-bold"
                                                                onclick="
                                                                    document.getElementById('status<?= $k['id_saran'] ?>').value='dibaca';
                                                                    this.closest('form').submit();
                                                                ">
                                                                    <span class="badge badge-info">&nbsp;</span>
                                                                    Dibaca
                                                                </a>

                                                                <div class="dropdown-divider"></div>

                                                                <a href="#"
                                                                class="dropdown-item text-success font-weight-bold"
                                                                onclick="
                                                                    document.getElementById('status<?= $k['id_saran'] ?>').value='ditindaklanjuti';
                                                                    this.closest('form').submit();
                                                                ">
                                                                    <span class="badge badge-success">&nbsp;</span>
                                                                    Ditindaklanjuti
                                                                </a>

                                                            </div>
                                                        </form>
                                                    </td>
                                                    <td class="text-center">
                                                        <div style="display:flex; justify-content:center; gap:5px;">

                                                            <!-- HAPUS -->
                                                            <button class="btn btn-sm btn-danger"
                                                                title="Hapus"
                                                                data-toggle="modal"
                                                                data-target="#deleteSaranModal<?= $k['id_saran'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:trash"></iconify-icon>
                                                            </button>

                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">
                                                            Data keluarga belum tersedia
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                            <?php foreach ($kotakSaran as $k): ?>
                                            <!-- Delete Confirmation Modal Saran -->
                                            <div class="modal fade"
                                                id="deleteSaranModal<?= $k['id_saran'] ?>"
                                                tabindex="-1"
                                                role="dialog">

                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content" style="border-radius:12px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header" style="background-color:#dc3545; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold">
                                                                Hapus Saran
                                                            </h5>
                                                            <button class="close text-white" type="button" data-dismiss="modal">
                                                                <span>×</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body text-gray-700" style="font-size:0.95rem;">
                                                            Apakah Anda yakin ingin
                                                            <strong class="text-danger">menghapus saran ini</strong>?<br>
                                                            Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                                                        </div>

                                                        <!-- Footer -->
                                                        <div class="modal-footer">

                                                            <button class="btn btn-secondary" data-dismiss="modal">
                                                                Batal
                                                            </button>

                                                            <!-- FORM HAPUS (DALAM FILE INI) -->
                                                            <form method="POST">
                                                                <input type="hidden" name="hapus_saran" value="1">
                                                                <input type="hidden" name="id_saran" value="<?= $k['id_saran'] ?>">

                                                                <button type="submit" class="btn btn-danger font-weight-bold">
                                                                    Hapus
                                                                </button>
                                                            </form>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="container-fluid">
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">KEGIATAN MASYARAKAT</h6>
                            <div class="d-flex align-items-center" style="gap:10px;">
                                <a href="#" class="btn btn-primary d-inline-flex align-items-center justify-content-center" 
                                    data-toggle="modal" data-target="#addKegiatanModal"
                                    style="border:none; border-radius:10px; font-weight:700; font-size:13px;">
                                    <iconify-icon icon="ic:round-plus" style="font-size:20px; margin-right:6px;"></iconify-icon>
                                    TAMBAH DATA
                                </a>

                                <a href="kegiatan_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
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
                                            <th>ID KEGIATAN</th>
                                            <th>JUDUL</th>
                                            <th>DESKRIPSI</th>
                                            <th>TANGGAL</th>
                                            <th>WAKTU</th>
                                            <th>TEMPAT</th>
                                            <th>EXPIRED</th>
                                            <th>DIBUAT</th>
                                            <th>OPSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($kegiatanWarga) > 0): ?>
                                            <?php foreach ($kegiatanWarga as $k): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($k['id_kegiatan']) ?></td>
                                                    <td><?= htmlspecialchars($k['judul']) ?></td>
                                                    <td><?= htmlspecialchars($k['deskripsi']) ?></td>
                                                    <td><?= htmlspecialchars($k['tanggal']) ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($k['waktu_mulai']) ?>
                                                        -
                                                        <?= htmlspecialchars($k['waktu_selesai']) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($k['tempat'] ?? '-') ?></td>
                                                    <td>
                                                        <?= $k['expired_at'] 
                                                            ? htmlspecialchars($k['expired_at']) 
                                                            : '-' ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($k['created_at']) ?></td>

                                                    <!-- OPSI -->
                                                    <td class="text-center">
                                                        <div style="display:flex; justify-content:center; gap:5px;">

                                                            <!-- EDIT -->
                                                            <button class="btn btn-sm btn-warning"
                                                                title="Edit"
                                                                data-toggle="modal"
                                                                data-target="#editKegiatanModal<?= $k['id_kegiatan'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:pencil"></iconify-icon>
                                                            </button>

                                                            <!-- HAPUS -->
                                                            <button class="btn btn-sm btn-danger"
                                                                title="Hapus"
                                                                data-toggle="modal"
                                                                data-target="#deleteKegiatanModal<?= $k['id_kegiatan'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:trash"></iconify-icon>
                                                            </button>

                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">
                                                    Data kegiatan masyarakat belum tersedia
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                            <?php foreach ($kegiatanWarga as $k): ?>
                                            <!-- Delete Confirmation Modal Kegiatan -->
                                            <div class="modal fade"
                                                id="deleteKegiatanModal<?= $k['id_kegiatan'] ?>"
                                                tabindex="-1"
                                                role="dialog">

                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content" style="border-radius:12px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header" style="background-color:#dc3545; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold">
                                                                Hapus Kegiatan
                                                            </h5>
                                                            <button class="close text-white" type="button" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body text-gray-700" style="font-size:0.95rem;">
                                                            Apakah Anda yakin ingin
                                                            <strong class="text-danger">menghapus kegiatan ini</strong>?<br>
                                                            Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                                                        </div>

                                                        <!-- Footer -->
                                                        <div class="modal-footer">
                                                            <button class="btn btn-secondary" data-dismiss="modal">
                                                                Batal
                                                            </button>

                                                            <!-- FORM HAPUS (TANPA FILE EKSTERNAL) -->
                                                            <form method="POST">
                                                                <input type="hidden" name="hapus_kegiatan" value="1">
                                                                <input type="hidden" name="id_kegiatan" value="<?= $k['id_kegiatan'] ?>">

                                                                <button type="submit" class="btn btn-danger font-weight-bold">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Data Kegiatan Modal -->
                                            <div class="modal fade" id="editKegiatanModal<?= $k['id_kegiatan'] ?>" tabindex="-1" role="dialog">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header d-flex justify-content-between align-items-center"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold mb-0">
                                                                Edit Kegiatan Masyarakat
                                                            </h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                                <span style="font-size:1.5rem;">&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body bg-white" style="padding:1.5rem;">
                                                            <form method="POST">

                                                                <!-- ID KEGIATAN -->
                                                                <input type="hidden" name="id_kegiatan" value="<?= $k['id_kegiatan'] ?>">

                                                                <!-- Judul -->
                                                                <div class="form-group">
                                                                    <label>Judul Kegiatan</label>
                                                                    <input type="text"
                                                                        name="judul"
                                                                        class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['judul']) ?>"
                                                                        required>
                                                                </div>

                                                                <!-- Deskripsi -->
                                                                <div class="form-group">
                                                                    <label>Deskripsi</label>
                                                                    <textarea name="deskripsi"
                                                                        class="form-control"
                                                                        rows="4"
                                                                        required><?= htmlspecialchars($k['deskripsi']) ?></textarea>
                                                                </div>

                                                                <!-- Tanggal & Tempat -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Tanggal</label>
                                                                        <input type="date"
                                                                            name="tanggal"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= $k['tanggal'] ?>"
                                                                            required>
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Tempat</label>
                                                                        <input type="text"
                                                                            name="tempat"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($k['tempat']) ?>"
                                                                            required>
                                                                    </div>
                                                                </div>

                                                                <!-- Waktu -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Waktu Mulai</label>
                                                                        <input type="time"
                                                                            name="waktu_mulai"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= $k['waktu_mulai'] ?>"
                                                                            required>
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Waktu Selesai</label>
                                                                        <input type="time"
                                                                            name="waktu_selesai"
                                                                            class="form-control rounded-pill"
                                                                            value="<?= $k['waktu_selesai'] ?>"
                                                                            required>
                                                                    </div>
                                                                </div>

                                                                <!-- Expired -->
                                                                <div class="form-group">
                                                                    <label>Tanggal Berakhir</label>
                                                                    <input type="date"
                                                                        name="expired_at"
                                                                        class="form-control rounded-pill"
                                                                        value="<?= $k['expired_at'] ?>">
                                                                </div>

                                                                <!-- Submit -->
                                                                <div class="form-group d-flex justify-content-end mt-3">
                                                                    <button type="submit"
                                                                        name="update_kegiatan"
                                                                        class="btn btn-info font-weight-bold"
                                                                        style="border-radius:10px;">
                                                                        SIMPAN PERUBAHAN
                                                                    </button>
                                                                </div>

                                                            </form>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>

                            </div>
                        </div>
                    </div>
                    <!-- Add Kegiatan Masyarakat Modal -->
                    <div class="modal fade" id="addKegiatanModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                            <!-- Header -->
                            <div class="modal-header d-flex justify-content-between align-items-center"
                                style="background-color:#4E73DF; border:none;">
                                <h5 class="modal-title text-white font-weight-bold mb-0">
                                    Tambah Kegiatan Masyarakat
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal">
                                    <span style="font-size:1.5rem;">&times;</span>
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="modal-body bg-white" style="padding:1.5rem;">
                                <form method="POST">

                                    <div class="form-group">
                                        <label>Judul Kegiatan</label>
                                        <input type="text" name="judul"
                                            class="form-control rounded-pill" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Deskripsi</label>
                                        <textarea name="deskripsi"
                                            class="form-control rounded-pill"
                                            rows="3"></textarea>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>Tanggal</label>
                                            <input type="date" name="tanggal"
                                                class="form-control rounded-pill" required>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label>Waktu</label>

                                            <div class="d-flex" style="gap:8px;">
                                                <input type="time"
                                                    name="waktu_mulai"
                                                    class="form-control rounded-pill"
                                                    required>

                                                <span class="d-flex align-items-center font-weight-bold">-</span>

                                                <input type="time"
                                                    name="waktu_selesai"
                                                    class="form-control rounded-pill"
                                                    required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Tempat</label>
                                        <input type="text" name="tempat"
                                            class="form-control rounded-pill">
                                    </div>

                                    <div class="form-group">
                                        <label>Expired At (Opsional)</label>
                                        <input type="datetime-local" name="expired_at"
                                            class="form-control rounded-pill">
                                        <small class="text-muted">
                                            Kosongkan jika ingin otomatis expire di akhir hari
                                        </small>
                                    </div>

                                    <button type="submit"
                                        name="tambah_kegiatan"
                                        class="btn btn-primary font-weight-bold">
                                        SIMPAN DATA
                                    </button>

                                </form>
                            </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="container-fluid">
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">KEGIATAN MASYARAKAT</h6>
                            <div class="d-flex align-items-center" style="gap:10px;">
                                <a href="#" class="btn btn-primary d-inline-flex align-items-center justify-content-center" 
                                    data-toggle="modal" data-target="#addNotifikasiModal"
                                    style="border:none; border-radius:10px; font-weight:700; font-size:13px;">
                                    <iconify-icon icon="ic:round-plus" style="font-size:20px; margin-right:6px;"></iconify-icon>
                                    NOTIFIKASI WARGA
                                </a>

                                <a href="notifikasi_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
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
                                            <th>ID NOTIFIKASI</th>
                                            <th>NAMA USER</th>
                                            <th>PESAN</th>
                                            <th>ID USER</th>
                                            <th>EXPIRED</th>
                                            <th>DIBUAT</th>
                                            <th>OPSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($notifikasiWarga) > 0): ?>
                                            <?php foreach ($notifikasiWarga as $n): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($n['id_notifikasi']) ?></td>

                                                    <td>
                                                        <?= htmlspecialchars($n['Nama_user'] ?? 'Semua Warga') ?>
                                                    </td>

                                                    <td><?= htmlspecialchars($n['pesan']) ?></td>

                                                    <td class="text-center">
                                                        <?= $n['id_user'] ?? '-' ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?= $n['expired_at']
                                                            ? date('d-m-Y H:i', strtotime($n['expired_at']))
                                                            : '-' ?>
                                                    </td>

                                                    <td class="text-center">
                                                        <?= date('d-m-Y H:i', strtotime($n['created_at'])) ?>
                                                    </td>

                                                    <!-- OPSI -->
                                                    <td class="text-center">
                                                        <div style="display:flex; justify-content:center; gap:5px;">

                                                            <!-- EDIT -->
                                                            <button class="btn btn-sm btn-warning"
                                                                title="Edit"
                                                                data-toggle="modal"
                                                                data-target="#editNotifModal<?= $n['id_notifikasi'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:pencil"></iconify-icon>
                                                            </button>


                                                            <!-- HAPUS -->
                                                            <button class="btn btn-sm btn-danger"
                                                                title="Hapus"
                                                                data-toggle="modal"
                                                                data-target="#deleteNotifModal<?= $n['id_notifikasi'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:trash"></iconify-icon>
                                                            </button>

                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">
                                                    Data notifikasi belum tersedia
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                            <?php foreach ($notifikasiWarga as $n): ?>
                                            <!-- Delete Confirmation Modal Notifikasi -->
                                            <div class="modal fade"
                                                id="deleteNotifModal<?= $n['id_notifikasi'] ?>"
                                                tabindex="-1"
                                                role="dialog">

                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content" style="border-radius:12px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header" style="background-color:#dc3545; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold">
                                                                Hapus Notifikasi
                                                            </h5>
                                                            <button class="close text-white" type="button" data-dismiss="modal">
                                                                <span>&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body text-gray-700" style="font-size:0.95rem;">
                                                            Apakah Anda yakin ingin
                                                            <strong class="text-danger">menghapus notifikasi ini</strong>?<br>
                                                            Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                                                        </div>

                                                        <!-- Footer -->
                                                        <div class="modal-footer">
                                                            <button class="btn btn-secondary" data-dismiss="modal">
                                                                Batal
                                                            </button>

                                                            <!-- FORM HAPUS (INLINE, TANPA FILE EKSTERNAL) -->
                                                            <form method="POST">
                                                                <input type="hidden" name="hapus_notifikasi" value="1">
                                                                <input type="hidden" name="id_notifikasi" value="<?= $n['id_notifikasi'] ?>">

                                                                <button type="submit" class="btn btn-danger font-weight-bold">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Notifikasi Warga Modal -->
                                            <div class="modal fade" id="editNotifModal<?= $n['id_notifikasi'] ?>" tabindex="-1" role="dialog">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header d-flex justify-content-between align-items-center"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold mb-0">
                                                                Edit Notifikasi Warga
                                                            </h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                                <span style="font-size:1.5rem;">&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body bg-white" style="padding:1.5rem;">
                                                            <form method="POST">

                                                                <!-- ID NOTIFIKASI -->
                                                                <input type="hidden" name="id_notifikasi" value="<?= $n['id_notifikasi'] ?>">

                                                                <!-- Tujuan -->
                                                                <div class="form-group">
                                                                    <label>Tujuan Notifikasi</label>
                                                                    <select name="id_user" class="form-control rounded-pill">
                                                                        <option value="">Semua Warga</option>
                                                                        <?php foreach ($listUser as $u): ?>
                                                                            <option value="<?= $u['Id_user'] ?>"
                                                                                <?= $n['id_user'] == $u['Id_user'] ? 'selected' : '' ?>>
                                                                                <?= $u['Id_user'] ?> - <?= htmlspecialchars($u['Nama_user']) ?>
                                                                            </option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>

                                                                <!-- Pesan -->
                                                                <div class="form-group">
                                                                    <label>Pesan Notifikasi</label>
                                                                    <textarea name="pesan"
                                                                        class="form-control"
                                                                        rows="4"
                                                                        required><?= htmlspecialchars($n['pesan']) ?></textarea>
                                                                </div>

                                                                <!-- Expired -->
                                                                <div class="form-group">
                                                                    <label>Tanggal Berakhir</label>
                                                                    <input type="datetime-local"
                                                                        name="expired_at"
                                                                        class="form-control rounded-pill"
                                                                        value="<?= $n['expired_at']
                                                                            ? date('Y-m-d\TH:i', strtotime($n['expired_at']))
                                                                            : '' ?>">
                                                                    <small class="text-muted">
                                                                        Kosongkan jika ingin tanpa batas waktu
                                                                    </small>
                                                                </div>

                                                                <!-- Submit -->
                                                                <div class="form-group d-flex justify-content-end mt-3">
                                                                    <button type="submit"
                                                                        name="update_notifikasi"
                                                                        class="btn btn-info font-weight-bold"
                                                                        style="border-radius:10px;">
                                                                        SIMPAN PERUBAHAN
                                                                    </button>
                                                                </div>

                                                            </form>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Add Notifikasi Warga Modal -->
                    <div class="modal fade" id="addNotifikasiModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                            <!-- Header -->
                            <div class="modal-header d-flex justify-content-between align-items-center"
                                style="background-color:#4E73DF; border:none;">
                                <h5 class="modal-title text-white font-weight-bold mb-0">
                                    Tambah Notifikasi Warga
                                </h5>
                                <button type="button" class="close text-white" data-dismiss="modal">
                                    <span style="font-size:1.5rem;">&times;</span>
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="modal-body bg-white" style="padding:1.5rem;">
                                <form method="POST">

                                    <!-- USER -->
                                    <div class="form-group">
                                        <label>Tujuan Notifikasi</label>
                                        <select name="id_user" class="form-control rounded-pill">
                                            <option value="">Semua Warga</option>
                                            <?php foreach ($userList as $u): ?>
                                                <option value="<?= $u['Id_user'] ?>">
                                                    <?= htmlspecialchars($u['Id_user']) ?> - <?= htmlspecialchars($u['Nama_user']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">
                                            Pilih "Semua Warga" untuk mengirim ke seluruh pengguna
                                        </small>
                                    </div>

                                    <!-- PESAN -->
                                    <div class="form-group">
                                        <label>Pesan Notifikasi</label>
                                        <textarea name="pesan"
                                            class="form-control rounded-pill"
                                            rows="4"
                                            required></textarea>
                                    </div>

                                    <!-- EXPIRED -->
                                    <div class="form-group">
                                        <label>Berlaku Sampai (Opsional)</label>
                                        <input type="datetime-local"
                                            name="expired_at"
                                            class="form-control rounded-pill">
                                        <small class="text-muted">
                                            Kosongkan jika ingin otomatis berakhir hari ini
                                        </small>
                                    </div>

                                    <!-- SUBMIT -->
                                    <button type="submit"
                                        name="tambah_notifikasi"
                                        class="btn btn-primary font-weight-bold">
                                        SIMPAN DATA
                                    </button>

                                </form>
                            </div>
                            </div>
                        </div>
                    </div>

                </div>


                <script>
                document.addEventListener('DOMContentLoaded', function () {

                    const waitTable = setInterval(() => {
                        if ($.fn.DataTable.isDataTable('#dataTable')) {
                            clearInterval(waitTable);

                            const table = $('#dataTable').DataTable();
                            const dtLength = document.querySelector('.dataTables_length');

                            if (!document.querySelector('#rtButtons')) {

                                const rtButtons = document.createElement('div');
                                rtButtons.id = 'rtButtons';
                                rtButtons.classList.add('btn-group');
                                rtButtons.setAttribute('role', 'group');

                                rtButtons.innerHTML = `
                                <div id="rtButtons"
                                    class="d-flex align-items-stretch ml-3"
                                    style="
                                        border:1px solid #cfd4e3;
                                        border-radius:10px;
                                        overflow:hidden;
                                    ">

                                    <!-- ALL (LEFT, FULL HEIGHT) -->
                                    <button type="button"
                                        class="btn active"
                                        data-all="1"
                                        style="
                                            padding:0;
                                            font-weight:600;
                                            min-width:50px;
                                            display:flex;
                                            align-items:center;
                                            justify-content:center;
                                            border-radius:0;
                                        ">
                                        ALL
                                    </button>

                                    <!-- RIGHT SIDE (RT + RW) -->
                                    <div class="d-flex flex-column">

                                        <!-- RT ROW -->
                                        <div class="d-flex">
                                            <button class="btn" data-rt="01" style="width:80px; border-radius:0;">RT01</button>
                                            <button class="btn" data-rt="02" style="width:80px; border-radius:0;">RT02</button>
                                            <button class="btn" data-rt="03" style="width:80px; border-radius:0;">RT03</button>
                                            <button class="btn" data-rt="04" style="width:80px; border-radius:0;">RT04</button>
                                        </div>

                                        <!-- RW ROW -->
                                        <div class="d-flex">
                                            <button class="btn" data-rw="01" style="width:80px; border-radius:0;">RW01</button>
                                            <button class="btn" data-rw="02" style="width:80px; border-radius:0;">RW02</button>
                                            <button class="btn" data-rw="03" style="width:80px; border-radius:0;">RW03</button>
                                            <button class="btn" data-rw="04" style="width:80px; border-radius:0;">RW04</button>
                                        </div>

                                    </div>
                                </div>
                                `;

                                dtLength.appendChild(rtButtons);

                                let currentRT = '';
                                let currentRW = '';

                                const buttons = rtButtons.querySelectorAll('button');

                                buttons.forEach(btn => {
                                    btn.addEventListener('click', function () {

                                        /* ===== ALL ===== */
                                        if (this.dataset.all) {
                                            currentRT = '';
                                            currentRW = '';

                                            buttons.forEach(b => b.classList.remove('active'));
                                            this.classList.add('active');
                                        }

                                        /* ===== RT ===== */
                                        if (this.dataset.rt !== undefined) {
                                            if (currentRT === this.dataset.rt) {
                                                currentRT = '';
                                                this.classList.remove('active');
                                            } else {
                                                currentRT = this.dataset.rt;
                                                buttons.forEach(b => {
                                                    if (b.dataset.rt !== undefined) b.classList.remove('active');
                                                });
                                                this.classList.add('active');
                                            }
                                            document.querySelector('[data-all]').classList.remove('active');
                                        }

                                        /* ===== RW ===== */
                                        if (this.dataset.rw !== undefined) {
                                            if (currentRW === this.dataset.rw) {
                                                currentRW = '';
                                                this.classList.remove('active');
                                            } else {
                                                currentRW = this.dataset.rw;
                                                buttons.forEach(b => {
                                                    if (b.dataset.rw !== undefined) b.classList.remove('active');
                                                });
                                                this.classList.add('active');
                                            }
                                            document.querySelector('[data-all]').classList.remove('active');
                                        }

                                        /* ===== APPLY FILTER ===== */
                                        table.column(6).search(currentRT); // RT
                                        table.column(7).search(currentRW); // RW
                                        table.draw();

                                        /* ===== AUTO ALL ===== */
                                        if (currentRT === '' && currentRW === '') {
                                            buttons.forEach(b => b.classList.remove('active'));
                                            document.querySelector('[data-all]').classList.add('active');
                                        }
                                    });
                                });
                            }
                        }
                    }, 200);
                });
                </script>

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

</body>


</html>