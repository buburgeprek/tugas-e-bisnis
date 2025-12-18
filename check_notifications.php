<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$result = $conn->query("SELECT COUNT(*) as count FROM notifikasi_admin WHERE dibaca = 0");
$data = $result->fetch_assoc();

echo json_encode(['count' => $data['count'] ?? 0]);
?>