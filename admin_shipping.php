<?php
session_start();
include 'koneksi.php';

// Cek apakah user adalah admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: masuk.php");
    exit();
}

// Variabel untuk pesan
$message = '';
$error = '';

// Handle Create/Update Pengiriman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_shipping'])) {
    $id_pengiriman = isset($_POST['id_pengiriman']) ? (int)$_POST['id_pengiriman'] : 0;
    $id_pesanan = (int)$_POST['id_pesanan'];
    $metode_pengiriman = trim($_POST['metode_pengiriman']);
    $tanggal_kirim = trim($_POST['tanggal_kirim']);
    $nama_kurir = trim($_POST['nama_kurir']);
    $jenis_kendaraan = trim($_POST['jenis_kendaraan']);
    $nomor_kendaraan = trim($_POST['nomor_kendaraan']);
    $status_pengiriman = trim($_POST['status_pengiriman']);

    // Validasi apakah pesanan exists dan metode delivery
    $check_order = $conn->prepare("SELECT id_pesanan, metode_pengiriman FROM pesanan_baru WHERE id_pesanan = ?");
    $check_order->bind_param("i", $id_pesanan);
    $check_order->execute();
    $check_result = $check_order->get_result();
    
    if ($check_result->num_rows === 0) {
        $error = "Pesanan dengan ID #$id_pesanan tidak ditemukan!";
    } else {
        $order_data = $check_result->fetch_assoc();
        
        // Validasi metode pengiriman harus delivery
        if ($order_data['metode_pengiriman'] !== 'delivery') {
            $error = "Pesanan dengan ID #$id_pesanan bukan metode delivery! Tidak bisa dibuatkan pengiriman.";
        } else {
            if ($id_pengiriman > 0) {
                // Update existing shipping
                $stmt = $conn->prepare("UPDATE pengiriman SET 
                                        id_pesanan = ?, 
                                        metode_pengiriman = ?, 
                                        tanggal_kirim = ?, 
                                        nama_kurir = ?, 
                                        jenis_kendaraan = ?, 
                                        nomor_kendaraan = ?, 
                                        status_pengiriman = ? 
                                        WHERE id_pengiriman = ?");
                $stmt->bind_param("issssssi", $id_pesanan, $metode_pengiriman, $tanggal_kirim, $nama_kurir, $jenis_kendaraan, $nomor_kendaraan, $status_pengiriman, $id_pengiriman);
            } else {
                // Create new shipping
                $stmt = $conn->prepare("INSERT INTO pengiriman 
                                        (id_pesanan, metode_pengiriman, tanggal_kirim, nama_kurir, jenis_kendaraan, nomor_kendaraan, status_pengiriman) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", $id_pesanan, $metode_pengiriman, $tanggal_kirim, $nama_kurir, $jenis_kendaraan, $nomor_kendaraan, $status_pengiriman);
            }

            if ($stmt->execute()) {
                $message = "Data pengiriman berhasil " . ($id_pengiriman > 0 ? "diupdate" : "ditambahkan") . "!";
                
                // Update status pesanan jika pengiriman dibuat
                if ($id_pengiriman == 0) {
                    $update_order = $conn->prepare("UPDATE pesanan_baru SET status_pesanan = 'dikirim' WHERE id_pesanan = ?");
                    $update_order->bind_param("i", $id_pesanan);
                    $update_order->execute();
                    $update_order->close();
                }
            } else {
                $error = "Gagal menyimpan data pengiriman: " . $conn->error;
            }
            $stmt->close();
        }
    }
    $check_order->close();
}

// Handle Delete Pengiriman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_shipping'])) {
    $id_pengiriman = (int)$_POST['id_pengiriman'];
    
    $stmt = $conn->prepare("DELETE FROM pengiriman WHERE id_pengiriman = ?");
    $stmt->bind_param("i", $id_pengiriman);
    
    if ($stmt->execute()) {
        $message = "Data pengiriman berhasil dihapus!";
    } else {
        $error = "Gagal menghapus data pengiriman: " . $conn->error;
    }
    $stmt->close();
}

// Search functionality
$search = '';
$where_conditions = [];
$params = [];
$types = '';

// Tambahkan kondisi default untuk hanya menampilkan pesanan delivery
$where_conditions[] = "pb.metode_pengiriman = 'delivery'";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    if (is_numeric($search)) {
        $where_conditions[] = "(pg.id_pengiriman = ? OR pb.id_pesanan = ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= 'ii';
    } else {
        $where_conditions[] = "(p.nama LIKE ? OR pg.nama_kurir LIKE ? OR pg.nomor_kendaraan LIKE ? OR pib.nama_produk LIKE ?)";
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types .= 'ssss';
    }
}

