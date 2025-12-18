<?php
session_start();

// Cek apakah user adalah admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: masuk.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
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
            color: black;
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
            color: #2c3e50;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
            color: #2c3e50;
            padding-left: 20px;
        }

        /* Dashboard Styles */
        .dashboard {
            padding: 40px 20px;
            background: #f8f9fa;
            min-height: calc(100vh - 70px);
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .dashboard-header h1 {
            color: #2c3e50;
            font-size: 36px;
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: #7f8c8d;
            font-size: 18px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            border-left: 5px solid #2c3e50;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .dashboard-card.products {
            border-left-color: #e74c3c;
        }

        .dashboard-card.shipping {
            border-left-color: #3498db;
        }

        .dashboard-card.orders {
            border-left-color: #27ae60;
        }

        .card-icon {
            font-size: 48px;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .dashboard-card.products .card-icon {
            color: #e74c3c;
        }

        .dashboard-card.shipping .card-icon {
            color: #3498db;
        }

        .dashboard-card.orders .card-icon {
            color: #27ae60;
        }

        .card-title {
            font-size: 24px;
            color: #2c3e50;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .card-description {
            color: #7f8c8d;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .btn {
            background: #2c3e50;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn:hover {
            background: #34495e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-products {
            background: #e74c3c;
        }

        .btn-products:hover {
            background: #c0392b;
        }

        .btn-shipping {
            background: #3498db;
        }

        .btn-shipping:hover {
            background: #2980b9;
        }

        .btn-orders {
            background: #27ae60;
        }

        .btn-orders:hover {
            background: #219a52;
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 16px;
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

            .dashboard-header h1 {
                font-size: 28px;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <h2 class="logo-text">
            <i class="fas fa-user-shield"></i>
            Admin 
        </h2>
        <ul>
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> Beranda</a></li>
            <li><a href="admin_products.php"><i class="fas fa-box"></i>  Produk</a></li>
            <li><a href="admin_shipping.php"><i class="fas fa-shipping-fast"></i> Pengiriman</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-clipboard-list"></i> Pesanan</a></li>
            <li><a href="admin_kategori.php"><i class="fas fa-tags"></i> Kategori</a></li>

            <!-- Profile Dropdown Admin -->
            <li class="profile-dropdown" id="profileDropdown">
                <div class="profile-toggle">
                    <img src="images/profil.jpeg" alt="Admin" class="profile-img">
                    <span class="profile-name"><?= htmlspecialchars($_SESSION['admin_name']); ?></span>
                    <i class="fas fa-chevron-down dropdown-arrow"></i>
                </div>
                <div class="dropdown-menu">
                    
                    <div class="dropdown-divider"></div>
                    <a href="admin_logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </li>
        </ul>
    </nav>

    <!-- Dashboard Content -->
    <section class="dashboard">
        <div class="dashboard-header">
            <h1>Selamat Datang, <?= htmlspecialchars($_SESSION['admin_name']); ?>!</h1>
            <p>Kelola toko Baker Old Anda dari dashboard admin</p>
        </div>

      

        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
    <!-- Manajemen Produk -->
    <div class="dashboard-card products">
        <div class="card-icon">
            <i class="fas fa-box"></i>
        </div>
        <h3 class="card-title">Manajemen Produk</h3>
        <p class="card-description">
            Kelola produk, stok, harga, dan kategori. Tambah, edit, atau hapus produk dari katalog.
        </p>
        <a href="admin_products.php" class="btn btn-products">
            <i class="fas fa-cog"></i> Kelola Produk
        </a>
    </div>

    <!-- Manajemen Pengiriman -->
    <div class="dashboard-card shipping">
        <div class="card-icon">
            <i class="fas fa-shipping-fast"></i>
        </div>
        <h3 class="card-title">Manajemen Pengiriman</h3>
        <p class="card-description">
            Pantau status pengiriman, atur kurir, dan kelola alamat pengiriman pelanggan.
        </p>
        <a href="admin_shipping.php" class="btn btn-shipping">
            <i class="fas fa-truck"></i> Kelola Pengiriman
        </a>
    </div>

    <!-- Manajemen Pesanan -->
    <div class="dashboard-card orders">
        <div class="card-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <h3 class="card-title">Manajemen Pesanan</h3>
        <p class="card-description">
            Lihat semua pesanan, proses pembayaran, dan update status pesanan pelanggan.
        </p>
        <a href="admin_orders.php" class="btn btn-orders">
            <i class="fas fa-list"></i> Kelola Pesanan
        </a>
    </div>

    <!-- Notifikasi Admin -->
    <div class="dashboard-card notifications">
        <div class="card-icon">
            <i class="fas fa-bell"></i>
            <?php
            // Include koneksi untuk mendapatkan jumlah notifikasi
            include 'koneksi.php';
            $notif_query = $conn->query("SELECT COUNT(*) as count FROM notifikasi_admin WHERE dibaca = 0");
            $notif_count = $notif_query->fetch_assoc()['count'];
            if ($notif_count > 0): ?>
                <span class="notification-badge-dashboard"><?= $notif_count ?></span>
            <?php endif; ?>
        </div>
        <h3 class="card-title">Notifikasi Sistem</h3>
        <p class="card-description">
            Lihat notifikasi konfirmasi dari user, pesanan baru, dan aktivitas sistem lainnya.
        </p>
        <a href="admin_notifications.php" class="btn btn-notifications">
            <i class="fas fa-bell"></i> Lihat Notifikasi
        </a>
    </div>
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
    </script>
</body>
</html>