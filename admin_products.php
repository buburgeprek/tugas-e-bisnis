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

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $nama_produk = trim($_POST['nama_produk']);
    $harga = (float)$_POST['harga'];
    $stok = (int)$_POST['stok'];
    $id_kategori = (int)$_POST['id_kategori'];
    $deskripsi_produk = trim($_POST['deskripsi_produk']);
    $diskon = (float)$_POST['diskon'];
    $expired_date = $_POST['expired_date'] ?: NULL;
    
    // Hitung harga setelah diskon
    $harga_setelah_diskon = $harga - ($harga * ($diskon / 100));
    
    // Tentukan status berdasarkan stok
    $status_produk = ($stok > 0) ? 'tersedia' : 'habis';
    
    // Handle file upload
    $gambar_produk = '';
    if (isset($_FILES['gambar_produk']) && $_FILES['gambar_produk']['error'] === 0) {
        $uploadDir = 'images/uploads/roti/';
        
        // Buat folder jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['gambar_produk']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;
        
        // Validasi file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            if (move_uploaded_file($_FILES['gambar_produk']['tmp_name'], $uploadFile)) {
                $gambar_produk = $uploadFile;
            } else {
                $error = "Gagal mengupload gambar.";
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, GIF, atau WEBP.";
        }
    }
    
    if (empty($error)) {
        $stmt = $conn->prepare("INSERT INTO produk (nama_produk, harga, stok, id_kategori, deskripsi_produk, gambar_produk, status_produk, diskon, harga_setelah_diskon, expired_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdiisssdds", $nama_produk, $harga, $stok, $id_kategori, $deskripsi_produk, $gambar_produk, $status_produk, $diskon, $harga_setelah_diskon, $expired_date);
        
        if ($stmt->execute()) {
            $message = "Produk berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan produk: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Update Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $id_produk = (int)$_POST['id_produk'];
    $nama_produk = trim($_POST['nama_produk']);
    $harga = (float)$_POST['harga'];
    $stok = (int)$_POST['stok'];
    $id_kategori = (int)$_POST['id_kategori'];
    $deskripsi_produk = trim($_POST['deskripsi_produk']);
    $diskon = (float)$_POST['diskon'];
    $expired_date = $_POST['expired_date'] ?: NULL;
    
    // Hitung harga setelah diskon
    $harga_setelah_diskon = $harga - ($harga * ($diskon / 100));
    
    // Tentukan status berdasarkan stok
    $status_produk = ($stok > 0) ? 'tersedia' : 'habis';
    
    // Handle file upload jika ada gambar baru
    $gambar_produk = $_POST['current_image'];
    if (isset($_FILES['gambar_produk']) && $_FILES['gambar_produk']['error'] === 0) {
        $uploadDir = 'images/uploads/roti/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['gambar_produk']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            if (move_uploaded_file($_FILES['gambar_produk']['tmp_name'], $uploadFile)) {
                // Hapus gambar lama hanya jika ada gambar baru yang berhasil diupload
                if (!empty($_POST['current_image']) && 
                    file_exists($_POST['current_image']) && 
                    $_POST['current_image'] !== '' &&
                    strpos($_POST['current_image'], 'images/uploads/roti/') !== false) {
                    unlink($_POST['current_image']);
                }
                $gambar_produk = $uploadFile;
            } else {
                $error = "Gagal mengupload gambar.";
            }
        } else {
            $error = "Format file tidak didukung.";
        }
    }
    
    if (empty($error)) {
        // Selalu update semua field termasuk gambar (meskipun tidak berubah)
        $stmt = $conn->prepare("UPDATE produk SET nama_produk=?, harga=?, stok=?, id_kategori=?, deskripsi_produk=?, gambar_produk=?, status_produk=?, diskon=?, harga_setelah_diskon=?, expired_date=? WHERE id_produk=?");
        $stmt->bind_param("sdiisssddsi", $nama_produk, $harga, $stok, $id_kategori, $deskripsi_produk, $gambar_produk, $status_produk, $diskon, $harga_setelah_diskon, $expired_date, $id_produk);
        
        if ($stmt->execute()) {
            $message = "Produk berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate produk: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle Delete Product
if (isset($_GET['delete_id'])) {
    $id_produk = (int)$_GET['delete_id'];
    
    // Ambil path gambar untuk dihapus
    $stmt = $conn->prepare("SELECT gambar_produk FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id_produk);
    $stmt->execute();
    $stmt->bind_result($gambar_produk);
    $stmt->fetch();
    $stmt->close();
    
    // Hapus gambar fisik jika ada
    if (!empty($gambar_produk) && file_exists($gambar_produk)) {
        unlink($gambar_produk);
    }
    
    // Hapus dari database
    $stmt = $conn->prepare("DELETE FROM produk WHERE id_produk = ?");
    $stmt->bind_param("i", $id_produk);
    
    if ($stmt->execute()) {
        $message = "Produk berhasil dihapus!";
    } else {
        $error = "Gagal menghapus produk: " . $conn->error;
    }
    $stmt->close();
}

// Ambil data produk dengan join kategori
$query = "SELECT p.*, k.nama_kategori 
          FROM produk p 
          LEFT JOIN kategori k ON p.id_kategori = k.id_kategori 
          ORDER BY p.created_at DESC";
$result = $conn->query($query);

// Ambil data kategori untuk dropdown
$kategori_result = $conn->query("SELECT * FROM kategori ORDER BY nama_kategori");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Produk - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }
        .table-rounded {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .table-rounded th {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 15px;
        }
        .table-rounded td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-tersedia {
            background: #d4edda;
            color: #155724;
        }
        .status-habis {
            background: #f8d7da;
            color: #721c24;
        }
        .action-buttons .btn {
            margin: 2px;
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        .form-required::after {
            content: " *";
            color: #dc3545;
        }
        .price-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9em;
        }
        .price-discount {
            color: #dc3545;
            font-weight: bold;
            font-size: 1.1em;
        }
        .discount-badge {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .expired-soon {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }
        .expired {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
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
                <a class="nav-link active" href="admin_products.php"><i class="fas fa-box"></i> Produk</a>
                <a class="nav-link" href="admin_shipping.php"><i class="fas fa-shipping-fast"></i> Pengiriman</a>
                <a class="nav-link" href="admin_orders.php"><i class="fas fa-tags"></i> Kategori</a>
                 <a class="nav-link" href="admin_orders.php"><i class="fas fa-clipboard-list"></i>Pesanan</a>
                <a class="nav-link" href="admin_kategori.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-5 fw-bold"><i class="fas fa-box me-3"></i>Manajemen Produk</h1>
                    <p class="lead mb-0">Kelola katalog produk Baker Old</p>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Produk
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

    <!-- Products Table -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-rounded">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Diskon</th>
                                <th>Harga Setelah Diskon</th>
                                <th>Stok</th>
                                <th>Kategori</th>
                                <th>Status</th>
                                <th>Tanggal Buat</th>
                                <th>Expired Date</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $today = date('Y-m-d');
                                    $expired_date = $row['expired_date'];
                                    $is_expired = $expired_date && $expired_date < $today;
                                    $is_expiring_soon = $expired_date && $expired_date > $today && (strtotime($expired_date) - strtotime($today)) <= 7 * 24 * 60 * 60;
                                ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($row['gambar_produk']) && file_exists($row['gambar_produk'])): ?>
                                                <img src="<?= $row['gambar_produk'] ?>" alt="<?= $row['nama_produk'] ?>" class="product-img">
                                            <?php else: ?>
                                                <div class="product-img bg-light d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama_produk']) ?></strong>
                                            <?php if (!empty($row['deskripsi_produk'])): ?>
                                                <br><small class="text-muted"><?= substr(htmlspecialchars($row['deskripsi_produk']), 0, 50) ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                            <?php if ($row['diskon'] > 0): ?>
                                                <small class="text-success">-<?= $row['diskon'] ?>%</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['diskon'] > 0): ?>
                                                <span class="badge bg-danger"><?= $row['diskon'] ?>%</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['diskon'] > 0): ?>
                                                <div class="price-original">Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                                <div class="price-discount">Rp <?= number_format($row['harga_setelah_diskon'], 0, ',', '.') ?></div>
                                            <?php else: ?>
                                                <div>Rp <?= number_format($row['harga'], 0, ',', '.') ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?= $row['stok'] > 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= $row['stok'] ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($row['nama_kategori'] ?? '-') ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $row['status_produk'] ?>">
                                                <?= ucfirst($row['status_produk']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= date('d M Y', strtotime($row['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($expired_date): ?>
                                                <?php if ($is_expired): ?>
                                                    <span class="expired">
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                        <?= date('d M Y', strtotime($expired_date)) ?>
                                                    </span>
                                                <?php elseif ($is_expiring_soon): ?>
                                                    <span class="expired-soon">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?= date('d M Y', strtotime($expired_date)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <small><?= date('d M Y', strtotime($expired_date)) ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editProductModal"
                                                    onclick="editProduct(
                                                        <?= $row['id_produk'] ?>,
                                                        '<?= addslashes($row['nama_produk']) ?>',
                                                        <?= $row['harga'] ?>,
                                                        <?= $row['stok'] ?>,
                                                        <?= $row['id_kategori'] ?>,
                                                        '<?= addslashes($row['deskripsi_produk']) ?>',
                                                        '<?= $row['gambar_produk'] ?>',
                                                        <?= $row['diskon'] ?>,
                                                        '<?= $row['expired_date'] ?>'
                                                    )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete_id=<?= $row['id_produk'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Yakin ingin menghapus produk ini?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Belum ada produk. Tambah produk pertama Anda!</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="fas fa-plus me-2"></i>Tambah Produk Pertama
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="addProductForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Produk Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label form-required">Nama Produk</label>
                                    <input type="text" class="form-control" name="nama_produk" required maxlength="100">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-required">Harga</label>
                                    <input type="number" class="form-control" name="harga" id="add_harga" min="0" step="100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Diskon (%)</label>
                                    <input type="number" class="form-control" name="diskon" id="add_diskon" min="0" max="100" step="0.01" value="0">
                                    <div class="form-text">Masukkan persentase diskon (contoh: 10 untuk 10%)</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Harga Setelah Diskon</label>
                                    <input type="text" class="form-control" id="add_harga_setelah_diskon" readonly style="background-color: #f8f9fa;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-required">Stok</label>
                                    <input type="number" class="form-control" name="stok" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label form-required">Kategori</label>
                                    <select class="form-select" name="id_kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php if ($kategori_result && $kategori_result->num_rows > 0): ?>
                                            <?php while ($kategori = $kategori_result->fetch_assoc()): ?>
                                                <option value="<?= $kategori['id_kategori'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                            <?php endwhile; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Expired Date</label>
                                    <input type="date" class="form-control" name="expired_date" min="<?= date('Y-m-d') ?>">
                                    <div class="form-text">Opsional. Kosongkan jika produk tidak ada expired date.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Gambar Produk</label>
                                    <input type="file" class="form-control" name="gambar_produk" accept="image/*" id="addImageInput">
                                    <div class="form-text">Format: JPG, JPEG, PNG, GIF, WEBP. Maks 2MB.</div>
                                    <div id="addImagePreview" class="mt-2"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi Produk</label>
                                    <textarea class="form-control" name="deskripsi_produk" rows="3" placeholder="Deskripsi produk..." maxlength="500"></textarea>
                                    <div class="form-text">Maksimal 500 karakter.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_product" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="editProductForm">
                    <input type="hidden" name="id_produk" id="edit_id_produk">
                    <input type="hidden" name="current_image" id="edit_current_image">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Produk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label form-required">Nama Produk</label>
                                    <input type="text" class="form-control" name="nama_produk" id="edit_nama_produk" required maxlength="100">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-required">Harga</label>
                                    <input type="number" class="form-control" name="harga" id="edit_harga" min="0" step="100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Diskon (%)</label>
                                    <input type="number" class="form-control" name="diskon" id="edit_diskon" min="0" max="100" step="0.01" value="0">
                                    <div class="form-text">Masukkan persentase diskon (contoh: 10 untuk 10%)</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Harga Setelah Diskon</label>
                                    <input type="text" class="form-control" id="edit_harga_setelah_diskon" readonly style="background-color: #f8f9fa;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-required">Stok</label>
                                    <input type="number" class="form-control" name="stok" id="edit_stok" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label form-required">Kategori</label>
                                    <select class="form-select" name="id_kategori" id="edit_id_kategori" required>
                                        <option value="">Pilih Kategori</option>
                                        <?php 
                                        if ($kategori_result) {
                                            $kategori_result->data_seek(0);
                                            while ($kategori = $kategori_result->fetch_assoc()): ?>
                                                <option value="<?= $kategori['id_kategori'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                            <?php endwhile;
                                        } ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Expired Date</label>
                                    <input type="date" class="form-control" name="expired_date" id="edit_expired_date" min="<?= date('Y-m-d') ?>">
                                    <div class="form-text">Opsional. Kosongkan jika produk tidak ada expired date.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Gambar Produk</label>
                                    <input type="file" class="form-control" name="gambar_produk" accept="image/*" id="editImageInput">
                                    <div class="form-text">Biarkan kosong jika tidak ingin mengubah gambar.</div>
                                    <div id="edit_image_preview" class="mt-2 mb-2"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Deskripsi Produk</label>
                                    <textarea class="form-control" name="deskripsi_produk" id="edit_deskripsi_produk" rows="3" maxlength="500"></textarea>
                                    <div class="form-text">Maksimal 500 karakter.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_product" class="btn btn-warning">
                            <i class="fas fa-sync-alt me-2"></i>Update Produk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk menghitung harga setelah diskon
        function calculateDiscountedPrice(harga, diskon) {
            if (!harga || harga <= 0) return 'Rp 0';
            
            const hargaNum = parseFloat(harga);
            const diskonNum = parseFloat(diskon || 0);
            const hargaSetelahDiskon = hargaNum - (hargaNum * (diskonNum / 100));
            
            return 'Rp ' + hargaSetelahDiskon.toLocaleString('id-ID');
        }

        // Event listeners untuk form tambah
        document.getElementById('add_harga').addEventListener('input', updateAddDiscountPrice);
        document.getElementById('add_diskon').addEventListener('input', updateAddDiscountPrice);

        function updateAddDiscountPrice() {
            const harga = document.getElementById('add_harga').value;
            const diskon = document.getElementById('add_diskon').value;
            document.getElementById('add_harga_setelah_diskon').value = calculateDiscountedPrice(harga, diskon);
        }

        // Event listeners untuk form edit
        document.getElementById('edit_harga').addEventListener('input', updateEditDiscountPrice);
        document.getElementById('edit_diskon').addEventListener('input', updateEditDiscountPrice);

        function updateEditDiscountPrice() {
            const harga = document.getElementById('edit_harga').value;
            const diskon = document.getElementById('edit_diskon').value;
            document.getElementById('edit_harga_setelah_diskon').value = calculateDiscountedPrice(harga, diskon);
        }

        function editProduct(id, nama, harga, stok, kategori, deskripsi, gambar, diskon, expired_date) {
            document.getElementById('edit_id_produk').value = id;
            document.getElementById('edit_nama_produk').value = nama;
            document.getElementById('edit_harga').value = harga;
            document.getElementById('edit_stok').value = stok;
            document.getElementById('edit_id_kategori').value = kategori;
            document.getElementById('edit_deskripsi_produk').value = deskripsi;
            document.getElementById('edit_current_image').value = gambar;
            document.getElementById('edit_diskon').value = diskon;
            document.getElementById('edit_expired_date').value = expired_date;
            
            // Update harga setelah diskon
            updateEditDiscountPrice();
            
            // Preview gambar
            const preview = document.getElementById('edit_image_preview');
            if (gambar && gambar !== '') {
                preview.innerHTML = `<img src="${gambar}" alt="Preview" class="product-img" style="max-width: 150px;">
                                     <div class="form-text mt-1">Gambar saat ini</div>`;
            } else {
                preview.innerHTML = '<div class="text-muted"><i class="fas fa-image me-1"></i>Tidak ada gambar</div>';
            }
        }

        // Image preview untuk form tambah
        document.getElementById('addImageInput').addEventListener('change', function(e) {
            const preview = document.getElementById('addImagePreview');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="product-img">`;
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Image preview untuk form edit
        document.getElementById('editImageInput').addEventListener('change', function(e) {
            const preview = document.getElementById('edit_image_preview');
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="product-img" style="max-width: 150px;">
                                         <div class="form-text mt-1">Gambar baru</div>`;
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                // Jika file input dikosongkan, kembalikan ke gambar asli
                const currentImage = document.getElementById('edit_current_image').value;
                if (currentImage && currentImage !== '') {
                    preview.innerHTML = `<img src="${currentImage}" alt="Preview" class="product-img" style="max-width: 150px;">
                                         <div class="form-text mt-1">Gambar saat ini</div>`;
                } else {
                    preview.innerHTML = '<div class="text-muted"><i class="fas fa-image me-1"></i>Tidak ada gambar</div>';
                }
            }
        });

        // Reset form ketika modal ditutup
        document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('addProductForm').reset();
            document.getElementById('addImagePreview').innerHTML = '';
            document.getElementById('add_harga_setelah_diskon').value = '';
        });

        document.getElementById('editProductModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('editImageInput').value = '';
        });
        
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