// Filter by status pengiriman
if (isset($_GET['status']) && $_GET['status'] !== 'all' && !empty($_GET['status'])) {
    $where_conditions[] = "pg.status_pengiriman = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

// Build query dengan JOIN yang lengkap dan filter delivery
$query = "SELECT 
            pg.*, 
            pb.id_pesanan, 
            pb.tanggal_pesanan, 
            pb.status_pesanan,
            pb.total_harga,
            pb.metode_pengiriman as metode_pesanan,
            p.nama as nama_pengguna, 
            p.alamat as alamat_pengguna,
            p.no_hp as no_hp_pengguna,
            GROUP_CONCAT(DISTINCT pib.nama_produk SEPARATOR ', ') as produk_dipesan
          FROM pengiriman pg
          INNER JOIN pesanan_baru pb ON pg.id_pesanan = pb.id_pesanan
          INNER JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan
          LEFT JOIN pesanan_items_baru pib ON pb.id_pesanan = pib.id_pesanan
          WHERE " . implode(" AND ", $where_conditions);

$query .= " GROUP BY pg.id_pengiriman, pb.id_pesanan, p.id_pelanggan
            ORDER BY pg.tanggal_kirim DESC, pg.id_pengiriman DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get orders without shipping for dropdown - HANYA DELIVERY
$orders_without_shipping = $conn->query("
    SELECT 
        pb.id_pesanan, 
        p.nama as nama_pengguna, 
        p.alamat as alamat_pengguna,
        p.no_hp,
        pb.tanggal_pesanan,
        pb.total_harga,
        pb.metode_pengiriman as metode_pesanan,
        pb.status_pesanan,
        GROUP_CONCAT(DISTINCT pib.nama_produk SEPARATOR ', ') as produk_dipesan
    FROM pesanan_baru pb 
    INNER JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan 
    LEFT JOIN pesanan_items_baru pib ON pb.id_pesanan = pib.id_pesanan
    WHERE pb.id_pesanan NOT IN (
        SELECT COALESCE(id_pesanan, 0) 
        FROM pengiriman 
        WHERE id_pesanan IS NOT NULL
    )
    AND pb.metode_pengiriman = 'delivery'  -- FILTER HANYA DELIVERY
    AND pb.status_pesanan IN ('pending', 'diproses', 'dikirim')
    GROUP BY pb.id_pesanan, p.id_pelanggan
    ORDER BY pb.tanggal_pesanan DESC
");

// Status options
$status_pengiriman_options = ['diproses', 'dikirim', 'dalam_perjalanan', 'selesai', 'dibatalkan'];
$metode_pengiriman_options = ['gojek', 'shopeefood', 'tim_delivery_baker_old'];
$jenis_kendaraan_options = ['motor', 'mobil', 'sepeda'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pengiriman - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        .shipping-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .shipping-card:hover {
            transform: translateY(-5px);
        }
        .shipping-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-diproses { background: #fff3cd; color: #856404; }
        .status-dikirim { background: #cce7ff; color: #004085; }
        .status-dalam_perjalanan { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }
        .search-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .action-buttons .btn {
            margin: 2px;
        }
        .order-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .product-list {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #dee2e6;
        }
        .customer-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .delivery-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-shield me-2"></i>
                Admin Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-home"></i> Beranda</a>
                <a class="nav-link" href="admin_products.php"><i class="fas fa-box"></i> Produk</a>
                <a class="nav-link active" href="admin_shipping.php"><i class="fas fa-shipping-fast"></i> Pengiriman</a>
                <a class="nav-link" href="admin_orders.php"><i class="fas fa-clipboard-list"></i> Pesanan</a>
                <a class="nav-link" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-5 fw-bold"><i class="fas fa-shipping-fast me-3"></i>Manajemen Pengiriman</h1>
                    <p class="lead mb-0">Kelola pengiriman pesanan Baker Old <span class="delivery-badge">DELIVERY ONLY</span></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <button class="btn btn-light btn-lg dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-2"></i>Filter Status
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?status=all<?= $search ? '&search=' . urlencode($search) : '' ?>">Semua Status</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?status=diproses<?= $search ? '&search=' . urlencode($search) : '' ?>">Diproses</a></li>
                            <li><a class="dropdown-item" href="?status=dikirim<?= $search ? '&search=' . urlencode($search) : '' ?>">Dikirim</a></li>
                            <li><a class="dropdown-item" href="?status=dalam_perjalanan<?= $search ? '&search=' . urlencode($search) : '' ?>">Dalam Perjalanan</a></li>
                            <li><a class="dropdown-item" href="?status=selesai<?= $search ? '&search=' . urlencode($search) : '' ?>">Selesai</a></li>
                            <li><a class="dropdown-item" href="?status=dibatalkan<?= $search ? '&search=' . urlencode($search) : '' ?>">Dibatalkan</a></li>
                        </ul>
                    </div>
                    <!-- Add New Shipping Button -->
                    <button class="btn btn-success btn-lg ms-2" data-bs-toggle="modal" data-bs-target="#addShippingModal">
                        <i class="fas fa-plus me-2"></i>Tambah Pengiriman
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="container mt-4">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Search Box -->
    <div class="container">
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan ID Pengiriman, ID Pesanan, Nama Pelanggan, Produk, atau Kurir..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="admin_shipping.php" class="btn btn-secondary w-100">
                        <i class="fas fa-refresh me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Shipping List -->
    <div class="container mt-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($shipping = $result->fetch_assoc()): ?>
                <div class="card shipping-card">
                    <div class="shipping-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-1">Pengiriman #<?= $shipping['id_pengiriman'] ?></h5>
                                <p class="mb-0 text-muted">
                                    Pesanan #<?= $shipping['id_pesanan'] ?> | 
                                    <?= date('d M Y H:i', strtotime($shipping['tanggal_pesanan'])) ?>
                                    <span class="delivery-badge ms-2">DELIVERY</span>
                                </p>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="status-badge status-<?= $shipping['status_pengiriman'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $shipping['status_pengiriman'])) ?>
                                </span>
                                <div class="mt-1">
                                    <small class="text-muted">
                                        Kirim: <?= date('d M Y', strtotime($shipping['tanggal_kirim'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Info Pelanggan -->
                        <div class="customer-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-user me-2"></i>Info Pelanggan</h6>
                                    <p class="mb-1"><strong><?= htmlspecialchars($shipping['nama_pengguna']) ?></strong></p>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($shipping['alamat_pengguna']) ?></p>
                                    <p class="mb-0 text-muted"><?= $shipping['no_hp_pengguna'] ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-shopping-bag me-2"></i>Info Pesanan</h6>
                                    <p class="mb-1"><strong>Total: Rp <?= number_format($shipping['total_harga'], 0, ',', '.') ?></strong></p>
                                    <p class="mb-1">Status Pesanan: <span class="badge bg-secondary"><?= ucfirst($shipping['status_pesanan']) ?></span></p>
                                    <p class="mb-0">Metode: <span class="delivery-badge"><?= ucfirst($shipping['metode_pesanan']) ?></span></p>
                                </div>
                            </div>
                        </div>

                        <!-- Produk yang Dipesan -->
                        <?php if (!empty($shipping['produk_dipesan'])): ?>
                        <div class="product-list">
                            <h6><i class="fas fa-box me-2"></i>Produk Dipesan</h6>
                            <p class="mb-0"><?= htmlspecialchars($shipping['produk_dipesan']) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Info Pengiriman -->
                        <div class="order-info">
                            <h6><i class="fas fa-truck me-2"></i>Info Pengiriman</h6>
                            <div class="row">
                                <div class="col-md-3">
                                    <small>Metode Pengiriman:</small><br>
                                    <strong><?= ucfirst(str_replace('_', ' ', $shipping['metode_pengiriman'])) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small>Nama Kurir:</small><br>
                                    <strong><?= htmlspecialchars($shipping['nama_kurir']) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small>Jenis Kendaraan:</small><br>
                                    <strong><?= ucfirst($shipping['jenis_kendaraan']) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small>No. Kendaraan:</small><br>
                                    <strong><?= htmlspecialchars($shipping['nomor_kendaraan']) ?></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap gap-2 action-buttons">
                                    <!-- Edit Button -->
                                    <button class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#addShippingModal"
                                            onclick="editShipping(
                                                <?= $shipping['id_pengiriman'] ?>,
                                                <?= $shipping['id_pesanan'] ?>,
                                                '<?= $shipping['metode_pengiriman'] ?>',
                                                '<?= $shipping['tanggal_kirim'] ?>',
                                                '<?= $shipping['nama_kurir'] ?>',
                                                '<?= $shipping['jenis_kendaraan'] ?>',
                                                '<?= $shipping['nomor_kendaraan'] ?>',
                                                '<?= $shipping['status_pengiriman'] ?>'
                                            )">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>

                                    <!-- Delete Button -->
                                    <button class="btn btn-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteShippingModal"
                                            onclick="setDeleteShippingId(<?= $shipping['id_pengiriman'] ?>)">
                                        <i class="fas fa-trash-alt me-1"></i> Hapus
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-shipping-fast fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Tidak ada data pengiriman</h4>
                    <p class="text-muted">
                        <?= $search ? 'Tidak ada hasil untuk "' . htmlspecialchars($search) . '"' : 'Belum ada data pengiriman untuk pesanan delivery' ?>
                    </p>
                    
                    <!-- Tampilkan pesanan yang tersedia untuk dikirim -->
                    <?php 
                    $available_orders = $conn->query("SELECT COUNT(*) as available FROM pesanan_baru WHERE status_pesanan IN ('diproses', 'dikirim') AND metode_pengiriman = 'delivery' AND id_pesanan NOT IN (SELECT id_pesanan FROM pengiriman WHERE id_pesanan IS NOT NULL)");
                    $available_count = $available_orders->fetch_assoc()['available'];
                    ?>
                    
                    <?php if ($available_count > 0): ?>
                        <p class="text-success">
                            <i class="fas fa-info-circle me-2"></i>
                            Ada <?= $available_count ?> pesanan <span class="delivery-badge">DELIVERY</span> yang siap untuk dikirim
                        </p>
                    <?php else: ?>
                        <p class="text-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Tidak ada pesanan delivery yang membutuhkan pengiriman saat ini
                        </p>
                    <?php endif; ?>
                    
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addShippingModal">
                        <i class="fas fa-plus me-2"></i>Tambah Pengiriman
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal untuk Tambah/Edit Pengiriman -->
    <div class="modal fade" id="addShippingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id_pengiriman" id="editShippingId" value="0">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="shippingModalTitle">
                            <i class="fas fa-plus me-2"></i>Tambah Pengiriman Baru
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Info Pesanan yang Dipilih -->
                        <div class="alert alert-info" id="orderInfo" style="display: none;">
                            <h6><i class="fas fa-info-circle me-2"></i>Info Pesanan Terpilih <span class="delivery-badge">DELIVERY</span></h6>
                            <div id="orderDetails"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Pesanan Delivery *</label>
                                    <select class="form-select" name="id_pesanan" id="id_pesanan" required onchange="showOrderInfo(this)">
                                        <option value="">-- Pilih Pesanan Delivery --</option>
                                        <?php 
                                        if ($orders_without_shipping && $orders_without_shipping->num_rows > 0):
                                            while ($order = $orders_without_shipping->fetch_assoc()): 
                                        ?>
                                            <option value="<?= $order['id_pesanan'] ?>" 
                                                    data-nama="<?= htmlspecialchars($order['nama_pengguna']) ?>"
                                                    data-alamat="<?= htmlspecialchars($order['alamat_pengguna']) ?>"
                                                    data-nohp="<?= $order['no_hp'] ?>"
                                                    data-total="<?= $order['total_harga'] ?>"
                                                    data-produk="<?= htmlspecialchars($order['produk_dipesan']) ?>"
                                                    data-tanggal="<?= $order['tanggal_pesanan'] ?>"
                                                    data-status="<?= $order['status_pesanan'] ?>">
                                                Pesanan #<?= $order['id_pesanan'] ?> - <?= htmlspecialchars($order['nama_pengguna']) ?> 
                                                (Rp <?= number_format($order['total_harga'], 0, ',', '.') ?>)
                                            </option>
                                        <?php 
                                            endwhile;
                                        else:
                                        ?>
                                            <option value="" disabled>Tidak ada pesanan delivery yang tersedia</option>
                                        <?php endif; ?>
                                    </select>
                                    <div class="form-text">Hanya menampilkan pesanan delivery yang belum memiliki pengiriman</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Kirim *</label>
                                    <input type="date" class="form-control" name="tanggal_kirim" id="tanggal_kirim" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Metode Pengiriman *</label>
                                    <select class="form-select" name="metode_pengiriman" id="metode_pengiriman" required>
                                        <option value="">-- Pilih Metode --</option>
                                        <?php foreach ($metode_pengiriman_options as $method): ?>
                                            <option value="<?= $method ?>"><?= ucfirst(str_replace('_', ' ', $method)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Kendaraan *</label>
                                    <select class="form-select" name="jenis_kendaraan" id="jenis_kendaraan" required>
                                        <option value="">-- Pilih Kendaraan --</option>
                                        <?php foreach ($jenis_kendaraan_options as $vehicle): ?>
                                            <option value="<?= $vehicle ?>"><?= ucfirst($vehicle) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Nomor Kendaraan *</label>
                                    <input type="text" class="form-control" name="nomor_kendaraan" id="nomor_kendaraan" placeholder="Contoh: B 1234 XYZ" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Nama Kurir *</label>
                                    <input type="text" class="form-control" name="nama_kurir" id="nama_kurir" placeholder="Nama kurir/pengirim" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status Pengiriman *</label>
                                    <select class="form-select" name="status_pengiriman" id="status_pengiriman" required>
                                        <?php foreach ($status_pengiriman_options as $status): ?>
                                            <option value="<?= $status ?>"><?= ucfirst(str_replace('_', ' ', $status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="save_shipping" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk Hapus Pengiriman -->
    <div class="modal fade" id="deleteShippingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id_pengiriman" id="deleteShippingId">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus data pengiriman ini?</p>
                        <p class="text-danger">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            Tindakan ini tidak dapat dibatalkan!
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="delete_shipping" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set today's date as default for tanggal_kirim
        document.getElementById('tanggal_kirim').valueAsDate = new Date();

        // Function to show order info when selected
        function showOrderInfo(select) {
            const selectedOption = select.options[select.selectedIndex];
            const orderInfo = document.getElementById('orderInfo');
            const orderDetails = document.getElementById('orderDetails');
            
            if (selectedOption.value) {
                const nama = selectedOption.getAttribute('data-nama');
                const alamat = selectedOption.getAttribute('data-alamat');
                const nohp = selectedOption.getAttribute('data-nohp');
                const total = selectedOption.getAttribute('data-total');
                const produk = selectedOption.getAttribute('data-produk');
                const tanggal = selectedOption.getAttribute('data-tanggal');
                const status = selectedOption.getAttribute('data-status');
                
                orderDetails.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Pelanggan:</strong> ${nama}<br>
                            <strong>No HP:</strong> ${nohp}<br>
                            <strong>Alamat:</strong> ${alamat}
                        </div>
                        <div class="col-md-6">
                            <strong>Total:</strong> Rp ${parseInt(total).toLocaleString('id-ID')}<br>
                            <strong>Status:</strong> ${status}<br>
                            <strong>Produk:</strong> ${produk || 'Tidak ada data produk'}
                        </div>
                    </div>
                `;
                orderInfo.style.display = 'block';
            } else {
                orderInfo.style.display = 'none';
            }
        }

        // Function to edit shipping data
        function editShipping(id, id_pesanan, metode_pengiriman, tanggal_kirim, nama_kurir, jenis_kendaraan, nomor_kendaraan, status_pengiriman) {
            document.getElementById('editShippingId').value = id;
            document.getElementById('id_pesanan').value = id_pesanan;
            document.getElementById('id_pesanan').disabled = true; // Can't change order when editing
            document.getElementById('metode_pengiriman').value = metode_pengiriman;
            document.getElementById('tanggal_kirim').value = tanggal_kirim;
            document.getElementById('nama_kurir').value = nama_kurir;
            document.getElementById('jenis_kendaraan').value = jenis_kendaraan;
            document.getElementById('nomor_kendaraan').value = nomor_kendaraan;
            document.getElementById('status_pengiriman').value = status_pengiriman;
            
            // Hide order info when editing
            document.getElementById('orderInfo').style.display = 'none';
            
            // Change modal title
            document.getElementById('shippingModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Data Pengiriman';
            
            // Show modal
            new bootstrap.Modal(document.getElementById('addShippingModal')).show();
        }

        // Reset form when add modal is shown
        document.getElementById('addShippingModal').addEventListener('show.bs.modal', function () {
            if (document.getElementById('editShippingId').value === '0') {
                document.getElementById('id_pesanan').disabled = false;
                document.getElementById('id_pesanan').value = '';
                document.getElementById('metode_pengiriman').value = '';
                document.getElementById('tanggal_kirim').valueAsDate = new Date();
                document.getElementById('nama_kurir').value = '';
                document.getElementById('jenis_kendaraan').value = '';
                document.getElementById('nomor_kendaraan').value = '';
                document.getElementById('status_pengiriman').value = 'diproses';
                document.getElementById('shippingModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Tambah Pengiriman Baru';
                document.getElementById('orderInfo').style.display = 'none';
            }
        });

        // Set shipping ID for deletion
        function setDeleteShippingId(shippingId) {
            document.getElementById('deleteShippingId').value = shippingId;
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>