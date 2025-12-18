<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: masuk.php");
    exit();
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifikasi_admin SET dibaca = 1");
    header("Location: admin_notifications.php");
    exit();
}

// Mark single as read
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $conn->query("UPDATE notifikasi_admin SET dibaca = 1 WHERE id_notifikasi = $id");
    header("Location: admin_notifications.php");
    exit();
}

// Get notifications
$notifications = $conn->query("SELECT * FROM notifikasi_admin ORDER BY created_at DESC LIMIT 50");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Notifikasi - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .notification-item.unread {
            background-color: #f0f7ff;
            border-left: 4px solid #007bff;
        }
        .notification-item.read {
            background-color: #f8f9fa;
        }
        .notification-time {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-bell"></i> Notifikasi Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_orders.php">
                    <i class="fas fa-arrow-left"></i> Kembali ke Pesanan
                </a>
                <a class="nav-link btn btn-primary text-white" href="?mark_all_read">
                    <i class="fas fa-check-double"></i> Tandai Semua Dibaca
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4><i class="fas fa-bell me-2"></i>Notifikasi Sistem</h4>
            </div>
            <div class="card-body">
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="list-group">
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <div class="list-group-item notification-item <?= $notif['dibaca'] ? 'read' : 'unread' ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if (!$notif['dibaca']): ?>
                                            <span class="badge bg-primary">BARU</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($notif['judul']) ?>
                                    </h6>
                                    <small class="notification-time">
                                        <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                        <?php if (!$notif['dibaca']): ?>
                                            <a href="?mark_read=<?= $notif['id_notifikasi'] ?>" class="text-success ms-2">
                                                <i class="fas fa-check"></i> Tandai dibaca
                                            </a>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($notif['pesan']) ?></p>
                                <?php if ($notif['id_pesanan']): ?>
                                    <a href="admin_orders.php?search=<?= $notif['id_pesanan'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt"></i> Lihat Pesanan #<?= $notif['id_pesanan'] ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Tidak ada notifikasi</h5>
                        <p class="text-muted">Semua notifikasi telah dibaca</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>