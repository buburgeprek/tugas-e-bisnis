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

// Ambil data pesanan
$stmt = $conn->prepare("
    SELECT p.*, u.nama as nama_pelanggan, u.no_hp, u.email 
    FROM pesanan_baru p 
    JOIN pengguna u ON p.id_pelanggan = u.id_pelanggan 
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

// Tentukan warna dan icon berdasarkan status
$status_config = [
    'pending' => [
        'color' => '#ffc107',
        'icon' => 'fas fa-clock',
        'title' => 'Menunggu Konfirmasi',
        'message' => 'Pesanan Anda sedang menunggu konfirmasi dari admin'
    ],
    'proses pembuatan' => [
        'color' => '#17a2b8',
        'icon' => 'fas fa-bread-slice',
        'title' => 'Sedang Diproses',
        'message' => 'Pesanan Anda sedang dibuat di dapur kami'
    ],
    'dikirim' => [
        'color' => '#007bff',
        'icon' => 'fas fa-truck',
        'title' => 'Sedang Dikirim',
        'message' => 'Pesanan Anda sedang dalam perjalanan'
    ],
    'selesai' => [
        'color' => '#28a745',
        'icon' => 'fas fa-check-double',
        'title' => 'Selesai',
        'message' => 'Pesanan telah selesai dan diterima'
    ],
    'cancelled' => [
        'color' => '#dc3545',
        'icon' => 'fas fa-times-circle',
        'title' => 'Dibatalkan',
        'message' => 'Pesanan telah dibatalkan'
    ]
];

$current_status = $pesanan['status_pesanan'];
$status_info = $status_config[$current_status] ?? $status_config['pending'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bukti Transaksi - Baker Old</title>
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

        .status-container {
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

        .status-icon {
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

        .status-message {
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

        .admin-info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }

        .admin-info h3 {
            color: #007bff;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .receipt-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-top: 30px;
            text-align: left;
            border: 2px solid <?= $status_info['color'] ?>;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .receipt-header h2 {
            color: #d37b2c;
            margin-bottom: 10px;
            font-size: 24px;
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

        .info-box p {
            margin: 5px 0;
            font-size: 14px;
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

        .payment-instruction {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #007bff;
        }

        .process-info {
            background: #d1ecf1;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #17a2b8;
        }

        .delivery-info {
            background: #cce7ff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #007bff;
        }

        .completed-info {
            background: #d4edda;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #28a745;
        }

        .waiting-info {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #ffc107;
        }

        .payment-instruction h3,
        .process-info h3,
        .delivery-info h3,
        .completed-info h3,
        .waiting-info h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payment-instruction ol {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .payment-instruction li,
        .process-info li,
        .delivery-info li,
        .completed-info li,
        .waiting-info li {
            margin-bottom: 8px;
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

        .btn-continue {
            background: #28a745;
            color: white;
        }

        .btn-continue:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-track {
            background: #17a2b8;
            color: white;
        }

        .btn-track:hover {
            background: #138496;
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

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-proses-pembuatan { background: #d1ecf1; color: #0c5460; }
        .status-dikirim { background: #cce7ff; color: #004085; }
        .status-selesai { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .status-timeline {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
            position: relative;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            background: white;
            border: 3px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .timeline-item.active .timeline-icon {
            background: <?= $status_info['color'] ?>;
            border-color: <?= $status_info['color'] ?>;
            color: white;
            transform: scale(1.1);
        }

        .timeline-item.completed .timeline-icon {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }

        .timeline-label {
            font-size: 12px;
            text-align: center;
            color: #6c757d;
            font-weight: 500;
        }

        .timeline-item.active .timeline-label {
            color: <?= $status_info['color'] ?>;
            font-weight: bold;
        }

        .timeline-item.completed .timeline-label {
            color: #28a745;
            font-weight: bold;
        }

        .progress-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: <?= $status_info['color'] ?>;
            border-radius: 3px;
            transition: width 0.5s ease;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .navbar, .action-buttons, .payment-instruction, .process-info, 
            .delivery-info, .completed-info, .waiting-info, .status-icon, 
            .status-container > h1, .status-container > .status-message,
            .status-timeline, .admin-info, .progress-bar {
                display: none;
            }
            
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                border: 1px solid #ddd;
            }
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
            
            .status-container {
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
            }

            .timeline-label {
                text-align: left;
                font-size: 14px;
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
        <ul style="display: flex; list-style: none; gap: 20px; margin: 0; padding: 0;">
            <li><a href="beranda.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 5px;"><i class="fas fa-home"></i> Beranda</a></li>
            <li><a href="menu.php" style="color: white; text-decoration: none; display: flex; align-items: center; gap: 5px;"><i class="fas fa-utensils"></i> Menu</a></li>
          
             
        </ul>
    </nav>

    <div class="status-container">
        <div class="status-icon" style="color: <?= $status_info['color'] ?>">
            <i class="<?= $status_info['icon'] ?>"></i>
        </div>
        <h1 style="color: <?= $status_info['color'] ?>"><?= $status_info['title'] ?></h1>
        <p class="status-message"><?= $status_info['message'] ?></p>
        <div class="order-id">
            Order ID: #<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?>
        </div>

        <!-- Informasi Admin -->
        <div class="admin-info">
            <h3><i class="fas fa-info-circle"></i> Informasi Status</h3>
            <p>Status pesanan akan diupdate secara manual oleh admin. Silakan Lihat <a href ="lacak_pengiriman.php">Lacak Pengiriman</a>.</p>
        </div>

        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-fill" style="width: 
                <?= $current_status == 'pending' ? '25%' : 
                   ($current_status == 'proses pembuatan' ? '50%' : 
                   ($current_status == 'dikirim' ? '75%' : '100%')) ?>">
            </div>
        </div>

        <!-- Status Timeline -->
        <div class="status-timeline">
            <?php
            $statuses = [
                'pending' => ['icon' => 'fas fa-clock', 'label' => 'Menunggu Konfirmasi'],
                'proses pembuatan' => ['icon' => 'fas fa-bread-slice', 'label' => 'Proses Pembuatan'],
                'dikirim' => ['icon' => 'fas fa-truck', 'label' => 'Dikirim'],
                'selesai' => ['icon' => 'fas fa-check-double', 'label' => 'Selesai']
            ];
            
            $current_index = array_search($current_status, array_keys($statuses));
            if ($current_index === false) $current_index = -1;
            
            foreach ($statuses as $status => $info):
                $status_index = array_search($status, array_keys($statuses));
                $is_completed = $status_index < $current_index;
                $is_active = $status_index == $current_index;
                $status_class = str_replace(' ', '-', $status);
            ?>
            <div class="timeline-item <?= $is_completed ? 'completed' : '' ?> <?= $is_active ? 'active' : '' ?>">
                <div class="timeline-icon">
                    <i class="<?= $info['icon'] ?>"></i>
                </div>
                <div class="timeline-label"><?= $info['label'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Bukti Transaksi -->
        <div class="receipt-container">
            <div class="receipt-header">
                <h2><i class="fas fa-receipt"></i> Detail Pesanan</h2>
                <p>Status: 
                    <span class="status-badge status-<?= str_replace(' ', '-', $current_status) ?>">
                        <?= $status_info['title'] ?>
                    </span>
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
                    <h3><i class="fas fa-shopping-cart"></i> Informasi Pesanan</h3>
                    <p><strong>No. Pesanan:</strong> #<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?></p>
                    <p><strong>Tanggal:</strong> <?= date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan'])) ?></p>
                    <p><strong>Status:</strong> 
                        <span class="status-badge status-<?= str_replace(' ', '-', $current_status) ?>">
                            <?= $status_info['title'] ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="info-box">
                <h3><i class="fas fa-truck"></i> Informasi Pengiriman & Pembayaran</h3>
                <p><strong>Metode Pengiriman:</strong> <?= ucfirst($pesanan['metode_pengiriman']) ?></p>
                <?php if ($pesanan['metode_pengiriman'] === 'delivery'): ?>
                    <p><strong>Alamat:</strong> <?= htmlspecialchars($pesanan['alamat_pengiriman']) ?></p>
                <?php endif; ?>
                <p><strong>Metode Pembayaran:</strong> <?= strtoupper($pesanan['metode_pembayaran']) ?></p>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th>Diskon</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nama_produk']) ?></td>
                            <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                            <td><?= $item['jumlah'] ?></td>
                            <td>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                            <td>
                                <?php if ($item['diskon'] > 0): ?>
                                    -Rp <?= number_format($item['diskon'], 0, ',', '.') ?>
                                    <?php if (!empty($item['jenis_diskon'])): ?>
                                        <br><small style="color: #28a745;"><?= htmlspecialchars($item['jenis_diskon']) ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>Rp <?= number_format($total_sebelum_ppn, 0, ',', '.') ?></span>
                </div>
                <div class="total-row">
                    <span>Total Diskon:</span>
                    <span>-Rp <?= number_format($pesanan['total_diskon'], 0, ',', '.') ?></span>
                </div>
                <div class="total-row">
                    <span>PPN (12%):</span>
                    <span>+Rp <?= number_format($ppn, 0, ',', '.') ?></span>
                </div>
                <div class="total-row final">
                    <span>Total Pembayaran:</span>
                    <span>Rp <?= number_format($pesanan['total_harga'], 0, ',', '.') ?></span>
                </div>
                
                <?php if ($pesanan['metode_pembayaran'] === 'cash'): ?>
                    <div class="total-row">
                        <span>Uang Dibayar:</span>
                        <span>Rp <?= number_format($pesanan['uang_dibayar'], 0, ',', '.') ?></span>
                    </div>
                    <div class="total-row final">
                        <span>Kembalian:</span>
                        <span>Rp <?= number_format($pesanan['kembalian'], 0, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Informasi Berdasarkan Status -->
            <?php if ($current_status == 'pending'): ?>
                <?php if (in_array($pesanan['metode_pembayaran'], ['qris', 'debit'])): ?>
                <div class="payment-instruction">
                    <h3><i class="fas fa-info-circle"></i> Instruksi Pembayaran</h3>
                    <ol>
                        <li>Screenshot bukti transaksi ini</li>
                        <li>Kirim bukti pembayaran ke WhatsApp kami: <strong>+62 812-3456-7890</strong></li>
                        <li>Sertakan informasi berikut dalam pesan:</li>
                        <ul>
                            <li>Waktu perkiraan memesan: <?= date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan'])) ?></li>
                            <li>No. Pesanan: #<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?></li>
                            <li>Nama pesanan: <?= count($items) ?> item produk Baker Old</li>
                        </ul>
                        <li>Tunggu konfirmasi dari admin</li>
                    </ol>
                    <p><strong>Untuk pembayaran CASH, cukup tunjukkan bukti transaksi ini pada kurir saat pengantaran.</strong></p>
                </div>
                <?php else: ?>
                <div class="waiting-info">
                    <h3><i class="fas fa-clock"></i> Menunggu Konfirmasi</h3>
                    <p>Pesanan Anda sedang menunggu konfirmasi dari admin.</p>
                    <p>Status akan berubah menjadi <strong>"Proses Pembuatan"</strong> setelah admin mengkonfirmasi pesanan Anda.</p>
                </div>
                <?php endif; ?>
            <?php elseif ($current_status == 'proses pembuatan'): ?>
            <div class="process-info">
                <h3><i class="fas fa-bread-slice"></i> Sedang Diproses</h3>
                <p>Pesanan Anda sedang dibuat dengan bahan-bahan terbaik oleh chef kami.</p>
                <p>Admin akan mengupdate status menjadi <strong>"Dikirim"</strong> ketika pesanan sudah siap.</p>
            </div>
            <?php elseif ($current_status == 'dikirim'): ?>
            <div class="delivery-info">
                <h3><i class="fas fa-truck"></i> Sedang Dikirim</h3>
                <p>Pesanan Anda sedang dalam perjalanan menuju lokasi Anda.</p>
                <?php if ($pesanan['metode_pengiriman'] === 'delivery'): ?>
                    <p><strong>Alamat pengiriman:</strong> <?= htmlspecialchars($pesanan['alamat_pengiriman']) ?></p>
                <?php else: ?>
                    <p><strong>Silakan mengambil pesanan di toko kami</strong></p>
                <?php endif; ?>
                <p>Admin akan mengupdate status menjadi <strong>"Selesai"</strong> ketika pesanan sudah diterima.</p>
            </div>
            <?php elseif ($current_status == 'selesai'): ?>
            <div class="completed-info">
                <h3><i class="fas fa-check-double"></i> Pesanan Selesai</h3>
                <p>Terima kasih telah memesan di Baker Old!</p>
                <p>Pesanan Anda telah selesai dan <?= $pesanan['metode_pengiriman'] === 'delivery' ? 'telah diterima' : 'telah diambil' ?>.</p>
                <p>Kami harap Anda menikmati produk kami. Jangan lupa untuk memberikan rating dan ulasan!</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <button class="btn btn-refresh" onclick="window.location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh Status
            </button>
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Bukti
            </button>
            <a href="beranda.php" class="btn btn-home">
                <i class="fas fa-home"></i> Ke Beranda
            </a>
            
            <?php if ($current_status == 'pending' && in_array($pesanan['metode_pembayaran'], ['qris', 'debit'])): ?>
                <a href="https://wa.me/6285711555527?text=Halo%20Baker%20Old,%20saya%20sudah%20melakukan%20pembayaran%20untuk%20pesanan%20%23<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?>%20pada%20<?= urlencode(date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan']))) ?>.%0A%0ADetail%20Pesanan:%0A- No. Pesanan: %23<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?>%0A- Waktu Pesan: <?= urlencode(date('d/m/Y H:i', strtotime($pesanan['tanggal_pesanan']))) ?>%0A- Total: Rp <?= number_format($pesanan['total_harga'], 0, ',', '.') ?>%0A- Metode: <?= strtoupper($pesanan['metode_pembayaran']) ?>%0A%0ATerima%20kasih." 
                   target="_blank" class="btn btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Konfirmasi Pembayaran
                </a>
            <?php elseif ($current_status == 'dikirim'): ?>
                <a href="https://wa.me/6285711555527?text=Halo%20Baker%20Old,%20saya%20ingin%20menanyakan%20status%20pengiriman%20pesanan%20%23<?= str_pad($pesanan['id_pesanan'], 6, '0', STR_PAD_LEFT) ?>%0A%0ANama: <?= urlencode($pesanan['nama_pelanggan']) ?>%0ANo. HP: <?= urlencode($pesanan['no_hp']) ?>%0A%0ATerima%20kasih." 
                   target="_blank" class="btn btn-track">
                    <i class="fas fa-truck"></i> Lacak Pengiriman
                </a>
            <?php elseif ($current_status == 'selesai'): ?>
                <a href="rating_produk.php" class="btn btn-continue">
                    <i class="fas fa-star"></i> Beri Rating
                </a>
            <?php endif; ?>
            
            <a href="menu.php" class="btn btn-continue">
                <i class="fas fa-utensils"></i> Pesan Lagi
            </a>
        </div>
    </div>

    <script>
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

        // Notification untuk refresh
        function showRefreshNotification() {
            if (Notification.permission === 'granted') {
                new Notification('Baker Old', {
                    body: 'Status pesanan mungkin telah diupdate. Refresh halaman untuk melihat perubahan.',
                    icon: '/favicon.ico'
                });
            }
        }

        // Minta permission untuk notification
        if ('Notification' in window) {
            Notification.requestPermission();
        }
    </script>
</body>
</html>