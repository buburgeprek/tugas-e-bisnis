<?php
session_start();

// Include koneksi database
include 'koneksi.php';

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fungsi untuk menambah produk ke keranjang
if (isset($_POST['add_to_cart'])) {
    // Jika belum login, redirect ke halaman login
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_message'] = "Silakan login terlebih dahulu untuk menambahkan produk ke keranjang";
        header("Location: masuk.php");
        exit();
    }
    
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = (float)$_POST['product_price'];
    $product_image = $_POST['product_image'];
    
    // Cek apakah produk sudah ada di keranjang
    $found = false;
    foreach ($_SESSION['cart'] as $index => &$item) {
        if ($item['id'] == $product_id) {
            $item['jumlah'] += 1; // Tambah jumlah jika sudah ada
            $found = true;
            break;
        }
    }
    
    // Jika produk belum ada, tambahkan sebagai item baru
    if (!$found) {
        $_SESSION['cart'][] = [
            'id' => $product_id,
            'nama' => $product_name,
            'harga' => $product_price,
            'gambar' => $product_image,
            'jumlah' => 1
        ];
    }
    
    $_SESSION['message'] = "✅ " . $product_name . " berhasil ditambahkan ke keranjang!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Query untuk mendapatkan produk favorit (produk dengan status tersedia)
$query_produk = "SELECT 
    p.id_produk, 
    p.nama_produk, 
    p.harga, 
    p.harga_setelah_diskon,
    p.diskon,
    p.gambar_produk, 
    p.deskripsi_produk,
    p.stok,
    p.status_produk,
    p.kategori,
    COALESCE(AVG(u.rating), 0) as rating_rata_rata,
    COUNT(u.id_ulasan) as total_ulasan
FROM produk p
LEFT JOIN ulasan_produk u ON p.id_produk = u.id_produk AND u.status_ulasan = 'disetujui'
WHERE p.status_produk = 'tersedia' 
AND p.stok > 0
GROUP BY p.id_produk
ORDER BY p.created_at DESC 
LIMIT 6";

$result_produk = mysqli_query($conn, $query_produk);
$produk_favorit = [];

