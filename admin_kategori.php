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

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $nama_kategori = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    
    // Validasi input
    if (empty($nama_kategori)) {
        $error = "Nama kategori harus diisi!";
    } else {
        // Cek apakah kategori sudah ada
        $check_stmt = $conn->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ?");
        $check_stmt->bind_param("s", $nama_kategori);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error = "Kategori dengan nama tersebut sudah ada!";
        } else {
            $stmt = $conn->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama_kategori, $deskripsi);
            
            if ($stmt->execute()) {
                $message = "Kategori berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan kategori: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Update Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $id_kategori = (int)$_POST['id_kategori'];
    $nama_kategori = trim($_POST['nama_kategori']);
    $deskripsi = trim($_POST['deskripsi']);
    
    // Validasi input
    if (empty($nama_kategori)) {
        $error = "Nama kategori harus diisi!";
    } else {
        // Cek apakah nama kategori sudah digunakan oleh kategori lain
        $check_stmt = $conn->prepare("SELECT id_kategori FROM kategori WHERE nama_kategori = ? AND id_kategori != ?");
        $check_stmt->bind_param("si", $nama_kategori, $id_kategori);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $error = "Kategori dengan nama tersebut sudah ada!";
        } else {
            $stmt = $conn->prepare("UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id_kategori = ?");
            $stmt->bind_param("ssi", $nama_kategori, $deskripsi, $id_kategori);
            
            if ($stmt->execute()) {
                $message = "Kategori berhasil diupdate!";
            } else {
                $error = "Gagal mengupdate kategori: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Handle Delete Category
if (isset($_GET['delete_id'])) {
    $id_kategori = (int)$_GET['delete_id'];
    
    // Cek apakah kategori digunakan oleh produk
    $check_stmt = $conn->prepare("SELECT COUNT(*) FROM produk WHERE id_kategori = ?");
    $check_stmt->bind_param("i", $id_kategori);
    $check_stmt->execute();
    $check_stmt->bind_result($product_count);
    $check_stmt->fetch();
    $check_stmt->close();
    
    if ($product_count > 0) {
        $error = "Tidak dapat menghapus kategori karena masih digunakan oleh " . $product_count . " produk!";
    } else {
        $stmt = $conn->prepare("DELETE FROM kategori WHERE id_kategori = ?");
        $stmt->bind_param("i", $id_kategori);
        
        if ($stmt->execute()) {
            $message = "Kategori berhasil dihapus!";
        } else {
            $error = "Gagal menghapus kategori: " . $conn->error;
        }
        $stmt->close();
    }
}

// Ambil data kategori
$query = "SELECT * FROM kategori ORDER BY created_at DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Kategori - Admin</title>
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
        .category-badge {
            background: #e9ecef;
            color: #495057;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .description-text {
            color: #6c757d;
            font-size: 0.9em;
            line-height: 1.4;
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
                <a class="nav-link active" href="admin_categories.php"><i class="fas fa-tags"></i> Kategori</a>
                <a class="nav-link" href="admin_shipping.php"><i class="fas fa-shipping-fast"></i> Pengiriman</a>
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
                    <h1 class="display-5 fw-bold"><i class="fas fa-tags me-3"></i>Manajemen Kategori</h1>
                    <p class="lead mb-0">Kelola kategori produk Baker Old</p>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-light btn-lg" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-plus-circle me-2"></i>Tambah Kategori
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

    <!-- Categories Table -->
    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-rounded">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Kategori</th>
                                <th>Deskripsi</th>
                                <th>Tanggal Dibuat</th>
                                <th>Terakhir Diupdate</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id_kategori'] ?></strong></td>
                                        <td>
                                            <span class="category-badge">
                                                <i class="fas fa-tag me-2"></i><?= htmlspecialchars($row['nama_kategori']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['deskripsi'])): ?>
                                                <div class="description-text"><?= htmlspecialchars($row['deskripsi']) ?></div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= date('d M Y H:i', strtotime($row['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <small><?= date('d M Y H:i', strtotime($row['updated_at'])) ?></small>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-sm btn-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editCategoryModal"
                                                    onclick="editCategory(
                                                        <?= $row['id_kategori'] ?>,
                                                        '<?= addslashes($row['nama_kategori']) ?>',
                                                        '<?= addslashes($row['deskripsi']) ?>'
                                                    )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?delete_id=<?= $row['id_kategori'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Yakin ingin menghapus kategori <?= addslashes($row['nama_kategori']) ?>?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Belum ada kategori. Tambah kategori pertama Anda!</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                            <i class="fas fa-plus me-2"></i>Tambah Kategori Pertama
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="addCategoryForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Tambah Kategori Baru</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label form-required">Nama Kategori</label>
                            <input type="text" class="form-control" name="nama_kategori" required maxlength="100" placeholder="Masukkan nama kategori">
                            <div class="form-text">Maksimal 100 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" rows="3" placeholder="Masukkan deskripsi kategori (opsional)" maxlength="500"></textarea>
                            <div class="form-text">Opsional. Maksimal 500 karakter.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_category" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Simpan Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="editCategoryForm">
                    <input type="hidden" name="id_kategori" id="edit_id_kategori">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Kategori</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label form-required">Nama Kategori</label>
                            <input type="text" class="form-control" name="nama_kategori" id="edit_nama_kategori" required maxlength="100">
                            <div class="form-text">Maksimal 100 karakter.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3" maxlength="500"></textarea>
                            <div class="form-text">Opsional. Maksimal 500 karakter.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_category" class="btn btn-warning">
                            <i class="fas fa-sync-alt me-2"></i>Update Kategori
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(id, nama, deskripsi) {
            document.getElementById('edit_id_kategori').value = id;
            document.getElementById('edit_nama_kategori').value = nama;
            document.getElementById('edit_deskripsi').value = deskripsi;
        }

        // Reset form ketika modal ditutup
        document.getElementById('addCategoryModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('addCategoryForm').reset();
        });

        document.getElementById('editCategoryModal').addEventListener('hidden.bs.modal', function () {
            // Reset form edit jika diperlukan
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Validasi form sebelum submit
        document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
            const namaKategori = document.querySelector('#addCategoryForm input[name="nama_kategori"]').value.trim();
            if (!namaKategori) {
                e.preventDefault();
                alert('Nama kategori harus diisi!');
            }
        });

        document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
            const namaKategori = document.getElementById('edit_nama_kategori').value.trim();
            if (!namaKategori) {
                e.preventDefault();
                alert('Nama kategori harus diisi!');
            }
        });
    </script>
</body>
</html>