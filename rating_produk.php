<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='masuk.php';</script>";
    exit;
}

include 'koneksi.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Query untuk mengambil pesanan selesai
$stmt = $conn->prepare("
    SELECT DISTINCT
        pb.id_pesanan, 
        pb.tanggal_pesanan, 
        pib.nama_produk,
        pr.id_produk,
        pr.gambar_produk,
        pb.status_pesanan
    FROM pesanan_baru pb 
    JOIN pesanan_items_baru pib ON pb.id_pesanan = pib.id_pesanan 
    LEFT JOIN produk pr ON LOWER(pib.nama_produk) = LOWER(pr.nama_produk)
    WHERE pb.id_pelanggan = ? 
    AND pb.status_pesanan = 'selesai'
    ORDER BY pb.tanggal_pesanan DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$produk_dibeli = [];

while ($row = $result->fetch_assoc()) {
    $key = ($row['id_produk'] ? $row['id_produk'] : urlencode($row['nama_produk'])) . '_' . $row['id_pesanan'];
    $produk_dibeli[$key] = $row;
}

// Handle form submit untuk ulasan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_ulasan'])) {
    $nama_produk = $_POST['nama_produk'];
    $id_pesanan = $_POST['id_pesanan'];
    $rating = $_POST['rating'];
    $ulasan = $_POST['ulasan'];
    
    // Validasi rating
    if ($rating == '0') {
        $_SESSION['error'] = "Silakan berikan rating dengan mengklik bintang!";
        header("Location: rating_produk.php");
        exit;
    }
    
    // Cari id_produk berdasarkan nama_produk
    $stmt_find_product = $conn->prepare("SELECT id_produk FROM produk WHERE LOWER(nama_produk) = LOWER(?)");
    $stmt_find_product->bind_param("s", $nama_produk);
    $stmt_find_product->execute();
    $product_result = $stmt_find_product->get_result();
    
    if ($product_result->num_rows > 0) {
        $product_data = $product_result->fetch_assoc();
        $id_produk = $product_data['id_produk'];
        
        // Validasi apakah user benar-benar membeli produk ini
        $stmt_check = $conn->prepare("
            SELECT pb.id_pesanan 
            FROM pesanan_baru pb 
            JOIN pesanan_items_baru pib ON pb.id_pesanan = pib.id_pesanan 
            WHERE pb.id_pelanggan = ? 
            AND LOWER(pib.nama_produk) = LOWER(?)
            AND pb.id_pesanan = ?
            AND pb.status_pesanan = 'selesai'
        ");
        $stmt_check->bind_param("isi", $user_id, $nama_produk, $id_pesanan);
        $stmt_check->execute();
        $check_result = $stmt_check->get_result();
        
        if ($check_result->num_rows > 0) {
            // Cek apakah sudah ada ulasan
            $stmt_check_existing = $conn->prepare("
                SELECT id_ulasan FROM ulasan_produk 
                WHERE id_pesanan = ? AND id_produk = ? AND nama_pelanggan = ?
            ");
            $stmt_check_existing->bind_param("iis", $id_pesanan, $id_produk, $user_name);
            $stmt_check_existing->execute();
            $existing_review = $stmt_check_existing->get_result();
            
            if ($existing_review->num_rows > 0) {
                $_SESSION['error'] = "Anda sudah memberikan ulasan untuk produk ini pada pesanan yang sama.";
            } else {
                // Insert ulasan baru
                $stmt_insert = $conn->prepare("
                    INSERT INTO ulasan_produk (id_pesanan, id_produk, nama_pelanggan, rating, ulasan, status_ulasan) 
                    VALUES (?, ?, ?, ?, ?, 'disetujui')
                ");
                $stmt_insert->bind_param("iisis", $id_pesanan, $id_produk, $user_name, $rating, $ulasan);
                
                if ($stmt_insert->execute()) {
                    $_SESSION['success'] = "Ulasan berhasil dikirim! Terima kasih atas ulasan Anda.";
                } else {
                    $_SESSION['error'] = "Gagal mengirim ulasan: " . $conn->error;
                }
            }
        } else {
            $_SESSION['error'] = "Anda tidak memiliki akses untuk memberikan ulasan pada produk ini atau pesanan belum selesai.";
        }
    } else {
        $_SESSION['error'] = "Produk tidak ditemukan dalam database.";
    }
    
    header("Location: rating_produk.php");
    exit;
}

// Ambil ulasan yang sudah diberikan user
$stmt_ulasan = $conn->prepare("
    SELECT up.*, pr.nama_produk, pr.gambar_produk 
    FROM ulasan_produk up 
    LEFT JOIN produk pr ON up.id_produk = pr.id_produk 
    WHERE up.nama_pelanggan = ? 
    ORDER BY up.tanggal_ulasan DESC
");
$stmt_ulasan->bind_param("s", $user_name);
$stmt_ulasan->execute();
$ulasan_result = $stmt_ulasan->get_result();
$ulasan_saya = [];

while ($row = $ulasan_result->fetch_assoc()) {
    $ulasan_saya[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rating Produk - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            background: #f9f5f0; 
            font-family: 'Poppins', sans-serif; 
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }

        .navbar {
            background: linear-gradient(135deg, #d37b2c 0%, #b36622 100%);
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            height: 70px;
            position: sticky;
            top: 0;
            z-index: 1000;
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
            font-size: 14px;
        }

        .navbar ul li a i {
            margin-right: 8px;
        }

        .navbar ul li a:hover,
        .navbar ul li a.active {
            background-color: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

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
            font-size: 14px;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .message {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            animation: slideDown 0.3s ease;
            font-size: 14px;
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

        .section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section h2 {
            color: #d37b2c;
            margin-bottom: 20px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .product-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .product-info h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .product-info p {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .rating-stars {
            display: flex;
            gap: 2px;
            margin: 15px 0;
        }

        .star {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .star:hover,
        .star.active {
            color: #ffc107;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
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
            resize: vertical;
        }

        .form-control:focus {
            outline: none;
            border-color: #d37b2c;
            box-shadow: 0 0 0 3px rgba(211, 123, 44, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            transform: translateY(-2px);
        }

        .ulasan-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .ulasan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .ulasan-product {
            font-weight: 600;
            color: #d37b2c;
        }

        .ulasan-date {
            color: #666;
            font-size: 12px;
        }

        .ulasan-rating {
            margin: 10px 0;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #856404;
        }

        .warning-box i {
            color: #f39c12;
            margin-right: 10px;
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

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #0c5460;
        }

        .info-box i {
            color: #17a2b8;
            margin-right: 10px;
        }

        .cannot-rate-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #721c24;
            text-align: center;
        }

        .cannot-rate-box i {
            color: #dc3545;
            margin-right: 10px;
        }

        .pesanan-belanja-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .success-badge {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .text-muted {
            color: #6c757d !important;
        }

        .me-2 {
            margin-right: 0.5rem;
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
            <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
            
            <li><a href="rating_produk.php" class="active"><i class="fas fa-star"></i> Rating Produk</a></li>
            
            <!-- Profile Dropdown -->
            <li class="profile-dropdown" id="profileDropdown">
                <div class="profile-toggle">
                    <img src="images/profil.jpeg" alt="User" class="profile-img">
                    <span class="profile-name"><?= htmlspecialchars($user_name); ?></span>
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
                    <a href="bukti_transaksi.php" class="dropdown-item">
                        <i class="fas fa-receipt"></i>
                         Lihat Tranksasi Saya
                    </a>
                     <a href="lacak_pengiriman.php" class="dropdown-item">
                        <i class="fa-solid fa-map-location-dot"></i>
                        Lihat Pengiriman
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

    <div class="container">
        <!-- Pesan Notifikasi -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Section: Beri Rating Produk -->
        <div class="section">
            <h2><i class="fas fa-star"></i> Beri Rating Produk</h2>
            
            <?php if (empty($produk_dibeli)): ?>
                <div class="empty-state">
                    <i class="fas fa-shopping-bag"></i>
                    <p>Belum ada produk yang bisa di-rating</p>
                    <p class="text-muted">Rating hanya bisa diberikan untuk produk dari pesanan yang sudah selesai</p>
                    
                    <!-- Info tentang pesanan yang belum selesai -->
                    <?php 
                    // Cek apakah user memiliki pesanan yang belum selesai
                    $stmt_pending = $conn->prepare("
                        SELECT COUNT(*) as total_pending 
                        FROM pesanan_baru 
                        WHERE id_pelanggan = ? 
                        AND status_pesanan != 'selesai'
                    ");
                    $stmt_pending->bind_param("i", $user_id);
                    $stmt_pending->execute();
                    $pending_result = $stmt_pending->get_result();
                    $pending_data = $pending_result->fetch_assoc();
                    ?>
                    
                    <?php if ($pending_data['total_pending'] > 0): ?>
                        <div class="pesanan-belanja-section">
                            <h4><i class="fas fa-info-circle"></i> Anda memiliki pesanan yang sedang diproses</h4>
                            <p>Setelah pesanan Anda selesai, Anda bisa memberikan rating untuk produk yang dibeli.</p>
                            <a href="orders.php" class="btn btn-primary">
                                <i class="fas fa-clipboard-list me-2"></i>Lihat Pesanan Saya
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Silakan melakukan transaksi terlebih dahulu dan tunggu pesanan selesai</p>
                        <button class="btn btn-primary" onclick="window.location='menu.php'">
                            <i class="fas fa-utensils"></i> Belanja Sekarang
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Informasi:</strong> Anda hanya dapat memberikan ulasan untuk produk dari pesanan yang sudah selesai.
                    Ulasan Anda akan langsung terposting dan dapat dilihat oleh pelanggan lain.
                </div>

                <div class="product-grid">
                    <?php foreach ($produk_dibeli as $key => $produk): 
                        // Cek apakah sudah memberikan ulasan untuk produk ini di pesanan ini
                        $stmt_check_ulasan = $conn->prepare("
                            SELECT up.* FROM ulasan_produk up 
                            JOIN produk pr ON up.id_produk = pr.id_produk 
                            WHERE up.id_pesanan = ? 
                            AND LOWER(pr.nama_produk) = LOWER(?)
                            AND up.nama_pelanggan = ?
                        ");
                        $stmt_check_ulasan->bind_param("iss", $produk['id_pesanan'], $produk['nama_produk'], $user_name);
                        $stmt_check_ulasan->execute();
                        $sudah_ulasan = $stmt_check_ulasan->get_result()->num_rows > 0;
                    ?>
                        <div class="product-card">
                            <div class="product-header">
                                <img src="<?= !empty($produk['gambar_produk']) ? htmlspecialchars($produk['gambar_produk']) : 'images/default-product.jpg' ?>" 
                                     alt="<?= $produk['nama_produk'] ?>" class="product-img" 
                                     onerror="this.src='images/default-product.jpg'">
                                <div class="product-info">
                                    <h3><?= htmlspecialchars($produk['nama_produk']) ?></h3>
                                    <p>Dibeli: <?= date('d M Y', strtotime($produk['tanggal_pesanan'])) ?></p>
                                    <p>ID Pesanan: #<?= $produk['id_pesanan'] ?></p>
                                    <p>Status: 
                                        <span class="status-badge status-completed">
                                            <?= ucfirst($produk['status_pesanan']) ?>
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <?php if ($sudah_ulasan): ?>
                                <div style="text-align: center; padding: 10px; background: #d4edda; border-radius: 6px;">
                                    <i class="fas fa-check-circle" style="color: #28a745;"></i>
                                    <span style="color: #155724;">Sudah diberi rating</span>
                                </div>
                            <?php else: ?>
                                <form method="POST" class="rating-form">
                                    <input type="hidden" name="nama_produk" value="<?= htmlspecialchars($produk['nama_produk']) ?>">
                                    <input type="hidden" name="id_pesanan" value="<?= $produk['id_pesanan'] ?>">
                                    
                                    <div class="form-group">
                                        <label>Rating: <span class="text-danger">*</span></label>
                                        <div class="rating-stars" id="stars-<?= $key ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star" data-rating="<?= $i ?>" data-key="<?= $key ?>">
                                                    <i class="far fa-star"></i>
                                                </span>
                                            <?php endfor; ?>
                                        </div>
                                        <input type="hidden" name="rating" id="rating-<?= $key ?>" value="0" required>
                                        <small class="text-muted">Klik bintang untuk memberikan rating</small>
                                    </div>

                                    <div class="form-group">
                                        <label for="ulasan-<?= $key ?>">Ulasan (opsional):</label>
                                        <textarea name="ulasan" id="ulasan-<?= $key ?>" class="form-control" 
                                                  rows="3" placeholder="Bagikan pengalaman Anda dengan produk ini..."></textarea>
                                        <small class="text-muted">Ulasan Anda akan langsung terposting</small>
                                    </div>

                                    <button type="submit" name="submit_ulasan" class="btn btn-primary" style="width: 100%;">
                                        <i class="fas fa-paper-plane"></i> Kirim Ulasan
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section: Ulasan Saya -->
        <div class="section">
            <h2><i class="fas fa-history"></i> Ulasan Saya</h2>
            
            <?php if (empty($ulasan_saya)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-alt"></i>
                    <p>Belum ada ulasan dari Anda</p>
                    <p class="text-muted">Berikan ulasan pertama Anda untuk produk yang sudah dibeli dan pesanan selesai</p>
                </div>
            <?php else: ?>
                <?php foreach ($ulasan_saya as $ulasan): ?>
                    <div class="ulasan-card">
                        <div class="ulasan-header">
                            <span class="ulasan-product"><?= htmlspecialchars($ulasan['nama_produk']) ?></span>
                            <span class="ulasan-date"><?= date('d M Y H:i', strtotime($ulasan['tanggal_ulasan'])) ?></span>
                        </div>
                        
                        <div class="ulasan-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $ulasan['rating']): ?>
                                    <i class="fas fa-star" style="color: #ffc107;"></i>
                                <?php else: ?>
                                    <i class="far fa-star" style="color: #ddd;"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span style="margin-left: 10px; font-weight: 500;"><?= $ulasan['rating'] ?>/5</span>
                        </div>
                        
                        <?php if (!empty($ulasan['ulasan'])): ?>
                            <div class="ulasan-text">
                                <p><?= htmlspecialchars($ulasan['ulasan']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ulasan-status" style="margin-top: 10px; font-size: 12px; color: #666;">
                            <span class="success-badge">
                                <i class="fas fa-check-circle"></i> Ulasan Terposting
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Star rating system
        document.querySelectorAll('.star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                const key = this.getAttribute('data-key');
                const starsContainer = document.getElementById('stars-' + key);
                const ratingInput = document.getElementById('rating-' + key);
                
                // Update stars display
                starsContainer.querySelectorAll('.star').forEach((s, index) => {
                    const starIcon = s.querySelector('i');
                    if (index < rating) {
                        starIcon.className = 'fas fa-star';
                        s.classList.add('active');
                    } else {
                        starIcon.className = 'far fa-star';
                        s.classList.remove('active');
                    }
                });
                
                // Update hidden input
                ratingInput.value = rating;
            });
        });

        // Profile dropdown
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileDropdown) {
            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                this.classList.toggle('active');
            });

            document.addEventListener('click', function() {
                profileDropdown.classList.remove('active');
            });
        }

        // Auto hide messages
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.display = 'none';
            });
        }, 5000);

        // Form validation
        document.querySelectorAll('.rating-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const ratingInput = this.querySelector('input[name="rating"]');
                if (ratingInput.value == '0') {
                    e.preventDefault();
                    alert('Silakan berikan rating dengan mengklik bintang!');
                    return false;
                }
            });
        });
    </script>
</body>
</html>