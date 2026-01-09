<?php
session_start();
require 'koneksi.php';

// PROTECT LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$error = "";

/* ===============================
   NORMAL FORM SUBMIT (KELUARGA)
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* =====================================
       TAMBAHAN AMAN (MODE CHECK)
       DEFAULT = NEW (KODE LAMA)
    ====================================== */
    $mode = $_POST['mode'] ?? 'new';

    /* =====================================
       FILE UPLOAD KK (HANYA MODE NEW)
    ====================================== */
    $fotoKK = null;
    if ($mode === 'new' && !empty($_FILES['foto_kk']['name'])) {
        $folder = "img/kk/";
        if (!is_dir($folder)) mkdir($folder, 0755, true);

        $ext = pathinfo($_FILES['foto_kk']['name'], PATHINFO_EXTENSION);
        $fotoKK = uniqid('kk_') . '.' . $ext;
        move_uploaded_file($_FILES['foto_kk']['tmp_name'], $folder . $fotoKK);
    }

    /* =====================================
       FORM DATA (AMAN UNTUK 2 MODE)
    ====================================== */
    if ($mode === 'existing') {
        // KK SUDAH ADA
        $no_kk = $_POST['existing_no_kk'];
    } else {
        // KODE LAMA (NEW)
        $no_kk           = $_POST['no_kk'];
        $kepala_keluarga = $_POST['kepala_keluarga'];
        $alamat          = $_POST['alamat'];
        $rt              = $_POST['rt'];
        $rw              = $_POST['rw'];
        $kelurahan       = $_POST['kelurahan'];
        $kecamatan       = $_POST['kecamatan'];
    }

    /* =====================================
       VALIDASI KK EXIST (HANYA MODE EXISTING)
    ====================================== */
    if ($mode === 'existing') {
        $cekKK = $koneksi->prepare("
            SELECT No_kk 
            FROM keluarga 
            WHERE No_kk = ?
            LIMIT 1
        ");
        $cekKK->execute([$no_kk]);

        if (!$cekKK->fetch()) {
            $_SESSION['error'] = 'Nomor Kartu Keluarga tidak ditemukan.';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    /* =====================================
       INSERT KELUARGA (KODE LAMA UTUH)
       HANYA JALAN JIKA MODE NEW
    ====================================== */
    if ($mode === 'new') {

        $cek = $koneksi->prepare("SELECT No_kk FROM keluarga WHERE No_kk = ?");
        $cek->execute([$no_kk]);

        if (!$cek->fetch()) {
            $insertKeluarga = $koneksi->prepare("
                INSERT INTO keluarga 
                (No_kk, Kepala_keluarga, Alamat, RT, RW, Kelurahan, Kecamatan, Dokumen_kk, Id_user, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $insertKeluarga->execute([
                $no_kk,
                $kepala_keluarga,
                $alamat,
                $rt,
                $rw,
                $kelurahan,
                $kecamatan,
                $fotoKK,
                $userId
            ]);
        }
    }

    /* =====================================
       INSERT ALL WARGA (KODE LAMA TANPA DIUBAH)
    ====================================== */
    if (!empty($_POST['warga'])) {

        foreach ($_POST['warga'] as $index => $warga) {

            $dokumen = null;
            if (!empty($_FILES['warga_file']['name'][$index])) {
                $dir = 'img/ktp/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                $dokumen = uniqid('ktp_') . '.' .
                    pathinfo($_FILES['warga_file']['name'][$index], PATHINFO_EXTENSION);

                move_uploaded_file(
                    $_FILES['warga_file']['tmp_name'][$index],
                    $dir . $dokumen
                );
            }

            $stmt = $koneksi->prepare("
                INSERT INTO warga (
                    NIK, Nama, Tempat_lahir, Tanggal_lahir, Jenis_kelamin,
                    Agama, Pendidikan, Pekerjaan, Status_perkawinan,
                    No_kk, Dokumen_ktp, Id_user, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $stmt->execute([
                $warga['nik'] ?? null,
                $warga['nama'] ?? null,
                $warga['tempat_lahir'] ?? null,
                $warga['tanggal_lahir'] ?? null,
                $warga['jenis_kelamin'] ?? null,
                $warga['agama'] ?? null,
                $warga['pendidikan'] ?? null,
                $warga['pekerjaan'] ?? null,
                $warga['status_perkawinan'] ?? null,
                $no_kk,      // 🔑 INI KUNCI RELASI
                $dokumen,
                $userId
            ]);
        }
    }

    /* =====================================
       CHECK STATUS (KODE LAMA UTUH)
    ====================================== */
    $cekKeluarga = $koneksi->prepare("
        SELECT status 
        FROM keluarga 
        WHERE No_kk = ?
        LIMIT 1
    ");
    $cekKeluarga->execute([$no_kk]);
    $keluarga = $cekKeluarga->fetch(PDO::FETCH_ASSOC);

    $cekWarga = $koneksi->prepare("
        SELECT COUNT(*) AS pending_count
        FROM warga
        WHERE No_kk = ? AND status != 'terverifikasi'
    ");
    $cekWarga->execute([$no_kk]);
    $warga = $cekWarga->fetch(PDO::FETCH_ASSOC);

    // LOGIC REDIRECT (KODE LAMA)
    if (
        $keluarga &&
        $keluarga['status'] === 'terverifikasi' &&
        $warga['pending_count'] == 0
    ) {
        header("Location: indexwarga.php");
    } else {
        header("Location: tungguverifikasi.php");
    }
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

  <title>Halaman Input Data</title>

  <link rel="stylesheet" href="css/style.css">
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
  <link
    href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
    rel="stylesheet">
  <link href="css/sb-admin-2.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@3.0.0/dist/iconify-icon.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

</head>

<body style="background-color:#4E73DF; position:relative; overflow:hidden; margin:0; padding:0; min-height:100vh;">

  <div style="position:absolute; top:0; right:0; width:50%; z-index:0;">
    <svg width="810" height="689" viewBox="0 0 810 689" fill="none" xmlns="http://www.w3.org/2000/svg" 
         style="width:100%; height:auto; transform:translate(15%,-40%) scale(1.3);">
      <path d="M2207.89 169.815L-2.57418e-05 688.587L802.529 -853.614L2207.89 169.815Z" fill="#5A84FF"/>
    </svg>
  </div>

  <div style="position:absolute; bottom:0; left:0; width:100%; z-index:0; line-height:0;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" style="width:100%; height:auto; display:block; margin-bottom:-1px;">
        <path fill="#5A84FF" fill-opacity="1" d="M0,64L48,64C96,64,192,64,288,96C384,128,480,192,576,197.3C672,203,768,149,864,133.3C960,117,1056,139,1152,149.3C1248,160,1344,160,1392,160L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
    </svg>
  </div>

<div style="position:relative; z-index:2;">

    <div class="container-fluid text-white d-flex align-items-center">
        <!-- Logo Image -->
        <img src="img/Logo_DataKITA(3).png" 
            alt="DataKITA Logo" 
            style="width: 130px; height: auto; flex-shrink: 0;">

        <!-- Heading Text -->
        <h4 class="mb-0" style="font-weight: 800; line-height:1.6; letter-spacing:1px;">
            UNTUK MENGAKSES APLIKASI INPUT DATA ANDA UNTUK<br>
            MEMASTIKAN BAHWA ANDA ADALAH WARGA KAMI.
        </h4>
    </div>

    <div style="padding: 0rem 7rem;">

        <!-- Form Fields -->
        <form method="POST" enctype="multipart/form-data" style="color: white;">
            <div class="row">

                <!-- LEFT -->
                <div class="col-md-6" style="padding-right: 7rem;">

                   <!-- MODE EXISTING KK (HIDDEN) -->
                    <div id="existingKKInfo" style="display:none;">
                        <label class="font-weight-bold">Nomor Kartu Keluarga</label>
                        <div class="d-flex align-items-center"
                            style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                            <input type="text"
                                name="existing_no_kk"
                                class="form-control border-0"
                                placeholder="Masukkan No KK yang sudah terdaftar..."
                                style="background:transparent;">
                        </div>
                    </div>

                    <input type="hidden" name="mode" id="formMode" value="new">

                    
                    <!-- FOTO KK & KEPALA KELUARGA -->
                    <div id="keluargaFields">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Nomor Kartu Keluarga</label>
                                <div class="d-flex align-items-center"
                                    style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                    <input type="text"
                                        name="no_kk"
                                        id="noKKField"
                                        required
                                        class="form-control border-0"
                                        placeholder="No Kartu Keluarga..."
                                        style="background:transparent;">
                                </div>
                        </div>

                        <div class="form-row">
    
                            <div class="form-group col-md-6">
                            <label class="font-weight-bold">Foto Kartu Keluarga</label>
                            <div class="d-flex align-items-center"
                                style="background-color:#fff;border-radius:50px;height:55px;padding:0 15px;">
                                <input type="file" name="foto_kk" required class="form-control border-0"
                                    style="font-size:0.9rem;background:transparent;">
                            </div>
                            </div>    
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Kepala Keluarga</label>
                                <div class="d-flex align-items-center"
                                    style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                    <input type="text"
                                        name="kepala_keluarga"
                                        id="kepalaKeluargaInput"
                                        required
                                        class="form-control border-0"
                                        placeholder="Nama Kepala Keluarga..."
                                        style="background:transparent;">
                                </div>
                            </div>
    
                        </div>
    
     
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Alamat</label>
                            <div class="d-flex align-items-center"
                                style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                <input type="text" name="alamat" required
                                    class="form-control border-0"
                                    placeholder="Alamat Anda..." style="background:transparent;">
                            </div>
                        </div>
    
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Kelurahan</label>
                                <div class="d-flex align-items-center"
                                    style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                    <input type="text" name="kelurahan" required
                                        class="form-control border-0"
                                        placeholder="Kelurahan..." style="background:transparent;">
                                </div>
                            </div>
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">Kecamatan</label>
                                <div class="d-flex align-items-center"
                                    style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                    <input type="text" name="kecamatan" required
                                        class="form-control border-0"
                                        placeholder="Kecamatan..." style="background:transparent;">
                                </div>
                            </div>
                        </div>
    
    
                        <!-- RT & RW -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">RT</label>
                                <div class="d-flex align-items-center"
                                    style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                    <select name="rt" required
                                        class="form-control border-0"
                                        style="background:transparent; font-size:0.9rem;">
                                        <option value="">Pilih RT</option>
                                        <option value="01">01</option>
                                        <option value="02">02</option>
                                        <option value="03">03</option>
                                        <option value="04">04</option>
                                    </select>
                                </div>
                            </div>
    
                            <div class="form-group col-md-6">
                                <label class="font-weight-bold">RW</label>
                                <div class="d-flex align-items-center"
                                    style="background:#fff;border-radius:50px;height:55px;padding:0 18px;">
                                    <select name="rw" required
                                        class="form-control border-0"
                                        style="background:transparent; font-size:0.9rem;">
                                        <option value="">Pilih RW</option>
                                        <option value="01">01</option>
                                        <option value="02">02</option>
                                        <option value="03">03</option>
                                        <option value="04">04</option>
                                    </select>
                                </div>
                            </div>
                    </div>
                    
                </div>
                <!-- Switch Mode -->
            <div class="mb-3">
                <small 
                    id="switchToExistingKK"
                    style="color:rgba(255,255,255,0.85); cursor:pointer; font-size:0.9rem;">
                    Sudah punya data keluarga?
                </small>
            </div>
                </div>

                <!-- RIGHT -->
                <div class="col-md-6" style="padding-right: 9rem;">
                    <div id="nikContainer">
                        <div class="nik-row" data-index="0">
                            <div class="form-group mb-4" style="position: relative;">
                                <label class="font-weight-bold">NIK KEPALA KELUARGA</label>

                                <div style="position: relative;">
                                    <input type="text" name="nik" required class="form-control"
                                        placeholder="NIK Kepala Keluarga..."
                                        style="border-radius:50px; font-size:0.9rem; height: 55px;
                                        padding:0.8rem 8rem 0.8rem 1.2rem; border:1px solid #d1d3e2;">

                                    <button type="button"
                                        class="btn btn-info font-weight-bold openModal"
                                        data-index="0"
                                        data-toggle="modal" data-target="#addWargaModal"
                                        style="position:absolute; top:50%; right:20px;
                                        transform:translateY(-50%);
                                        border-radius:10px; font-size:0.8rem; height: 30px;
                                        padding:0.3rem 1rem; line-height:1;">
                                        + DATA WARGA
                                    </button>
                                    <small id="status-0" class="text-success" style="display:none;">
                                        KTP tersimpan 
                                    </small>

                                </div>
                                
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4">
                        <a href="#" id="addAnggota"
                            style="color:rgba(255,255,255,0.85);  text-decoration: none; font-size: 0.9rem;">
                            Tambah Anggota keluarga +
                        </a>
                        <span id="removeAnggota"
                            style="color:rgba(255,255,255,0.85);  margin-left:12px;
                            font-size: 0.9rem; cursor:pointer; text-decoration: none;">
                            Kurangi Anggota keluarga -
                        </span>
                    </div>

                    <!-- Bottom Buttons -->
                    <div class="mt-1">
                        <button type="reset"
                            class="btn btn-danger font-weight-bold mr-2"
                            style="border-radius: 10px; font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                            KOSONGKAN
                        </button>
                        <button type="submit"
                            class="btn btn-success font-weight-bold"
                            style="border-radius: 10px; font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                            KIRIM DATA
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Modal Tambah Data Warga -->
<div class="modal fade" id="addWargaModal" tabindex="-1" role="dialog" aria-labelledby="addWargaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 18px; overflow: hidden;">

        <!-- Header -->
        <div class="modal-header" style="background-color: #4E73DF; border: none;">
            <h5 class="modal-title text-white font-weight-bold">Tambah Data Warga</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="opacity: 1;">
                <span aria-hidden="true" style="font-size: 1.6rem;">&times;</span>
            </button>
        </div>

        <!-- Body -->
        <div class="modal-body bg-white" style="padding: 1.5rem;">
            <form id="wargaForm" enctype="multipart/form-data">
                <input type="hidden" id="wargaIndex">

                <!-- REQUIRED HIDDEN FIELDS -->
                <input type="hidden" name="ajax" value="save_warga">
                <input type="hidden" name="no_kk" id="modalNoKK">

                <!-- Row 1 -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control rounded-pill"
                            placeholder="Masukkan Nama Lengkap"
                            style="font-size: 0.9rem; padding: 0.55rem 1rem;" required>
                    </div>

                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Pendidikan</label>
                        <select name="pendidikan" class="form-control rounded-pill"
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
                </div>

                <!-- Row 2 -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-control rounded-pill"
                            placeholder="Masukkan Tempat Lahir"
                            style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                    </div>

                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Pekerjaan</label>
                        <input type="text" name="pekerjaan" class="form-control rounded-pill"
                            placeholder="Masukkan Pekerjaan"
                            style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Kosongkan jika tidak punya
                        </small>
                    </div>
                </div>

                <!-- Row 3 -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control rounded-pill"
                            style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                    </div>

                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Status Perkawinan</label>
                        <select name="status_perkawinan" class="form-control rounded-pill"
                            style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            <option value="">Pilih Status</option>
                            <option>Belum Menikah</option>
                            <option>Menikah</option>
                            <option>Cerai</option>
                        </select>
                    </div>
                </div>

                <!-- Row 4 -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Kelamin</label>
                        <select name="jenis_kelamin" class="form-control rounded-pill"
                            style="font-size: 0.9rem; padding: 0.55rem 1rem;">
                            <option value="">Pilih Jenis Kelamin</option>
                            <option>Laki-laki</option>
                            <option>Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Foto KTP</label>
                        <input type="file" name="dokumen_ktp" class="form-control-file mt-1"
                            style="font-size: 0.9rem;">
                        <small class="text-muted" style="font-size: 0.75rem;">
                            Kosongkan jika tidak punya
                        </small>
                    </div>
                </div>

                <!-- Row 5 -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label style="font-weight: 500; color: #6c757d;">Agama</label>
                        <select name="agama" class="form-control rounded-pill"
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

                <!-- Buttons -->
                <div class="form-row mt-3">
                    <div class="form-group col-md-12 d-flex justify-content-end">
                        <button type="reset" class="btn btn-danger font-weight-bold mr-2"
                            style="border-radius: 10px; font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                            KOSONGKAN
                        </button>
                        <button type="button" id="saveWargaBtn" class="btn btn-info font-weight-bold"
                            style="border-radius: 10px; font-size: 0.9rem; padding: 0.5rem 1.2rem;">
                            SIMPAN DATA
                        </button>
                    </div>
                </div>

            </form>
        </div>

        </div>
    </div>
</div>


  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="js/sb-admin-2.min.js"></script>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const switchBtn   = document.getElementById('switchToExistingKK');
    const keluargaBox = document.getElementById('keluargaFields');
    const existingBox = document.querySelector('#existingKKInfo');
    const modeInput   = document.getElementById('formMode');

    // semua input di keluargaFields
    const keluargaInputs = keluargaBox.querySelectorAll('input, select');

    // field kanan index 0
    const nikRow   = document.querySelector('.nik-row[data-index="0"]');
    const nikLabel = nikRow.querySelector('label');
    const nikInput = nikRow.querySelector('input[type="text"]');

    let isExisting = false;

    switchBtn.addEventListener('click', function () {

        isExisting = !isExisting;

        if (isExisting) {
            /* ===== EXISTING ===== */
            keluargaBox.style.display = 'none';
            existingBox.style.display = 'block';
            modeInput.value = 'existing';

            // MATIKAN REQUIRED KELUARGA
            keluargaInputs.forEach(el => {
                el.dataset.required = el.required;
                el.required = false;
            });

            // FIELD NIK KEPALA
            nikLabel.innerText = 'NIK ANGGOTA KELUARGA';
            nikInput.placeholder = 'NIK Anggota Keluarga...';
            nikInput.required = false;
            nikInput.removeAttribute('name');

            switchBtn.innerText = 'Ingin membuat data keluarga baru?';

        } else {
            /* ===== NEW ===== */
            keluargaBox.style.display = 'block';
            existingBox.style.display = 'none';
            modeInput.value = 'new';

            // AKTIFKAN REQUIRED LAGI
            keluargaInputs.forEach(el => {
                if (el.dataset.required === "true") el.required = true;
            });

            nikLabel.innerText = 'NIK KEPALA KELUARGA';
            nikInput.placeholder = 'NIK Kepala Keluarga...';
            nikInput.required = true;
            nikInput.setAttribute('name', 'nik');

            switchBtn.innerText = 'Sudah punya data keluarga?';
        }
    });
});
</script>

<script>
    /* ======================================================
    GLOBAL STATE
    ====================================================== */
    let nikIndex = 0;
    const wargaData  = {};   // text data per warga
    const wargaFiles = {};   // KTP file per warga

    const addBtn    = document.getElementById('addAnggota');
    const removeBtn = document.getElementById('removeAnggota');
    const container = document.getElementById('nikContainer');

    removeBtn.style.display = 'none';

    /* ======================================================
    ADD ANGGOTA KELUARGA
    ====================================================== */
    addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        nikIndex++;

        container.insertAdjacentHTML('beforeend', `
            <div class="nik-row mt-3" data-index="${nikIndex}">
                <label class="font-weight-bold">NIK ANGGOTA KELUARGA</label>
                <div style="position: relative;">
                    <input type="text" name="nik_anggota[]" class="form-control"
                        placeholder="NIK Anggota Keluarga..."
                        style="border-radius:50px; font-size:0.9rem; height:55px;
                        padding:0.8rem 8rem 0.8rem 1.2rem; border:1px solid #d1d3e2;">

                    <button type="button"
                        class="btn btn-info font-weight-bold openModal"
                        data-index="${nikIndex}"
                        style="position:absolute; top:50%; right:20px;
                        transform:translateY(-50%);
                        border-radius:10px; font-size:0.8rem; height:30px;">
                        + DATA WARGA
                    </button>
                    <small id="status-${nikIndex}" class="text-success" style="display:none;">
                        KTP tersimpan
                    </small>
                </div>
            </div>
        `);

        removeBtn.style.display = 'inline';
    });

    /* ======================================================
    REMOVE ANGGOTA (KEEP INDEX 0)
    ====================================================== */
    removeBtn.addEventListener('click', function () {
        const rows = container.querySelectorAll('.nik-row');
        if (rows.length > 1) {
            const last = rows[rows.length - 1];
            const idx  = last.dataset.index;

            delete wargaData[idx];
            delete wargaFiles[idx];

            last.remove();
            nikIndex--;

            if (nikIndex === 0) {
                removeBtn.style.display = 'none';
            }
        }
    });

    /* ======================================================
    OPEN MODAL (PER INDEX)
    ====================================================== */
    document.addEventListener('click', function (e) {
        if (!e.target.classList.contains('openModal')) return;

        const index = e.target.dataset.index;
        document.getElementById('wargaIndex').value = index;

        const row = document.querySelector(`.nik-row[data-index="${index}"]`);
        const nikInput = row.querySelector('input[type="text"]');

        // save nik immediately
        wargaData[index] = wargaData[index] || {};
        wargaData[index].nik = nikInput.value || '';

        const modal = document.getElementById('addWargaModal');
        const data  = wargaData[index];

        modal.querySelector('[name="nama"]').value              = data.nama || '';
        modal.querySelector('[name="tempat_lahir"]').value      = data.tempat_lahir || '';
        modal.querySelector('[name="tanggal_lahir"]').value     = data.tanggal_lahir || '';
        modal.querySelector('[name="jenis_kelamin"]').value     = data.jenis_kelamin || '';
        modal.querySelector('[name="agama"]').value             = data.agama || '';
        modal.querySelector('[name="pendidikan"]').value        = data.pendidikan || '';
        modal.querySelector('[name="pekerjaan"]').value         = data.pekerjaan || '';
        modal.querySelector('[name="status_perkawinan"]').value = data.status_perkawinan || '';

        // always reset file input
        modal.querySelector('[name="dokumen_ktp"]').value = '';

        $('#addWargaModal').modal('show');
    });

    /* ======================================================
    SAVE MODAL DATA (NO PAGE RELOAD)
    ====================================================== */
    document.getElementById('saveWargaBtn').addEventListener('click', function () {
        const index = document.getElementById('wargaIndex').value;
        const modal = document.getElementById('addWargaModal');

        const row = document.querySelector(`.nik-row[data-index="${index}"]`);
        const nikInput = row.querySelector('input[type="text"]');

        wargaData[index] = {
            nik: nikInput.value,
            nama: modal.querySelector('[name="nama"]').value,
            tempat_lahir: modal.querySelector('[name="tempat_lahir"]').value,
            tanggal_lahir: modal.querySelector('[name="tanggal_lahir"]').value,
            jenis_kelamin: modal.querySelector('[name="jenis_kelamin"]').value,
            agama: modal.querySelector('[name="agama"]').value,
            pendidikan: modal.querySelector('[name="pendidikan"]').value,
            pekerjaan: modal.querySelector('[name="pekerjaan"]').value,
            status_perkawinan: modal.querySelector('[name="status_perkawinan"]').value
        };

        const fileInput = modal.querySelector('[name="dokumen_ktp"]');
        if (fileInput.files.length > 0) {
            wargaFiles[index] = fileInput.files[0];

            const statusEl = document.getElementById(`status-${index}`);
            if (statusEl) {
                statusEl.style.display = 'block';
            }
        }

        $('#addWargaModal').modal('hide');
    });

    /* ======================================================
    AUTO-FILL NAMA (ONLY KEPALA KELUARGA)
    ====================================================== */
    document.addEventListener('DOMContentLoaded', function () {

        const kepalaInput = document.getElementById('kepalaKeluargaInput');

        function autofillIfKepala() {
            const idx = document.getElementById('wargaIndex')?.value;
            if (idx !== '0') return;

            const modalNama = document.querySelector('#addWargaModal input[name="nama"]');
            if (modalNama) modalNama.value = kepalaInput.value;
        }

        kepalaInput.addEventListener('input', autofillIfKepala);

        $('#addWargaModal').on('shown.bs.modal', autofillIfKepala);
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // TARGET ONLY KELUARGA FORM
    const keluargaForm = document.querySelector('form[method="POST"]:not(#wargaForm)');

    keluargaForm.addEventListener('submit', function () {

        keluargaForm.querySelectorAll('.warga-hidden').forEach(el => el.remove());

        Object.keys(wargaData).forEach(index => {

            const data = wargaData[index];

            // TEXT FIELDS
            for (const key in data) {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = `warga[${index}][${key}]`;
                input.value = data[key];
                input.classList.add('warga-hidden');
                keluargaForm.appendChild(input);
            }

            // FILE (KTP)
            if (wargaFiles[index]) {
                const fileInput = document.createElement('input');
                fileInput.type  = 'file';
                fileInput.name  = `warga_file[${index}]`;
                fileInput.classList.add('warga-hidden');

                const dt = new DataTransfer();
                dt.items.add(wargaFiles[index]);
                fileInput.files = dt.files;

                keluargaForm.appendChild(fileInput);
            }
        });

    });

});
</script>


</body>
</html>
