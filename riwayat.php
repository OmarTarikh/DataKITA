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
        u.Foto_profil
    FROM User u
    WHERE u.Id_user = ?
");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

$fotoProfil = $user['Foto_profil'] ?: 'default.png';

$riwayatStmt = $koneksi->query("
    SELECT 
        r.id,
        r.jenis_data,
        r.id_data,
        r.aksi,
        r.keterangan,
        r.dilakukan_oleh,
        r.created_at,
        u.Nama_user
    FROM riwayat_administrasi r
    LEFT JOIN User u ON u.Id_user = r.dilakukan_oleh
    ORDER BY r.created_at DESC
");

$dataRiwayat = $riwayatStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Halaman Riwayat</title>

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
                                <h6 class="m-0 font-weight-bold text-primary">TABEL RIWAYAT ADMINISTRASI</h6>
                                <div class="d-flex align-items-center" style="gap:10px;">
                                    <a href="riwayat_pdf.php"
                                    target="_blank"
                                    class="btn btn-danger d-inline-flex align-items-center justify-content-center"
                                    style="border:none; border-radius:10px; font-weight:700; font-size:13px;">
                                    <iconify-icon icon="mingcute:pdf-fill"
                                                    style="font-size:18px; margin-right:6px;"></iconify-icon>
                                    CETAK PDF
                                    </a>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                        <thead style="background-color: #4E73DF; color: #fff;">
                                            <tr>
                                                <th>ID</th>
                                                <th>Jenis Data</th>
                                                <th>ID Data</th>
                                                <th>Aksi</th>
                                                <th>Keterangan</th>
                                                <th>Dilakukan Oleh</th>
                                                <th>Tanggal</th>
                                                <th>Opsi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($dataRiwayat)): ?>
                                                <?php foreach ($dataRiwayat as $r): ?>
                                                    <tr>
                                                        <td><?= $r['id'] ?></td>
                                                        <td>
                                                            <?php
                                                                $badge = 'secondary';

                                                                if ($r['jenis_data'] === 'keluarga') {
                                                                    $badge = 'primary';
                                                                } elseif ($r['jenis_data'] === 'warga') {
                                                                    $badge = 'info';
                                                                } elseif ($r['jenis_data'] === 'dashboard') {
                                                                    $badge = 'success';
                                                                }
                                                            ?>
                                                            <span class="badge badge-<?= $badge ?>">
                                                                <?= ucfirst($r['jenis_data']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($r['id_data']) ?></td>
                                                        <td>
                                                            <span class="badge badge-<?=
                                                                $r['aksi'] === 'tambah' ? 'success' :
                                                                ($r['aksi'] === 'hapus' ? 'danger' :
                                                                ($r['aksi'] === 'verifikasi' ? 'warning' : 'secondary'))
                                                            ?>">
                                                                <?= ucfirst($r['aksi']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= htmlspecialchars($r['keterangan']) ?></td>
                                                        <td><?= htmlspecialchars($r['Nama_user'] ?? 'System') ?></td>
                                                        <td><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td>                                                
                                                        <td class="text-center align-middle">
                                                            <div style="display: flex; justify-content: center; align-items: center; gap: 6px; height: 100%;">
                                                                <button class="btn btn-sm btn-secondary d-flex align-items-center justify-content-center" 
                                                                title="Lihat" data-toggle="modal" data-target="#showRiwayatModal<?= $r['id'] ?>"
                                                                style="width: 28px; height: 28px; border-radius: 6px;">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:eye"></iconify-icon>
                                                                </button>

                                                                <a href="riwayat_pdf_row.php?id=<?= $r['id'] ?>"
                                                                    class="btn btn-sm btn-info"
                                                                    title="Unduh PDF"
                                                                    target="_blank">
                                                                    <iconify-icon style="font-size:18px;" icon="mingcute:pdf-fill"></iconify-icon>
                                                                </a>
                                                            </div>
                                                        </td>
                                            </tr>
                    <!-- Show Riwayat Administrasi Modal -->
                    <div class="modal fade" id="showRiwayatModal<?= $r['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="showRiwayatModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                            <!-- Header -->
                            <div class="modal-header d-flex justify-content-between align-items-center" style="background-color:#4E73DF; border:none;">
                                <h5 class="modal-title text-white font-weight-bold mb-0">Detail Riwayat Administrasi</h5>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:1;">
                                <span aria-hidden="true" style="font-size:1.5rem;">&times;</span>
                                </button>
                            </div>

                            <!-- Body -->
                            <div class="modal-body bg-white" style="padding:1.5rem;">
                                <form>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                        <label style="font-weight:500; color:#6c757d;">JENIS DATA</label>
                                        <input type="text" class="form-control rounded-pill" value="<?= ucfirst($r['jenis_data']) ?>"
                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                        </div>
                                        <div class="form-group col-md-6">
                                        <label style="font-weight:500; color:#6c757d;">ID DATA</label>
                                        <input type="text" class="form-control rounded-pill" value="<?= $r['id_data'] ?>"
                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                        <label style="font-weight:500; color:#6c757d;">AKSI</label>
                                        <input type="text" class="form-control rounded-pill" value="<?= ucfirst($r['aksi']) ?>"
                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                        </div>
                                        <div class="form-group col-md-6">
                                        <label style="font-weight:500; color:#6c757d;">DILAKUKAN OLEH</label>
                                        <input type="text" class="form-control rounded-pill" value="<?= $r['Nama_user'] ?? 'System' ?>"
                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                        <label style="font-weight:500; color:#6c757d;">TANGGAL</label>
                                        <input type="text" class="form-control rounded-pill" value="<?= $r['created_at'] ?>"
                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-12">
                                        <label style="font-weight:500; color:#6c757d;">KETERANGAN</label>
                                        <input type="text" class="form-control rounded-pill" value="<?= $r['keterangan'] ?>"
                                            style="font-size:0.9rem; background-color:#e9ecef;" readonly>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-center text-muted">
                    Belum ada data riwayat
                </td>
            </tr>
        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Footer -->
                <footer class="sticky-footer bg-white">
                    <div class="container my-auto">
                        <div class="copyright text-center my-auto">
                            <span>Copyright &copy; DataKITA 2025</span>
                        </div>
                    </div>
                </footer>
            </div>
            <!-- End of Main Content -->

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