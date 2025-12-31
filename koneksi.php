<?php
$host = "localhost";
$db   = "DataKITA";
$user = "root";
$pass = "";
$charset = "utf8mb4";

try {
    $koneksi = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
