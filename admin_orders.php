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

// ==================== HANDLE ACTIONS ====================

// Handle Delete Pesanan by ID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    
    try {
        $conn->begin_transaction();
        
        // 1. Hapus data ulasan yang terkait dengan pesanan ini
        $stmt_ulasan = $conn->prepare("DELETE FROM ulasan_produk WHERE id_pesanan = ?");
        $stmt_ulasan->bind_param("i", $id_pesanan);
        $stmt_ulasan->execute();
        $stmt_ulasan->close();
        
        // 2. Hapus items pesanan
        $stmt_items = $conn->prepare("DELETE FROM pesanan_items_baru WHERE id_pesanan = ?");
        $stmt_items->bind_param("i", $id_pesanan);
        $stmt_items->execute();
        $stmt_items->close();
        
        // 3. Hapus pesanan
        $stmt_order = $conn->prepare("DELETE FROM pesanan_baru WHERE id_pesanan = ?");
        $stmt_order->bind_param("i", $id_pesanan);
        
        if ($stmt_order->execute()) {
            $conn->commit();
            $message = "Pesanan #" . $id_pesanan . " berhasil dihapus!";
        } else {
            throw new Exception("Gagal menghapus pesanan: " . $conn->error);
        }
        $stmt_order->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Handle Delete All Pesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_orders'])) {
    $confirmation = trim($_POST['confirmation_text']);
    
    if ($confirmation === 'HAPUS SEMUA') {
        try {
            $conn->begin_transaction();
            
            // 1. Hapus semua data ulasan yang terkait dengan pesanan
            $stmt_delete_ulasan = $conn->prepare("DELETE FROM ulasan_produk");
            $stmt_delete_ulasan->execute();
            $stmt_delete_ulasan->close();
            
            // 2. Hapus semua items pesanan
            $stmt_delete_items = $conn->prepare("DELETE FROM pesanan_items_baru");
            $stmt_delete_items->execute();
            $stmt_delete_items->close();
            
            // 3. Hapus semua pesanan
            $stmt_delete_orders = $conn->prepare("DELETE FROM pesanan_baru");
            
            if ($stmt_delete_orders->execute()) {
                $affected_rows = $stmt_delete_orders->affected_rows;
                $conn->commit();
                $message = "Semua pesanan (" . $affected_rows . ") berhasil dihapus!";
            } else {
                throw new Exception("Gagal menghapus semua pesanan: " . $conn->error);
            }
            $stmt_delete_orders->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = "Konfirmasi tidak valid! Ketik 'HAPUS SEMUA' untuk menghapus semua data.";
    }
}

// Handle Update Status Pesanan dan Pengiriman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    $status_pesanan = trim($_POST['status_pesanan']);
    
    try {
        $conn->begin_transaction();
        
        // Update status pesanan di tabel pesanan_baru
        $stmt_pesanan = $conn->prepare("UPDATE pesanan_baru SET status_pesanan = ?, updated_at = NOW() WHERE id_pesanan = ?");
        $stmt_pesanan->bind_param("si", $status_pesanan, $id_pesanan);
        
        if (!$stmt_pesanan->execute()) {
            throw new Exception("Gagal mengupdate status pesanan: " . $conn->error);
        }
        $stmt_pesanan->close();
        
        // Jika status pesanan diubah menjadi "selesai", update juga status pengiriman
        if ($status_pesanan === 'selesai') {
            // Cek apakah ada data pengiriman untuk pesanan ini
            $stmt_cek_pengiriman = $conn->prepare("SELECT id_pengiriman FROM pengiriman WHERE id_pesanan = ?");
            $stmt_cek_pengiriman->bind_param("i", $id_pesanan);
            $stmt_cek_pengiriman->execute();
            $result_cek = $stmt_cek_pengiriman->get_result();
            
            if ($result_cek->num_rows > 0) {
                // Update status pengiriman menjadi "selesai"
                $stmt_pengiriman = $conn->prepare("UPDATE pengiriman SET status_pengiriman = 'selesai', waktu_tiba = NOW(), updated_at = NOW() WHERE id_pesanan = ?");
                $stmt_pengiriman->bind_param("i", $id_pesanan);
                
                if (!$stmt_pengiriman->execute()) {
                    throw new Exception("Gagal mengupdate status pengiriman: " . $conn->error);
                }
                $stmt_pengiriman->close();
            }
            $stmt_cek_pengiriman->close();
        }
        
        $conn->commit();
        $message = "Status pesanan berhasil diupdate!" . ($status_pesanan === 'selesai' ? " Status pengiriman juga diupdate menjadi selesai." : "");
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Handle Update Status Pengiriman dan Pesanan (Dengan timestamp - 3 MENIT untuk testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shipping_status'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    $status_pengiriman = trim($_POST['status_pengiriman']);
    
    try {
        $conn->begin_transaction();
        
        // Update status pengiriman di tabel pengiriman
        $stmt_pengiriman = $conn->prepare("UPDATE pengiriman SET status_pengiriman = ?, updated_at = NOW() WHERE id_pesanan = ?");
        $stmt_pengiriman->bind_param("si", $status_pengiriman, $id_pesanan);
        
        if (!$stmt_pengiriman->execute()) {
            throw new Exception("Gagal mengupdate status pengiriman: " . $conn->error);
        }
        $stmt_pengiriman->close();
        
        // Jika status pengiriman diubah menjadi "dikirim", catat waktu
        if ($status_pengiriman === 'dikirim') {
            // Update tanggal_dikirim di pesanan
            $stmt_tanggal = $conn->prepare("UPDATE pesanan_baru SET tanggal_dikirim = NOW(), updated_at = NOW() WHERE id_pesanan = ?");
            $stmt_tanggal->bind_param("i", $id_pesanan);
            $stmt_tanggal->execute();
            $stmt_tanggal->close();
            
            // UBAH INI: +3 minutes untuk testing (sebelumnya +2 hours)
            $estimasi_tiba = date('Y-m-d H:i:s', strtotime('+3 minutes'));
            $stmt_estimasi = $conn->prepare("UPDATE pengiriman SET estimasi_waktu_tiba = ? WHERE id_pesanan = ?");
            $stmt_estimasi->bind_param("si", $estimasi_tiba, $id_pesanan);
            $stmt_estimasi->execute();
            $stmt_estimasi->close();
            
            $message = "Status pengiriman berhasil diupdate! Pesanan dikirim dan dapat dikonfirmasi oleh user dalam 3 menit.";
        }
        
        // Jika status pengiriman diubah menjadi "selesai", update juga status pesanan
        if ($status_pengiriman === 'selesai') {
            $stmt_pesanan = $conn->prepare("UPDATE pesanan_baru SET status_pesanan = 'selesai', dikonfirmasi_oleh = 'admin', updated_at = NOW() WHERE id_pesanan = ?");
            $stmt_pesanan->bind_param("i", $id_pesanan);
            
            if (!$stmt_pesanan->execute()) {
                throw new Exception("Gagal mengupdate status pesanan: " . $conn->error);
            }
            $stmt_pesanan->close();
        }
        
        $conn->commit();
        if ($status_pengiriman !== 'dikirim') {
            $message = "Status pengiriman berhasil diupdate!" . ($status_pengiriman === 'selesai' ? " Status pesanan juga diupdate menjadi selesai." : "");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }
}

// Handle Upload Bukti Pembayaran Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti_admin'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    
    // Handle file upload
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === 0) {
        $uploadDir = 'images/uploads/bukti_pembayaran/';
        
        // Buat folder jika belum ada
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
        $fileName = 'admin_' . $id_pesanan . '_' . time() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;
        
        // Validasi file type
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        if (in_array(strtolower($fileExtension), $allowedTypes)) {
            if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $uploadFile)) {
                $bukti_pembayaran = $uploadFile;
                
                // Update database
                $stmt = $conn->prepare("UPDATE pesanan_baru SET bukti_pembayaran = ? WHERE id_pesanan = ?");
                $stmt->bind_param("si", $bukti_pembayaran, $id_pesanan);
                
                if ($stmt->execute()) {
                    $message = "Bukti pembayaran berhasil diupload!";
                } else {
                    $error = "Gagal menyimpan bukti pembayaran: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error = "Gagal mengupload file.";
            }
        } else {
            $error = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, GIF, atau PDF.";
        }
    } else {
        $error = "Silakan pilih file untuk diupload.";
    }
}

// Handle Update Info Pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_info'])) {
    $id_pesanan = (int)$_POST['id_pesanan'];
    $uang_dibayar = (float)$_POST['uang_dibayar'];
    $kembalian = (float)$_POST['kembalian'];
    
    $stmt = $conn->prepare("UPDATE pesanan_baru SET uang_dibayar = ?, kembalian = ? WHERE id_pesanan = ?");
    $stmt->bind_param("ddi", $uang_dibayar, $kembalian, $id_pesanan);
    
    if ($stmt->execute()) {
        $message = "Informasi pembayaran berhasil diupdate!";
    } else {
        $error = "Gagal mengupdate informasi pembayaran: " . $conn->error;
    }
    $stmt->close();
}

// ==================== ANALYTICS & REPORTS ====================

// Get analytics data
$top_products = [];
$monthly_sales = [];
$category_sales = [];
$payment_methods = [];
$customer_stats = [];
$popular_times = [];
$today_stats = [];
$week_stats = [];

