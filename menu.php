<?php
session_start();
include 'koneksi.php';

// pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='masuk.php';</script>";
    exit;
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ambil data produk dari database dengan join kategori dan rating
$query = "SELECT p.*, k.nama_kategori,
          COALESCE(AVG(u.rating), 0) as rating_rata_rata,
          COUNT(u.id_ulasan) as total_ulasan
          FROM produk p 
          LEFT JOIN kategori k ON p.id_kategori = k.id_kategori 
          LEFT JOIN ulasan_produk u ON p.id_produk = u.id_produk AND u.status_ulasan = 'disetujui'
          WHERE p.status_produk = 'tersedia' 
          GROUP BY p.id_produk
          ORDER BY p.diskon DESC, p.created_at DESC";
$result = $conn->query($query);

// Tambahkan ke keranjang jika ada kiriman POST
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['nama'];
    $product_price = (int)$_POST['harga'];
    $product_image = $_POST['gambar'];
    
    // Cek apakah produk sudah ada di keranjang
    $found = false;
    foreach ($_SESSION['cart'] as $index => &$cart_item) {
        if ($cart_item['id'] == $product_id) {
            $cart_item['jumlah']++;
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Menu - Baker Old</title>
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

        /* Warna khusus untuk item Profil Saya dan Logout */
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

        /* Menu Header */
        .menu-header {
            text-align: center;
            padding: 40px 20px;
            background: #f9f5f0;
        }

        .menu-header h1 {
            color: #333;
            margin-bottom: 15px;
            font-size: 36px;
        }

        .menu-header p {
            color: #666;
            font-size: 18px;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Promo Banner */
        .promo-banner {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            text-align: center;
            padding: 20px;
            margin: 0 20px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .promo-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .promo-title {
            margin: 0;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .promo-title i {
            color: #ffdd59;
        }

        .countdown-container {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .countdown-title {
            font-size: 14px;
            margin-bottom: 5px;
            opacity: 0.9;
        }

        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 15px;
            font-family: 'Courier New', monospace;
        }

        .countdown-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .countdown-number {
            font-size: 24px;
            font-weight: bold;
            background: rgba(255,255,255,0.9);
            color: #ee5a24;
            padding: 5px 10px;
            border-radius: 5px;
            min-width: 40px;
            text-align: center;
        }

        .countdown-label {
            font-size: 10px;
            margin-top: 2px;
            opacity: 0.8;
            text-transform: uppercase;
        }

        /* Styling kartu menu */
        .menu-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .menu-card {
            background: #fff;
            border-radius: 12px;
            width: 280px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }

        /* Badge diskon */
        .discount-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 2;
        }

        /* Badge expired */
        .expired-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #6c757d;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            z-index: 2;
        }

        .expiring-soon-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            z-index: 2;
        }

        .menu-card img {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            object-fit: cover;
            margin-bottom: 20px;
        }

        .menu-card h3 {
            color: #333;
            margin: 10px 0 5px;
            font-size: 20px;
        }

        .menu-card .category {
            color: #d37b2c;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .menu-card p {
            font-size: 14px;
            color: #666;
            min-height: 45px;
            margin-bottom: 15px;
        }

        .price-container {
            margin-bottom: 15px;
        }

        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
            display: block;
        }

        .price {
            font-weight: bold;
            color: #d37b2c;
            display: block;
            font-size: 18px;
        }

        .discount-price {
            color: #e74c3c;
            font-weight: bold;
        }

        .after-discount {
            font-size: 12px;
            color: #27ae60;
            font-weight: bold;
            margin-top: 5px;
        }

        .promo-tag {
            background: #ffdd59;
            color: #333;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
            display: inline-block;
        }

        .product-info {
            font-size: 12px;
            color: #6c757d;
            margin-top: 10px;
            border-top: 1px solid #f0f0f0;
            padding-top: 10px;
        }

        .product-info div {
            margin-bottom: 3px;
        }

        /* Rating Styles */
        .rating-container {
            margin: 10px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }

        .star {
            color: #ddd;
            font-size: 14px;
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
            font-size: 11px;
            color: #666;
            margin-left: 5px;
        }

        .no-rating {
            font-size: 11px;
            color: #999;
            font-style: italic;
        }

        .btn {
            background: #d37b2c;
            color: #fff;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            background: #b36622;
            transform: translateY(-2px);
        }

        .btn-cart {
            background: #28a745;
        }

        .btn-cart:hover {
            background: #218838;
        }

        .btn-disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .btn-disabled:hover {
            background: #6c757d;
            transform: none;
        }

        /* Stok habis overlay */
        .out-of-stock {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
            border-radius: 12px;
            z-index: 1;
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

            .menu-header h1 {
                font-size: 28px;
            }

            .menu-header p {
                font-size: 16px;
            }

            .promo-title {
                font-size: 16px;
            }

            .countdown-number {
                font-size: 18px;
                min-width: 35px;
            }

            .countdown-timer {
                gap: 10px;
            }

            .menu-container {
                gap: 20px;
            }

            .menu-card {
                width: 100%;
                max-width: 300px;
            }
        }

        @media (max-width: 480px) {
            .promo-content {
                gap: 10px;
            }
            
            .promo-title {
                font-size: 14px;
                flex-direction: column;
                gap: 5px;
            }

            .countdown-number {
                font-size: 16px;
                min-width: 30px;
                padding: 3px 8px;
            }

            .countdown-label {
                font-size: 9px;
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
            <li><a href="menu.php" class="active"><i class="fas fa-utensils"></i> Menu</a></li>
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
            
            <!-- Profile Dropdown -->
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
        </ul>
    </nav>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="notification">
            <i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?>
        </div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <section class="menu-header">
        <h1>Selamat Datang, <?= htmlspecialchars($_SESSION['user_name']); ?> 👋</h1>
        <p>Pilih roti favoritmu dan tambahkan ke keranjang!</p>
    </section>

    <!-- Promo Banner dengan Countdown -->
    <div class="promo-banner">
        <div class="promo-content">
            <h3 class="promo-title">
                <i class="fas fa-gift"></i>
                PROMO SPESIAL: Diskon Up To 90% Untuk Semua Roti
            </h3>
            <div class="countdown-container">
                <div class="countdown-title">Berakhir dalam:</div>
                <div class="countdown-timer" id="countdown">
                    <div class="countdown-item">
                        <span class="countdown-number" id="days">00</span>
                        <span class="countdown-label">Hari</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number" id="hours">00</span>
                        <span class="countdown-label">Jam</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number" id="minutes">00</span>
                        <span class="countdown-label">Menit</span>
                    </div>
                    <div class="countdown-item">
                        <span class="countdown-number" id="seconds">00</span>
                        <span class="countdown-label">Detik</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="menu-container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($product = $result->fetch_assoc()): 
                $today = date('Y-m-d');
                $expired_date = $product['expired_date'];
                $is_expired = $expired_date && $expired_date < $today;
                $is_expiring_soon = $expired_date && $expired_date > $today && (strtotime($expired_date) - strtotime($today)) <= 7 * 24 * 60 * 60;
                $is_out_of_stock = $product['stok'] <= 0;
                
                // Tentukan harga yang akan ditampilkan
                $display_price = $product['diskon'] > 0 ? $product['harga_setelah_diskon'] : $product['harga'];
                
                // Data rating
                $rating = floatval($product['rating_rata_rata']);
                $total_ulasan = intval($product['total_ulasan']);
            ?>
            <div class="menu-card">
                <?php if ($product['diskon'] > 0): ?>
                    <div class="discount-badge">-<?= $product['diskon'] ?>%</div>
                <?php endif; ?>
                
                <?php if ($is_expired): ?>
                    <div class="expired-badge">EXPIRED</div>
                <?php elseif ($is_expiring_soon): ?>
                    <div class="expiring-soon-badge">SEGERA EXPIRED</div>
                <?php endif; ?>

                <?php if ($is_out_of_stock): ?>
                    <div class="out-of-stock">
                        <span>STOK HABIS</span>
                    </div>
                <?php endif; ?>
                
                <img src="<?= !empty($product['gambar_produk']) && file_exists($product['gambar_produk']) ? $product['gambar_produk'] : 'images/placeholder.jpg'; ?>" alt="<?= $product['nama_produk']; ?>">
                <h3><?= htmlspecialchars($product['nama_produk']); ?></h3>
                <div class="category"><?= htmlspecialchars($product['nama_kategori'] ?? 'Uncategorized'); ?></div>
                
                <!-- Rating Stars -->
                <div class="rating-container">
                    <?php
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
                
                <p><?= htmlspecialchars($product['deskripsi_produk']); ?></p>
                
                <div class="price-container">
                    <?php if ($product['diskon'] > 0): ?>
                        <span class="original-price">Rp <?= number_format($product['harga'], 0, ',', '.'); ?></span>
                        <span class="price discount-price">Rp <?= number_format($display_price, 0, ',', '.'); ?></span>
                        <span class="after-discount">Hemat Rp <?= number_format($product['harga'] - $display_price, 0, ',', '.'); ?></span>
                    <?php else: ?>
                        <span class="price">Rp <?= number_format($display_price, 0, ',', '.'); ?></span>
                    <?php endif; ?>
                    
                    <!-- Info stok -->
                    <div class="product-info">
                        <div><i class="fas fa-box"></i> Stok: <?= $product['stok']; ?> pcs</div>
                        <?php if ($expired_date): ?>
                            <div><i class="fas fa-calendar-alt"></i> Exp: <?= date('d M Y', strtotime($expired_date)); ?></div>
                        <?php endif; ?>
                        <div><i class="fas fa-layer-group"></i> Kategori: <?= htmlspecialchars($product['nama_kategori'] ?? '-'); ?></div>
                    </div>

                    <!-- Tag promo beli 3 gratis 1 untuk semua roti -->
                    <div class="promo">
                        
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="product_id" value="<?= $product['id_produk']; ?>">
                    <input type="hidden" name="nama" value="<?= htmlspecialchars($product['nama_produk']); ?>">
                    <input type="hidden" name="harga" value="<?= $display_price; ?>">
                    <input type="hidden" name="gambar" value="<?= !empty($product['gambar_produk']) ? $product['gambar_produk'] : 'images/placeholder.jpg'; ?>">
                    <button type="submit" name="add_to_cart" class="btn btn-cart <?= ($is_out_of_stock || $is_expired) ? 'btn-disabled' : ''; ?>" 
                        <?= ($is_out_of_stock || $is_expired) ? 'disabled' : ''; ?>>
                        <i class="fas fa-cart-plus"></i> 
                        <?php if ($is_out_of_stock): ?>
                            Stok Habis
                        <?php elseif ($is_expired): ?>
                            Produk Expired
                        <?php else: ?>
                            Tambah ke Keranjang
                        <?php endif; ?>
                    </button>
                </form>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; width: 100%; padding: 40px;">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <h3 style="color: #666;">Belum ada produk tersedia</h3>
                <p style="color: #999;">Silakan kembali lagi nanti</p>
            </div>
        <?php endif; ?>
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

        // Auto hide notification after 3 seconds
        setTimeout(function() {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 3000);

        // Countdown Timer untuk 10 hari
        function startCountdown() {
            // Set waktu akhir (10 hari dari sekarang)
            const countDownDate = new Date();
            countDownDate.setDate(countDownDate.getDate() + 10);
            countDownDate.setHours(23, 59, 59, 999); // Set sampai akhir hari

            // Update countdown setiap 1 detik
            const countdownFunction = setInterval(function() {
                // Waktu sekarang
                const now = new Date().getTime();
                
                // Selisih waktu antara sekarang dan waktu akhir
                const distance = countDownDate.getTime() - now;
                
                // Perhitungan waktu
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Tampilkan hasil
                document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
                document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
                document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
                document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
                
                // Jika waktu habis
                if (distance < 0) {
                    clearInterval(countdownFunction);
                    document.getElementById("countdown").innerHTML = "<div style='color:#ffdd59; font-weight: bold;'>PROMO TELAH BERAKHIR</div>";
                }
            }, 1000);
        }

        // Jalankan countdown ketika halaman dimuat
        document.addEventListener('DOMContentLoaded', startCountdown);
    </script>
</body>
</html>