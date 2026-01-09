<?php
session_start();
require 'koneksi.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

/* ================= HANDLE UPDATE STATUS ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pending'], $_POST['status'])) {

    $id_pending = $_POST['id_pending'];
    $status     = $_POST['status'];

    $koneksi->beginTransaction();

    try {

        /* === AMBIL DATA PENDING === */
        $stmt = $koneksi->prepare("SELECT * FROM data_pending WHERE id_pending = ?");
        $stmt->execute([$id_pending]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            throw new Exception('Data pending tidak ditemukan');
        }

        /* =====================================================
           JIKA DISETUJUI (TERVERIFIKASI)
        ====================================================== */
        if ($status === 'terverifikasi') {

            /* ================= EDIT WARGA ================= */
            if ($p['aksi'] === 'edit' && $p['tipe_data'] === 'warga') {

                /* UPDATE DATA WARGA */
                $updateWarga = $koneksi->prepare("
                    UPDATE warga SET
                        Nama = ?,
                        Tempat_lahir = ?,
                        Tanggal_lahir = ?,
                        Jenis_kelamin = ?,
                        Agama = ?,
                        Pendidikan = ?,
                        Pekerjaan = ?,
                        Status_perkawinan = ?,
                        Dokumen_ktp = ?
                    WHERE NIK = ?
                ");
                $updateWarga->execute([
                    $p['nama'],
                    $p['tempat_lahir'],
                    $p['tanggal_lahir'],
                    $p['jenis_kelamin'],
                    $p['agama'],
                    $p['pendidikan'],
                    $p['pekerjaan'],
                    $p['status_perkawinan'],
                    $p['dokumen_ktp'], // <-- update KTP
                    $p['nik']
                ]);

                /* === UPDATE DOKUMEN KK (DARI EDIT WARGA) === */
                if (!empty($p['dokumen_kk'])) {

                    // Ambil No_kk asli dari tabel warga
                    $getKK = $koneksi->prepare("
                        SELECT No_kk FROM warga WHERE NIK = ?
                    ");
                    $getKK->execute([$p['nik']]);
                    $rowKK = $getKK->fetch(PDO::FETCH_ASSOC);

                    if ($rowKK && !empty($rowKK['No_kk'])) {

                        $updateKK = $koneksi->prepare("
                            UPDATE keluarga 
                            SET Dokumen_kk = ?
                            WHERE No_kk = ?
                        ");
                        $updateKK->execute([
                            $p['dokumen_kk'],
                            $rowKK['No_kk']
                        ]);
                    }
                }
            }

            /* ================= EDIT KELUARGA ================= */
            if ($p['aksi'] === 'edit' && $p['tipe_data'] === 'keluarga') {

                $updateKeluarga = $koneksi->prepare("
                    UPDATE keluarga SET
                        Kepala_keluarga = ?,
                        Alamat = ?,
                        RT = ?,
                        RW = ?,
                        Kelurahan = ?,
                        Kecamatan = ?,
                        Dokumen_kk = ?
                    WHERE No_kk = ?
                ");
                $updateKeluarga->execute([
                    $p['kepala_keluarga'],
                    $p['alamat'],
                    $p['rt'],
                    $p['rw'],
                    $p['kelurahan'],
                    $p['kecamatan'],
                    $p['dokumen_kk'],
                    $p['no_kk']
                ]);
            }

            /* ===== SIMPAN RIWAYAT ADMINISTRASI ===== */
            $jenisData = $p['tipe_data']; // warga / keluarga
            $idData    = ($p['tipe_data'] === 'warga') ? $p['nik'] : $p['no_kk'];

            $keterangan = ($p['tipe_data'] === 'warga')
                ? "Perubahan data warga dengan NIK {$p['nik']} telah diverifikasi admin"
                : "Perubahan data keluarga dengan No KK {$p['no_kk']} telah diverifikasi admin";

            $koneksi->prepare("
                INSERT INTO riwayat_administrasi
                (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
                VALUES (?, ?, 'ubah', ?, ?)
            ")->execute([
                $jenisData,
                $idData,
                $keterangan,
                $_SESSION['user_id']
            ]);

            /* ================= HAPUS DATA PENDING ================= */
            $hapusPending = $koneksi->prepare("
                DELETE FROM data_pending 
                WHERE id_pending = ?
            ");
            $hapusPending->execute([$id_pending]);
        }

        /* =====================================================
           JIKA TIDAK TERVERIFIKASI (PENDING / DITOLAK)
        ====================================================== */
        else {
            $updateStatus = $koneksi->prepare("
                UPDATE data_pending 
                SET status = ?, reviewed_at = NOW()
                WHERE id_pending = ?
            ");
            $updateStatus->execute([$status, $id_pending]);
        }

        $koneksi->commit();

    } catch (Exception $e) {
        $koneksi->rollBack();
        die("Gagal memproses pengajuan: " . $e->getMessage());
    }

    header("Location: pendingedit.php");
    exit;
}

/* ================= DATA PENDING EDIT ================= */
$pendingEdit = $koneksi->query("
    SELECT 
        dp.id_pending,
        dp.tipe_data,
        dp.aksi,
        dp.status,
        dp.id_user,
        dp.created_at,

        /* IDENTITAS */
        dp.nik,
        dp.no_kk,

        /* NAMA */
        COALESCE(dp.nama, w.Nama) AS nama_tampil,
        COALESCE(dp.kepala_keluarga, k.Kepala_keluarga) AS kepala_keluarga_tampil,

        /* ALAMAT & WILAYAH */
        COALESCE(dp.alamat, k.Alamat)       AS alamat,
        COALESCE(dp.rt, k.RT)               AS rt,
        COALESCE(dp.rw, k.RW)               AS rw,
        COALESCE(dp.kelurahan, k.Kelurahan) AS kelurahan,
        COALESCE(dp.kecamatan, k.Kecamatan) AS kecamatan,

        /* DOKUMEN (PENTING UNTUK MODAL) */
        COALESCE(dp.dokumen_ktp, w.Dokumen_ktp) AS dokumen_ktp,
        COALESCE(dp.dokumen_kk,  k.Dokumen_kk)  AS dokumen_kk,

        /* CATATAN */
        dp.catatan

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
")->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   DELETE DATA PENDING (EDIT) + SIMPAN RIWAYAT
====================================================== */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_pending'], $_POST['id_pending'])
) {
    $id_pending = $_POST['id_pending'];

    $koneksi->beginTransaction();

    try {

        /* === AMBIL DATA PENDING === */
        $stmt = $koneksi->prepare("
            SELECT tipe_data, nik, no_kk 
            FROM data_pending 
            WHERE id_pending = ?
        ");
        $stmt->execute([$id_pending]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) {
            throw new Exception('Data pending tidak ditemukan');
        }

        /* === TENTUKAN DATA RIWAYAT === */
        $jenisData = $p['tipe_data']; // warga / keluarga
        $idData    = ($p['tipe_data'] === 'warga') ? $p['nik'] : $p['no_kk'];

        $keterangan = ($p['tipe_data'] === 'warga')
            ? "Pengajuan edit data warga dengan NIK {$p['nik']} dihapus oleh admin"
            : "Pengajuan edit data keluarga dengan No KK {$p['no_kk']} dihapus oleh admin";

        /* === INSERT KE RIWAYAT ADMINISTRASI === */
        $insertRiwayat = $koneksi->prepare("
            INSERT INTO riwayat_administrasi
            (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
            VALUES (?, ?, 'hapus', ?, ?)
        ");
        $insertRiwayat->execute([
            $jenisData,
            $idData,
            $keterangan,
            $_SESSION['user_id']
        ]);

        /* === HAPUS DATA PENDING === */
        $hapus = $koneksi->prepare("
            DELETE FROM data_pending 
            WHERE id_pending = ?
        ");
        $hapus->execute([$id_pending]);

        $koneksi->commit();

    } catch (Exception $e) {
        $koneksi->rollBack();
        die("Gagal menghapus data pending: " . $e->getMessage());
    }

    header("Location: pendingedit.php");
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

    <title>Halaman Pengajuan Data</title>

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
                            <h6 class="m-0 font-weight-bold text-primary">TABEL PENGAJUAN EDIT DATA</h6>
                            <div class="d-flex align-items-center" style="gap:10px;">

                                <a href="pendingedit_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
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
                                            <th>TIPE DATA</th>
                                            <th>NIK / NO KK</th>
                                            <th>NAMA / KEPALA KELUARGA</th>
                                            <th>ALAMAT</th>
                                            <th>RT</th>
                                            <th>RW</th>
                                            <th>KELURAHAN</th>
                                            <th>KECAMATAN</th>
                                            <th>ID USER</th>
                                            <th>STATUS</th>
                                            <th>OPSI</th>
                                        </tr>                                    
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pendingEdit)): ?>
                                            <?php foreach ($pendingEdit as $p): ?>
                                                <tr>

                                                    <!-- TIPE -->
                                                    <td><?= ucfirst($p['tipe_data']) ?></td>

                                                    <!-- IDENTITAS -->
                                                    <td>
                                                        <?= $p['tipe_data'] === 'warga'
                                                            ? htmlspecialchars($p['nik'])
                                                            : htmlspecialchars($p['no_kk']) ?>
                                                    </td>

                                                    <!-- NAMA -->
                                                    <td>
                                                        <?= $p['tipe_data'] === 'warga'
                                                            ? htmlspecialchars($p['nama_tampil'])
                                                            : htmlspecialchars($p['kepala_keluarga_tampil']) ?>
                                                    </td>

                                                    <!-- ALAMAT -->
                                                    <td><?= htmlspecialchars($p['alamat']) ?></td>
                                                    <td><?= htmlspecialchars($p['rt']) ?></td>
                                                    <td><?= htmlspecialchars($p['rw']) ?></td>
                                                    <td><?= htmlspecialchars($p['kelurahan']) ?></td>
                                                    <td><?= htmlspecialchars($p['kecamatan']) ?></td>

                                                    <!-- ID USER -->
                                                    <td><?= htmlspecialchars($p['id_user']) ?></td>                                                    
                                                    <td>
                                                        <form method="POST">
                                                            <input type="hidden" name="id_pending" value="<?= $p['id_pending'] ?>">
                                                            <input type="hidden" name="status" id="status<?= $p['id_pending'] ?>">

                                                            <a class="btn btn-sm nav-link dropdown-toggle
                                                                <?= $p['status'] === 'terverifikasi' ? 'btn-success' : 'btn-warning' ?>"
                                                                data-toggle="dropdown"
                                                                style="min-width:90px; font-weight:600;">
                                                                <?= ucfirst($p['status']) ?>
                                                            </a>
                                                            <div class="dropdown-menu dropdown-menu-right">
                                                                <a href="#"
                                                                    class="dropdown-item text-warning font-weight-bold"
                                                                    onclick="
                                                                        document.getElementById('status<?= $p['id_pending'] ?>').value='pending';
                                                                        this.closest('form').submit();">
                                                                        Pending
                                                                </a>

                                                                <div class="dropdown-divider"></div>

                                                                <a href="#"
                                                                    class="dropdown-item text-success font-weight-bold"
                                                                    onclick="
                                                                        document.getElementById('status<?= $p['id_pending'] ?>').value='terverifikasi';
                                                                        this.closest('form').submit();">
                                                                    Terverifikasi
                                                                </a>
                                                            </div>
                                                        </form>
                                                    </td>                                                    
                                                    <td class="text-center">
                                                        <div style="display:flex; justify-content:center; gap:5px;">

                                                                <!-- LIHAT -->
                                                            <button class="btn btn-sm btn-secondary"
                                                                title="Lihat"
                                                                data-toggle="modal"
                                                                data-target="#showKeluargaModal<?= $p['id_pending'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:eye"></iconify-icon>
                                                            </button>

                                                            <!-- HAPUS -->
                                                            <button class="btn btn-sm btn-danger"
                                                                title="Hapus"
                                                                data-toggle="modal"
                                                                data-target="#deletePendingEditModal<?= $p['id_pending'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:trash"></iconify-icon>
                                                            </button>

                                                            <!-- CETAK PDF PER BARIS -->
                                                            <a href="pendingedit_pdf_row.php?id=<?= $p['id_pending'] ?>"
                                                                target="_blank"
                                                                class="btn btn-sm btn-info"
                                                                title="Unduh PDF">
                                                                <iconify-icon style="font-size:18px;" icon="mingcute:pdf-fill"></iconify-icon>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                         <tr>
                                            <td colspan="8" class="text-center text-muted">
                                                Belum tersedia pengajuan data
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                            <?php foreach ($pendingEdit as $p): ?>

<!-- Delete Confirmation Modal (Pending Edit) -->
<div class="modal fade"
     id="deletePendingEditModal<?= $p['id_pending'] ?>"
     tabindex="-1"
     role="dialog">

    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius:12px;">

            <!-- Header -->
            <div class="modal-header" style="background-color:#dc3545; border:none;">
                <h5 class="modal-title text-white font-weight-bold">
                    Hapus Data Pending
                </h5>
                <button class="close text-white" type="button" data-dismiss="modal">
                    <span>×</span>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body text-gray-700" style="font-size:0.95rem;">
                Apakah Anda yakin ingin
                <strong class="text-danger">menghapus pengajuan ini</strong>?<br>
                Data utama <strong>tidak akan berubah</strong>.
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">
                    Batal
                </button>

                <!-- FORM DELETE (INTERNAL PHP) -->
                <form method="POST">
                    <input type="hidden" name="delete_pending" value="1">
                    <input type="hidden" name="id_pending" value="<?= $p['id_pending'] ?>">
                    <button type="submit" class="btn btn-danger font-weight-bold">
                        Hapus
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

                                            <!-- Show Data Modal -->
                                            <div class="modal fade"
                                                id="showKeluargaModal<?= $p['id_pending'] ?>"
                                                tabindex="-1"
                                                role="dialog"
                                                aria-hidden="true">

                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header d-flex justify-content-between align-items-center"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold mb-0">
                                                                <?= $p['tipe_data'] === 'warga'
                                                                    ? 'Detail Data Warga'
                                                                    : 'Detail Data Keluarga' ?>
                                                            </h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                                <span style="font-size:1.5rem;">&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body bg-white" style="padding:1.5rem;">
                                                            <form>

                                                                <!-- IDENTITAS -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label><?= $p['tipe_data'] === 'warga' ? 'NIK' : 'Nomor KK' ?></label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars(
                                                                                $p['tipe_data'] === 'warga'
                                                                                    ? $p['nik']
                                                                                    : $p['no_kk']
                                                                            ) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label><?= $p['tipe_data'] === 'warga' ? 'Nama' : 'Kepala Keluarga' ?></label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars(
                                                                                $p['tipe_data'] === 'warga'
                                                                                    ? $p['nama_tampil']
                                                                                    : $p['kepala_keluarga_tampil']
                                                                            ) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- ALAMAT -->
                                                                <div class="form-group">
                                                                    <label>Alamat</label>
                                                                    <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($p['alamat'] ?? '-') ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                </div>

                                                                <!-- RT RW -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>RT</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['rt'] ?? '-') ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>RW</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['rw'] ?? '-') ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- KELURAHAN KECAMATAN -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Kelurahan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['kelurahan'] ?? '-') ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Kecamatan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['kecamatan'] ?? '-') ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- CATATAN -->
                                                                <div class="form-group">
                                                                    <label>Catatan Pengajuan</label>
                                                                    <textarea class="form-control"
                                                                            rows="3"
                                                                            readonly
                                                                            style="background-color:#e9ecef;"><?= htmlspecialchars($p['catatan'] ?? '-') ?></textarea>
                                                                </div>

                                                                <!-- DOKUMEN KTP (JIKA WARGA) -->
                                                                <?php if ($p['tipe_data'] === 'warga'): ?>
                                                                    <div class="form-group text-center mt-3">
                                                                        <label>Dokumen KTP</label><br>
                                                                        <?php if (!empty($p['dokumen_ktp'])): ?>
                                                                            <img src="img/ktp/<?= htmlspecialchars($p['dokumen_ktp']) ?>"
                                                                                style="max-width:400px; border-radius:10px;">
                                                                        <?php else: ?>
                                                                            <span class="text-muted">Dokumen KTP belum tersedia</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <!-- DOKUMEN KK (SELALU DITAMPILKAN) -->
                                                                <div class="form-group text-center mt-4">
                                                                    <label>Dokumen Pendukung (Kartu Keluarga)</label><br>
                                                                    <?php if (!empty($p['dokumen_kk'])): ?>
                                                                        <img src="img/kk/<?= htmlspecialchars($p['dokumen_kk']) ?>"
                                                                            style="max-width:400px; border-radius:10px;">
                                                                    <?php else: ?>
                                                                        <span class="text-muted">Dokumen KK belum tersedia</span>
                                                                    <?php endif; ?>
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
                                                            <button class="btn" data-rt="01" style="width:90px; border-radius:0;">RT01</button>
                                                            <button class="btn" data-rt="02" style="width:90px; border-radius:0;">RT02</button>
                                                            <button class="btn" data-rt="03" style="width:90px; border-radius:0;">RT03</button>
                                                            <button class="btn" data-rt="04" style="width:90px; border-radius:0;">RT04</button>
                                                        </div>

                                                        <!-- RW ROW -->
                                                        <div class="d-flex">
                                                            <button class="btn" data-rw="01" style="width:90px; border-radius:0;">RW01</button>
                                                            <button class="btn" data-rw="02" style="width:90px; border-radius:0;">RW02</button>
                                                            <button class="btn" data-rw="03" style="width:90px; border-radius:0;">RW03</button>
                                                            <button class="btn" data-rw="04" style="width:90px; border-radius:0;">RW04</button>
                                                        </div>

                                                    </div>
                                                </div>
                                `;

                                dtLength.appendChild(rtButtons);

                                let currentRT = '';
                                let currentRW = '';

                                rtButtons.querySelectorAll('button').forEach(btn => {
                                    btn.addEventListener('click', function () {

                                        if (this.dataset.all) {
                                            currentRT = '';
                                            currentRW = '';
                                            rtButtons.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                                            this.classList.add('active');
                                        }

                                        if (this.dataset.rt !== undefined) {
                                            currentRT = currentRT === this.dataset.rt ? '' : this.dataset.rt;
                                            rtButtons.querySelectorAll('[data-rt]').forEach(b => b.classList.remove('active'));
                                            if (currentRT) this.classList.add('active');
                                            rtButtons.querySelector('[data-all]').classList.remove('active');
                                        }

                                        if (this.dataset.rw !== undefined) {
                                            currentRW = currentRW === this.dataset.rw ? '' : this.dataset.rw;
                                            rtButtons.querySelectorAll('[data-rw]').forEach(b => b.classList.remove('active'));
                                            if (currentRW) this.classList.add('active');
                                            rtButtons.querySelector('[data-all]').classList.remove('active');
                                        }

                                        /* ✅ FIXED COLUMN INDEX */
                                        table.column(4).search(currentRT); // RT
                                        table.column(5).search(currentRW); // RW
                                        table.draw();

                                        if (!currentRT && !currentRW) {
                                            rtButtons.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                                            rtButtons.querySelector('[data-all]').classList.add('active');
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