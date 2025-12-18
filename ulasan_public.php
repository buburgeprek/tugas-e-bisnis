<?php
include 'koneksi.php';

// Ambil parameter untuk filter dan sorting
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'terbaru';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;

// Build query untuk mengambil ulasan yang sudah disetujui
$where_conditions = ["up.status_ulasan = 'disetujui'"];
$params = [];
$types = '';

// Filter berdasarkan pencarian
if (!empty($search)) {
    $where_conditions[] = "(pr.nama_produk LIKE ? OR up.ulasan LIKE ? OR up.nama_pelanggan LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= 'sss';
}

// Filter berdasarkan rating
if ($rating_filter > 0) {
    $where_conditions[] = "up.rating = ?";
    $params[] = $rating_filter;
    $types .= 'i';
}

// Build query
$query = "SELECT up.*, pr.nama_produk, pr.gambar_produk 
          FROM ulasan_produk up 
          LEFT JOIN produk pr ON up.id_produk = pr.id_produk 
          WHERE " . implode(" AND ", $where_conditions);

// Sorting
switch ($sort) {
    case 'rating_tertinggi':
        $query .= " ORDER BY up.rating DESC, up.tanggal_ulasan DESC";
        break;
    case 'rating_terendah':
        $query .= " ORDER BY up.rating ASC, up.tanggal_ulasan DESC";
        break;
    case 'terlama':
        $query .= " ORDER BY up.tanggal_ulasan ASC";
        break;
    default: // terbaru
        $query .= " ORDER BY up.tanggal_ulasan DESC";
        break;
}

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$ulasan_list = [];

while ($row = $result->fetch_assoc()) {
    $ulasan_list[] = $row;
}

// Hitung statistik rating
$stats_query = "
    SELECT 
        COUNT(*) as total_ulasan,
        AVG(rating) as rata_rata,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1
    FROM ulasan_produk 
    WHERE status_ulasan = 'disetujui'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Hitung persentase untuk setiap rating
