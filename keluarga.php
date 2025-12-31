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

/* ================= DATA ANGGOTA ================= */
$anggotaStmt = $koneksi->query("
    SELECT NIK, Nama, No_kk FROM Warga ORDER BY Nama ASC
");

$anggotaKeluarga = [];
while ($row = $anggotaStmt->fetch(PDO::FETCH_ASSOC)) {
    $anggotaKeluarga[$row['No_kk']][] = $row;
}

/* ======================================================
   UPDATE STATUS KELUARGA + RIWAYAT
====================================================== */
if (
    isset($_POST['status'], $_POST['no_kk']) &&
    !isset($_POST['update_keluarga']) &&
    !isset($_POST['tambah_keluarga'])
) {
    $stmt = $koneksi->prepare("UPDATE Keluarga SET status = ? WHERE No_kk = ?");
    $stmt->execute([$_POST['status'], $_POST['no_kk']]);

    /* ===== RIWAYAT ===== */
    $ket = "Status keluarga diubah menjadi ".$_POST['status'];
    $riwayat = $koneksi->prepare("
        INSERT INTO riwayat_administrasi
        (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
        VALUES ('keluarga', ?, 'verifikasi', ?, ?)
    ");
    $riwayat->execute([$_POST['no_kk'], $ket, $user_id]);

    header("Location: keluarga.php");
    exit;
}

/* ======================================================
   UPDATE DATA KELUARGA + RIWAYAT
====================================================== */
if (isset($_POST['update_keluarga'])) {

    $no_kk = $_POST['no_kk'];
    $kepala = $_POST['kepala_keluarga'];
    $alamat = $_POST['alamat'];
    $rt = $_POST['rt'];
    $rw = $_POST['rw'];
    $kelurahan = $_POST['kelurahan'];
    $kecamatan = $_POST['kecamatan'];

    if (!is_dir('img/kk')) mkdir('img/kk', 0755, true);

    $dokumen = null;
    if (!empty($_FILES['dokumen_kk']['name'])) {
        $ext = pathinfo($_FILES['dokumen_kk']['name'], PATHINFO_EXTENSION);
        $dokumen = 'kk_'.$no_kk.'_'.time().'.'.$ext;
        move_uploaded_file($_FILES['dokumen_kk']['tmp_name'], 'img/kk/'.$dokumen);
    }

    $sql = "
        UPDATE Keluarga SET
            Kepala_keluarga=?,
            Alamat=?,
            RT=?,
            RW=?,
            Kelurahan=?,
            Kecamatan=?
            ".($dokumen ? ", Dokumen_kk=?" : "")."
        WHERE No_kk=?
    ";

    $params = [$kepala,$alamat,$rt,$rw,$kelurahan,$kecamatan];
    if ($dokumen) $params[] = $dokumen;
    $params[] = $no_kk;

    $stmt = $koneksi->prepare($sql);
    $stmt->execute($params);

    /* ===== RIWAYAT ===== */
    $ket = "Data keluarga diperbarui";
    $riwayat = $koneksi->prepare("
        INSERT INTO riwayat_administrasi
        (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
        VALUES ('keluarga', ?, 'ubah', ?, ?)
    ");
    $riwayat->execute([$no_kk, $ket, $user_id]);

    header("Location: keluarga.php");
    exit;
}

/* ======================================================
   TAMBAH DATA KELUARGA + RIWAYAT
====================================================== */
if (isset($_POST['tambah_keluarga'])) {

    $no_kk = $_POST['no_kk'];
    $kepala = $_POST['kepala_keluarga'];
    $alamat = $_POST['alamat'];
    $rt = $_POST['rt'];
    $rw = $_POST['rw'];
    $kelurahan = $_POST['kelurahan'];
    $kecamatan = $_POST['kecamatan'];
    $id_user = $_POST['id_user'];
    $status = $_POST['status'];

    if (!is_dir('img/kk')) mkdir('img/kk', 0755, true);

    $dokumen_kk = null;
    if (!empty($_FILES['dokumen_kk']['name'])) {
        $ext = pathinfo($_FILES['dokumen_kk']['name'], PATHINFO_EXTENSION);
        $dokumen_kk = 'kk_'.$no_kk.'_'.time().'.'.$ext;
        move_uploaded_file($_FILES['dokumen_kk']['tmp_name'], 'img/kk/'.$dokumen_kk);
    }

    $stmt = $koneksi->prepare("
        INSERT INTO Keluarga
        (No_kk, Kepala_keluarga, Alamat, RT, RW, Kelurahan, Kecamatan, Dokumen_kk, Id_user, status)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $no_kk,$kepala,$alamat,$rt,$rw,
        $kelurahan,$kecamatan,$dokumen_kk,
        $id_user,$status
    ]);

    /* ===== RIWAYAT ===== */
    $ket = "Data keluarga baru ditambahkan";
    $riwayat = $koneksi->prepare("
        INSERT INTO riwayat_administrasi
        (jenis_data, id_data, aksi, keterangan, dilakukan_oleh)
        VALUES ('keluarga', ?, 'tambah', ?, ?)
    ");
    $riwayat->execute([$no_kk, $ket, $user_id]);

    header("Location: keluarga.php");
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

    <title>Halaman Data Keluarga</title>

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
                            <h6 class="m-0 font-weight-bold text-primary">TABEL KELUARGA</h6>
                            <div class="d-flex align-items-center" style="gap:10px;">
                                <a href="#" class="btn btn-primary d-inline-flex align-items-center justify-content-center" 
                                    data-toggle="modal" data-target="#addKeluargaModal"
                                    style="border:none; border-radius:10px; font-weight:700; font-size:13px;">
                                    <iconify-icon icon="ic:round-plus" style="font-size:20px; margin-right:6px;"></iconify-icon>
                                    TAMBAH DATA
                                </a>

                                <a href="keluarga_pdf.php" class="btn btn-danger d-inline-flex align-items-center justify-content-center"
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
                                            <th>NO. KARTU KELUARGA</th>
                                            <th>KEPALA KELUARGA</th>
                                            <th>ALAMAT</th>
                                            <th>RT</th>
                                            <th>RW</th>
                                            <th>KELURAHAN</th>
                                            <th>KECAMATAN</th>
                                            <th>DOKUMEN KK</th>
                                            <th>ID USER</th>
                                            <th>STATUS</th>
                                            <th>OPSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dataKeluarga) > 0): ?>
                                            <?php foreach ($dataKeluarga as $k): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($k['No_kk']) ?></td>
                                                    <td><?= htmlspecialchars($k['Kepala_keluarga']) ?></td>
                                                    <td><?= htmlspecialchars($k['Alamat']) ?></td>
                                                    <td><?= htmlspecialchars($k['RT']) ?></td>
                                                    <td><?= htmlspecialchars($k['RW']) ?></td>
                                                    <td><?= htmlspecialchars($k['Kelurahan']) ?></td>
                                                    <td><?= htmlspecialchars($k['Kecamatan']) ?></td>
                                                    <td><?= htmlspecialchars($k['Dokumen_kk']) ?></td>
                                                    <td><?= htmlspecialchars($k['Id_user']) ?></td>
                                                    <td>
                                                        <form method="POST">
                                                            <input type="hidden" name="no_kk" value="<?= $k['No_kk'] ?>">
                                                            <input type="hidden" name="status" id="status<?= $k['No_kk'] ?>">

                                                            <a class="btn btn-sm nav-link dropdown-toggle 
                                                                    <?= $k['status'] === 'terverifikasi'
                                                                        ? 'btn-success'
                                                                        : 'btn-warning' ?>"
                                                                id="statusDropdown<?= $k['No_kk'] ?>" 
                                                                href="#"
                                                                role="button"
                                                                data-toggle="dropdown"
                                                                aria-haspopup="true"
                                                                aria-expanded="false"
                                                                style="min-width:80px; font-weight:600;">
                                                                <?= ucfirst($k['status']) ?>
                                                            </a>

                                                            <div class="dropdown-menu dropdown-menu-right animated--fade-in"
                                                                aria-labelledby="statusDropdown<?= $k['No_kk'] ?>">

                                                                <a href="#"
                                                                class="dropdown-item text-warning font-weight-bold"
                                                                onclick="
                                                                        document.getElementById('status<?= $k['No_kk'] ?>').value='pending';
                                                                        this.closest('form').submit();
                                                                ">
                                                                    <span class="badge badge-warning">&nbsp;</span>
                                                                    Pending
                                                                </a>

                                                                <div class="dropdown-divider"></div>

                                                                <a href="#"
                                                                class="dropdown-item text-success font-weight-bold"
                                                                onclick="
                                                                        document.getElementById('status<?= $k['No_kk'] ?>').value='terverifikasi';
                                                                        this.closest('form').submit();
                                                                ">
                                                                    <span class="badge badge-success">&nbsp;</span>
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
                                                                data-target="#showKeluargaModal<?= $k['No_kk'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:eye"></iconify-icon>
                                                            </button>

                                                            <!-- EDIT -->
                                                            <button class="btn btn-sm btn-warning"
                                                                title="Edit"
                                                                data-toggle="modal"
                                                                data-target="#editKeluargaModal<?= $k['No_kk'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:pencil"></iconify-icon>
                                                            </button>

                                                            <!-- HAPUS -->
                                                            <button class="btn btn-sm btn-danger"
                                                                title="Hapus"
                                                                data-toggle="modal"
                                                                data-target="#deleteKeluargaModal<?= $k['No_kk'] ?>">
                                                                <iconify-icon style="font-size:16px;" icon="mdi:trash"></iconify-icon>
                                                            </button>

                                                            <!-- CETAK PDF PER BARIS -->
                                                            <a href="keluarga_pdf_row.php?id=<?= $k['No_kk'] ?>"
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
                                                            Data keluarga belum tersedia
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                            <?php foreach ($dataKeluarga as $k): ?>
                                            <!-- Add Data Keluarga Modal -->
                                            <div class="modal fade" id="addKeluargaModal" tabindex="-1" role="dialog" aria-labelledby="addKeluargaModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">


                                                    <!-- Header -->
                                                    <div class="modal-header d-flex justify-content-between align-items-center" style="background-color:#4E73DF; border:none;">
                                                        <h5 class="modal-title text-white font-weight-bold mb-0">Tambah Data Keluarga</h5>
                                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity:1;">
                                                        <span aria-hidden="true" style="font-size:1.5rem;">&times;</span>
                                                        </button>
                                                    </div>


                                                    <!-- Body -->
                                                    <div class="modal-body bg-white" style="padding:1.5rem;">
                                                        <form method="POST" enctype="multipart/form-data">

                                                        <!-- ================= KELUARGA ================= -->

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>Nomor Kartu Keluarga</label>
                                                                <input type="text" name="no_kk"
                                                                    class="form-control rounded-pill" required>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Kepala Keluarga</label>
                                                                <input type="text" id="kepala_keluarga"
                                                                    name="kepala_keluarga"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-12">
                                                                <label>Alamat</label>
                                                                <input type="text" name="alamat"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>RT</label>
                                                                <select name="rt" class="form-control rounded-pill" required>
                                                                    <option value="">Pilih RT</option>
                                                                    <option value="01">01</option>
                                                                    <option value="02">02</option>
                                                                    <option value="03">03</option>
                                                                    <option value="04">04</option>
                                                                </select>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>RW</label>
                                                                <select name="rw" class="form-control rounded-pill" required>
                                                                    <option value="">Pilih RW</option>
                                                                    <option value="01">01</option>
                                                                    <option value="02">02</option>
                                                                    <option value="03">03</option>
                                                                    <option value="04">04</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>Kelurahan</label>
                                                                <input type="text" name="kelurahan"
                                                                    class="form-control rounded-pill" required>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Kecamatan</label>
                                                                <input type="text" name="kecamatan"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>ID User</label>
                                                                <input type="number" name="id_user"
                                                                    class="form-control rounded-pill"
                                                                    value="<?= $_SESSION['user_id'] ?>" readonly>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Status</label>
                                                                <select name="status" class="form-control rounded-pill">
                                                                    <option value="pending">Pending</option>
                                                                    <option value="terverifikasi">Terverifikasi</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Dokumen KK</label>
                                                            <input type="file" name="dokumen_kk"
                                                                class="form-control rounded-pill">
                                                        </div>

                                                        <hr style="margin:25px 0; border-top:2px dashed #ddd;">

                                                        <!-- ================= WARGA (KEPALA KELUARGA) ================= -->

                                                        <h6 class="font-weight-bold text-primary">Data Kepala Keluarga</h6>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>NIK</label>
                                                                <input type="text" name="nik"
                                                                    class="form-control rounded-pill" required>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Nama</label>
                                                                <input type="text" id="nama_warga"
                                                                    name="nama"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>Tempat Lahir</label>
                                                                <input type="text" name="tempat_lahir"
                                                                    class="form-control rounded-pill" required>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Tanggal Lahir</label>
                                                                <input type="date" name="tanggal_lahir"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>Jenis Kelamin</label>
                                                                <select name="jenis_kelamin" class="form-control rounded-pill">
                                                                    <option value="Laki-laki">Laki-laki</option>
                                                                    <option value="Perempuan">Perempuan</option>
                                                                </select>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Agama</label>
                                                                <input type="text" name="agama"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>Pendidikan</label>
                                                                <select name="pendidikan" class="form-control rounded-pill" required>
                                                                    <option value="SD">SD</option>
                                                                    <option value="SMP">SMP</option>
                                                                    <option value="SMA">SMA</option>
                                                                    <option value="D3">D3</option>
                                                                    <option value="S1">S1</option>
                                                                    <option value="S2">S2</option>
                                                                </select>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Pekerjaan</label>
                                                                <input type="text" name="pekerjaan"
                                                                    class="form-control rounded-pill" required>
                                                            </div>
                                                        </div>

                                                        <div class="form-row">
                                                            <div class="form-group col-md-6">
                                                                <label>Status Perkawinan</label>
                                                                <select name="status_perkawinan"
                                                                    class="form-control rounded-pill">
                                                                    <option value="Kawin">Kawin</option>
                                                                    <option value="Belum Kawin">Belum Kawin</option>
                                                                </select>
                                                            </div>

                                                            <div class="form-group col-md-6">
                                                                <label>Dokumen KTP</label>
                                                                <input type="file" name="dokumen_ktp"
                                                                    class="form-control rounded-pill">
                                                            </div>
                                                        </div>

                                                        <button type="submit" name="tambah_keluarga"
                                                            class="btn btn-primary font-weight-bold">
                                                            SIMPAN DATA
                                                        </button>

                                                        </form>

                                                    <script>
                                                    document.getElementById('kepala_keluarga')
                                                    .addEventListener('input', function () {
                                                        document.getElementById('nama_warga').value = this.value;
                                                    });
                                                    </script>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Delete Confirmation Modal keluarga -->
                                            <div class="modal fade"
                                                id="deleteKeluargaModal<?= $k['No_kk'] ?>"
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
                                                            <form action="keluarga_delete.php" method="GET">
                                                                <input type="hidden" name="id" value="<?= $k['No_kk'] ?>">
                                                                <button type="submit" class="btn btn-danger font-weight-bold">
                                                                    Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Show Data Keluarga Modal -->
                                            <div class="modal fade" id="showKeluargaModal<?= $k['No_kk'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
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
                                                                        value="<?= htmlspecialchars($k['No_kk']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Kepala Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['Kepala_keluarga']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 2 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-12">
                                                                    <label>Alamat</label>
                                                                    <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['Alamat']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 3 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                    <label>RT</label>
                                                                    <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['RT']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                </div>

                                                                <div class="form-group col-md-6">
                                                                    <label>RW</label>
                                                                    <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['RW']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 4 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                    <label>Kelurahan</label>
                                                                    <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['Kelurahan']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                    <label>Kecamatan</label>
                                                                    <input type="text" class="form-control rounded-pill"
                                                                        value="<?= htmlspecialchars($k['Kecamatan']) ?>"
                                                                        readonly style="background-color:#e9ecef;">
                                                                    </div>
                                                                </div>

                                                                <!-- ===== ANGGOTA KELUARGA ===== -->
                                                                <div class="form-group">
                                                                    <label style="font-weight:500; color:#6c757d;">Anggota Keluarga</label>
                                                                    <div style="border:1px solid #d1d3e2; border-radius:10px; padding:10px 15px; background-color:#f8f9fc;">

                                                                        <div class="d-flex justify-content-between"
                                                                            style="font-size:0.9rem; font-weight:600; color:#4E73DF;">
                                                                            <span>NIK</span>
                                                                            <span>Nama</span>
                                                                        </div>

                                                                        <hr style="margin:6px 0;">

                                                                        <div style="font-size:0.9rem; color:#5a5c69;">
                                                                        <?php if (!empty($anggotaKeluarga[$k['No_kk']])): ?>
                                                                            <?php foreach ($anggotaKeluarga[$k['No_kk']] as $a): ?>
                                                                                <div class="d-flex justify-content-between">
                                                                                    <span><?= htmlspecialchars($a['NIK']) ?></span>
                                                                                    <span><?= htmlspecialchars($a['Nama']) ?></span>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        <?php else: ?>
                                                                            <div class="text-center text-muted">
                                                                                Tidak ada anggota keluarga
                                                                            </div>
                                                                        <?php endif; ?>
                                                                        </div>

                                                                    </div>
                                                                </div>

                                                                <!-- ===== DOKUMEN KK ===== -->
                                                                <div class="form-group text-center mt-3">
                                                                    <label>Dokumen KK</label><br>
                                                                        <?php if (!empty($k['Dokumen_kk'])): ?>
                                                                            <img src="img/kk/<?= htmlspecialchars($k['Dokumen_kk']) ?>"
                                                                                style="max-width:400px; border-radius:10px;">
                                                                        <?php else: ?>
                                                                            <span class="text-muted">Belum ada dokumen</span>
                                                                        <?php endif; ?>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Edit Data Keluarga Modal -->
                                            <div class="modal fade" id="editKeluargaModal<?= $k['No_kk'] ?>" tabindex="-1" role="dialog">
                                                <div class="modal-dialog modal-lg" role="document">
                                                    <div class="modal-content" style="border-radius:18px; overflow:hidden;">

                                                        <!-- Header -->
                                                        <div class="modal-header d-flex justify-content-between align-items-center"
                                                            style="background-color:#4E73DF; border:none;">
                                                            <h5 class="modal-title text-white font-weight-bold mb-0">Edit Data Keluarga</h5>
                                                            <button type="button" class="close text-white" data-dismiss="modal">
                                                                <span style="font-size:1.5rem;">&times;</span>
                                                            </button>
                                                        </div>

                                                        <!-- Body -->
                                                        <div class="modal-body bg-white" style="padding:1.5rem;">
                                                            <form method="POST" enctype="multipart/form-data">

                                                                <input type="hidden" name="no_kk" value="<?= $k['No_kk'] ?>">

                                                                <!-- Row 1 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Nomor Kartu Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            value="<?= $k['No_kk'] ?>" readonly>
                                                                    </div>

                                                                    <div class="form-group col-md-6">
                                                                        <label>Kepala Keluarga</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            name="kepala_keluarga"
                                                                            value="<?= $k['Kepala_keluarga'] ?>">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 2 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-12">
                                                                        <label>Alamat</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            name="alamat"
                                                                            value="<?= $k['Alamat'] ?>">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 3 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>RT</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            name="rt"
                                                                            value="<?= $k['RT'] ?>">
                                                                    </div>
                                                                    <div class="form-group col-md-6">
                                                                        <label>RW</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            name="rw"
                                                                            value="<?= $k['RW'] ?>">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 4 -->
                                                                <div class="form-row">
                                                                    <div class="form-group col-md-6">
                                                                        <label>Kelurahan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            name="kelurahan"
                                                                            value="<?= $k['Kelurahan'] ?>">
                                                                    </div>
                                                                    <div class="form-group col-md-6">
                                                                        <label>Kecamatan</label>
                                                                        <input type="text" class="form-control rounded-pill"
                                                                            name="kecamatan"
                                                                            value="<?= $k['Kecamatan'] ?>">
                                                                    </div>
                                                                </div>

                                                                <!-- Row 5 -->
                                                                <div class="form-group">
                                                                    <label>Upload Dokumen KK</label>
                                                                    <input type="file" name="dokumen_kk" class="form-control rounded-pill">
                                                                </div>

                                                                <div class="form-row">
                                                                    <div class="form-group col-md-12 d-flex mt-2">
                                                                        <button type="submit" name="update_keluarga"
                                                                            class="btn btn-info font-weight-bold"
                                                                            style="border-radius:10px;">
                                                                            SIMPAN PERUBAHAN
                                                                        </button>
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
                                        table.column(3).search(currentRT); // RT
                                        table.column(4).search(currentRW); // RW
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
                    <a class="btn btn-primary" href="login.html">Logout</a>
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