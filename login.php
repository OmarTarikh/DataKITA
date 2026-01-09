<?php
session_start();
require 'koneksi.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $koneksi->prepare("SELECT * FROM User WHERE Email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = "Email atau password salah";
    } else {
        $dbPassword = $user['Password'];
        $loginOk = false;

        // HASHED PASSWORD
        if (str_starts_with($dbPassword, '$2y$')) {
            if (password_verify($password, $dbPassword)) {
                $loginOk = true;
            }
        } 
        // PLAIN TEXT PASSWORD
        else {
            if ($password === $dbPassword) {
                $loginOk = true;
            }
        }

        if (!$loginOk) {
            $error = "Email atau password salah";
        } else {

            // ===== LOGIN SUCCESS =====
            $_SESSION['user_id'] = $user['Id_user'];
            $_SESSION['role']    = $user['Role'];

            // ===== ADMIN =====
            if ($user['Role'] === 'admin') {
                header("Location: index.php");
                exit;
            }

// ===== USER FLOW =====

// 1️⃣ CEK APAKAH USER SUDAH PUNYA DATA WARGA
$cekWargaUser = $koneksi->prepare("
    SELECT COUNT(*) AS total_warga
    FROM Warga
    WHERE Id_user = ?
");
$cekWargaUser->execute([$user['Id_user']]);
$wargaUser = $cekWargaUser->fetch(PDO::FETCH_ASSOC);

// ✅ JIKA SUDAH ADA WARGA → LANGSUNG MASUK
if ($wargaUser['total_warga'] > 0) {
    header("Location: indexwarga.php");
    exit;
}

// 2️⃣ CEK KELUARGA (KALAU BELUM ADA WARGA)
$cekKeluarga = $koneksi->prepare("
    SELECT No_kk, status
    FROM Keluarga
    WHERE Id_user = ?
    LIMIT 1
");
$cekKeluarga->execute([$user['Id_user']]);
$keluarga = $cekKeluarga->fetch(PDO::FETCH_ASSOC);

// ❌ BELUM ADA DATA SAMA SEKALI
if (!$keluarga) {
    header("Location: inputdata.php");
    exit;
}

// 3️⃣ ADA KELUARGA TAPI BELUM ADA WARGA
header("Location: tungguverifikasi.php");
exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Halaman Login</title>

    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-primary d-flex justify-content-center align-items-center min-vh-100">
<div class="container">
<div class="row justify-content-center">
<div class="col-xl-10 col-lg-12 col-md-9">
<div class="card o-hidden border-0 shadow-lg my-5 ">
<div class="card-body p-0">
<div class="row">

<div class="col-lg-6 d-none d-lg-flex ">
<img src="img/vector.png"
style="width: 400px; margin-left: 60px;">
</div>

<div class="col-lg-6">
<div class="p-5">
<div class="text-center">
<h1 class="h4 text-gray-900 mb-4">Masukan Akun Kamu</h1>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form class="user" method="POST">
<div class="form-group">
<input type="email" name="email" class="form-control form-control-user"
placeholder="Alamat Email..." required>
</div>
<div class="form-group">
<input type="password" name="password" class="form-control form-control-user"
placeholder="Kata Sandi..." required>
</div>
<button type="submit" class="btn btn-primary btn-user btn-block">
Login
</button>
</form>

<hr>
<div class="text-center">
<a class="small" href="register.php">Belum Punya Akun?Daftar</a>
</div>

</div>
</div>

</div>
</div>
</div>
</div>
</div>
</div>
</body>
</html>
