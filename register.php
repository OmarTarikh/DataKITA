<?php
session_start();
require 'koneksi.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = $_POST['nama'];
    $email   = $_POST['email'];
    $pass    = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($pass !== $confirm) {
        $error = "Password tidak sama";
    } else {
        $cek = $koneksi->prepare("SELECT Id_user FROM User WHERE Email = ?");
        $cek->execute([$email]);

        if ($cek->rowCount() > 0) {
            $error = "Email sudah terdaftar";
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);

            $stmt = $koneksi->prepare(
                "INSERT INTO User (Nama_user, Email, Password, Role)
                 VALUES (?, ?, ?, 'user')"
            );
            $stmt->execute([$nama, $email, $hash]);

            header("Location: login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Halaman Buat Akun</title>

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

<div class="col-lg-6 d-none d-lg-flex justify-content-center">
<img src="img/vector.png"
style="width: 400px; margin-left: 60px;">
</div>

<div class="col-lg-6">
<div class="p-5">
<div class="text-center">
<h1 class="h4 text-gray-900 mb-4">Buat Akun Kamu</h1>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form class="user" method="POST">
<div class="form-group">
<input type="text" name="nama" class="form-control form-control-user"
placeholder="Nama Panjang..." required>
</div>
<div class="form-group">
<input type="email" name="email" class="form-control form-control-user"
placeholder="Alamat Email..." required>
</div>
<div class="form-group row">
<div class="col-sm-6 mb-3 mb-sm-0">
<input type="password" name="password" class="form-control form-control-user"
placeholder="Password..." required>
</div>
<div class="col-sm-6">
<input type="password" name="confirm" class="form-control form-control-user"
placeholder="Ulangi Password..." required>
</div>
</div>
<button type="submit" class="btn btn-primary btn-user btn-block">
Daftarkan Akun
</button>
<hr>
</form>

<div class="text-center">
<a class="small" href="login.php">Sudah Punya Akun?Masuk</a>
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
