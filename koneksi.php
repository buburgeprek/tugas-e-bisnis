<?php
// koneksi.php
$host = "localhost";
$user = "root"; // ganti sesuai konfigurasi MySQL kamu
$pass = "";
$db   = "project_bakerold";

$conn = new mysqli($host, $user, $pass, $db);

// set charset
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
