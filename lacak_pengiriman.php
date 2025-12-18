<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='masuk.php';</script>";
    exit;
}

// Include koneksi database
include 'koneksi.php';

// Ambil order_id dari URL atau session
if (isset($_GET['order_id'])) {
    $last_order_id = $_GET['order_id'];
} elseif (isset($_SESSION['last_order_id'])) {
    $last_order_id = $_SESSION['last_order_id'];
} else {
    echo "<script>alert('Tidak ada data transaksi!'); window.location='keranjang.php';</script>";
    exit;
}

// Ambil data pesanan terakhir user
$user_id = $_SESSION['user_id'];

// Ambil data pesanan dengan JOIN ke tabel pengiriman
$stmt = $conn->prepare("
    SELECT 
        p.*, 
        u.nama as nama_pelanggan, 
        u.no_hp, 
        u.email,
        pg.id_pengiriman,
        pg.metode_pengiriman as metode_kurir,
        pg.tanggal_kirim,
        pg.nama_kurir,
        pg.jenis_kendaraan,
        pg.nomor_kendaraan,
        pg.status_pengiriman
    FROM pesanan_baru p 
    JOIN pengguna u ON p.id_pelanggan = u.id_pelanggan 
    LEFT JOIN pengiriman pg ON p.id_pesanan = pg.id_pesanan
    WHERE p.id_pesanan = ? AND p.id_pelanggan = ?
");
$stmt->bind_param("ii", $last_order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$pesanan = $result->fetch_assoc();

if (!$pesanan) {
    echo "<script>alert('Data pesanan tidak ditemukan!'); window.location='keranjang.php';</script>";
    exit;
}

// Ambil item pesanan
$stmt_items = $conn->prepare("
    SELECT * FROM pesanan_items_baru 
    WHERE id_pesanan = ? 
    ORDER BY id_item
");
$stmt_items->bind_param("i", $last_order_id);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Hitung PPN (12%)
$total_sebelum_ppn = $pesanan['total_harga'] / 1.12;
$ppn = $pesanan['total_harga'] - $total_sebelum_ppn;

// Konfigurasi status pengiriman
$status_pengiriman_config = [
    'diproses' => [
        'color' => '#ffc107',
        'icon' => 'fas fa-clock',
        'title' => 'Pengiriman Diproses',
        'message' => 'Pesanan Anda sedang dipersiapkan untuk dikirim',
        'progress' => 25
    ],
    'dikirim' => [
        'color' => '#17a2b8',
        'icon' => 'fas fa-shipping-fast',
        'title' => 'Pesanan Dikirim',
        'message' => 'Pesanan Anda telah dikirim oleh kurir',
        'progress' => 50
    ],
    'dalam_perjalanan' => [
        'color' => '#007bff',
        'icon' => 'fas fa-truck',
        'title' => 'Dalam Perjalanan',
        'message' => 'Pesanan Anda sedang dalam perjalanan menuju lokasi Anda',
        'progress' => 75
    ],
    'selesai' => [
        'color' => '#28a745',
        'icon' => 'fas fa-check-double',
        'title' => 'Pesanan Selesai',
        'message' => 'Pesanan telah sampai di tujuan',
        'progress' => 100
    ],
    'dibatalkan' => [
        'color' => '#dc3545',
        'icon' => 'fas fa-times-circle',
        'title' => 'Pengiriman Dibatalkan',
        'message' => 'Pengiriman pesanan dibatalkan',
        'progress' => 0
    ]
];

// Tentukan status pengiriman - jika pesanan selesai, otomatis ubah status pengiriman juga
if ($pesanan['status_pesanan'] === 'selesai') {
    $current_shipping_status = 'selesai';
} else {
    $current_shipping_status = $pesanan['status_pengiriman'] ?? 'diproses';
}

$shipping_info = $status_pengiriman_config[$current_shipping_status] ?? $status_pengiriman_config['diproses'];

// Konfigurasi status pesanan
$status_pesanan_config = [
    'pending' => [
        'color' => '#ffc107',
        'icon' => 'fas fa-clock',
        'title' => 'Menunggu Konfirmasi'
    ],
    'proses pembuatan' => [
        'color' => '#17a2b8',
        'icon' => 'fas fa-bread-slice',
        'title' => 'Sedang Diproses'
    ],
    'dikirim' => [
        'color' => '#007bff',
        'icon' => 'fas fa-truck',
        'title' => 'Sedang Dikirim'
    ],
    'selesai' => [
        'color' => '#28a745',
        'icon' => 'fas fa-check-double',
        'title' => 'Selesai'
    ],
    'cancelled' => [
        'color' => '#dc3545',
        'icon' => 'fas fa-times-circle',
        'title' => 'Dibatalkan'
    ]
];

$current_pesanan_status = $pesanan['status_pesanan'];
$pesanan_info = $status_pesanan_config[$current_pesanan_status] ?? $status_pesanan_config['pending'];

// Tentukan apakah metode pengiriman adalah delivery atau takeaway
$is_delivery = ($pesanan['metode_pengiriman'] === 'delivery');

// Tampilkan pesan khusus untuk status selesai
$completion_message = '';
if ($current_pesanan_status === 'selesai' || $current_shipping_status === 'selesai') {
    $completion_message = $is_delivery 
        ? "Terima kasih telah berbelanja di Baker Old! Pesanan Anda telah selesai dan sampai di tujuan."
        : "Terima kasih telah berbelanja di Baker Old! Pesanan takeaway Anda telah selesai.";
}

// Tentukan judul dan pesan berdasarkan status
if ($current_pesanan_status === 'selesai') {
    $page_title = 'Pesanan Selesai - Baker Old';
    $main_title = 'Pesanan Selesai';
    $main_message = 'Pesanan Anda telah berhasil diselesaikan';
} else {
    $page_title = $is_delivery ? 'Lacak Pengiriman' : 'Status Pesanan' . ' - Baker Old';
    $main_title = $is_delivery ? $shipping_info['title'] : 'Status Pesanan Takeaway';
    $main_message = $is_delivery ? $shipping_info['message'] : 'Pesanan takeaway Anda sedang diproses';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
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
            padding: 20px;
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
            margin-bottom: 30px;
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

        .tracking-container {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .tracking-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 1s ease;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }

        h1 {
            margin-bottom: 20px;
            font-size: 32px;
        }

        .tracking-message {
            color: #666;
            margin-bottom: 15px;
            font-size: 18px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .order-id {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-size: 24px;
            font-weight: bold;
            color: #d37b2c;
            margin: 20px 0;
            border: 2px dashed #d37b2c;
        }

        .completion-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
            text-align: center;
        }

        .completion-message h3 {
            color: #155724;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .shipping-info-card {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #2196F3;
            text-align: left;
        }

        .completed-shipping-card {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
            text-align: left;
        }

        .takeaway-info-card {
            background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #4caf50;
            text-align: left;
        }

        .completed-takeaway-card {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 5px solid #28a745;
            text-align: left;
        }

        .shipping-info-card h3, .takeaway-info-card h3, 
        .completed-shipping-card h3, .completed-takeaway-card h3 {
            color: #1976D2;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .takeaway-info-card h3 {
            color: #2e7d32;
        }

        .completed-shipping-card h3, .completed-takeaway-card h3 {
            color: #155724;
        }

        .courier-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .detail-item {
            background: rgba(255,255,255,0.7);
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #2196F3;
        }

        .completed-detail-item {
            background: rgba(255,255,255,0.7);
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #28a745;
        }

        .takeaway-detail-item {
            background: rgba(255,255,255,0.7);
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #4caf50;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }

        .progress-bar {
            height: 12px;
            background: #e9ecef;
            border-radius: 10px;
            margin: 30px 0;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, <?= $shipping_info['color'] ?>, <?= $shipping_info['color'] ?>);
            border-radius: 10px;
            transition: width 0.5s ease;
            width: <?= $shipping_info['progress'] ?>%;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        .status-timeline {
            display: flex;
            justify-content: space-between;
            margin: 40px 0;
            position: relative;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 4px;
            background: #e9ecef;
            z-index: 1;
        }

        .timeline-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .timeline-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            background: white;
            border: 4px solid #e9ecef;
            transition: all 0.3s ease;
            font-size: 18px;
        }

        .timeline-item.active .timeline-icon {
            background: <?= $shipping_info['color'] ?>;
            border-color: <?= $shipping_info['color'] ?>;
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .timeline-item.completed .timeline-icon {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }

        .timeline-label {
            font-size: 14px;
            text-align: center;
            color: #6c757d;
            font-weight: 500;
            max-width: 120px;
        }

        .timeline-item.active .timeline-label {
            color: <?= $shipping_info['color'] ?>;
            font-weight: bold;
        }

        .timeline-item.completed .timeline-label {
            color: #28a745;
            font-weight: bold;
        }

        .receipt-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-top: 30px;
            text-align: left;
            border: 2px solid <?= $shipping_info['color'] ?>;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .receipt-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #d37b2c;
        }

        .info-box h3 {
            color: #d37b2c;
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .items-table th, .items-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .items-table th {
            background: #fff2e0;
            color: #d37b2c;
            font-weight: 600;
            font-size: 14px;
        }

        .total-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }

        .total-row.final {
            border-top: 2px solid #e9ecef;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: bold;
            font-size: 18px;
            color: #d37b2c;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

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

        .btn-print {
            background: #d37b2c;
            color: white;
        }

        .btn-print:hover {
            background: #b36622;
            transform: translateY(-2px);
        }

        .btn-home {
            background: #6c757d;
            color: white;
        }

        .btn-home:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-whatsapp {
            background: #25D366;
            color: white;
        }

        .btn-whatsapp:hover {
            background: #128C7E;
            transform: translateY(-2px);
        }

        .btn-refresh {
            background: #6f42c1;
            color: white;
        }

        .btn-refresh:hover {
            background: #5a2d91;
            transform: translateY(-2px);
        }

        .btn-contact {
            background: #17a2b8;
            color: white;
        }

        .btn-contact:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .btn-track {
            background: #dc3545;
            color: white;
        }

        .btn-track:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-rate {
            background: #ffc107;
            color: #212529;
        }

        .btn-rate:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .status-diproses { background: #fff3cd; color: #856404; }
        .status-dikirim { background: #cce7ff; color: #004085; }
        .status-dalam_perjalanan { background: #d1ecf1; color: #0c5460; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-dibatalkan { background: #f8d7da; color: #721c24; }

        .estimated-time {
            background: #fff3cd;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }

        .completed-estimated-time {
            background: #d4edda;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }

        .estimated-time h4, .completed-estimated-time h4 {
            color: #856404;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .completed-estimated-time h4 {
            color: #155724;
        }

        .map-placeholder {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
            border: 2px dashed #dee2e6;
        }

        .map-placeholder i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .receipt-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .tracking-container {
                padding: 20px;
            }

            .status-timeline {
                flex-direction: column;
                gap: 20px;
            }

            .status-timeline::before {
                display: none;
            }

            .timeline-item {
                flex-direction: row;
                gap: 15px;
                justify-content: flex-start;
            }

            .timeline-label {
                text-align: left;
                font-size: 14px;
            }

            .courier-details {
                grid-template-columns: 1fr;
            }
        }

        .real-time-update {
            background: #d4edda;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
            animation: pulse 2s infinite;
        }

        .last-update {
            font-size: 12px;
            color: #666;
            text-align: center;
            margin-top: 10px;
        }

        .delivery-only {
            display: <?= $is_delivery ? 'block' : 'none' ?>;
        }

        .takeaway-only {
            display: <?= !$is_delivery ? 'block' : 'none' ?>;
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
        <ul style="display: flex; list-style: none; gap: 20px; margin: 0; padding: 0;">
            <li><a href="beranda.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 5px;"><i class="fas fa-home"></i> Beranda</a></li>
            <li><a href="menu.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 5px;"><i class="fas fa-utensils"></i> Menu</a></li>
        </ul>
    </nav>

    <div class="tracking-container">
        <div class="tracking-icon" style="color: <?= $shipping_info['color'] ?>">
            <i class="<?= $shipping_info['icon'] ?>"></i>
        </div>
        <h1 style="color: <?= $shipping_info['color'] ?>">
            <?= $main_title ?>
        </h1>
        <p class="tracking-message">
            <?= $main_message ?>
        </p>
        <div class="order-id">
            Order ID: #<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?>
        </div>

        <!-- Pesan Penyelesaian -->
        <?php if ($completion_message): ?>
        <div class="completion-message">
            <h3><i class="fas fa-check-circle"></i> Pesanan Selesai!</h3>
            <p><?= $completion_message ?></p>
            <?php if ($is_delivery): ?>
                <p><strong>Terima kasih telah mempercayakan pengiriman kepada Baker Old!</strong></p>
            <?php else: ?>
                <p><strong>Kami tunggu kunjungan Anda berikutnya!</strong></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Informasi Real-time Update -->
        <div class="real-time-update">
            <h4><i class="fas fa-sync-alt"></i> Update Real-time</h4>
            <p>Status <?= $is_delivery ? 'pengiriman' : 'pesanan' ?> diperbarui secara otomatis.</p>
        </div>

        <!-- Progress Bar - Hanya untuk delivery -->
        <?php if ($is_delivery): ?>
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <div class="progress-labels">
            <span>Diproses</span>
            <span>Dikirim</span>
            <span>Dalam Perjalanan</span>
            <span>Selesai</span>
        </div>
        <?php endif; ?>

        <!-- Status Timeline - Hanya untuk delivery -->
        <?php if ($is_delivery): ?>
        <div class="status-timeline">
            <?php
            $shipping_statuses = [
                'diproses' => ['icon' => 'fas fa-clock', 'label' => 'Diproses'],
                'dikirim' => ['icon' => 'fas fa-shipping-fast', 'label' => 'Dikirim'],
                'dalam_perjalanan' => ['icon' => 'fas fa-truck', 'label' => 'Dalam Perjalanan'],
                'selesai' => ['icon' => 'fas fa-check-double', 'label' => 'Selesai']
            ];
            
            $current_shipping_index = array_search($current_shipping_status, array_keys($shipping_statuses));
            if ($current_shipping_index === false) $current_shipping_index = -1;
            
            foreach ($shipping_statuses as $status => $info):
                $status_index = array_search($status, array_keys($shipping_statuses));
                $is_completed = $status_index < $current_shipping_index;
                $is_active = $status_index == $current_shipping_index;
            ?>
            <div class="timeline-item <?= $is_completed ? 'completed' : '' ?> <?= $is_active ? 'active' : '' ?>">
                <div class="timeline-icon">
                    <i class="<?= $info['icon'] ?>"></i>
                </div>
                <div class="timeline-label"><?= $info['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Informasi Kurir/Pengiriman - Hanya untuk delivery -->
        <?php if ($is_delivery): ?>
            <?php if ($current_shipping_status === 'selesai'): ?>
                <!-- Tampilan ketika selesai -->
                <div class="completed-shipping-card">
                    <h3><i class="fas fa-check-circle"></i> Informasi Pengiriman</h3>
                    <p><strong>Pengiriman telah berhasil diselesaikan!</strong></p>
                    <?php if ($pesanan['id_pengiriman']): ?>
                    <div class="courier-details">
                        <div class="completed-detail-item">
                            <div class="detail-label">Status Pengiriman</div>
                            <div class="detail-value">Selesai</div>
                        </div>
                        <?php if ($pesanan['nama_kurir']): ?>
                        <div class="completed-detail-item">
                            <div class="detail-label">Kurir</div>
                            <div class="detail-value"><?= htmlspecialchars($pesanan['nama_kurir']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($pesanan['tanggal_kirim']): ?>
                        <div class="completed-detail-item">
                            <div class="detail-label">Tanggal Pengiriman</div>
                            <div class="detail-value"><?= date('d F Y', strtotime($pesanan['tanggal_kirim'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($pesanan['id_pengiriman']): ?>
                <!-- Tampilan normal ketika ada data pengiriman -->
                <div class="shipping-info-card">
                    <h3><i class="fas fa-truck"></i> Informasi Kurir</h3>
                    <div class="courier-details">
                        <div class="detail-item">
                            <div class="detail-label">Nama Kurir</div>
                            <div class="detail-value"><?= htmlspecialchars($pesanan['nama_kurir'] ?? 'Sedang diproses') ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Metode Pengiriman</div>
                            <div class="detail-value"><?= ucfirst(str_replace('_', ' ', $pesanan['metode_kurir'] ?? 'Standard')) ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Jenis Kendaraan</div>
                            <div class="detail-value"><?= ucfirst($pesanan['jenis_kendaraan'] ?? '-') ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">No. Kendaraan</div>
                            <div class="detail-value"><?= htmlspecialchars($pesanan['nomor_kendaraan'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Tampilan ketika belum ada data pengiriman -->
                <div class="shipping-info-card">
                    <h3><i class="fas fa-info-circle"></i> Informasi Pengiriman</h3>
                    <p>Informasi pengiriman akan tersedia setelah pesanan diproses oleh admin.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
        <!-- Informasi Takeaway -->
            <?php if ($current_pesanan_status === 'selesai'): ?>
                <div class="completed-takeaway-card">
                    <h3><i class="fas fa-check-circle"></i> Informasi Takeaway</h3>
                    <p><strong>Pesanan takeaway telah berhasil diselesaikan!</strong></p>
                    <div class="courier-details">
                        <div class="completed-detail-item">
                            <div class="detail-label">Status Pesanan</div>
                            <div class="detail-value">Selesai</div>
                        </div>
                        <div class="completed-detail-item">
                            <div class="detail-label">Lokasi Pengambilan</div>
                            <div class="detail-value">Baker Old Store</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="takeaway-info-card">
                    <h3><i class="fas fa-store"></i> Informasi Takeaway</h3>
                    <p>Silakan mengambil pesanan Anda di toko Baker Old. Pesanan akan siap dalam waktu 30-45 menit.</p>
                    <div class="courier-details">
                        <div class="takeaway-detail-item">
                            <div class="detail-label">Lokasi Toko</div>
                            <div class="detail-value">Jl. Roti Enak No. 123, Kota Baker</div>
                        </div>
                        <div class="takeaway-detail-item">
                            <div class="detail-label">Jam Operasional</div>
                            <div class="detail-value">08:00 - 22:00 WIB</div>
                        </div>
                        <div class="takeaway-detail-item">
                            <div class="detail-label">No. Telepon</div>
                            <div class="detail-value">(021) 1234-5678</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Estimasi Waktu -->
        <div class="<?= ($current_pesanan_status === 'selesai' || $current_shipping_status === 'selesai') ? 'completed-estimated-time' : 'estimated-time' ?>">
            <h4><i class="fas fa-clock"></i> 
                <?= $is_delivery ? 'Estimasi Waktu Pengiriman' : 'Estimasi Waktu Penyiapan' ?>
            </h4>
            <p>
                <?php if ($is_delivery): ?>
                    <?php if ($current_shipping_status == 'diproses'): ?>
                        Pesanan Anda akan dikirim dalam waktu 1-2 jam ke depan.
                    <?php elseif ($current_shipping_status == 'dikirim'): ?>
                        Kurir sedang dalam perjalanan menuju lokasi Anda. Estimasi sampai: 30-45 menit.
                    <?php elseif ($current_shipping_status == 'dalam_perjalanan'): ?>
                        Kurir sedang mendekati lokasi Anda. Estimasi sampai: 15-30 menit.
                    <?php elseif ($current_shipping_status == 'selesai'): ?>
                        <strong>Pesanan telah sampai di tujuan! Pengiriman berhasil diselesaikan.</strong>
                    <?php else: ?>
                        Estimasi waktu akan tersedia setelah pengiriman diproses.
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($current_pesanan_status == 'pending'): ?>
                        Pesanan Anda sedang dikonfirmasi. Akan siap dalam 30-45 menit.
                    <?php elseif ($current_pesanan_status == 'proses pembuatan'): ?>
                        Pesanan sedang diproses. Silakan datang ke toko dalam 15-30 menit.
                    <?php elseif ($current_pesanan_status == 'selesai'): ?>
                        <strong>Pesanan sudah siap dan telah diambil! Proses takeaway berhasil diselesaikan.</strong>
                    <?php else: ?>
                        Pesanan akan siap dalam waktu 30-45 menit.
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Detail Pesanan -->
        <div class="receipt-container">
            <div class="receipt-header">
                <h2><i class="fas fa-receipt"></i> Detail Pesanan</h2>
                <p>
                    Status Pesanan: 
                    <span class="status-badge status-<?= str_replace(' ', '-', $current_pesanan_status) ?>">
                        <?= $pesanan_info['title'] ?>
                    </span>
                    <?php if ($is_delivery): ?>
                    | 
                    Status Pengiriman: 
                    <span class="status-badge status-<?= $current_shipping_status ?>">
                        <?= $shipping_info['title'] ?>
                    </span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="receipt-info">
                <div class="info-box">
                    <h3><i class="fas fa-user"></i> Informasi Pelanggan</h3>
                    <p><strong>Nama:</strong> <?= htmlspecialchars($pesanan['nama_pelanggan']) ?></p>
                    <p><strong>No. HP:</strong> <?= htmlspecialchars($pesanan['no_hp']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($pesanan['email']) ?></p>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-map-marker-alt"></i> Informasi <?= $is_delivery ? 'Pengiriman' : 'Takeaway' ?></h3>
                    <p><strong>Metode:</strong> <?= ucfirst($pesanan['metode_pengiriman']) ?></p>
                    <?php if ($is_delivery): ?>
                        <p><strong>Alamat:</strong> <?= htmlspecialchars($pesanan['alamat_pengiriman']) ?></p>
                    <?php else: ?>
                        <p><strong>Ambil di:</strong> Toko Baker Old</p>
                    <?php endif; ?>
                    <p><strong>Pembayaran:</strong> <?= strtoupper($pesanan['metode_pembayaran']) ?></p>
                </div>
            </div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nama_produk']) ?></td>
                            <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                            <td><?= $item['jumlah'] ?></td>
                            <td>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-section">
                <div class="total-row final">
                    <span>Total Pembayaran:</span>
                    <span>Rp <?= number_format($pesanan['total_harga'], 0, ',', '.') ?></span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($current_pesanan_status !== 'selesai' && $current_shipping_status !== 'selesai'): ?>
            <button class="btn btn-refresh" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh Status
            </button>
            <?php endif; ?>
            
            <a href="https://wa.me/6285711555527?text=Halo%20Baker%20Old,%20saya%20ingin%20menanyakan%20status%20<?= $is_delivery ? 'pengiriman' : 'pesanan' ?>%20%23<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?>%0A%0ANama: <?= urlencode($pesanan['nama_pelanggan']) ?>%0ANo. HP: <?= urlencode($pesanan['no_hp']) ?>%0AStatus: <?= urlencode($is_delivery ? $shipping_info['title'] : $pesanan_info['title']) ?>%0A%0ATerima%20kasih." 
               target="_blank" class="btn btn-whatsapp">
                <i class="fab fa-whatsapp"></i> Hubungi Admin
            </a>

            <a href="beranda.php" class="btn btn-home">
                <i class="fas fa-home"></i> Ke Beranda
            </a>

            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Bukti
            </button>

            <!-- Tombol Beri Rating - Hanya untuk pesanan yang selesai -->
            <?php if ($current_pesanan_status === 'selesai' || $current_shipping_status === 'selesai'): ?>
            <a href="rating_produk.php?order_id=<?= $pesanan['id_pesanan'] ?>" class="btn btn-rate">
                <i class="fas fa-star"></i> Beri Rating
            </a>
            <?php endif; ?>
        </div>

        <div class="last-update">
            Terakhir diupdate: <?= date('d/m/Y H:i:s') ?>
        </div>
    </div>

    <script>
        // Auto refresh setiap 30 detik (kecuali status sudah selesai)
        <?php if ($current_pesanan_status !== 'selesai' && $current_shipping_status !== 'selesai'): ?>
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        <?php endif; ?>

        // Animasi untuk timeline
        document.addEventListener('DOMContentLoaded', function() {
            const timelineItems = document.querySelectorAll('.timeline-item');
            timelineItems.forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });

        // Simulasi update real-time (opsional)
        <?php if ($is_delivery): ?>
        let progress = <?= $shipping_info['progress'] ?>;
        const progressBar = document.querySelector('.progress-fill');
        
        function animateProgress() {
            let currentWidth = parseFloat(progressBar.style.width) || progress;
            if (currentWidth < progress) {
                currentWidth += 1;
                progressBar.style.width = currentWidth + '%';
                setTimeout(animateProgress, 20);
            }
        }
        
        // Mulai animasi setelah halaman load
        window.addEventListener('load', animateProgress);
        <?php endif; ?>

        // Tampilkan konfirmasi untuk rating
        document.addEventListener('DOMContentLoaded', function() {
            const rateButton = document.querySelector('.btn-rate');
            if (rateButton) {
                rateButton.addEventListener('click', function(e) {
                    if (!confirm('Apakah Anda ingin memberikan rating dan ulasan untuk pesanan ini?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>