// Get top 10 products
$top_products_query = $conn->query("
    SELECT 
        pib.nama_produk,
        SUM(pib.jumlah) as total_terjual,
        SUM(pib.subtotal) as total_pendapatan,
        COUNT(DISTINCT pib.id_pesanan) as total_pesanan
    FROM pesanan_items_baru pib
    JOIN pesanan_baru pb ON pib.id_pesanan = pb.id_pesanan
    WHERE pb.status_pesanan = 'selesai'
    GROUP BY pib.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 10
");

while ($row = $top_products_query->fetch_assoc()) {
    $top_products[] = $row;
}

// Get monthly sales (6 bulan terakhir)
$monthly_sales_query = $conn->query("
    SELECT 
        DATE_FORMAT(tanggal_pesanan, '%Y-%m') as bulan,
        COUNT(*) as total_pesanan,
        SUM(total_harga) as total_pendapatan,
        AVG(total_harga) as rata_rata_pesanan
    FROM pesanan_baru
    WHERE status_pesanan = 'selesai'
        AND tanggal_pesanan >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pesanan, '%Y-%m')
    ORDER BY bulan
");

while ($row = $monthly_sales_query->fetch_assoc()) {
    $monthly_sales[] = $row;
}

// Get sales by category
$category_sales_query = $conn->query("
    SELECT 
        COALESCE(pr.kategori, 'Tidak Ada Kategori') as kategori,
        SUM(pib.jumlah) as total_terjual,
        SUM(pib.subtotal) as total_pendapatan
    FROM pesanan_items_baru pib
    JOIN pesanan_baru pb ON pib.id_pesanan = pb.id_pesanan
    LEFT JOIN produk pr ON LOWER(pib.nama_produk) = LOWER(pr.nama_produk)
    WHERE pb.status_pesanan = 'selesai'
    GROUP BY pr.kategori
    ORDER BY total_pendapatan DESC
");

while ($row = $category_sales_query->fetch_assoc()) {
    $category_sales[] = $row;
}

// Get payment methods statistics
$payment_methods_query = $conn->query("
    SELECT 
        metode_pembayaran,
        COUNT(*) as total_pesanan,
        SUM(total_harga) as total_pendapatan,
        AVG(total_harga) as rata_rata_pesanan
    FROM pesanan_baru
    WHERE status_pesanan = 'selesai'
    GROUP BY metode_pembayaran
    ORDER BY total_pendapatan DESC
");

while ($row = $payment_methods_query->fetch_assoc()) {
    $payment_methods[] = $row;
}

// Get customer statistics
$customer_stats_query = $conn->query("
    SELECT 
        p.nama,
        COUNT(pb.id_pesanan) as total_pesanan,
        SUM(pb.total_harga) as total_belanja,
        AVG(pb.total_harga) as rata_rata_belanja,
        MAX(pb.tanggal_pesanan) as pesanan_terakhir
    FROM pesanan_baru pb
    JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan
    WHERE pb.status_pesanan = 'selesai'
    GROUP BY pb.id_pelanggan, p.nama
    ORDER BY total_belanja DESC
    LIMIT 10
");

while ($row = $customer_stats_query->fetch_assoc()) {
    $customer_stats[] = $row;
}

// Get popular times (jam/jam sibuk)
$popular_times_query = $conn->query("
    SELECT 
        HOUR(tanggal_pesanan) as jam,
        COUNT(*) as total_pesanan,
        SUM(total_harga) as total_pendapatan
    FROM pesanan_baru
    WHERE status_pesanan = 'selesai'
    GROUP BY HOUR(tanggal_pesanan)
    ORDER BY total_pendapatan DESC
");

while ($row = $popular_times_query->fetch_assoc()) {
    $popular_times[] = $row;
}

// Get today's quick stats
$today_stats = $conn->query("
    SELECT 
        COUNT(*) as pesanan_hari_ini,
        SUM(total_harga) as pendapatan_hari_ini,
        AVG(total_harga) as rata_rata_hari_ini
    FROM pesanan_baru
    WHERE DATE(tanggal_pesanan) = CURDATE()
        AND status_pesanan = 'selesai'
")->fetch_assoc();

// Get weekly stats
$week_stats = $conn->query("
    SELECT 
        COUNT(*) as pesanan_minggu_ini,
        SUM(total_harga) as pendapatan_minggu_ini
    FROM pesanan_baru
    WHERE YEARWEEK(tanggal_pesanan, 1) = YEARWEEK(CURDATE(), 1)
        AND status_pesanan = 'selesai'
")->fetch_assoc();

// ==================== EXPORT HANDLERS WITH BORDERS ====================

// Handle Export to Excel - Simple Report with Borders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_excel'])) {
    // Ambil parameter filter
    $start_date = $_POST['start_date'] ?? date('Y-m-01');
    $end_date = $_POST['end_date'] ?? date('Y-m-t');
    $status_filter = $_POST['status_filter'] ?? 'all';
    $payment_filter = $_POST['payment_filter'] ?? 'all';
    
    // Build query untuk export
    $export_query = "SELECT 
        pb.id_pesanan,
        pb.tanggal_pesanan,
        p.nama as nama_pelanggan,
        pb.metode_pembayaran,
        pb.status_pesanan,
        pb.total_harga,
        pb.total_diskon,
        pb.uang_dibayar,
        pb.kembalian,
        pb.dikonfirmasi_oleh,
        pb.tanggal_dikirim,
        pb.tanggal_konfirmasi_user,
        pg.status_pengiriman,
        pg.nama_kurir
    FROM pesanan_baru pb 
    JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan
    LEFT JOIN pengiriman pg ON pb.id_pesanan = pg.id_pesanan
    WHERE pb.tanggal_pesanan BETWEEN ? AND ?";
    
    $params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    $types = 'ss';
    
    if ($status_filter !== 'all') {
        $export_query .= " AND pb.status_pesanan = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    if ($payment_filter !== 'all') {
        $export_query .= " AND pb.metode_pembayaran = ?";
        $params[] = $payment_filter;
        $types .= 's';
    }
    
    $export_query .= " ORDER BY pb.tanggal_pesanan DESC";
    
    $stmt = $conn->prepare($export_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $export_result = $stmt->get_result();
    
    // Set header untuk download Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="laporan_penjualan_' . date('Y-m-d') . '.xls"');
    
    // Output Excel dengan border
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }";
    echo "th { background-color: #4CAF50; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }";
    echo "td { padding: 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }";
    echo ".header { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }";
    echo ".subheader { font-size: 14px; text-align: center; margin-bottom: 20px; color: #666; }";
    echo ".summary { margin-top: 20px; padding: 10px; background-color: #f9f9f9; border: 1px solid #ddd; }";
    echo ".summary h4 { margin-top: 0; color: #333; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo ".bold { font-weight: bold; }";
    echo ".border-all { border: 1px solid #000; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    // Header Laporan
    echo "<div class='header'>LAPORAN PENJUALAN BAKER OLD</div>";
    echo "<div class='subheader'>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</div>";
    
    // Tabel Data
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>ID Pesanan</th>";
    echo "<th>Tanggal</th>";
    echo "<th>Nama Pelanggan</th>";
    echo "<th>Metode Pembayaran</th>";
    echo "<th>Status</th>";
    echo "<th>Total Harga</th>";
    echo "<th>Diskon</th>";
    echo "<th>Uang Dibayar</th>";
    echo "<th>Kembalian</th>";
    echo "<th>Dikonfirmasi Oleh</th>";
    echo "<th>Status Pengiriman</th>";
    echo "<th>Kurir</th>";
    echo "<th>Tanggal Dikirim</th>";
    echo "<th>Waktu Konfirmasi</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $no = 1;
    $total_penjualan = 0;
    $total_selesai = 0;
    $total_user_confirm = 0;
    $total_diskon = 0;
    $total_pesanan = 0;
    
    while ($row = $export_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td class='center'>" . $no++ . "</td>";
        echo "<td>" . $row['id_pesanan'] . "</td>";
        echo "<td>" . date('d/m/Y H:i', strtotime($row['tanggal_pesanan'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_pelanggan']) . "</td>";
        echo "<td class='center'>" . strtoupper($row['metode_pembayaran']) . "</td>";
        echo "<td class='center'>" . ucfirst($row['status_pesanan']) . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_harga'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_diskon'], 0, ',', '.') . "</td>";
        echo "<td class='number'>" . ($row['uang_dibayar'] ? "Rp " . number_format($row['uang_dibayar'], 0, ',', '.') : "-") . "</td>";
        echo "<td class='number'>" . ($row['kembalian'] ? "Rp " . number_format($row['kembalian'], 0, ',', '.') : "-") . "</td>";
        echo "<td class='center'>" . ucfirst($row['dikonfirmasi_oleh'] ?? 'admin') . "</td>";
        echo "<td class='center'>" . ucfirst(str_replace('_', ' ', $row['status_pengiriman'] ?? '-')) . "</td>";
        echo "<td>" . ($row['nama_kurir'] ?? '-') . "</td>";
        echo "<td>" . ($row['tanggal_dikirim'] ? date('d/m/Y H:i', strtotime($row['tanggal_dikirim'])) : '-') . "</td>";
        echo "<td>" . ($row['tanggal_konfirmasi_user'] ? date('d/m/Y H:i', strtotime($row['tanggal_konfirmasi_user'])) : '-') . "</td>";
        echo "</tr>";
        
        $total_penjualan += $row['total_harga'];
        $total_diskon += $row['total_diskon'];
        $total_pesanan++;
        
        if ($row['status_pesanan'] === 'selesai') {
            $total_selesai++;
        }
        if ($row['dikonfirmasi_oleh'] === 'user') {
            $total_user_confirm++;
        }
    }
    
    echo "</tbody>";
    
    // Footer dengan total
    echo "<tfoot>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td colspan='6' class='center'>TOTAL</td>";
    echo "<td class='number'>Rp " . number_format($total_penjualan, 0, ',', '.') . "</td>";
    echo "<td class='number'>Rp " . number_format($total_diskon, 0, ',', '.') . "</td>";
    echo "<td colspan='8'></td>";
    echo "</tr>";
    echo "</tfoot>";
    
    echo "</table>";
    
    // Summary Section
    echo "<div class='summary'>";
    echo "<h4>RINGKASAN LAPORAN</h4>";
    echo "<table border='0' style='width: 100%; border: none;'>";
    echo "<tr>";
    echo "<td style='width: 50%; vertical-align: top;'>";
    echo "<strong>Statistik Pesanan:</strong><br>";
    echo "• Total Pesanan: " . $total_pesanan . "<br>";
    echo "• Pesanan Selesai: " . $total_selesai . " (" . ($total_pesanan > 0 ? round(($total_selesai / $total_pesanan) * 100, 2) : 0) . "%)<br>";
    echo "• Dikonfirmasi User: " . $total_user_confirm . "<br>";
    echo "</td>";
    echo "<td style='width: 50%; vertical-align: top;'>";
    echo "<strong>Statistik Keuangan:</strong><br>";
    echo "• Total Penjualan: Rp " . number_format($total_penjualan, 0, ',', '.') . "<br>";
    echo "• Total Diskon: Rp " . number_format($total_diskon, 0, ',', '.') . "<br>";
    echo "• Rata-rata Pesanan: Rp " . ($total_pesanan > 0 ? number_format($total_penjualan / $total_pesanan, 0, ',', '.') : 0) . "<br>";
    echo "</td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td colspan='2' style='padding-top: 10px; font-size: 11px; color: #666;'>";
   
    echo "Laporan dihasilkan pada: " . date('d/m/Y H:i:s') . " oleh Admin";
    echo "</td>";
    echo "</tr>";
    echo "</table>";
    echo "</div>";
    
    echo "</body>";
    echo "</html>";
    exit;
}

// Function untuk export analytics produk dengan border
function exportProductsAnalytics($conn, $start_date, $end_date) {
    $query = "SELECT 
        pib.nama_produk,
        SUM(pib.jumlah) as total_terjual,
        SUM(pib.subtotal) as total_pendapatan,
        COUNT(DISTINCT pib.id_pesanan) as total_pesanan,
        AVG(pib.harga) as harga_rata_rata
    FROM pesanan_items_baru pib
    JOIN pesanan_baru pb ON pib.id_pesanan = pb.id_pesanan
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY pib.nama_produk
    ORDER BY total_terjual DESC";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }";
    echo "th { background-color: #2c3e50; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }";
    echo "td { padding: 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }";
    echo ".header { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }";
    echo ".subheader { font-size: 14px; text-align: center; margin-bottom: 20px; color: #666; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<div class='header'>ANALYTICS PRODUK TERLARIS - BAKER OLD</div>";
    echo "<div class='subheader'>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</div>";
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Produk</th>";
    echo "<th>Terjual</th>";
    echo "<th>Pendapatan</th>";
    echo "<th>Pesanan</th>";
    echo "<th>Harga Rata-rata</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $no = 1;
    $total_terjual = 0;
    $total_pendapatan = 0;
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td class='center'>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_produk']) . "</td>";
        echo "<td class='number'>" . number_format($row['total_terjual']) . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</td>";
        echo "<td class='center'>" . $row['total_pesanan'] . "</td>";
        echo "<td class='number'>Rp " . number_format($row['harga_rata_rata'], 0, ',', '.') . "</td>";
        echo "</tr>";
        
        $total_terjual += $row['total_terjual'];
        $total_pendapatan += $row['total_pendapatan'];
    }
    
    echo "</tbody>";
    echo "<tfoot>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td colspan='2' class='center'>TOTAL</td>";
    echo "<td class='number'>" . number_format($total_terjual) . "</td>";
    echo "<td class='number'>Rp " . number_format($total_pendapatan, 0, ',', '.') . "</td>";
    echo "<td colspan='2'></td>";
    echo "</tr>";
    echo "</tfoot>";
    echo "</table>";
    echo "</body>";
    echo "</html>";
}

// Function untuk export analytics penjualan dengan border
function exportSalesAnalytics($conn, $start_date, $end_date) {
    $query = "SELECT 
        DATE(pb.tanggal_pesanan) as tanggal,
        COUNT(*) as total_pesanan,
        SUM(pb.total_harga) as total_pendapatan,
        AVG(pb.total_harga) as rata_rata_pesanan
    FROM pesanan_baru pb
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY DATE(pb.tanggal_pesanan)
    ORDER BY tanggal";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }";
    echo "th { background-color: #3498db; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }";
    echo "td { padding: 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }";
    echo ".header { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }";
    echo ".subheader { font-size: 14px; text-align: center; margin-bottom: 20px; color: #666; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<div class='header'>ANALYTICS PENJUALAN - BAKER OLD</div>";
    echo "<div class='subheader'>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</div>";
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Tanggal</th>";
    echo "<th>Pesanan</th>";
    echo "<th>Pendapatan</th>";
    echo "<th>Rata-rata</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $no = 1;
    $total_pesanan = 0;
    $total_pendapatan = 0;
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td class='center'>" . $no++ . "</td>";
        echo "<td>" . date('d/m/Y', strtotime($row['tanggal'])) . "</td>";
        echo "<td class='center'>" . $row['total_pesanan'] . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['rata_rata_pesanan'], 0, ',', '.') . "</td>";
        echo "</tr>";
        
        $total_pesanan += $row['total_pesanan'];
        $total_pendapatan += $row['total_pendapatan'];
    }
    
    echo "</tbody>";
    echo "<tfoot>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td colspan='2' class='center'>TOTAL</td>";
    echo "<td class='center'>" . number_format($total_pesanan) . "</td>";
    echo "<td class='number'>Rp " . number_format($total_pendapatan, 0, ',', '.') . "</td>";
    echo "<td class='number'>Rp " . ($total_pesanan > 0 ? number_format($total_pendapatan / $total_pesanan, 0, ',', '.') : 0) . "</td>";
    echo "</tr>";
    echo "</tfoot>";
    echo "</table>";
    echo "</body>";
    echo "</html>";
}

// Function untuk export analytics pelanggan dengan border
function exportCustomersAnalytics($conn, $start_date, $end_date) {
    $query = "SELECT 
        p.nama,
        COUNT(pb.id_pesanan) as total_pesanan,
        SUM(pb.total_harga) as total_belanja,
        AVG(pb.total_harga) as rata_rata_belanja,
        MAX(pb.tanggal_pesanan) as pesanan_terakhir
    FROM pesanan_baru pb
    JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY pb.id_pelanggan, p.nama
    ORDER BY total_belanja DESC";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }";
    echo "th { background-color: #9b59b6; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }";
    echo "td { padding: 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }";
    echo ".header { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }";
    echo ".subheader { font-size: 14px; text-align: center; margin-bottom: 20px; color: #666; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<div class='header'>ANALYTICS PELANGGAN - BAKER OLD</div>";
    echo "<div class='subheader'>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</div>";
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Nama Pelanggan</th>";
    echo "<th>Total Pesanan</th>";
    echo "<th>Total Belanja</th>";
    echo "<th>Rata-rata</th>";
    echo "<th>Pesanan Terakhir</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $no = 1;
    $total_customers = 0;
    $total_belanja = 0;
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td class='center'>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td class='center'>" . $row['total_pesanan'] . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_belanja'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['rata_rata_belanja'], 0, ',', '.') . "</td>";
        echo "<td>" . ($row['pesanan_terakhir'] ? date('d/m/Y', strtotime($row['pesanan_terakhir'])) : '-') . "</td>";
        echo "</tr>";
        
        $total_customers++;
        $total_belanja += $row['total_belanja'];
    }
    
    echo "</tbody>";
    echo "<tfoot>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td colspan='3' class='center'>TOTAL</td>";
    echo "<td class='number'>Rp " . number_format($total_belanja, 0, ',', '.') . "</td>";
    echo "<td class='number'>Rp " . ($total_customers > 0 ? number_format($total_belanja / $total_customers, 0, ',', '.') : 0) . "</td>";
    echo "<td></td>";
    echo "</tr>";
    echo "</tfoot>";
    echo "</table>";
    echo "</body>";
    echo "</html>";
}

// Function untuk export analytics pembayaran dengan border
function exportPaymentsAnalytics($conn, $start_date, $end_date) {
    $query = "SELECT 
        pb.metode_pembayaran,
        COUNT(*) as total_pesanan,
        SUM(pb.total_harga) as total_pendapatan,
        AVG(pb.total_harga) as rata_rata_pesanan
    FROM pesanan_baru pb
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY pb.metode_pembayaran
    ORDER BY total_pendapatan DESC";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }";
    echo "th { background-color: #e74c3c; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }";
    echo "td { padding: 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }";
    echo ".header { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 10px; }";
    echo ".subheader { font-size: 14px; text-align: center; margin-bottom: 20px; color: #666; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<div class='header'>ANALYTICS METODE PEMBAYARAN - BAKER OLD</div>";
    echo "<div class='subheader'>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</div>";
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Metode Pembayaran</th>";
    echo "<th>Pesanan</th>";
    echo "<th>Pendapatan</th>";
    echo "<th>Rata-rata</th>";
    echo "<th>Persentase</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $no = 1;
    $total_pesanan = 0;
    $total_pendapatan = 0;
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $total_pesanan += $row['total_pesanan'];
        $total_pendapatan += $row['total_pendapatan'];
    }
    
    foreach ($data as $row) {
        $percentage = $total_pendapatan > 0 ? ($row['total_pendapatan'] / $total_pendapatan) * 100 : 0;
        
        echo "<tr>";
        echo "<td class='center'>" . $no++ . "</td>";
        echo "<td class='center'>" . strtoupper($row['metode_pembayaran']) . "</td>";
        echo "<td class='center'>" . number_format($row['total_pesanan']) . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['rata_rata_pesanan'], 0, ',', '.') . "</td>";
        echo "<td class='number'>" . number_format($percentage, 2) . "%</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "<tfoot>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td colspan='2' class='center'>TOTAL</td>";
    echo "<td class='center'>" . number_format($total_pesanan) . "</td>";
    echo "<td class='number'>Rp " . number_format($total_pendapatan, 0, ',', '.') . "</td>";
    echo "<td colspan='2'></td>";
    echo "</tr>";
    echo "</tfoot>";
    echo "</table>";
    echo "</body>";
    echo "</html>";
}

// Function untuk export full analytics dengan border
function exportFullAnalytics($conn, $start_date, $end_date) {
    // Gabungkan semua laporan menjadi satu HTML
    echo "<html>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<style>";
    echo "table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; margin-bottom: 30px; }";
    echo "th { background-color: #2c3e50; color: white; font-weight: bold; text-align: center; padding: 8px; border: 1px solid #ddd; }";
    echo "td { padding: 6px; border: 1px solid #ddd; text-align: left; vertical-align: top; }";
    echo ".header { font-size: 22px; font-weight: bold; text-align: center; margin-bottom: 5px; color: #2c3e50; }";
    echo ".subheader { font-size: 16px; text-align: center; margin-bottom: 10px; color: #7f8c8d; }";
    echo ".section-header { font-size: 18px; font-weight: bold; margin: 30px 0 15px 0; color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; }";
    echo ".number { text-align: right; }";
    echo ".center { text-align: center; }";
    echo ".summary { background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; margin: 20px 0; border-radius: 5px; }";
    echo ".page-break { page-break-after: always; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
    
    echo "<div class='header'>LAPORAN ANALYTICS LENGKAP - BAKER OLD</div>";
    echo "<div class='subheader'>Periode: " . date('d/m/Y', strtotime($start_date)) . " - " . date('d/m/Y', strtotime($end_date)) . "</div>";
    echo "<div class='subheader'>Dihasilkan pada: " . date('d/m/Y H:i:s') . "</div>";
    
    // Ambil data untuk masing-masing bagian
    $products_data = getProductsData($conn, $start_date, $end_date);
    $sales_data = getSalesData($conn, $start_date, $end_date);
    $customers_data = getCustomersData($conn, $start_date, $end_date);
    $payments_data = getPaymentsData($conn, $start_date, $end_date);
    
    // Section 1: Products
    echo "<div class='section-header'>PRODUK TERLARIS</div>";
    echoProductsTable($products_data);
    
    // Section 2: Sales
    echo "<div class='section-header'>ANALISIS PENJUALAN</div>";
    echoSalesTable($sales_data);
    
    // Section 3: Customers
    echo "<div class='section-header'>ANALISIS PELANGGAN</div>";
    echoCustomersTable($customers_data);
    
    // Section 4: Payments
    echo "<div class='section-header'>ANALISIS PEMBAYARAN</div>";
    echoPaymentsTable($payments_data);
    
    // Summary
    echo "<div class='summary'>";
    echo "<h4>RINGKASAN EXECUTIVE</h4>";
    echo "<p>• Total Produk Terjual: " . array_sum(array_column($products_data, 'total_terjual')) . " unit</p>";
    echo "<p>• Total Pendapatan: Rp " . number_format(array_sum(array_column($products_data, 'total_pendapatan')), 0, ',', '.') . "</p>";
    echo "<p>• Total Pelanggan: " . count($customers_data) . " orang</p>";
    echo "<p>• Rata-rata Belanja per Pelanggan: Rp " . 
         (count($customers_data) > 0 ? number_format(array_sum(array_column($customers_data, 'total_belanja')) / count($customers_data), 0, ',', '.') : 0) . "</p>";
    echo "<p>• Metode Pembayaran Terpopuler: " . 
         (!empty($payments_data) ? strtoupper($payments_data[0]['metode_pembayaran']) : '-') . 
         " (" . (!empty($payments_data) ? number_format($payments_data[0]['total_pesanan']) : 0) . " transaksi)</p>";
    echo "</div>";
    
    echo "</body>";
    echo "</html>";
}

// Helper functions untuk full analytics
function getProductsData($conn, $start_date, $end_date) {
    $query = "SELECT 
        pib.nama_produk,
        SUM(pib.jumlah) as total_terjual,
        SUM(pib.subtotal) as total_pendapatan,
        COUNT(DISTINCT pib.id_pesanan) as total_pesanan
    FROM pesanan_items_baru pib
    JOIN pesanan_baru pb ON pib.id_pesanan = pb.id_pesanan
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY pib.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 10";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getSalesData($conn, $start_date, $end_date) {
    $query = "SELECT 
        DATE(pb.tanggal_pesanan) as tanggal,
        COUNT(*) as total_pesanan,
        SUM(pb.total_harga) as total_pendapatan,
        AVG(pb.total_harga) as rata_rata_pesanan
    FROM pesanan_baru pb
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY DATE(pb.tanggal_pesanan)
    ORDER BY tanggal";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getCustomersData($conn, $start_date, $end_date) {
    $query = "SELECT 
        p.nama,
        COUNT(pb.id_pesanan) as total_pesanan,
        SUM(pb.total_harga) as total_belanja,
        AVG(pb.total_harga) as rata_rata_belanja,
        MAX(pb.tanggal_pesanan) as pesanan_terakhir
    FROM pesanan_baru pb
    JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY pb.id_pelanggan, p.nama
    ORDER BY total_belanja DESC
    LIMIT 10";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function getPaymentsData($conn, $start_date, $end_date) {
    $query = "SELECT 
        pb.metode_pembayaran,
        COUNT(*) as total_pesanan,
        SUM(pb.total_harga) as total_pendapatan,
        AVG(pb.total_harga) as rata_rata_pesanan
    FROM pesanan_baru pb
    WHERE pb.status_pesanan = 'selesai'
        AND pb.tanggal_pesanan BETWEEN ? AND ?
    GROUP BY pb.metode_pembayaran
    ORDER BY total_pendapatan DESC";
    
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_datetime, $end_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function echoProductsTable($data) {
    if (empty($data)) {
        echo "<p>Tidak ada data produk</p>";
        return;
    }
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Rank</th>";
    echo "<th>Produk</th>";
    echo "<th>Terjual</th>";
    echo "<th>Pendapatan</th>";
    echo "<th>Pesanan</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $rank = 1;
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td class='center'>" . $rank++ . "</td>";
        echo "<td>" . htmlspecialchars($row['nama_produk']) . "</td>";
        echo "<td class='number'>" . number_format($row['total_terjual']) . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</td>";
        echo "<td class='center'>" . $row['total_pesanan'] . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

function echoSalesTable($data) {
    if (empty($data)) {
        echo "<p>Tidak ada data penjualan</p>";
        return;
    }
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Tanggal</th>";
    echo "<th>Pesanan</th>";
    echo "<th>Pendapatan</th>";
    echo "<th>Rata-rata</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $total_pesanan = 0;
    $total_pendapatan = 0;
    
    foreach ($data as $row) {
        echo "<tr>";
        echo "<td>" . date('d/m/Y', strtotime($row['tanggal'])) . "</td>";
        echo "<td class='center'>" . $row['total_pesanan'] . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['rata_rata_pesanan'], 0, ',', '.') . "</td>";
        echo "</tr>";
        
        $total_pesanan += $row['total_pesanan'];
        $total_pendapatan += $row['total_pendapatan'];
    }
    
    echo "</tbody>";
    echo "<tfoot>";
    echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>";
    echo "<td>TOTAL</td>";
    echo "<td class='center'>" . number_format($total_pesanan) . "</td>";
    echo "<td class='number'>Rp " . number_format($total_pendapatan, 0, ',', '.') . "</td>";
    echo "<td class='number'>Rp " . ($total_pesanan > 0 ? number_format($total_pendapatan / $total_pesanan, 0, ',', '.') : 0) . "</td>";
    echo "</tr>";
    echo "</tfoot>";
    echo "</table>";
}

function echoCustomersTable($data) {
    if (empty($data)) {
        echo "<p>Tidak ada data pelanggan</p>";
        return;
    }
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Nama</th>";
    echo "<th>Pesanan</th>";
    echo "<th>Total Belanja</th>";
    echo "<th>Rata-rata</th>";
    echo "<th>Status</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($data as $row) {
        $status = '';
        if ($row['total_pesanan'] >= 10) {
            $status = '<span style="color: #d4af37; font-weight: bold;">VIP</span>';
        } elseif ($row['total_pesanan'] >= 5) {
            $status = '<span style="color: #c0c0c0; font-weight: bold;">Regular</span>';
        } else {
            $status = '<span style="color: #cd7f32;">New</span>';
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['nama']) . "</td>";
        echo "<td class='center'>" . $row['total_pesanan'] . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_belanja'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['rata_rata_belanja'], 0, ',', '.') . "</td>";
        echo "<td class='center'>" . $status . "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

function echoPaymentsTable($data) {
    if (empty($data)) {
        echo "<p>Tidak ada data pembayaran</p>";
        return;
    }
    
    echo "<table border='1'>";
    echo "<thead>";
    echo "<tr>";
    echo "<th>Metode</th>";
    echo "<th>Pesanan</th>";
    echo "<th>Pendapatan</th>";
    echo "<th>Rata-rata</th>";
    echo "<th>%</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    $total_pendapatan = array_sum(array_column($data, 'total_pendapatan'));
    
    foreach ($data as $row) {
        $percentage = $total_pendapatan > 0 ? ($row['total_pendapatan'] / $total_pendapatan) * 100 : 0;
        
        echo "<tr>";
        echo "<td class='center'>" . strtoupper($row['metode_pembayaran']) . "</td>";
        echo "<td class='center'>" . number_format($row['total_pesanan']) . "</td>";
        echo "<td class='number'>Rp " . number_format($row['total_pendapatan'], 0, ',', '.') . "</td>";
        echo "<td class='number'>Rp " . number_format($row['rata_rata_pesanan'], 0, ',', '.') . "</td>";
        echo "<td class='number'>" . number_format($percentage, 1) . "%</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
}

// Handle Export Analytics Report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_analytics'])) {
    $report_type = $_POST['report_type'];
    $start_date = $_POST['analytics_start_date'] ?? date('Y-m-01');
    $end_date = $_POST['analytics_end_date'] ?? date('Y-m-t');
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="analytics_' . $report_type . '_' . date('Y-m-d') . '.xls"');
    
    switch ($report_type) {
        case 'products':
            exportProductsAnalytics($conn, $start_date, $end_date);
            break;
        case 'sales':
            exportSalesAnalytics($conn, $start_date, $end_date);
            break;
        case 'customers':
            exportCustomersAnalytics($conn, $start_date, $end_date);
            break;
        case 'payments':
            exportPaymentsAnalytics($conn, $start_date, $end_date);
            break;
        default:
            exportFullAnalytics($conn, $start_date, $end_date);
    }
    exit;
}

// Search functionality
$search = '';
$where_conditions = [];
$params = [];
$types = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
    if (is_numeric($search)) {
        // Search by id_pesanan
        $where_conditions[] = "pb.id_pesanan = ?";
        $params[] = $search;
        $types .= 'i';
    } else {
        // Search by customer name
        $where_conditions[] = "p.nama LIKE ?";
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
}

// Filter by status
if (isset($_GET['status']) && $_GET['status'] !== 'all' && !empty($_GET['status'])) {
    $where_conditions[] = "pb.status_pesanan = ?";
    $params[] = $_GET['status'];
    $types .= 's';
}

// Build query
$query = "SELECT pb.*, p.nama, p.email, p.no_hp, 
                 pg.id_pengiriman, pg.status_pengiriman, pg.nama_kurir, 
                 pg.estimasi_waktu_tiba, pg.waktu_tiba, pg.konfirmasi_diterima
          FROM pesanan_baru pb 
          JOIN pengguna p ON pb.id_pelanggan = p.id_pelanggan
          LEFT JOIN pengiriman pg ON pb.id_pesanan = pg.id_pesanan";

if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

$query .= " ORDER BY pb.tanggal_pesanan DESC";

// Prepare and execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get total orders count for delete all confirmation
$total_orders_query = $conn->query("SELECT COUNT(*) as total FROM pesanan_baru");
$total_orders = $total_orders_query->fetch_assoc()['total'];

// Status options
$status_options = ['pending', 'proses pembuatan', 'dikirim', 'selesai'];
$shipping_status_options = ['diproses', 'dikirim', 'dalam_perjalanan', 'selesai'];

// Get notification count
$notif_query = $conn->query("SELECT COUNT(*) as count FROM notifikasi_admin WHERE dibaca = 0");
$notif_count = $notif_query->fetch_assoc()['count'];

// Get total sales
$total_sales = $conn->query("SELECT SUM(total_harga) as total FROM pesanan_baru WHERE status_pesanan = 'selesai'")->fetch_assoc();
$avg_order = $conn->query("SELECT AVG(total_harga) as avg FROM pesanan_baru WHERE status_pesanan = 'selesai'")->fetch_assoc();
$total_customers = $conn->query("SELECT COUNT(DISTINCT id_pelanggan) as total FROM pesanan_baru WHERE status_pesanan = 'selesai'")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Pesanan & Analytics - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            border-radius: 0 0 20px 20px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-proses-pembuatan {
            background: #cce7ff;
            color: #004085;
        }
        .status-dikirim {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-selesai {
            background: #d4edda;
            color: #155724;
        }
        .shipping-status-badge {
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }
        .shipping-diproses { background: #fff3cd; color: #856404; }
        .shipping-dikirim { background: #cce7ff; color: #004085; }
        .shipping-dalam_perjalanan { background: #d1ecf1; color: #0c5460; }
        .shipping-selesai { background: #d4edda; color: #155724; }
        .order-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-5px);
        }
        .order-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
            border-radius: 15px 15px 0 0;
        }
        .order-body {
            padding: 20px;
        }
        .product-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        .action-buttons .btn {
            margin: 2px;
        }
        .payment-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        .bukti-img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .bukti-img:hover {
            transform: scale(1.05);
        }
        .modal-bukti-img {
            max-width: 100%;
            max-height: 80vh;
        }
        .search-box {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .delete-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
        }
        .delete-btn:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            color: white;
        }
        .warning-text {
            color: #dc3545;
            font-weight: bold;
        }
        .shipping-info {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
            border-left: 4px solid #007bff;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .report-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .confirmation-badge {
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }
        .confirmed-by-admin { background: #cce7ff; color: #004085; }
        .confirmed-by-user { background: #d4edda; color: #155724; }
        .testing-badge {
            background: #ffc107;
            color: #212529;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
        }
        .timer-badge {
            background: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
            font-family: monospace;
        }
        .time-info {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
        }
        .testing-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-size: 12px;
        }
        
        /* Analytics Styles */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .analytics-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .analytics-card h6 {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .chart-placeholder {
            width: 100%;
            min-height: 300px;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 14px;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .stats-sub {
            font-size: 12px;
            color: #28a745;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
            padding: 10px 15px;
            border: none;
        }
        
        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            background: transparent;
        }
        
        .product-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .badge-gold { background: #ffd700; color: #000; }
        .badge-silver { background: #c0c0c0; color: #000; }
        .badge-bronze { background: #cd7f32; color: #fff; }
        
        .progress-wrapper {
            margin-bottom: 15px;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
            background: #e9ecef;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 5px;
            transition: width 0.5s ease;
        }
        
        .trend-up {
            color: #28a745;
        }
        
        .trend-down {
            color: #dc3545;
        }
        
        .trend-stable {
            color: #6c757d;
        }
        
        .analytics-table {
            font-size: 12px;
        }
        
        .analytics-table th {
            background: #f8f9fa;
        }
        
        .modal-xl {
            max-width: 95%;
        }
        
        /* Styles untuk Export Modal */
        .export-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .export-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .export-option:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        
        .export-option.active {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        
        .export-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: #0d6efd;
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
                <a class="nav-link" href="admin_shipping.php"><i class="fas fa-shipping-fast"></i> Pengiriman</a>
                <a class="nav-link active" href="admin_orders.php"><i class="fas fa-clipboard-list"></i> Pesanan & Analytics</a>
                <a class="nav-link position-relative" href="admin_notifications.php">
                    <i class="fas fa-bell"></i> Notifikasi
                    <?php if ($notif_count > 0): ?>
                        <span class="notification-badge"><?= $notif_count ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="admin_kategori.php"><i class="fas fa-tags"></i> Kategori</a>
                <a class="nav-link" href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="display-5 fw-bold"><i class="fas fa-chart-line me-3"></i>Analytics & Manajemen Pesanan</h1>
                    <p class="lead mb-0">Kelola pesanan dan analisis performa penjualan</p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <button class="btn btn-light btn-lg dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-filter me-2"></i>Filter Status
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?status=all<?= $search ? '&search=' . urlencode($search) : '' ?>">Semua Status</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?status=pending<?= $search ? '&search=' . urlencode($search) : '' ?>">Pending</a></li>
                            <li><a class="dropdown-item" href="?status=proses pembuatan<?= $search ? '&search=' . urlencode($search) : '' ?>">Proses Pembuatan</a></li>
                            <li><a class="dropdown-item" href="?status=dikirim<?= $search ? '&search=' . urlencode($search) : '' ?>">Dikirim</a></li>
                            <li><a class="dropdown-item" href="?status=selesai<?= $search ? '&search=' . urlencode($search) : '' ?>">Selesai</a></li>
                        </ul>
                    </div>
                    <!-- Analytics Button -->
                    <button class="btn btn-primary btn-lg ms-2" data-bs-toggle="modal" data-bs-target="#analyticsModal">
                        <i class="fas fa-chart-bar me-2"></i>Analytics
                    </button>
                    <!-- Delete All Button -->
                    <button class="btn delete-btn btn-lg ms-2" 
                            data-bs-toggle="modal" 
                            data-bs-target="#deleteAllModal"
                            <?= $total_orders == 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-trash-alt me-2"></i>Hapus Semua
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

    <!-- Quick Analytics Overview -->
    <div class="container">
        <div class="report-section">
            <h4><i class="fas fa-tachometer-alt me-2"></i>Dashboard Analytics Cepat</h4>
            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-primary">
                            <?= number_format($total_orders) ?>
                        </div>
                        <div class="stats-label">Total Pesanan</div>
                        <div class="stats-sub">
                            <?= number_format($total_sales['total'] ?? 0, 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-success">
                            <?= number_format($today_stats['pesanan_hari_ini'] ?? 0) ?>
                        </div>
                        <div class="stats-label">Pesanan Hari Ini</div>
                        <div class="stats-sub">
                            Rp <?= number_format($today_stats['pendapatan_hari_ini'] ?? 0, 0, ',', '.') ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-warning">
                            <?= number_format($total_customers['total'] ?? 0) ?>
                        </div>
                        <div class="stats-label">Total Pelanggan</div>
                        <div class="stats-sub">
                            Rp <?= number_format($avg_order['avg'] ?? 0, 0, ',', '.') ?> / pesanan
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-info">
                            <?= count($top_products) ?>
                        </div>
                        <div class="stats-label">Produk Terlaris</div>
                        <div class="stats-sub">
                            <?= $top_products[0]['nama_produk'] ?? '-' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="container">
        <div class="search-box">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan ID Pesanan atau Nama Pelanggan..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="admin_orders.php" class="btn btn-secondary w-100">
                        <i class="fas fa-refresh me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders List -->
    <div class="container mt-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($order = $result->fetch_assoc()): 
                // Ambil items pesanan
                $items_query = $conn->prepare("
                    SELECT pib.*, pr.gambar_produk 
                    FROM pesanan_items_baru pib 
                    LEFT JOIN produk pr ON LOWER(pib.nama_produk) = LOWER(pr.nama_produk)
                    WHERE pib.id_pesanan = ?
                ");
                $items_query->bind_param("i", $order['id_pesanan']);
                $items_query->execute();
                $items_result = $items_query->get_result();
                
                // Hitung sisa waktu untuk konfirmasi (3 menit)
                $waktu_tersisa = '';
                $bisa_konfirmasi = false;
                if ($order['status_pengiriman'] === 'dalam_perjalanan' && $order['tanggal_dikirim']) {
                    $waktu_dikirim = strtotime($order['tanggal_dikirim']);
                    $waktu_sekarang = time();
                    $selisih_detik = $waktu_sekarang - $waktu_dikirim;
                    $detik_menunggu = 3 * 60; // 3 menit
                    
                    if ($selisih_detik < $detik_menunggu) {
                        $sisa_detik = $detik_menunggu - $selisih_detik;
                        $menit = floor($sisa_detik / 60);
                        $detik = $sisa_detik % 60;
                        $waktu_tersisa = sprintf('%02d:%02d', $menit, $detik);
                    } else {
                        $bisa_konfirmasi = true;
                    }
                }
            ?>
                <div class="card order-card">
                    <div class="order-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-1">Pesanan #<?= $order['id_pesanan'] ?></h5>
                                <p class="mb-0 text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?= date('d M Y H:i', strtotime($order['tanggal_pesanan'])) ?>
                                </p>
                                <?php if ($order['dikonfirmasi_oleh']): ?>
                                    <small class="text-muted">
                                        Dikonfirmasi oleh: 
                                        <span class="confirmation-badge confirmed-by-<?= $order['dikonfirmasi_oleh'] ?>">
                                            <?= $order['dikonfirmasi_oleh'] == 'user' ? 'Pelanggan' : 'Admin' ?>
                                        </span>
                                        <?php if ($order['dikonfirmasi_oleh'] == 'user'): ?>
                                            <span class="testing-badge">Testing Mode</span>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                                <?php if ($order['tanggal_konfirmasi_user']): ?>
                                    <small class="time-info">
                                        <i class="fas fa-clock"></i> Dikonfirmasi: <?= date('d/m/Y H:i', strtotime($order['tanggal_konfirmasi_user'])) ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="status-badge status-<?= str_replace(' ', '-', $order['status_pesanan']) ?>">
                                    <?= ucfirst($order['status_pesanan']) ?>
                                </span>
                                <?php if ($order['id_pengiriman'] && $order['status_pengiriman']): ?>
                                    <span class="shipping-status-badge shipping-<?= $order['status_pengiriman'] ?>">
                                        Pengiriman: <?= ucfirst(str_replace('_', ' ', $order['status_pengiriman'])) ?>
                                        <?php if ($order['status_pengiriman'] === 'dalam_perjalanan' && $waktu_tersisa): ?>
                                            <span class="timer-badge" title="Waktu tersisa untuk user konfirmasi">
                                                <i class="fas fa-clock"></i> <?= $waktu_tersisa ?>
                                            </span>
                                        <?php elseif ($bisa_konfirmasi): ?>
                                            <span class="testing-badge" title="User bisa konfirmasi sekarang">
                                                <i class="fas fa-check-circle"></i> Ready
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <div class="mt-1">
                                    <small class="text-muted">
                                        Total: Rp <?= number_format($order['total_harga'], 0, ',', '.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-body">
                        <!-- Customer Info -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6><i class="fas fa-user me-2"></i>Info Pelanggan</h6>
                                <p class="mb-1"><strong><?= htmlspecialchars($order['nama']) ?></strong></p>
                                <p class="mb-1 text-muted"><?= htmlspecialchars($order['email']) ?></p>
                                <p class="mb-0 text-muted"><?= $order['no_hp'] ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-truck me-2"></i>Info Pengiriman</h6>
                                <p class="mb-1"><strong>Metode:</strong> <?= ucfirst($order['metode_pengiriman']) ?></p>
                                <p class="mb-1"><strong>Pembayaran:</strong> <?= ucfirst($order['metode_pembayaran']) ?></p>
                                <?php if ($order['alamat_pengiriman']): ?>
                                    <p class="mb-0"><strong>Alamat:</strong> <?= htmlspecialchars($order['alamat_pengiriman']) ?></p>
                                <?php endif; ?>
                                
                                <!-- Informasi Pengiriman -->
                                <?php if ($order['id_pengiriman']): ?>
                                    <div class="shipping-info mt-2">
                                        <p class="mb-1"><strong>Status Pengiriman:</strong> <?= ucfirst(str_replace('_', ' ', $order['status_pengiriman'])) ?></p>
                                        <?php if ($order['nama_kurir']): ?>
                                            <p class="mb-0"><strong>Kurir:</strong> <?= htmlspecialchars($order['nama_kurir']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($order['estimasi_waktu_tiba'] && $order['status_pengiriman'] == 'dalam_perjalanan'): ?>
                                            <p class="mb-0"><strong>Estimasi Tiba:</strong> <?= date('H:i', strtotime($order['estimasi_waktu_tiba'])) ?></p>
                                            <p class="mb-0 text-info">
                                                <small>
                                                    <i class="fas fa-info-circle"></i> 
                                                    User dapat konfirmasi setelah <?= date('H:i', strtotime($order['estimasi_waktu_tiba'])) ?>
                                                    <?php if ($bisa_konfirmasi): ?>
                                                        <span class="text-success">(Sekarang bisa)</span>
                                                    <?php endif; ?>
                                                </small>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($order['konfirmasi_diterima']): ?>
                                            <p class="mb-0 text-success"><strong><i class="fas fa-check-circle"></i> Dikonfirmasi User</strong></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <h6><i class="fas fa-shopping-bag me-2"></i>Items Pesanan</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Gambar</th>
                                        <th>Produk</th>
                                        <th>Harga</th>
                                        <th>Jumlah</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($item = $items_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($item['gambar_produk']) && file_exists($item['gambar_produk'])): ?>
                                                    <img src="<?= $item['gambar_produk'] ?>" alt="<?= $item['nama_produk'] ?>" class="product-img">
                                                <?php else: ?>
                                                    <div class="product-img bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($item['nama_produk']) ?></td>
                                            <td>Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                                            <td><?= $item['jumlah'] ?></td>
                                            <td>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Payment Information -->
                        <div class="payment-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-money-bill-wave me-2"></i>Informasi Pembayaran</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <small>Total Harga:</small><br>
                                            <strong>Rp <?= number_format($order['total_harga'], 0, ',', '.') ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small>Uang Dibayar:</small><br>
                                            <strong>
                                                <?= $order['uang_dibayar'] ? 'Rp ' . number_format($order['uang_dibayar'], 0, ',', '.') : '-' ?>
                                            </strong>
                                        </div>
                                        <div class="col-6 mt-2">
                                            <small>Kembalian:</small><br>
                                            <strong>
                                                <?= $order['kembalian'] ? 'Rp ' . number_format($order['kembalian'], 0, ',', '.') : '-' ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-receipt me-2"></i>Bukti Pembayaran</h6>
                                    <?php if ($order['bukti_pembayaran']): ?>
                                        <div class="mb-2">
                                            <img src="<?= $order['bukti_pembayaran'] ?>" 
                                                 alt="Bukti Pembayaran" 
                                                 class="bukti-img"
                                                 data-bs-toggle="modal" 
                                                 data-bs-target="#buktiModal"
                                                 onclick="showBukti('<?= $order['bukti_pembayaran'] ?>')">
                                        </div>
                                        <small class="text-muted">Klik gambar untuk memperbesar</small>
                                    <?php else: ?>
                                        <p class="text-muted mb-2">Belum ada bukti pembayaran</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap gap-2 action-buttons">
                                    <!-- Update Status Pesanan Form -->
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id_pesanan" value="<?= $order['id_pesanan'] ?>">
                                        <div class="input-group input-group-sm" style="width: 250px;">
                                            <select class="form-select" name="status_pesanan" required>
                                                <?php foreach ($status_options as $status): ?>
                                                    <option value="<?= $status ?>" <?= $order['status_pesanan'] == $status ? 'selected' : '' ?>>
                                                        <?= ucfirst($status) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-warning">
                                                <i class="fas fa-sync-alt"></i> Update Status
                                            </button>
                                        </div>
                                    </form>

                                    <!-- Update Status Pengiriman Form -->
                                    <?php if ($order['id_pengiriman']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="id_pesanan" value="<?= $order['id_pesanan'] ?>">
                                            <div class="input-group input-group-sm" style="width: 280px;">
                                                <select class="form-select" name="status_pengiriman" required>
                                                    <?php foreach ($shipping_status_options as $status): ?>
                                                        <option value="<?= $status ?>" <?= $order['status_pengiriman'] == $status ? 'selected' : '' ?>>
                                                            <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="update_shipping_status" class="btn btn-info">
                                                    <i class="fas fa-truck"></i> Update Pengiriman
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Upload Bukti Pembayaran Admin -->
                                    <button class="btn btn-info btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#uploadBuktiModal"
                                            onclick="setUploadOrderId(<?= $order['id_pesanan'] ?>)">
                                        <i class="fas fa-upload me-1"></i> Upload Bukti
                                    </button>

                                    <!-- Update Payment Info -->
                                    <button class="btn btn-success btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updatePaymentModal"
                                            onclick="setPaymentOrderId(<?= $order['id_pesanan'] ?>, <?= $order['total_harga'] ?>)">
                                        <i class="fas fa-money-bill-wave me-1"></i> Update Pembayaran
                                    </button>

                                    <!-- Delete Order Button -->
                                    <button class="btn btn-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteOrderModal"
                                            onclick="setDeleteOrderId(<?= $order['id_pesanan'] ?>)">
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
                    <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">Tidak ada pesanan ditemukan</h4>
                    <p class="text-muted">
                        <?= $search ? 'Tidak ada hasil untuk "' . htmlspecialchars($search) . '"' : 'Belum ada pesanan yang masuk' ?>
                    </p>
                    <?php if ($search): ?>
                        <a href="admin_orders.php" class="btn btn-primary">
                            <i class="fas fa-refresh me-2"></i>Lihat Semua Pesanan
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==================== MODALS ==================== -->

    <!-- Modal untuk melihat bukti pembayaran -->
    <div class="modal fade" id="buktiModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalBuktiImg" src="" alt="Bukti Pembayaran" class="modal-bukti-img">
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk upload bukti pembayaran admin -->
    <div class="modal fade" id="uploadBuktiModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_pesanan" id="uploadOrderId">
                    <div class="modal-header bg-info text-white">
                        <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Bukti Pembayaran</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Pilih File Bukti Pembayaran</label>
                            <input type="file" class="form-control" name="bukti_pembayaran" accept="image/*,.pdf" required>
                            <div class="form-text">Format: JPG, JPEG, PNG, GIF, PDF. Maks 5MB.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="upload_bukti_admin" class="btn btn-info">
                            <i class="fas fa-upload me-2"></i>Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk update informasi pembayaran -->
    <div class="modal fade" id="updatePaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id_pesanan" id="paymentOrderId">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fas fa-money-bill-wave me-2"></i>Informasi Pembayaran</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Total Harga</label>
                            <input type="text" class="form-control" id="totalHarga" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Uang Dibayar</label>
                            <input type="number" class="form-control" name="uang_dibayar" id="uangDibayar" min="0" step="1000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kembalian</label>
                            <input type="text" class="form-control" id="kembalian" readonly style="background-color: #f8f9fa;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_payment_info" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk hapus pesanan -->
    <div class="modal fade" id="deleteOrderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id_pesanan" id="deleteOrderId">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Konfirmasi Hapus</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus pesanan ini?</p>
                        <p class="warning-text">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            <strong>Perhatian:</strong> Tindakan ini akan menghapus:
                        </p>
                        <ul class="warning-text">
                            <li>Data pesanan</li>
                            <li>Item-item pesanan</li>
                            <li>Ulasan yang terkait dengan pesanan ini</li>
                        </ul>
                        <p class="warning-text">Tindakan ini tidak dapat dibatalkan!</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="delete_order" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-2"></i>Hapus Pesanan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk hapus semua pesanan -->
    <div class="modal fade" id="deleteAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Hapus Semua Pesanan</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="warning-text"><strong>PERINGATAN TINGGI!</strong></p>
                        <p>Anda akan menghapus <strong>semua <?= $total_orders ?> pesanan</strong> dari database.</p>
                        <p class="warning-text">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            Tindakan ini akan menghapus:
                        </p>
                        <ul class="warning-text">
                            <li>Semua data pesanan</li>
                            <li>Semua item pesanan</li>
                            <li>Semua ulasan produk yang terkait</li>
                        </ul>
                        <p class="warning-text">Tindakan ini <strong>tidak dapat dibatalkan</strong>!</p>
                        
                        <div class="mb-3">
                            <label for="confirmation_text" class="form-label">
                                Ketik <strong>HAPUS SEMUA</strong> untuk konfirmasi:
                            </label>
                            <input type="text" class="form-control" id="confirmation_text" name="confirmation_text" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="delete_all_orders" class="btn btn-danger" id="confirmDeleteAll" disabled>
                            <i class="fas fa-trash-alt me-2"></i>Hapus Semua Data
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal untuk Analytics & Laporan Lengkap -->
    <div class="modal fade" id="analyticsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-chart-line me-2"></i>Analytics & Laporan Lengkap</h5>
                    <button type="button" class="btn-close btn-close-dark" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs" id="analyticsTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button">
                                <i class="fas fa-chart-pie me-1"></i> Ringkasan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                                <i class="fas fa-box me-1"></i> Produk Terlaris
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                                <i class="fas fa-chart-line me-1"></i> Penjualan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button">
                                <i class="fas fa-users me-1"></i> Pelanggan
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="export-tab" data-bs-toggle="tab" data-bs-target="#export" type="button">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="analyticsTabContent">
                        <!-- Tab 1: Ringkasan -->
                        <div class="tab-pane fade show active" id="summary">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Quick Overview</h5>
                                </div>
                            </div>
                            
                            <!-- Quick Charts -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-chart-bar me-2"></i>Top 5 Produk Terlaris</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="topProductsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-chart-pie me-2"></i>Metode Pembayaran</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="paymentMethodsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="row mt-3">
                                <div class="col-md-4">
                                    <div class="analytics-card">
                                        <h6><i class="fas fa-fire text-warning me-2"></i>Produk Paling Laris</h6>
                                        <?php if (!empty($top_products)): ?>
                                        <div class="mt-2">
                                            <?php foreach (array_slice($top_products, 0, 3) as $index => $product): ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>
                                                    <?php if ($index == 0): ?><span class="badge-gold">🥇</span>
                                                    <?php elseif ($index == 1): ?><span class="badge-silver">🥈</span>
                                                    <?php else: ?><span class="badge-bronze">🥉</span><?php endif; ?>
                                                    <?= htmlspecialchars(substr($product['nama_produk'], 0, 20)) . (strlen($product['nama_produk']) > 20 ? '...' : '') ?>
                                                </span>
                                                <span class="fw-bold"><?= number_format($product['total_terjual']) ?> pcs</span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-muted">Belum ada data produk</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="analytics-card">
                                        <h6><i class="fas fa-clock text-info me-2"></i>Jam Puncak</h6>
                                        <?php if (!empty($popular_times)): ?>
                                        <div class="mt-2">
                                            <?php foreach (array_slice($popular_times, 0, 3) as $time): ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span><?= $time['jam'] ?>:00</span>
                                                <span class="fw-bold"><?= $time['total_pesanan'] ?> pesanan</span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php else: ?>
                                        <p class="text-muted">Belum ada data waktu</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="analytics-card">
                                        <h6><i class="fas fa-money-bill-wave text-success me-2"></i>Statistik Pembayaran</h6>
                                        <div class="mt-2">
                                            <?php foreach ($payment_methods as $pm): ?>
                                            <div class="progress-wrapper">
                                                <div class="progress-label">
                                                    <span><?= strtoupper($pm['metode_pembayaran']) ?></span>
                                                    <span><?= number_format($pm['total_pesanan']) ?> pesanan</span>
                                                </div>
                                                <div class="progress-bar-custom">
                                                    <?php
                                                    $total_payments = array_sum(array_column($payment_methods, 'total_pesanan'));
                                                    $percentage = $total_payments > 0 ? ($pm['total_pesanan'] / $total_payments) * 100 : 0;
                                                    ?>
                                                    <div class="progress-fill" style="width: <?= $percentage ?>%; background: <?= 
                                                        $pm['metode_pembayaran'] == 'cash' ? '#28a745' : 
                                                        ($pm['metode_pembayaran'] == 'debit' ? '#17a2b8' : 
                                                        ($pm['metode_pembayaran'] == 'qris' ? '#ffc107' : '#6c757d')) ?>"></div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Produk Terlaris -->
                        <div class="tab-pane fade" id="products">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Analisis Produk Terlaris</h5>
                                </div>
                            </div>
                            
                            <!-- Top Products Table -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="table-responsive">
                                        <table class="table table-sm analytics-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Produk</th>
                                                    <th>Terjual</th>
                                                    <th>Pendapatan</th>
                                                    <th>Pesanan</th>
                                                    <th>Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($top_products as $index => $product): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($product['nama_produk']) ?></td>
                                                    <td><?= number_format($product['total_terjual']) ?></td>
                                                    <td>Rp <?= number_format($product['total_pendapatan'], 0, ',', '.') ?></td>
                                                    <td><?= $product['total_pesanan'] ?></td>
                                                    <td>
                                                        <?php if ($index == 0): ?>
                                                            <span class="trend-up"><i class="fas fa-arrow-up"></i> Terlaris</span>
                                                        <?php elseif ($index < 3): ?>
                                                            <span class="trend-up"><i class="fas fa-arrow-up"></i> Naik</span>
                                                        <?php elseif ($index < 6): ?>
                                                            <span class="trend-stable"><i class="fas fa-minus"></i> Stabil</span>
                                                        <?php else: ?>
                                                            <span class="trend-down"><i class="fas fa-arrow-down"></i> Turun</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Product Chart -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-chart-bar me-2"></i>Perbandingan Produk Terlaris</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="detailedProductsChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Penjualan -->
                        <div class="tab-pane fade" id="sales">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Analisis Penjualan</h5>
                                </div>
                            </div>
                            
                            <!-- Sales Charts -->
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-chart-line me-2"></i>Trend Penjualan 6 Bulan Terakhir</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="monthlySalesChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-clock me-2"></i>Distribusi Jam Penjualan</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="peakHoursChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Category Sales -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="analytics-card">
                                        <h6><i class="fas fa-tags me-2"></i>Penjualan per Kategori</h6>
                                        <div class="mt-3">
                                            <?php if (!empty($category_sales)): ?>
                                            <?php $total_category_revenue = array_sum(array_column($category_sales, 'total_pendapatan')); ?>
                                            <?php foreach ($category_sales as $cat): ?>
                                            <div class="progress-wrapper">
                                                <div class="progress-label">
                                                    <span><?= htmlspecialchars($cat['kategori']) ?></span>
                                                    <span>Rp <?= number_format($cat['total_pendapatan'], 0, ',', '.') ?></span>
                                                </div>
                                                <div class="progress-bar-custom">
                                                    <?php $percentage = $total_category_revenue > 0 ? ($cat['total_pendapatan'] / $total_category_revenue) * 100 : 0; ?>
                                                    <div class="progress-fill" style="width: <?= $percentage ?>%; background: #17a2b8"></div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <p class="text-muted">Belum ada data kategori</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Pelanggan -->
                        <div class="tab-pane fade" id="customers">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Analisis Pelanggan</h5>
                                </div>
                            </div>
                            
                            <!-- Top Customers Table -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="table-responsive">
                                        <table class="table table-sm analytics-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>#</th>
                                                    <th>Nama Pelanggan</th>
                                                    <th>Total Pesanan</th>
                                                    <th>Total Belanja</th>
                                                    <th>Rata-rata</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($customer_stats as $index => $customer): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($customer['nama']) ?></td>
                                                    <td><?= number_format($customer['total_pesanan']) ?></td>
                                                    <td>Rp <?= number_format($customer['total_belanja'], 0, ',', '.') ?></td>
                                                    <td>Rp <?= number_format($customer['rata_rata_belanja'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <?php if ($customer['total_pesanan'] >= 10): ?>
                                                            <span class="badge bg-warning">VIP</span>
                                                        <?php elseif ($customer['total_pesanan'] >= 5): ?>
                                                            <span class="badge bg-info">Regular</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">New</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Charts -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-chart-pie me-2"></i>Segmentasi Pelanggan</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="customerSegmentationChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="chart-container">
                                        <h6><i class="fas fa-chart-bar me-2"></i>Nilai Pelanggan</h6>
                                        <div class="chart-placeholder">
                                            <canvas id="customerValueChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 5: Export -->
                        <div class="tab-pane fade" id="export">
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5 class="mb-3">Export Laporan & Analytics</h5>
                                    <p class="text-muted">Pilih jenis laporan yang ingin di-export dalam format Excel dengan border.</p>
                                </div>
                            </div>
                            
                            <!-- Export Forms -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="export-form">
                                        <h6><i class="fas fa-file-excel text-success me-2"></i>Export Laporan Penjualan</h6>
                                        <form method="POST" class="mt-3">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Tanggal Mulai</label>
                                                    <input type="date" class="form-control" name="start_date" value="<?= date('Y-m-01') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Tanggal Akhir</label>
                                                    <input type="date" class="form-control" name="end_date" value="<?= date('Y-m-t') ?>" required>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Filter Status</label>
                                                    <select class="form-select" name="status_filter">
                                                        <option value="all">Semua Status</option>
                                                        <option value="pending">Pending</option>
                                                        <option value="proses pembuatan">Proses Pembuatan</option>
                                                        <option value="dikirim">Dikirim</option>
                                                        <option value="selesai">Selesai</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Filter Pembayaran</label>
                                                    <select class="form-select" name="payment_filter">
                                                        <option value="all">Semua Metode</option>
                                                        <option value="cash">Cash</option>
                                                        <option value="debit">Debit</option>
                                                        <option value="qris">QRIS</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <button type="submit" name="export_excel" class="btn btn-success w-100">
                                                <i class="fas fa-download me-2"></i>Download Laporan Penjualan
                                            </button>
                                            <small class="text-muted mt-2 d-block">Laporan akan berisi semua pesanan dalam periode yang dipilih dengan format tabel Excel lengkap dengan border.</small>
                                        </form>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="export-form">
                                        <h6><i class="fas fa-chart-line text-primary me-2"></i>Export Analytics Report</h6>
                                        <form method="POST" class="mt-3">
                                            <div class="mb-3">
                                                <label class="form-label">Jenis Laporan Analytics</label>
                                                <select class="form-select" name="report_type" required>
                                                    <option value="full">Laporan Analytics Lengkap</option>
                                                    <option value="products">Laporan Produk Terlaris</option>
                                                    <option value="sales">Laporan Penjualan</option>
                                                    <option value="customers">Laporan Pelanggan</option>
                                                    <option value="payments">Laporan Metode Pembayaran</option>
                                                </select>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Tanggal Mulai</label>
                                                    <input type="date" class="form-control" name="analytics_start_date" value="<?= date('Y-m-01') ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Tanggal Akhir</label>
                                                    <input type="date" class="form-control" name="analytics_end_date" value="<?= date('Y-m-t') ?>" required>
                                                </div>
                                            </div>
                                            <button type="submit" name="export_analytics" class="btn btn-primary w-100">
                                                <i class="fas fa-download me-2"></i>Download Analytics Report
                                            </button>
                                            <small class="text-muted mt-2 d-block">Laporan analytics dengan tabel lengkap, grafik, dan border untuk analisis data.</small>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Export Options -->
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h6><i class="fas fa-info-circle text-info me-2"></i>Fitur Export Excel dengan Border:</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="export-option">
                                                <div class="export-icon">
                                                    <i class="fas fa-border-all"></i>
                                                </div>
                                                <h6>Border Lengkap</h6>
                                                <p class="small">Semua sel memiliki border untuk tampilan yang rapi</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="export-option">
                                                <div class="export-icon">
                                                    <i class="fas fa-palette"></i>
                                                </div>
                                                <h6>Warna Header</h6>
                                                <p class="small">Header tabel dengan warna berbeda untuk setiap laporan</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="export-option">
                                                <div class="export-icon">
                                                    <i class="fas fa-calculator"></i>
                                                </div>
                                                <h6>Total & Summary</h6>
                                                <p class="small">Footer dengan total dan ringkasan eksekutif</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="export-option">
                                                <div class="export-icon">
                                                    <i class="fas fa-file-alt"></i>
                                                </div>
                                                <h6>Format Rapi</h6>
                                                <p class="small">Format angka, tanggal, dan teks yang konsisten</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="refreshAnalytics()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script>
        // Global variables for charts
        let charts = {};
        
        // Show bukti pembayaran in modal
        function showBukti(imageSrc) {
            document.getElementById('modalBuktiImg').src = imageSrc;
        }

        // Set order ID for upload bukti
        function setUploadOrderId(orderId) {
            document.getElementById('uploadOrderId').value = orderId;
        }

        // Set order ID for delete
        function setDeleteOrderId(orderId) {
            document.getElementById('deleteOrderId').value = orderId;
        }

        // Set order ID and total harga for payment info
        function setPaymentOrderId(orderId, totalHarga) {
            document.getElementById('paymentOrderId').value = orderId;
            document.getElementById('totalHarga').value = 'Rp ' + totalHarga.toLocaleString('id-ID');
            document.getElementById('uangDibayar').value = '';
            document.getElementById('kembalian').value = '';
        }

        // Calculate kembalian
        document.addEventListener('DOMContentLoaded', function() {
            const uangDibayarInput = document.getElementById('uangDibayar');
            if (uangDibayarInput) {
                uangDibayarInput.addEventListener('input', function() {
                    const totalHargaText = document.getElementById('totalHarga').value;
                    const totalHarga = parseFloat(totalHargaText.replace(/[^\d]/g, '')) || 0;
                    const uangDibayar = parseFloat(this.value) || 0;
                    const kembalian = uangDibayar - totalHarga;
                    
                    document.getElementById('kembalian').value = 'Rp ' + (kembalian >= 0 ? kembalian.toLocaleString('id-ID') : '0');
                });
            }
            
            // Confirm delete all validation
            const confirmationInput = document.getElementById('confirmation_text');
            if (confirmationInput) {
                confirmationInput.addEventListener('input', function() {
                    const confirmButton = document.getElementById('confirmDeleteAll');
                    if (confirmButton) {
                        confirmButton.disabled = this.value !== 'HAPUS SEMUA';
                    }
                });
            }
        });

        // Initialize charts when analytics modal is shown
        const analyticsModal = document.getElementById('analyticsModal');
        if (analyticsModal) {
            analyticsModal.addEventListener('shown.bs.modal', function () {
                initializeCharts();
            });
            
            analyticsModal.addEventListener('hidden.bs.modal', function () {
                destroyCharts();
            });
        }

        function initializeCharts() {
            // Data from PHP
            const topProductsData = <?php echo json_encode(array_slice($top_products, 0, 5)); ?>;
            const paymentMethodsData = <?php echo json_encode($payment_methods); ?>;
            const monthlySalesData = <?php echo json_encode($monthly_sales); ?>;
            const popularTimesData = <?php echo json_encode($popular_times); ?>;
            const customerStatsData = <?php echo json_encode($customer_stats); ?>;
            
            // Destroy existing charts
            destroyCharts();
            
            // 1. Top Products Chart (Bar)
            const topProductsCtx = document.getElementById('topProductsChart');
            if (topProductsCtx) {
                charts.topProducts = new Chart(topProductsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: topProductsData.map(p => p.nama_produk.substring(0, 15) + (p.nama_produk.length > 15 ? '...' : '')),
                        datasets: [{
                            label: 'Jumlah Terjual',
                            data: topProductsData.map(p => p.total_terjual),
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(255, 206, 86, 0.8)',
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(153, 102, 255, 0.8)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
            
            // 2. Payment Methods Chart (Doughnut)
            const paymentMethodsCtx = document.getElementById('paymentMethodsChart');
            if (paymentMethodsCtx) {
                charts.paymentMethods = new Chart(paymentMethodsCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: paymentMethodsData.map(p => p.metode_pembayaran.toUpperCase()),
                        datasets: [{
                            data: paymentMethodsData.map(p => p.total_pendapatan),
                            backgroundColor: [
                                'rgba(40, 167, 69, 0.8)',
                                'rgba(23, 162, 184, 0.8)',
                                'rgba(255, 193, 7, 0.8)',
                                'rgba(108, 117, 125, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
            
            // 3. Monthly Sales Chart (Line)
            const monthlySalesCtx = document.getElementById('monthlySalesChart');
            if (monthlySalesCtx) {
                charts.monthlySales = new Chart(monthlySalesCtx.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: monthlySalesData.map(m => m.bulan),
                        datasets: [{
                            label: 'Pendapatan',
                            data: monthlySalesData.map(m => m.total_pendapatan),
                            borderColor: 'rgba(54, 162, 235, 1)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: true }
                        }
                    }
                });
            }
            
            // 4. Peak Hours Chart (Bar)
            const peakHoursCtx = document.getElementById('peakHoursChart');
            if (peakHoursCtx) {
                const hourLabels = Array.from({length: 24}, (_, i) => i + ':00');
                const hourData = Array.from({length: 24}, (_, i) => {
                    const found = popularTimesData.find(p => p.jam == i);
                    return found ? found.total_pesanan : 0;
                });
                
                charts.peakHours = new Chart(peakHoursCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: hourLabels,
                        datasets: [{
                            label: 'Pesanan',
                            data: hourData,
                            backgroundColor: 'rgba(255, 159, 64, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { ticks: { maxTicksLimit: 12 } }
                        }
                    }
                });
            }
            
            // 5. Detailed Products Chart (Bar)
            const detailedProductsCtx = document.getElementById('detailedProductsChart');
            if (detailedProductsCtx) {
                charts.detailedProducts = new Chart(detailedProductsCtx.getContext('2d'), {
                    type: 'horizontalBar',
                    data: {
                        labels: topProductsData.map(p => p.nama_produk.substring(0, 20) + (p.nama_produk.length > 20 ? '...' : '')),
                        datasets: [{
                            label: 'Terjual',
                            data: topProductsData.map(p => p.total_terjual),
                            backgroundColor: 'rgba(75, 192, 192, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
            
            // 6. Customer Segmentation Chart (Pie)
            const customerSegmentationCtx = document.getElementById('customerSegmentationChart');
            if (customerSegmentationCtx) {
                const vipCount = customerStatsData.filter(c => c.total_pesanan >= 10).length;
                const regularCount = customerStatsData.filter(c => c.total_pesanan >= 5 && c.total_pesanan < 10).length;
                const newCount = customerStatsData.filter(c => c.total_pesanan < 5).length;
                
                charts.customerSegmentation = new Chart(customerSegmentationCtx.getContext('2d'), {
                    type: 'pie',
                    data: {
                        labels: ['VIP (≥10)', 'Regular (5-9)', 'New (<5)'],
                        datasets: [{
                            data: [vipCount, regularCount, newCount],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(255, 206, 86, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            }
            
            // 7. Customer Value Chart (Bar)
            const customerValueCtx = document.getElementById('customerValueChart');
            if (customerValueCtx) {
                charts.customerValue = new Chart(customerValueCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: customerStatsData.map((_, i) => 'Pelanggan ' + (i + 1)),
                        datasets: [{
                            label: 'Total Belanja',
                            data: customerStatsData.map(c => c.total_belanja / 1000), // Scale down
                            backgroundColor: 'rgba(40, 167, 69, 0.8)'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                title: {
                                    display: true,
                                    text: 'Total Belanja (Rp x1000)'
                                }
                            }
                        }
                    }
                });
            }
        }

        function destroyCharts() {
            Object.values(charts).forEach(chart => {
                if (chart) chart.destroy();
            });
            charts = {};
        }

        // Refresh analytics data
        function refreshAnalytics() {
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
            btn.disabled = true;
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Print analytics report
        function printAnalyticsReport() {
            const analyticsContent = document.getElementById('analyticsTabContent').cloneNode(true);
            
            // Remove canvas elements and replace with placeholders
            const canvases = analyticsContent.querySelectorAll('canvas');
            canvases.forEach(canvas => {
                const placeholder = document.createElement('div');
                placeholder.className = 'chart-placeholder-print';
                placeholder.innerHTML = `<p style="text-align:center; color:#666; padding:20px;">[Chart: ${canvas.parentElement.previousElementSibling?.innerText || 'Data Visualization'}]</p>`;
                canvas.parentElement.replaceChild(placeholder, canvas);
            });
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <meta charset="utf-8"/>
                        <title>Laporan Analytics - Baker Old</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
                        <style>
                            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial; padding:20px; color:#343a40; }
                            h3 { margin-top: 0; }
                            .chart-placeholder-print { border:1px solid #e9ecef; border-radius:6px; padding:20px; margin-bottom:15px; background:#fafafa; }
                            .analytics-metadata { color:#6c757d; margin-bottom:10px; }
                            table { width:100%; border-collapse:collapse; }
                        </style>
                    </head>
                    <body>
                        <div class="container-fluid">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h3>Laporan Analytics - Baker Old</h3>
                                    <div class="analytics-metadata">Periode: <?= date('d/m/Y') ?> — Generated: <?= date('d/m/Y H:i') ?></div>
                                </div>
                                <div>
                                    <small class="text-muted">Printed from Admin Dashboard</small>
                                </div>
                            </div>
                            <hr/>
                            ${analyticsContent.innerHTML}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();

            // Give the new window a moment to render, then print and close
            printWindow.focus();
            setTimeout(function() {
                printWindow.print();
                printWindow.close();
            }, 500);
        }

        // Export charts / analytics data to JSON
        function exportChartsData() {
            const payload = {
                top_products: <?php echo json_encode($top_products); ?>,
                payment_methods: <?php echo json_encode($payment_methods); ?>,
                monthly_sales: <?php echo json_encode($monthly_sales); ?>,
                popular_times: <?php echo json_encode($popular_times); ?>,
                customer_stats: <?php echo json_encode($customer_stats); ?>,
                category_sales: <?php echo json_encode($category_sales); ?>
            };
            const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'analytics_export_<?php echo date('Y-m-d'); ?>.json';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }

        // Screenshot dashboard (modal) using html2canvas
        function screenshotDashboard() {
            const el = document.querySelector('#analyticsModal .modal-content');
            if (!el) return alert('Analytics modal tidak ditemukan.');
            html2canvas(el, { scale: 1.5 }).then(canvas => {
                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png');
                link.download = 'analytics_screenshot_<?php echo date('Y-m-d_H-i'); ?>.png';
                document.body.appendChild(link);
                link.click();
                link.remove();
            }).catch(err => {
                console.error(err);
                alert('Gagal mengambil screenshot.');
            });
        }
    </script>
</body>
</html>