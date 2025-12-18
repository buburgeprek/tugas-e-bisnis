<?php
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header("Location: masuk.php");
    exit();
}

// Include koneksi database
include 'koneksi.php';

// Ambil data user
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pelanggan = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User tidak ditemukan");
}

// Inisialisasi variabel
$errors = [];
$success = "";

// Proses update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $nama = trim($_POST['nama']);
    $email = trim($_POST['email']);
    $no_hp = trim($_POST['no_hp']);
    $alamat = trim($_POST['alamat']);
    $tipe_pengiriman = $_POST['tipe_pengiriman'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi
    if (empty($nama)) {
        $errors[] = "Nama harus diisi";
    }
    
    if (empty($email)) {
        $errors[] = "Email harus diisi";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid";
    }
    
    if (empty($no_hp)) {
        $errors[] = "Nomor HP harus diisi";
    }
    
    if ($tipe_pengiriman === 'kirim' && empty($alamat)) {
        $errors[] = "Alamat harus diisi untuk pengiriman";
    }
    
    // Validasi password jika diisi
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = "Password minimal 6 karakter";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Konfirmasi password tidak sesuai";
        }
    }
    
    // Proses upload foto
    $foto_profil = $user['foto_profil']; // Default ke foto lama
    
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'images/uploads/user/';
        
        // Buat folder jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $file = $_FILES['foto_profil'];
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        // Validasi file
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = "Format file tidak didukung. Gunakan JPG, PNG, atau GIF";
        } elseif ($file['size'] > $maxSize) {
            $errors[] = "Ukuran file terlalu besar. Maksimal 2MB";
        } else {
            // Generate nama file acak
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $randomName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $uploadPath = $uploadDir . $randomName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Hapus foto lama jika ada
                if ($user['foto_profil'] && file_exists($user['foto_profil'])) {
                    unlink($user['foto_profil']);
                }
                $foto_profil = $uploadPath;
            } else {
                $errors[] = "Gagal mengupload foto";
            }
        }
    }
    
    // Jika tidak ada error, update database
    if (empty($errors)) {
        try {
            // Prepare update query
            if (!empty($new_password)) {
                // Update dengan password baru
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE pengguna SET nama=?, email=?, no_hp=?, alamat=?, tipe_pengiriman=?, foto_profil=?, password=? WHERE id_pelanggan=?");
                $stmt->bind_param("sssssssi", $nama, $email, $no_hp, $alamat, $tipe_pengiriman, $foto_profil, $hashed_password, $user_id);
            } else {
                // Update tanpa password
                $stmt = $conn->prepare("UPDATE pengguna SET nama=?, email=?, no_hp=?, alamat=?, tipe_pengiriman=?, foto_profil=? WHERE id_pelanggan=?");
                $stmt->bind_param("ssssssi", $nama, $email, $no_hp, $alamat, $tipe_pengiriman, $foto_profil, $user_id);
            }
            
            if ($stmt->execute()) {
                // Update session
                $_SESSION['user_name'] = $nama;
                $_SESSION['user_email'] = $email;
                
                $success = "Profile berhasil diupdate!";
                
                // Refresh data user
                $stmt = $conn->prepare("SELECT * FROM pengguna WHERE id_pelanggan = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
            } else {
                $errors[] = "Terjadi kesalahan saat update database";
            }
            
        } catch (Exception $e) {
            $errors[] = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Saya - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body { 
            background: #f9f5f0; 
            font-family: 'Poppins', sans-serif; 
            margin: 0;
            padding: 0;
        }

        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, #d37b2c 0%, #b36622 100%);
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            height: 70px;
        }

        .logo-text {
            color: white;
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            display: flex;
            align-items: center;
        }

        .logo-text i {
            margin-right: 10px;
        }

        .navbar ul {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
            height: 100%;
        }

        .navbar ul li {
            margin-left: 15px;
            position: relative;
        }

        .navbar ul li a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .navbar ul li a i {
            margin-right: 8px;
        }

        .navbar ul li a:hover,
        .navbar ul li a.active {
            background-color: rgba(255,255,255,0.15);
        }

        /* Cart Counter Badge */
        .cart-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
            position: relative;
            top: -8px;
        }

        /* Profile Dropdown Styles */
        .profile-dropdown {
            position: relative;
            margin-left: auto;
        }

        .profile-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .profile-toggle:hover {
            background-color: rgba(255,255,255,0.15);
        }

        .profile-img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
        }

        .profile-name {
            color: white;
            font-weight: 500;
            margin-right: 5px;
        }

        .dropdown-arrow {
            color: white;
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .profile-dropdown.active .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            border: 1px solid #e0e0e0;
        }

        .profile-dropdown.active .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        /* PERUBAHAN UTAMA: Warna teks dropdown menjadi hitam */
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #000000 !important; /* Hitam dengan important */
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            color: #d37b2c;
        }

        .dropdown-item:hover {
            background: #f9f5f0;
            color: #000000 !important; /* Tetap hitam saat hover */
            padding-left: 20px;
        }

        .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }

        /* Profile Container */
        .profile-container { 
            max-width: 800px; 
            margin: 40px auto; 
            background: #fff; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
        }

        h2 { 
            color: #d37b2c; 
            text-align: center; 
            margin-bottom: 30px;
            font-size: 28px;
        }

        /* Message Styles */
        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Profile Header */
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-avatar {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }

        .avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #d37b2c;
        }

        .avatar-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: #d37b2c;
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .avatar-upload:hover {
            background: #b36622;
            transform: scale(1.1);
        }

        .profile-info {
            text-align: center;
        }

        .profile-name-large {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .profile-email {
            color: #666;
            font-size: 16px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #d37b2c;
            box-shadow: 0 0 0 3px rgba(211, 123, 44, 0.1);
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            margin: 0;
        }

        .file-input {
            padding: 8px 0;
        }

        .file-input-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        /* Button Styles */
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #d37b2c;
            color: white;
        }

        .btn-primary:hover {
            background: #b36622;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Password Fields */
        .password-fields {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .password-fields .form-row {
            margin-bottom: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 15px;
                flex-wrap: wrap;
                height: auto;
                min-height: 70px;
            }

            .navbar ul {
                width: 100%;
                justify-content: center;
                margin-top: 10px;
            }

            .profile-dropdown {
                margin-left: 0;
            }

            .profile-container {
                margin: 20px 15px;
                padding: 20px;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
                gap: 15px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <h2 class="logo-text">
            <i class="fas fa-bread-slice"></i>
            Baker Old
        </h2>
        <ul>
            <li><a href="beranda.php"><i class="fas fa-home"></i> Beranda</a></li>
            <li><a href="menu.php"><i class="fas fa-utensils"></i> Menu</a></li>
            <li>
                <a href="keranjang.php">
                    <i class="fas fa-shopping-cart"></i> Keranjang
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <span class="cart-badge"><?= count($_SESSION['cart']); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="tentang.php"><i class="fas fa-info-circle"></i> Tentang</a></li>
            
            <!-- Profile Dropdown -->
            <li class="profile-dropdown" id="profileDropdown">
                <div class="profile-toggle">
                    <img src="<?= !empty($user['foto_profil']) ? htmlspecialchars($user['foto_profil']) : 'images/profil.jpeg' ?>" 
                         alt="User" class="profile-img" onerror="this.src='images/profil.jpeg'">
                    <span class="profile-name"><?= htmlspecialchars($user['nama']); ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu">
                     <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        Profil Saya
                    </a>
                   <a href="rating_produk.php" class="dropdown-item">
                        <i class="fas fa-star"></i>
                         Rating Saya
                    </a>
                    <a href="lacak_pengiriman.php" class="dropdown-item">
                        <i class="fa-solid fa-map-location-dot"></i>
                        Lihat Pengiriman
                    </a>
                    <a href="bukti_transaksi.php" class="dropdown-item">
                        <i class="fas fa-receipt"></i>
                         Lihat Tranksasi Saya
                    </a>

                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <div class="profile-container">
        <h2><i class="fas fa-user-edit"></i> Edit Profil</h2>

        <!-- Pesan Notifikasi -->
        <?php if (!empty($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> 
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">
                    <img src="<?= !empty($user['foto_profil']) ? htmlspecialchars($user['foto_profil']) : 'images/profil.jpeg' ?>" 
                         alt="Foto Profil" class="avatar-img" id="avatar-preview" onerror="this.src='images/profil.jpeg'">
                    <label for="foto_profil" class="avatar-upload" title="Ubah Foto">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" name="foto_profil" id="foto_profil" accept="image/*" 
                           class="file-input" style="display: none;" onchange="previewImage(this)">
                </div>
                <div class="profile-info">
                    <div class="profile-name-large"><?= htmlspecialchars($user['nama']); ?></div>
                    <div class="profile-email"><?= htmlspecialchars($user['email']); ?></div>
                </div>
            </div>

            <!-- Informasi Pribadi -->
            <div class="form-row">
                <div class="form-group">
                    <label for="nama"><i class="fas fa-user"></i> Nama Lengkap *</label>
                    <input type="text" id="nama" name="nama" class="form-control" 
                           value="<?= htmlspecialchars($user['nama']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="no_hp"><i class="fas fa-phone"></i> Nomor HP *</label>
                    <input type="tel" id="no_hp" name="no_hp" class="form-control" 
                           value="<?= htmlspecialchars($user['no_hp']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-truck"></i> Tipe Pengiriman *</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="tipe_pengiriman" value="ambil" 
                                   <?= ($user['tipe_pengiriman'] ?? 'ambil') === 'ambil' ? 'checked' : '' ?>>
                            <span>Ambil di Lokasi</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="tipe_pengiriman" value="kirim" 
                                   <?= ($user['tipe_pengiriman'] ?? 'ambil') === 'kirim' ? 'checked' : '' ?>>
                            <span>Kirim</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="alamat"><i class="fas fa-map-marker-alt"></i> Alamat</label>
                <textarea id="alamat" name="alamat" class="form-control" rows="3" 
                          placeholder="Masukkan alamat lengkap untuk pengiriman"><?= htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                <small class="file-input-info">* Wajib diisi jika memilih pengiriman</small>
            </div>

            <!-- Ubah Password -->
            <div class="password-fields">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Ubah Password</label>
                    <small class="file-input-info">* Kosongkan jika tidak ingin mengubah password</small>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <input type="password" name="new_password" class="form-control" 
                                   placeholder="Password Baru (minimal 6 karakter)" minlength="6">
                        </div>
                        
                        <div class="form-group">
                            <input type="password" name="confirm_password" class="form-control" 
                                   placeholder="Konfirmasi Password Baru">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upload Foto -->
            <div class="form-group">
                <label for="foto_profil_visible"><i class="fas fa-image"></i> Upload Foto Profil</label>
                <input type="file" id="foto_profil_visible" name="foto_profil" 
                       accept="image/*" class="form-control file-input" onchange="previewImage(this)">
                <small class="file-input-info">Format: JPG, PNG, GIF. Maksimal 2MB</small>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="beranda.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <script>
        // Toggle dropdown profile
        const profileDropdown = document.getElementById('profileDropdown');
        
        profileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            profileDropdown.classList.remove('active');
        });

        // Prevent dropdown from closing when clicking inside
        document.querySelector('.dropdown-menu').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Preview image before upload
        function previewImage(input) {
            const preview = document.getElementById('avatar-preview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(file);
            }
        }

        // Auto hide notification after 5 seconds
        setTimeout(function() {
            const notification = document.querySelector('.message');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000);

        // Toggle alamat requirement based on delivery type
        document.querySelectorAll('input[name="tipe_pengiriman"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const alamatField = document.getElementById('alamat');
                const alamatInfo = alamatField.nextElementSibling;
                
                if (this.value === 'kirim') {
                    alamatField.required = true;
                    alamatInfo.style.color = '#dc3545';
                    alamatInfo.textContent = '* Wajib diisi untuk pengiriman';
                } else {
                    alamatField.required = false;
                    alamatInfo.style.color = '#666';
                    alamatInfo.textContent = '* Wajib diisi jika memilih pengiriman';
                }
            });
        });

        // Trigger change event on page load
        document.addEventListener('DOMContentLoaded', function() {
            const selectedRadio = document.querySelector('input[name="tipe_pengiriman"]:checked');
            if (selectedRadio) {
                selectedRadio.dispatchEvent(new Event('change'));
            }
        });

        // Show file name when selected
        document.getElementById('foto_profil_visible').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Pilih file...';
            const label = this.previousElementSibling;
            const originalText = label.textContent;
            label.innerHTML = `<i class="fas fa-image"></i> ${originalText} - ${fileName}`;
        });
    </script>
</body>
</html>