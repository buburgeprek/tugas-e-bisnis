<?php
include 'koneksi.php';

echo "<h2>Membuat Akun Admin</h2>";

// Data admin
$nama_admin = "Administrator ku";
$email = "admin@gmail.com";
$username = "adminku";
$password = "admin12345678"; 
$role = "admin";

// Hash password
$hash = password_hash($password, PASSWORD_DEFAULT);

// Query insert
$sql = "INSERT INTO admin (nama_admin, email, username, password, role) 
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssss", $nama_admin, $email, $username, $hash, $role);

if ($stmt->execute()) {
    echo "<p style='color: green;'>✓ Admin berhasil dibuat!</p>";
    echo "<p><strong>Login details:</strong></p>";
    echo "<p>Email: " . $email . "</p>";
    echo "<p>Username: " . $username . "</p>";
    echo "<p>Password: " . $password . "</p>";
    echo "<p>Role: " . $role . "</p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

$stmt->close();
$conn->close();
?>