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
           JIKA DIVERIFIKASI
        ====================================================== */
        if ($status === 'terverifikasi') {

            /* ================= UPDATE DOKUMEN KK ================= */
            if (!empty($p['dokumen_kk'])) {

                $noKK = null;

                // Jika tipe keluarga → pakai langsung
                if ($p['tipe_data'] === 'keluarga' && !empty($p['no_kk'])) {
                    $noKK = $p['no_kk'];
                }

                // Jika tipe warga → cari No_kk asli
                if ($p['tipe_data'] === 'warga' && !empty($p['nik'])) {
                    $getKK = $koneksi->prepare("
                        SELECT No_kk FROM warga WHERE NIK = ?
                    ");
                    $getKK->execute([$p['nik']]);
                    $rowKK = $getKK->fetch(PDO::FETCH_ASSOC);

                    if ($rowKK && !empty($rowKK['No_kk'])) {
                        $noKK = $rowKK['No_kk'];
                    }
                }

                // UPDATE dokumen KK jika No KK valid
                if ($noKK) {
                    $updateKK = $koneksi->prepare("
                        UPDATE keluarga 
                        SET Dokumen_kk = ?
                        WHERE No_kk = ?
                    ");
                    $updateKK->execute([
                        $p['dokumen_kk'],
                        $noKK
                    ]);
                }
            }

            /* ================= AKSI HAPUS DATA ================= */
            if ($p['aksi'] === 'hapus') {

                if ($p['tipe_data'] === 'warga' && !empty($p['nik'])) {
                    $koneksi->prepare("DELETE FROM warga WHERE NIK = ?")
                            ->execute([$p['nik']]);
                }

                if ($p['tipe_data'] === 'keluarga' && !empty($p['no_kk'])) {
                    $koneksi->prepare("DELETE FROM keluarga WHERE No_kk = ?")
                            ->execute([$p['no_kk']]);
                }
            }

            /* ================= SIMPAN RIWAYAT ADMINISTRASI ================= */
            $jenisData = $p['tipe_data']; // warga / keluarga
            $idData    = ($p['tipe_data'] === 'warga') ? $p['nik'] : $p['no_kk'];

            $keterangan = ($p['tipe_data'] === 'warga')
                ? "Penghapusan data warga dengan NIK {$p['nik']} telah diverifikasi admin"
                : "Penghapusan data keluarga dengan No KK {$p['no_kk']} telah diverifikasi admin";

            $koneksi->prepare("
                INSERT INTO riwayat_administrasi
                (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
                VALUES (?, ?, 'hapus', ?, ?)
            ")->execute([
                $jenisData,
                $idData,
                $keterangan,
                $_SESSION['user_id']
            ]);

            /* ================= HAPUS DATA_PENDING ================= */
            $koneksi->prepare("DELETE FROM data_pending WHERE id_pending = ?")
                    ->execute([$id_pending]);
        }

        /* =====================================================
           JIKA DITOLAK
        ====================================================== */
        else {
            $koneksi->prepare("
                UPDATE data_pending 
                SET status = ?, reviewed_at = NOW()
                WHERE id_pending = ?
            ")->execute([$status, $id_pending]);
        }

        $koneksi->commit();

    } catch (Exception $e) {
        $koneksi->rollBack();
        die("Gagal memproses data: " . $e->getMessage());
    }

    header("Location: pendinghapus.php");
    exit;
}
/* ================= DATA PENDING HAPUS ================= */
$pendingHapus = $koneksi->query("
    SELECT 
        dp.*,

        /* ===== PRIORITAS DATA_PENDING ===== */
        COALESCE(dp.nik, w.NIK) AS nik_final,
        COALESCE(dp.no_kk, w.No_kk, k.No_kk) AS no_kk_final,

        COALESCE(dp.nama, w.Nama) AS nama_warga_final,
        COALESCE(dp.kepala_keluarga, k.Kepala_keluarga) AS kepala_keluarga_final,

        COALESCE(dp.alamat, k.Alamat) AS alamat_final,
        COALESCE(dp.rt, k.RT) AS rt_final,
        COALESCE(dp.rw, k.RW) AS rw_final,
        COALESCE(dp.kelurahan, k.Kelurahan) AS kelurahan_final,
        COALESCE(dp.kecamatan, k.Kecamatan) AS kecamatan_final,

        /* ===== DOKUMEN ===== */
        COALESCE(dp.dokumen_ktp, w.Dokumen_ktp) AS dokumen_ktp_final,
        COALESCE(dp.dokumen_kk, k.Dokumen_kk) AS dokumen_kk_final

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

    WHERE dp.aksi = 'hapus'
    ORDER BY dp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= HANDLE HAPUS DATA ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_pending'])) {

    $id_pending = $_POST['id_pending'];

    /* === AMBIL DATA PENDING (SEBELUM DIHAPUS) === */
    $stmt = $koneksi->prepare("
        SELECT * FROM data_pending 
        WHERE id_pending = ?
    ");
    $stmt->execute([$id_pending]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        die('Data pending tidak ditemukan');
    }

    /* ================= JIKA KLIK TOMBOL HAPUS ================= */
    if (isset($_POST['hapus_pending'])) {

        /* ================= SIMPAN KE RIWAYAT ADMINISTRASI ================= */
        $jenisData = $p['tipe_data']; // warga / keluarga
        $idData    = ($p['tipe_data'] === 'warga') ? $p['nik'] : $p['no_kk'];

        $keterangan = ($p['tipe_data'] === 'warga')
            ? "Pengajuan hapus data warga dengan NIK {$p['nik']} dibatalkan / dihapus oleh admin"
            : "Pengajuan hapus data keluarga dengan No KK {$p['no_kk']} dibatalkan / dihapus oleh admin";

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

        /* ================= HAPUS DATA PENDING ================= */
        $koneksi->prepare("
            DELETE FROM data_pending 
            WHERE id_pending = ?
        ")->execute([$id_pending]);

        header("Location: pendinghapus.php");
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

                <div class="container-fluid">
                    <div class="card shadow mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">TABEL PENGAJUAN HAPUS DATA</h6>
                            <div class="d-flex align-items-center" style="gap:10px;">

                                <a href="pendinghapus_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
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
                                            <th>NIK</th>
                                            <th>NO KK</th>
                                            <th>ID USER</th>
                                            <th>NAMA WARGA</th>
                                            <th>KEPALA KELUARGA</th>
                                            <th style="display:none;">RT</th>
                                            <th style="display:none;">RW</th>
                                            <th>CATATAN</th>
                                            <th>STATUS</th>
                                            <th>OPSI</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                    <?php if (count($pendingHapus) > 0): ?>
                                        <?php foreach ($pendingHapus as $p): ?>
                                            <tr>
                                                <td><?= ucfirst($p['tipe_data']) ?></td>

                                                <!-- NIK -->
                                                <td><?= htmlspecialchars($p['nik_final'] ?? '-') ?></td>

                                                <!-- NO KK -->
                                                <td><?= htmlspecialchars($p['no_kk_final'] ?? '-') ?></td>

                                                <!-- ID USER -->
                                                <td><?= htmlspecialchars($p['id_user']) ?></td>

                                                <!-- NAMA WARGA -->
                                                <td><?= htmlspecialchars($p['nama_warga_final'] ?? '-') ?></td>

                                                <!-- KEPALA KELUARGA -->
                                                <td><?= htmlspecialchars($p['kepala_keluarga_final'] ?? '-') ?></td>

                                                <!-- RT (HIDDEN) -->
                                                <td style="display:none;"><?= htmlspecialchars($p['rt_final'] ?? '') ?></td>

                                                <!-- RW (HIDDEN) -->
                                                <td style="display:none;"><?= htmlspecialchars($p['rw_final'] ?? '') ?></td>

                                                <!-- CATATAN -->
                                                <td><?= htmlspecialchars($p['catatan']) ?></td>

                                                <!-- STATUS -->
                                                <td>
                                                    <form method="POST">
                                                        <input type="hidden" name="id_pending" value="<?= $p['id_pending'] ?>">
                                                        <input type="hidden" name="status" id="statusH<?= $p['id_pending'] ?>">

                                                        <a class="btn btn-sm nav-link dropdown-toggle
                                                            <?= $p['status'] === 'terverifikasi' ? 'btn-success' : 'btn-warning' ?>"
                                                        data-toggle="dropdown"
                                                        style="min-width:90px; font-weight:600;">
                                                            <?= ucfirst($p['status']) ?>
                                                        </a>

                                                        <div class="dropdown-menu dropdown-menu-right">
                                                            <a href="#"
                                                            class="dropdown-item text-success font-weight-bold"
                                                            onclick="
                                                                document.getElementById('statusH<?= $p['id_pending'] ?>').value='terverifikasi';
                                                                this.closest('form').submit();
                                                            ">
                                                                Setujui & Hapus
                                                            </a>
                                                        </div>
                                                    </form>
                                                </td>

                                                <!-- OPSI -->
                                                <td class="text-center">
                                                    <div style="display:flex; justify-content:center; gap:5px;">

                                                        <button class="btn btn-sm btn-secondary"
                                                            title="Lihat"
                                                            data-toggle="modal"
                                                            data-target="#pendingHapusModal<?= $p['id_pending'] ?>">
                                                            <iconify-icon style="font-size:16px;" icon="mdi:eye"></iconify-icon>
                                                        </button>

                                                        <button class="btn btn-sm btn-danger"
                                                            title="Hapus"
                                                            data-toggle="modal"
                                                            data-target="#deleteKeluargaModal<?= $p['id_pending'] ?>">
                                                            <iconify-icon style="font-size:16px;" icon="mdi:trash"></iconify-icon>
                                                        </button>

                                                        <a href="pendinghapus_pdf_row.php?id=<?= $p['id_pending'] ?>"
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
                                            <td colspan="11" class="text-center text-muted">
                                                Belum tersedia pengajuan data
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                                            <?php foreach ($pendingHapus as $p): ?>

                                            <!-- Delete Confirmation Modal  -->
                                            <div class="modal fade"
                                                id="deleteKeluargaModal<?= $p['id_pending'] ?>"
                                                tabindex="-1"
                                                role="dialog">

                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content" style="border-radius:12px;">

                                                        <!-- Header -->
                                                        <div class="modal-header" style="background-color:#dc3545; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold">
                                                                Hapus Data Keluarga
                                                            </h5>
                                                            <button class="close text-white" type="button" data-dismiss="modal">
                                                                <span>×</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body text-gray-700" style="font-size:0.95rem;">
                                                            Apakah Anda yakin ingin
                                                            <strong class="text-danger">menghapus data keluarga ini</strong>?<br>
                                                            Tindakan ini <strong>tidak dapat dibatalkan</strong>.
                                                        </div>

                                                        <!-- Footer -->
                                                        <div class="modal-footer">
                                                            <button class="btn btn-secondary" data-dismiss="modal">Batal</button>

                                                            <!-- FORM DELETE -->
                                                            <form method="POST">
                                                                <input type="hidden" name="id_pending" value="<?= $p['id_pending'] ?>">
                                                                <input type="hidden" name="hapus_pending" value="1">

                                                                <button type="submit" class="btn btn-danger font-weight-bold">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Show Data Modal -->
                                            <div class="modal fade" id="pendingHapusModal<?= $p['id_pending'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header d-flex justify-content-between align-items-center"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold mb-0">
                                                                Detail Data Keluarga
                                                            </h5>
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
                                                                        <label>Nomor Kartu Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['no_kk_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Kepala Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['kepala_keluarga_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 2 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-12">
                                                                        <label>Alamat</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['alamat_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 3 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>RT</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['rt_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>RW</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['rw_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 4 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Kelurahan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['kelurahan_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Kecamatan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= htmlspecialchars($p['kecamatan_final']) ?>"
                                                                            readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- ===== DOKUMEN PENDUKUNG ===== -->
                                                                <div class="form-group text-center mt-4">
                                                                    <label style="font-weight:600;">Dokumen Pendukung</label>

                                                                    <!-- KTP -->
                                                                    <div class="mt-2">
                                                                        <small class="text-muted d-block">Dokumen KTP</small>
                                                                        <?php if (!empty($p['dokumen_ktp_final']) && file_exists('img/ktp/'.$p['dokumen_ktp_final'])): ?>
                                                                            <img src="img/ktp/<?= htmlspecialchars($p['dokumen_ktp_final']) ?>"
                                                                                style="max-width:350px; border-radius:10px; margin-top:6px;">
                                                                        <?php else: ?>
                                                                            <div class="text-muted">Tidak ada dokumen KTP</div>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <!-- KK -->
                                                                    <div class="mt-3">
                                                                        <small class="text-muted d-block">Dokumen KK</small>
                                                                        <?php if (!empty($p['dokumen_kk_final']) && file_exists('img/kk/'.$p['dokumen_kk_final'])): ?>
                                                                            <img src="img/kk/<?= htmlspecialchars($p['dokumen_kk_final']) ?>"
                                                                                style="max-width:350px; border-radius:10px; margin-top:6px;">
                                                                        <?php else: ?>
                                                                            <div class="text-muted">Tidak ada dokumen KK</div>
                                                                        <?php endif; ?>
                                                                    </div>
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