if ($stats['total_ulasan'] > 0) {
    $stats['persen_5'] = round(($stats['rating_5'] / $stats['total_ulasan']) * 100, 1);
    $stats['persen_4'] = round(($stats['rating_4'] / $stats['total_ulasan']) * 100, 1);
    $stats['persen_3'] = round(($stats['rating_3'] / $stats['total_ulasan']) * 100, 1);
    $stats['persen_2'] = round(($stats['rating_2'] / $stats['total_ulasan']) * 100, 1);
    $stats['persen_1'] = round(($stats['rating_1'] / $stats['total_ulasan']) * 100, 1);
    $stats['rata_rata'] = round($stats['rata_rata'], 1);
} else {
    $stats['persen_5'] = $stats['persen_4'] = $stats['persen_3'] = $stats['persen_2'] = $stats['persen_1'] = 0;
    $stats['rata_rata'] = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulasan Pelanggan - Baker Old</title>
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

        .navbar ul li a:hover {
            background-color: rgba(255,255,255,0.15);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header h1 {
            color: #d37b2c;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 1.1rem;
        }

        .stats-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: center;
        }

        .overall-rating {
            text-align: center;
            padding: 20px;
        }

        .rating-score {
            font-size: 3rem;
            font-weight: bold;
            color: #d37b2c;
            margin-bottom: 10px;
        }

        .rating-stars {
            display: flex;
            justify-content: center;
            gap: 2px;
            margin-bottom: 15px;
        }

        .rating-stars .star {
            font-size: 20px;
            color: #ffc107;
        }

        .total-reviews {
            color: #666;
            font-size: 0.9rem;
        }

        .rating-bars {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .rating-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rating-label {
            display: flex;
            align-items: center;
            gap: 5px;
            width: 80px;
        }

        .rating-label .star {
            color: #ffc107;
            font-size: 14px;
        }

        .bar-container {
            flex: 1;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            height: 8px;
        }

        .bar-fill {
            height: 100%;
            background: #ffc107;
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .rating-percent {
            width: 40px;
            text-align: right;
            font-size: 0.9rem;
            color: #666;
        }

        .filters-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .filter-group select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .ulasan-grid {
            display: grid;
            gap: 20px;
        }

        .ulasan-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .ulasan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .ulasan-header {
            display: flex;
            justify-content: between;
            align-items: start;
            margin-bottom: 15px;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .product-details h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .customer-name {
            color: #666;
            font-size: 14px;
        }

        .ulasan-rating {
            display: flex;
            gap: 2px;
        }

        .ulasan-rating .star {
            font-size: 16px;
            color: #ffc107;
        }

        .ulasan-date {
            color: #999;
            font-size: 12px;
            text-align: right;
        }

        .ulasan-text {
            margin-top: 15px;
        }

        .ulasan-text p {
            color: #555;
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #888;
            margin-bottom: 20px;
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

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }

        .pagination-btn {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover {
            background: #f0f0f0;
        }

        .pagination-btn.active {
            background: #d37b2c;
            color: white;
            border-color: #d37b2c;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .ulasan-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .ulasan-date {
                text-align: left;
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
            <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
            <li><a href="rating_produk.php" class="active"><i class="fas fa-star"></i> Rating Produk</a></li>
            
        </ul>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-star me-2"></i>Ulasan Pelanggan</h1>
            <p>Lihat pengalaman nyata dari pelanggan Baker Old</p>
        </div>

        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stats-grid">
                <div class="overall-rating">
                    <div class="rating-score"><?= $stats['rata_rata'] ?></div>
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($stats['rata_rata'])): ?>
                                <i class="fas fa-star star"></i>
                            <?php elseif ($i == ceil($stats['rata_rata']) && fmod($stats['rata_rata'], 1) >= 0.5): ?>
                                <i class="fas fa-star-half-alt star"></i>
                            <?php else: ?>
                                <i class="far fa-star star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <div class="total-reviews">Berdasarkan <?= $stats['total_ulasan'] ?> ulasan</div>
                </div>
                
                <div class="rating-bars">
                    <?php for ($rating = 5; $rating >= 1; $rating--): 
                        $count = $stats["rating_$rating"];
                        $percent = $stats["persen_$rating"];
                    ?>
                        <div class="rating-bar">
                            <div class="rating-label">
                                <span><?= $rating ?></span>
                                <i class="fas fa-star star"></i>
                            </div>
                            <div class="bar-container">
                                <div class="bar-fill" style="width: <?= $percent ?>%"></div>
                            </div>
                            <div class="rating-percent"><?= $percent ?>%</div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Cari ulasan, produk, atau pelanggan..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </div>
                
                <div class="filter-group">
                    <label for="rating">Filter Rating:</label>
                    <select name="rating" id="rating" onchange="this.form.submit()">
                        <option value="0" <?= $rating_filter == 0 ? 'selected' : '' ?>>Semua Rating</option>
                        <option value="5" <?= $rating_filter == 5 ? 'selected' : '' ?>>5 Bintang</option>
                        <option value="4" <?= $rating_filter == 4 ? 'selected' : '' ?>>4 Bintang</option>
                        <option value="3" <?= $rating_filter == 3 ? 'selected' : '' ?>>3 Bintang</option>
                        <option value="2" <?= $rating_filter == 2 ? 'selected' : '' ?>>2 Bintang</option>
                        <option value="1" <?= $rating_filter == 1 ? 'selected' : '' ?>>1 Bintang</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="sort">Urutkan:</label>
                    <select name="sort" id="sort" onchange="this.form.submit()">
                        <option value="terbaru" <?= $sort == 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="terlama" <?= $sort == 'terlama' ? 'selected' : '' ?>>Terlama</option>
                        <option value="rating_tertinggi" <?= $sort == 'rating_tertinggi' ? 'selected' : '' ?>>Rating Tertinggi</option>
                        <option value="rating_terendah" <?= $sort == 'rating_terendah' ? 'selected' : '' ?>>Rating Terendah</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Ulasan List -->
        <?php if (!empty($ulasan_list)): ?>
            <div class="ulasan-grid">
                <?php foreach ($ulasan_list as $ulasan): ?>
                    <div class="ulasan-card">
                        <div class="ulasan-header">
                            <div class="product-info">
                                <?php if (!empty($ulasan['gambar_produk']) && file_exists($ulasan['gambar_produk'])): ?>
                                    <img src="<?= $ulasan['gambar_produk'] ?>" alt="<?= htmlspecialchars($ulasan['nama_produk']) ?>" class="product-img">
                                <?php else: ?>
                                    <div class="product-img" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image" style="color: #ccc;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="product-details">
                                    <h3><?= htmlspecialchars($ulasan['nama_produk']) ?></h3>
                                    <div class="customer-name">
                                        <i class="fas fa-user"></i>
                                        <?= htmlspecialchars($ulasan['nama_pelanggan']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="ulasan-meta">
                                <div class="ulasan-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $ulasan['rating']): ?>
                                            <i class="fas fa-star star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <div class="ulasan-date">
                                    <?= date('d M Y', strtotime($ulasan['tanggal_ulasan'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($ulasan['ulasan'])): ?>
                            <div class="ulasan-text">
                                <p><?= htmlspecialchars($ulasan['ulasan']) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="ulasan-text">
                                <p style="color: #999; font-style: italic;">Pelanggan memberikan rating tanpa ulasan tertulis.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-comment-slash"></i>
                <h3>Tidak ada ulasan ditemukan</h3>
                <p>
                    <?php if ($search || $rating_filter > 0): ?>
                        Tidak ada ulasan yang sesuai dengan filter Anda. Coba ubah kriteria pencarian.
                    <?php else: ?>
                        Belum ada ulasan dari pelanggan. Jadilah yang pertama memberikan ulasan!
                    <?php endif; ?>
                </p>
                <?php if ($search || $rating_filter > 0): ?>
                    <a href="ulasan_public.php" class="btn btn-primary">
                        <i class="fas fa-refresh"></i> Tampilkan Semua Ulasan
                    </a>
                <?php else: ?>
                    <a href="masuk.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login untuk Beri Ulasan
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto submit form ketika input search berubah (dengan delay)
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });

        // Highlight search terms in results
        <?php if (!empty($search)): ?>
            const searchTerms = '<?= addslashes($search) ?>'.toLowerCase().split(' ');
            const elements = document.querySelectorAll('.ulasan-card h3, .ulasan-card .customer-name, .ulasan-card p');
            
            elements.forEach(element => {
                let html = element.innerHTML;
                searchTerms.forEach(term => {
                    if (term.length > 2) {
                        const regex = new RegExp(term, 'gi');
                        html = html.replace(regex, match => `<mark style="background: #fff3cd; padding: 2px 4px; border-radius: 2px;">${match}</mark>`);
                    }
                });
                element.innerHTML = html;
            });
        <?php endif; ?>
    </script>
</body>
</html>