if ($result_produk) {
    while ($row = mysqli_fetch_assoc($result_produk)) {
        $produk_favorit[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Beranda - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
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

        /* Auth Buttons */
        .auth-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .btn-login {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-login:hover {
            background: rgba(255,255,255,0.3);
        }

        .btn-register {
            background: white;
            color: #d37b2c;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-register:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
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
            min-width: 180px;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .profile-dropdown.active .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
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
            color: #d37b2c;
            padding-left: 20px;
        }

        .dropdown-item.profile-link,
        .dropdown-item.logout-link {
            color: #333;
        }

        .dropdown-item.profile-link:hover,
        .dropdown-item.logout-link:hover {
            color: #d37b2c;
        }

        .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }

        /* Notification Styles */
        .notification {
            background: #d4edda;
            color: #155724;
            padding: 12px 15px;
            margin: 10px 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #c3e6cb;
            animation: slideDown 0.3s ease;
        }

        .notification.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 120px 20px;
            margin-top: 0;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .hero p {
            font-size: 20px;
            margin-bottom: 30px;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn {
            background: #d37b2c;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn:hover {
            background: #b36622;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-cart {
            background: #28a745;
        }

        .btn-cart:hover {
            background: #218838;
        }

        .btn-cart:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        /* Menu Favorit Section */
        .menu-fav {
            padding: 80px 20px;
            text-align: center;
            background: #f9f5f0;
        }

        .menu-fav h2 {
            color: #333;
            margin-bottom: 50px;
            font-size: 36px;
            position: relative;
            display: inline-block;
        }

        .menu-fav h2:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #d37b2c;
            margin: 15px auto;
            border-radius: 2px;
        }

        .menu-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
        }

        .menu-item {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            width: 280px;
            transition: all 0.3s ease;
            position: relative;
        }

        .menu-item:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        .menu-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .menu-item h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .menu-item p {
            color: #d37b2c;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 20px;
        }

        /* Price Styles */
        .price-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 15px;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 14px;
        }

        .discount-price {
            color: #d37b2c;
            font-weight: bold;
            font-size: 18px;
        }

        .discount-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .stock-info {
            font-size: 12px;
            color: #666;
            margin-bottom: 10px;
        }

        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
        }

        /* Rating Styles */
        .rating-container {
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .star {
            color: #ddd;
            font-size: 16px;
        }

        .star.filled {
            color: #ffc107;
        }

        .star.half-filled {
            position: relative;
            color: #ddd;
        }

        .star.half-filled::before {
            content: '★';
            position: absolute;
            left: 0;
            width: 50%;
            overflow: hidden;
            color: #ffc107;
        }

        .rating-text {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
        }

        .no-rating {
            font-size: 12px;
            color: #999;
            font-style: italic;
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

        /* Login Prompt */
        .login-prompt {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            margin: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #ffeaa7;
        }

        .login-prompt a {
            color: #d37b2c;
            font-weight: bold;
            text-decoration: none;
        }

        .login-prompt a:hover {
            text-decoration: underline;
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

            .auth-buttons {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                justify-content: center;
            }

            .profile-dropdown {
                margin-left: 0;
            }

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 18px;
            }

            .menu-container {
                gap: 20px;
            }

            .menu-item {
                width: 100%;
                max-width: 300px;
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
            <li><a href="beranda.php" class="active"><i class="fas fa-home"></i> Beranda</a></li>
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
             <li><a href="ulasan_public.php"> <i class="fas fa-star"></i> Ulasan Produk</a></li>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Profile Dropdown (muncul hanya jika login) -->
                <li class="profile-dropdown" id="profileDropdown">
                    <div class="profile-toggle">
                        <img src="images/profil.jpeg" alt="User" class="profile-img">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item profile-link">
                            <i class="fas fa-user"></i>
                            Profil Saya
                        </a>
                        <a href="rating_produk.php" class="dropdown-item profile-link">
                           <i class="fas fa-star"></i>
                           Rating Saya
                        </a>
                         <a href="bukti_transaksi.php" class="dropdown-item profile-link">
                             <i class="fas fa-receipt"></i>
                           Lihat Tranksasi Saya
                        </a>
                         <a href="lacak_pengiriman.php" class="dropdown-item profile-link">
                             <i class="fa-solid fa-map-location-dot"></i>
                           Lihat Pengiriman
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>

                    </div>
                </li>
            <?php else: ?>
                <!-- Auth Buttons (muncul hanya jika belum login) -->
                <div class="auth-buttons">
                    <a href="masuk.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="notification">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['redirect_message'])): ?>
        <div class="notification warning">
            <i class="fas fa-exclamation-triangle"></i> <?= $_SESSION['redirect_message'] ?>
        </div>
        <?php unset($_SESSION['redirect_message']); ?>
    <?php endif; ?>

    <!-- Hero Section -->
    <section class="hero">
        <h1>Baked with love, served with joy</h1>
        <p>Soft inside, smile outside...
            <?php if (isset($_SESSION['user_id'])): ?>
                Selamat datang kembali, <?= htmlspecialchars($_SESSION['user_name']); ?>!
            <?php else: ?>
                Temukan roti terbaik dengan cita rasa tradisional
            <?php endif; ?>
        </p>
        <a href="menu.php" class="btn">Lihat Semua Menu</a>
        <?php if (!isset($_SESSION['user_id'])): ?>
            
        <?php endif; ?>
    </section>

    <!-- Menu Favorit Section -->
    <section class="menu-fav">
        <h2>Menu Favorit Kami</h2>
        
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="login-prompt">
                <i class="fas fa-info-circle"></i> 
                Silakan <a href="masuk.php">login</a> terlebih dahulu untuk menambahkan produk ke keranjang
            </div>
        <?php endif; ?>
        
        <div class="menu-container">
            <?php if (!empty($produk_favorit)): ?>
                <?php foreach ($produk_favorit as $produk): ?>
                    <div class="menu-item">
                        <?php if ($produk['diskon'] > 0): ?>
                            <div class="discount-badge">-<?= $produk['diskon'] ?>%</div>
                        <?php endif; ?>
                        
                        <img src="<?= htmlspecialchars($produk['gambar_produk'] ?? 'images/default-product.jpg') ?>" 
                             alt="<?= htmlspecialchars($produk['nama_produk']) ?>"
                             onerror="this.src='images/default-product.jpg'">
                        
                        <h3><?= htmlspecialchars($produk['nama_produk']) ?></h3>
                        
                        <!-- Rating Stars -->
                        <div class="rating-container">
                            <?php
                            $rating = floatval($produk['rating_rata_rata']);
                            $total_ulasan = intval($produk['total_ulasan']);
                            
                            if ($total_ulasan > 0) {
                                // Tampilkan bintang berdasarkan rating
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= floor($rating)) {
                                        // Bintang penuh
                                        echo '<i class="fas fa-star star filled"></i>';
                                    } elseif ($i == ceil($rating) && $rating - floor($rating) >= 0.5) {
                                        // Bintang setengah
                                        echo '<i class="fas fa-star-half-alt star filled"></i>';
                                    } else {
                                        // Bintang kosong
                                        echo '<i class="far fa-star star"></i>';
                                    }
                                }
                                echo '<span class="rating-text">(' . number_format($rating, 1) . ') ' . $total_ulasan . ' ulasan</span>';
                            } else {
                                // Jika belum ada rating
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<i class="far fa-star star"></i>';
                                }
                                echo '<span class="no-rating">Belum ada rating</span>';
                            }
                            ?>
                        </div>
                        
                        <div class="price-container">
                            <?php if ($produk['diskon'] > 0 && $produk['harga_setelah_diskon'] > 0): ?>
                                <span class="original-price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></span>
                                <span class="discount-price">Rp <?= number_format($produk['harga_setelah_diskon'], 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="discount-price">Rp <?= number_format($produk['harga'], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stock-info <?= $produk['stok'] == 0 ? 'out-of-stock' : '' ?>">
                            Stok: <?= $produk['stok'] ?> <?= $produk['kategori'] ? " | {$produk['kategori']}" : '' ?>
                        </div>
                        
                        <?php if ($produk['stok'] > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= $produk['id_produk'] ?>">
                                <input type="hidden" name="product_name" value="<?= htmlspecialchars($produk['nama_produk']) ?>">
                                <input type="hidden" name="product_price" value="<?= $produk['harga_setelah_diskon'] > 0 ? $produk['harga_setelah_diskon'] : $produk['harga'] ?>">
                                <input type="hidden" name="product_image" value="<?= htmlspecialchars($produk['gambar_produk'] ?? 'images/default-product.jpg') ?>">
                                <button type="submit" name="add_to_cart" class="btn btn-cart" <?= !isset($_SESSION['user_id']) ? 'disabled' : '' ?>>
                                    <i class="fas fa-cart-plus"></i> 
                                    <?= isset($_SESSION['user_id']) ? 'Tambah ke Keranjang' : 'Login Dulu' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-cart" disabled style="background: #6c757d;">
                                <i class="fas fa-times"></i> Stok Habis
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; width: 100%; padding: 40px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #d37b2c; margin-bottom: 20px;"></i>
                    <h3 style="color: #666;">Belum ada produk tersedia</h3>
                    <p style="color: #999;">Silakan kembali lagi nanti</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        // Toggle dropdown profile
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown) {
            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                this.classList.toggle('active');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            if (profileDropdown) {
                profileDropdown.classList.remove('active');
            }
        });

        // Prevent dropdown from closing when clicking inside
        const dropdownMenu = document.querySelector('.dropdown-menu');
        if (dropdownMenu) {
            dropdownMenu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Auto hide notification after 3 seconds
        setTimeout(function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 3000);
    </script>
</body>
</html>

<?php
// Tutup koneksi database
mysqli_close($conn);
?>