<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('Silakan login terlebih dahulu!'); window.location='masuk.php';</script>";
    exit;
}

// Include koneksi database
include 'koneksi.php';

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Ambil data user untuk alamat
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT nama, email, no_hp, alamat, tipe_pengiriman, foto_profil FROM pengguna WHERE id_pelanggan = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fungsi untuk mengambil data diskon dari database berdasarkan nama produk
function getDiskonFromDatabase($nama_produk, $conn) {
    $stmt = $conn->prepare("SELECT diskon, harga, harga_setelah_diskon FROM produk WHERE LOWER(nama_produk) = LOWER(?)");
    $stmt->bind_param("s", $nama_produk);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Fungsi untuk menghitung diskon hanya dari database
function hitungDiskon($nama_produk, $harga, $jumlah, $conn) {
    $diskon = 0;
    $jenis_diskon = '';
    
    // Ambil diskon dari database
    $diskon_db = getDiskonFromDatabase($nama_produk, $conn);
    if ($diskon_db && $diskon_db['diskon'] > 0) {
        $diskon_db_amount = ($harga * $diskon_db['diskon'] / 100) * $jumlah;
        $diskon = $diskon_db_amount;
        $jenis_diskon = 'Diskon ' . $diskon_db['diskon'] . '% dari produk';
    }
    
    return [
        'diskon' => $diskon,
        'jenis_diskon' => $jenis_diskon
    ];
}

// Fungsi untuk update jumlah item
if (isset($_POST['update_quantity'])) {
    $index = $_POST['index'];
    $new_quantity = (int)$_POST['quantity'];
    
    if ($new_quantity > 0 && isset($_SESSION['cart'][$index])) {
        $item = $_SESSION['cart'][$index];
        
        // Cek stok untuk semua produk
        $stmt = $conn->prepare("SELECT stok FROM produk WHERE LOWER(nama_produk) = LOWER(?)");
        $stmt->bind_param("s", $item['nama']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $produk = $result->fetch_assoc();
            if ($new_quantity > $produk['stok']) {
                $_SESSION['error'] = "Stok " . $item['nama'] . " hanya tersedia " . $produk['stok'] . " item!";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
        
        $_SESSION['cart'][$index]['jumlah'] = $new_quantity;
        
        // Hitung ulang diskon ketika quantity diupdate
        $diskon_info = hitungDiskon($item['nama'], $item['harga'], $new_quantity, $conn);
        $_SESSION['cart'][$index]['diskon'] = $diskon_info['diskon'];
        $_SESSION['cart'][$index]['jenis_diskon'] = $diskon_info['jenis_diskon'];
        
        $_SESSION['message'] = "Jumlah berhasil diupdate!";
    } else {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        $_SESSION['message'] = "Item berhasil dihapus!";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fungsi untuk hapus item
if (isset($_POST['remove_item'])) {
    $index = $_POST['index'];
    if (isset($_SESSION['cart'][$index])) {
        $product_name = $_SESSION['cart'][$index]['nama'];
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        $_SESSION['message'] = "✅ " . $product_name . " berhasil dihapus dari keranjang!";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fungsi untuk hapus semua item
if (isset($_POST['clear_cart'])) {
    if (!empty($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
        $_SESSION['message'] = "✅ Keranjang berhasil dikosongkan!";
    } else {
        $_SESSION['error'] = "Keranjang sudah kosong!";
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fungsi untuk checkout dengan PPN 12% dan update stok
if (isset($_POST['checkout'])) {
    $metode_pengiriman = $_POST['metode_pengiriman'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $alamat_pengiriman = $_POST['alamat_pengiriman'] ?? '';
    
    // Validasi
    if (empty($_SESSION['cart'])) {
        $_SESSION['error'] = "Keranjang masih kosong!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if ($metode_pengiriman === 'delivery' && empty($alamat_pengiriman)) {
        $_SESSION['error'] = "Alamat pengiriman harus diisi!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
    
    // Cek stok semua produk sebelum checkout
    foreach ($_SESSION['cart'] as $item) {
        $stmt = $conn->prepare("SELECT stok, nama_produk FROM produk WHERE LOWER(nama_produk) = LOWER(?)");
        $stmt->bind_param("s", $item['nama']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $produk = $result->fetch_assoc();
            if ($item['jumlah'] > $produk['stok']) {
                $_SESSION['error'] = "Stok " . $produk['nama_produk'] . " tidak mencukupi! Tersedia: " . $produk['stok'] . " item";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
    
    // Hitung total dengan diskon
    $total_harga = 0;
    $total_diskon = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal = $item['harga'] * $item['jumlah'];
        $diskon_item = $item['diskon'] ?? 0;
        $total_harga += $subtotal - $diskon_item;
        $total_diskon += $diskon_item;
    }
    
    // Hitung PPN 12%
    $ppn = $total_harga * 0.12;
    $total_setelah_ppn = $total_harga + $ppn;
    
    // Proses pembayaran
    $uang_dibayar = $total_setelah_ppn;
    $kembalian = 0;
    
    if ($metode_pembayaran === 'cash') {
        $uang_dibayar = (float)$_POST['uang_dibayar'];
        
        if ($uang_dibayar < $total_setelah_ppn) {
            $_SESSION['error'] = "Uang yang dibayarkan kurang!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        $kembalian = $uang_dibayar - $total_setelah_ppn;
    }
    
    // Simpan pesanan ke database
    try {
        $conn->begin_transaction();
        
        // Insert ke table pesanan_baru
        $tanggal_pesanan = date('Y-m-d H:i:s');
        $status_pesanan = 'pending';
        $total_diskon_order = $total_diskon;
        
        $stmt = $conn->prepare("INSERT INTO pesanan_baru (id_pelanggan, tanggal_pesanan, status_pesanan, total_harga, total_diskon, metode_pembayaran, metode_pengiriman, alamat_pengiriman, uang_dibayar, kembalian) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssddssdd", $user_id, $tanggal_pesanan, $status_pesanan, $total_setelah_ppn, $total_diskon_order, $metode_pembayaran, $metode_pengiriman, $alamat_pengiriman, $uang_dibayar, $kembalian);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan pesanan: " . $stmt->error);
        }
        
        $id_pesanan = $conn->insert_id;
        
        // Insert items ke table pesanan_items_baru dan update stok
        foreach ($_SESSION['cart'] as $item) {
            $subtotal = $item['harga'] * $item['jumlah'];
            $diskon_item = $item['diskon'] ?? 0;
            $subtotal_setelah_diskon = $subtotal - $diskon_item;
            $jenis_diskon = $item['jenis_diskon'] ?? '';
            
            // Insert item utama
            $stmt = $conn->prepare("INSERT INTO pesanan_items_baru (id_pesanan, nama_produk, harga, jumlah, subtotal, diskon, jenis_diskon, subtotal_setelah_diskon, is_promo_gratis) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $is_promo_gratis = 0; // Semua item dibayar (tidak ada promo gratis)
            $stmt->bind_param("isdiddsdi", $id_pesanan, $item['nama'], $item['harga'], $item['jumlah'], $subtotal, $diskon_item, $jenis_diskon, $subtotal_setelah_diskon, $is_promo_gratis);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal menyimpan item pesanan: " . $stmt->error);
            }
            
            // Update stok produk
            $stmt_update = $conn->prepare("UPDATE produk SET stok = stok - ? WHERE LOWER(nama_produk) = LOWER(?)");
            $stmt_update->bind_param("is", $item['jumlah'], $item['nama']);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal update stok produk: " . $stmt_update->error);
            }
            
            // Update status produk jika stok habis
            $stmt_status = $conn->prepare("UPDATE produk SET status_produk = 'habis' WHERE LOWER(nama_produk) = LOWER(?) AND stok <= 0");
            $stmt_status->bind_param("s", $item['nama']);
            $stmt_status->execute();
        }
        
        $conn->commit();
        
        // Kosongkan keranjang dan redirect ke success page
        $_SESSION['last_order_id'] = $id_pesanan;
        $_SESSION['cart'] = [];
        
        header("Location: payment_success.php");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Hitung total dan diskon untuk tampilan
$cart = $_SESSION['cart'];
$total_sebelum_diskon = 0;
$total_diskon = 0;
$total_setelah_diskon = 0;

foreach ($cart as $index => $item) {
    // Hitung diskon untuk setiap item
    $diskon_info = hitungDiskon($item['nama'], $item['harga'], $item['jumlah'], $conn);
    $_SESSION['cart'][$index]['diskon'] = $diskon_info['diskon'];
    $_SESSION['cart'][$index]['jenis_diskon'] = $diskon_info['jenis_diskon'];
    
    $subtotal = $item['harga'] * $item['jumlah'];
    $total_sebelum_diskon += $subtotal;
    $total_diskon += $diskon_info['diskon'];
}

$total_setelah_diskon = $total_sebelum_diskon - $total_diskon;
$ppn = $total_setelah_diskon * 0.12;
$total_setelah_ppn = $total_setelah_diskon + $ppn;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS Styles - Tetap sama seperti sebelumnya */
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

        .cart-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
            position: relative;
            top: -8px;
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
         color: #000000 !important;
        text-decoration: none;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.3s ease;
        font-size: 14px;
    }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item i {
        margin-right: 10px;
        width: 16px;
        text-align: center;
        color: #d37b2c; /* Ubah ikon juga menjadi hitam */
    }

        .dropdown-item:hover {
            background: #f9f5f0;
            color: #000000; /* Tetap hitam saat hover */
            padding-left: 20px;
        }

        .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }

        .cart-container { 
            max-width: 1200px; 
            margin: 40px auto; 
            background: #fff; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
        }

        h2 { 
            color: #d37b2c; 
            text-align: center; 
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 700;
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

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td { 
            padding: 15px; 
            border-bottom: 1px solid #eee; 
            text-align: center; 
        }

        th { 
            background: #fff2e0; 
            color: #d37b2c;
            font-weight: 600;
            font-size: 14px;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .product-img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #f0f0f0;
        }

        .product-name {
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .quantity-btn {
            background: #f0f0f0;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-weight: bold;
            color: #333;
        }

        .quantity-btn:hover {
            background: #d37b2c;
            color: white;
            transform: scale(1.1);
        }

        .quantity-btn:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            background: white;
        }

        .quantity-input:focus {
            outline: none;
            border-color: #d37b2c;
            box-shadow: 0 0 0 2px rgba(211, 123, 44, 0.1);
        }

        .action-btns {
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-update {
            background: #28a745;
            color: white;
        }

        .btn-update:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-remove {
            background: #dc3545;
            color: white;
        }

        .btn-remove:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-clear {
            background: #6c757d;
            color: white;
        }

        .btn-clear:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-checkout {
            background: #d37b2c;
            color: white;
            padding: 12px 25px;
            font-size: 16px;
        }

        .btn-checkout:hover {
            background: #b36622;
            transform: translateY(-2px);
        }

        .btn-continue {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            font-size: 16px;
        }

        .btn-continue:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .total-section { 
            text-align: right; 
            padding: 20px 0; 
            color: #333; 
            font-size: 18px;
            border-top: 2px solid #eee;
            margin-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }

        .total-row.subtotal {
            color: #666;
            font-size: 16px;
        }

        .total-row.diskon {
            color: #28a745;
            font-size: 16px;
        }

        .total-row.ppn-info {
            color: #007bff;
            font-size: 16px;
        }

        .total-row.final-total {
            border-top: 2px solid #e9ecef;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: bold;
            font-size: 20px;
            color: #d37b2c;
        }

        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            gap: 15px;
        }

        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        .empty-cart i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-cart p {
            font-size: 18px;
            margin-bottom: 20px;
        }

        .price-update {
            animation: priceFlash 0.5s ease;
        }

        @keyframes priceFlash {
            0% { background-color: transparent; }
            50% { background-color: #fff2e0; }
            100% { background-color: transparent; }
        }

        .checkout-section {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid #e9ecef;
        }
        
        .checkout-section h3 {
            color: #d37b2c;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            box-sizing: border-box;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #d37b2c;
            box-shadow: 0 0 0 3px rgba(211, 123, 44, 0.1);
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s ease;
            flex: 1;
            background: white;
        }
        
        .radio-option:hover {
            border-color: #d37b2c;
            transform: translateY(-2px);
        }
        
        .radio-option.selected {
            border-color: #d37b2c;
            background: rgba(211, 123, 44, 0.05);
        }
        
        .radio-option input[type="radio"] {
            margin: 0;
        }
        
        .payment-method {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .payment-method.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .qris-image {
            max-width: 300px;
            margin: 15px 0;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .cash-calculation {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-top: 10px;
        }
        
        .calculation-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 5px 0;
        }
        
        .calculation-row.total {
            border-top: 2px solid #e9ecef;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: bold;
            font-size: 16px;
            color: #d37b2c;
        }
        
        .address-display {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-top: 10px;
            font-style: italic;
            color: #666;
            font-size: 14px;
        }
        
        .btn-checkout-final {
            background: #d37b2c;
            color: white;
            padding: 15px 30px;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .btn-checkout-final:hover {
            background: #b36622;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 123, 44, 0.3);
        }
        
        .btn-checkout-final:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .diskon-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 8px;
        }

        .diskon-info {
            font-size: 12px;
            color: #28a745;
            font-style: italic;
            margin-top: 5px;
        }

        .stok-info {
            font-size: 12px;
            color: #666;
            margin-top: 3px;
        }

        .stok-warning {
            color: #dc3545;
            font-weight: bold;
        }

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
                flex-wrap: wrap;
            }

            .profile-dropdown {
                margin-left: 0;
            }

            .cart-container {
                margin: 20px 15px;
                padding: 20px;
            }

            table {
                font-size: 14px;
                display: block;
                overflow-x: auto;
            }

            th, td {
                padding: 10px 5px;
                font-size: 12px;
            }

            .product-info {
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }

            .quantity-controls {
                flex-direction: column;
                gap: 5px;
            }

            .action-btns {
                flex-direction: column;
                gap: 5px;
            }

            .cart-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }

            .btn-continue, .btn-checkout {
                width: 100%;
                text-align: center;
                justify-content: center;
            }

            .radio-group {
                flex-direction: column;
                gap: 10px;
            }

            .checkout-section {
                padding: 15px;
            }

            .total-section {
                text-align: left;
            }

            .total-row {
                flex-direction: column;
                gap: 5px;
            }
        }

        @media (max-width: 480px) {
            .cart-container {
                padding: 15px;
                margin: 10px;
            }

            h2 {
                font-size: 22px;
            }

            .navbar ul li a {
                padding: 8px 10px;
                font-size: 12px;
            }

            .logo-text {
                font-size: 18px;
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
            <li>
                <a href="keranjang.php" class="active">
                    <i class="fas fa-shopping-cart"></i> Keranjang
                    <?php if (!empty($_SESSION['cart'])): ?>
                        <span class="cart-badge"><?= count($_SESSION['cart']); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="tentang.php"><i class="fas fa-info-circle"></i> Tentang</a></li>
             <li><a href="ulasan_public.php"> <i class="fas fa-star"></i> Ulasan Produk</a></li>
            
            <!-- Profile Dropdown -->
            <li class="profile-dropdown" id="profileDropdown">
                <div class="profile-toggle">
                    <img src="<?= !empty($user['foto_profil']) ? htmlspecialchars($user['foto_profil']) : 'images/profil.jpeg' ?>" 
                         alt="User" class="profile-img" onerror="this.src='images/profil.jpeg'">
                    <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name']); ?></span>
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
                    
                     <a href="bukti_transaksi.php" class="dropdown-item profile-link">
                             <i class="fas fa-receipt"></i>
                         Lihat Tranksasi Saya
                        </a>
                    <a href="lacak_pengiriman.php" class="dropdown-item profile-link">
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

    <div class="cart-container">
        <h2><i class="fas fa-shopping-cart"></i> Keranjang Belanja</h2>

        <!-- Pesan Notifikasi -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['message'] ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (empty($cart)): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <p>Keranjang masih kosong 😢</p>
                <button class="btn btn-continue" onclick="window.location='menu.php'">
                    <i class="fas fa-utensils"></i> Mulai Belanja
                </button>
            </div>
        <?php else: ?>
        
        <table>
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Harga</th>
                    <th>Jumlah</th>
                    <th>Subtotal</th>
                    <th>Diskon</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cart as $index => $item): 
                    $subtotal = $item['harga'] * $item['jumlah'];
                    $diskon = $item['diskon'] ?? 0;
                    $subtotal_setelah_diskon = $subtotal - $diskon;
                    
                    // Ambil info stok dari database
                    $stmt_stok = $conn->prepare("SELECT stok FROM produk WHERE LOWER(nama_produk) = LOWER(?)");
                    $stmt_stok->bind_param("s", $item['nama']);
                    $stmt_stok->execute();
                    $result_stok = $stmt_stok->get_result();
                    $stok_info = $result_stok->fetch_assoc();
                    $stok = $stok_info ? $stok_info['stok'] : 0;
                ?>
                <tr data-index="<?= $index ?>" data-price="<?= $item['harga'] ?>">
                    <td>
                        <div class="product-info">
                            <img src="<?= htmlspecialchars($item['gambar']); ?>" alt="<?= htmlspecialchars($item['nama']); ?>" class="product-img" onerror="this.src='images/default-product.jpg'">
                            <div>
                                <span class="product-name"><?= htmlspecialchars($item['nama']); ?></span>
                                <div class="stok-info <?= $stok < $item['jumlah'] ? 'stok-warning' : '' ?>">
                                    <i class="fas fa-box"></i> Stok: <?= $stok ?> 
                                    <?php if ($stok < $item['jumlah']): ?>
                                        ❌ Stok tidak mencukupi
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="price-cell">Rp <?= number_format($item['harga'], 0, ',', '.'); ?></td>
                    <td>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn minus" onclick="updateQuantity(<?= $index ?>, -1)" <?= $item['jumlah'] <= 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" name="quantity" value="<?= $item['jumlah']; ?>" min="1" max="<?= min(99, $stok) ?>" 
                                   class="quantity-input" id="quantity-<?= $index ?>" 
                                   onchange="updateSubtotal(<?= $index ?>)">
                            <button type="button" class="quantity-btn plus" onclick="updateQuantity(<?= $index ?>, 1)" <?= $item['jumlah'] >= min(99, $stok) ? 'disabled' : '' ?>>
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <span class="subtotal" id="subtotal-<?= $index ?>">
                            Rp <?= number_format($subtotal, 0, ',', '.'); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($diskon > 0): ?>
                            <span style="color: #28a745; font-weight: bold;">
                                -Rp <?= number_format($diskon, 0, ',', '.'); ?>
                            </span>
                            <?php if (!empty($item['jenis_diskon'])): ?>
                                <div class="diskon-info">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($item['jenis_diskon']) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #666;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="action-btns">
                            <input type="hidden" name="index" value="<?= $index ?>">
                            <button type="submit" name="remove_item" class="btn btn-remove" 
                                    onclick="return confirm('Yakin ingin menghapus <?= htmlspecialchars($item['nama']); ?> dari keranjang?')" title="Hapus Item">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Total Section dengan Breakdown dan PPN -->
        <div class="total-section">
            <div class="total-row subtotal">
                <span>Subtotal:</span>
                <span id="total-sebelum-diskon">Rp <?= number_format($total_sebelum_diskon, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row diskon">
                <span>Total Diskon:</span>
                <span id="total-diskon">-Rp <?= number_format($total_diskon, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row subtotal">
                <span>Total Setelah Diskon:</span>
                <span id="total-setelah-diskon">Rp <?= number_format($total_setelah_diskon, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row ppn-info">
                <span>PPN (12%):</span>
                <span id="total-ppn">+Rp <?= number_format($ppn, 0, ',', '.'); ?></span>
            </div>
            <div class="total-row final-total">
                <span>Total Pembayaran:</span>
                <span id="total-setelah-ppn">Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?></span>
            </div>
        </div>
        
        <div class="cart-actions">
            <form method="POST">
                <button type="submit" name="clear_cart" class="btn btn-clear" 
                        onclick="return confirm('Yakin ingin mengosongkan keranjang?')">
                    <i class="fas fa-broom"></i> Kosongkan Keranjang
                </button>
            </form>
            
            <div style="display: flex; gap: 15px;">
                <button class="btn btn-continue" onclick="window.location='menu.php'">
                    <i class="fas fa-arrow-left"></i> Lanjut Belanja
                </button>
            </div>
        </div>

        <!-- Checkout Section -->
        <div class="checkout-section">
            <h3><i class="fas fa-truck"></i> Metode Pengiriman</h3>
            
            <div class="form-group">
                <div class="radio-group">
                    <label class="radio-option" id="option-takeaway">
                        <input type="radio" name="metode_pengiriman" value="takeaway" checked>
                        <i class="fas fa-store"></i>
                        <span>Take Away</span>
                    </label>
                    <label class="radio-option" id="option-delivery">
                        <input type="radio" name="metode_pengiriman" value="delivery">
                        <i class="fas fa-motorcycle"></i>
                        <span>Delivery</span>
                    </label>
                </div>
            </div>

            <div id="delivery-address" style="display: none;">
                <div class="form-group">
                    <label for="alamat_pengiriman">Alamat Pengiriman</label>
                    <textarea id="alamat_pengiriman" name="alamat_pengiriman" class="form-control" rows="3" 
                              placeholder="Masukkan alamat lengkap untuk pengiriman"><?= htmlspecialchars($user['alamat'] ?? ''); ?></textarea>
                    <?php if (!empty($user['alamat'])): ?>
                        <div class="address-display">
                            <strong>Alamat tersimpan:</strong> <?= htmlspecialchars($user['alamat']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <h3><i class="fas fa-credit-card"></i> Metode Pembayaran</h3>
            
            <div class="form-group">
                <div class="radio-group">
                    <label class="radio-option" id="option-cash">
                        <input type="radio" name="metode_pembayaran" value="cash" checked>
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Cash</span>
                    </label>
                    <label class="radio-option" id="option-qris">
                        <input type="radio" name="metode_pembayaran" value="qris">
                        <i class="fas fa-qrcode"></i>
                        <span>QRIS</span>
                    </label>
                    <label class="radio-option" id="option-debit">
                        <input type="radio" name="metode_pembayaran" value="debit">
                        <i class="fas fa-credit-card"></i>
                        <span>Transfer Bank</span>
                    </label>
                </div>
            </div>

            <!-- Cash Payment Method -->
            <div id="cash-payment" class="payment-method active">
                <div class="form-group">
                    <label for="uang_dibayar">Uang yang Dibayarkan harap sesuai dengan total !<strong> Dan Tunjukkan Bukti Tranksasi Pada Kurir atau Kasir </strong> </label>
                      <label for="uang_dibayar">Untuk Pengiriman Delivery !<strong> Harap Bayar Ongkos Tambahan Sesuai Nominal Yang Akan Disebutkan Admin.Batas pengiriman 3KM </strong> </label>
                    <input type="number" id="uang_dibayar" name="uang_dibayar" class="form-control" 
                           min="<?= $total_setelah_ppn ?>" value="<?= $total_setelah_ppn ?>" onchange="calculateChange()">
                </div>
                <div class="cash-calculation">
                    <div class="calculation-row">
                        <span>Total Harga:</span>
                        <span id="display-total">Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?></span>
                    </div>
                    <div class="calculation-row">
                        <span>Uang Dibayar:</span>
                        <span id="display-paid">Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?></span>
                    </div>
                    <div class="calculation-row total">
                        <span>Kembalian:</span>
                        <span id="display-change">Rp 0</span>
                    </div>
                </div>
            </div>

            <!-- QRIS Payment Method -->
          <!-- QRIS Payment Method -->
        <div id="qris-payment" class="payment-method">
            <div class="form-group">
                <p><strong>Instruksi Pembayaran QRIS:</strong></p>
                <ol style="margin-left: 20px; margin-bottom: 15px;">
                    <li>Scan QR Code berikut menggunakan aplikasi e-wallet atau mobile banking Anda</li>
                    <li>Pastikan nominal pembayaran sesuai dengan total: <strong>Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?></strong></li>
                    <li>Setelah pembayaran berhasil, screenshot bukti pembayaran</li>
                    <li>Kirim bukti pembayaran ke WhatsApp kami dengan menyebutkan:</li>
                    <ul style="margin-left: 20px;">
                        <li>Waktu perkiraan memesan</li>
                        <li>No. Pesanan (akan diberikan setelah checkout)</li>
                        <li>Nama Pelanggan </li>
                    </ul>
                    <li>WhatsApp: <strong>+62 857-1155-5527</strong></li>
                     <li>Untuk pembayaran CASH, cukup tunjukkan bukti transaksi pada kurir atau kasir saat pengantaran atau pembayaran</li>
                     <li>Untuk Pengiriman Delivery,Harap Bayar Ongkos Tambahan Sesuai Nominal Yang Akan Disebutkan Admin</li>
                  <li>Untuk Pengiriman Delivery,batas pengiriman 3KM</li>

                </ol>
                <img src="images/qris.jpg" alt="QRIS Payment" class="qris-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='block'">
                <p style="display:none; color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Gambar QRIS tidak ditemukan</p>
                <p><strong>Total: Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?></strong></p>
            </div>
        </div>
           <!-- Debit Payment Method -->
<div id="debit-payment" class="payment-method">
    <div class="form-group">
        <p><strong>Instruksi Pembayaran Transfer Bank:</strong></p>
        <ol style="margin-left: 20px; margin-bottom: 15px;">
            <li>Transfer ke rekening berikut:</li>
        </ol>
        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef; margin-bottom: 15px;">
            <p><strong>Bank: BCA</strong></p>
            <p><strong>No. Rekening: 1234567890</strong></p>
            <p><strong>Atas Nama: Baker Old</strong></p>
            <p><strong>Total: Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?></strong></p>
        </div>
        <ol start="2" style="margin-left: 20px;">
            <li>Setelah transfer berhasil, screenshot bukti transfer</li>
            <li>Kirim bukti transfer ke WhatsApp kami dengan menyebutkan:</li>
            <ul style="margin-left: 20px;">
                <li>Waktu perkiraan memesan</li>
                <li>No. Pesanan (akan diberikan setelah checkout)</li>
                <li>Nama Pelanggan</li>
            </ul>
            <li>WhatsApp: <strong>+62 857-1155-5527</strong></li>
            <li>Untuk pembayaran <strong>CASH</strong>, cukup tunjukkan bukti transaksi pada kurir atau kasir saat pengantaran atau pembayaran</li>
            <li>Untuk Pengiriman Delivery ! Harap Bayar Ongkos Tambahan Sesuai Nominal Yang Akan Disebutkan Admin</li>
            <li>Untuk Pengiriman Delivery,batas pengiriman 3KM</li>

        </ol>
    </div>
</div>

            <!-- FORM UTAMA CHECKOUT -->
            <form method="POST" id="checkout-form">
                <input type="hidden" name="metode_pengiriman" id="final_metode_pengiriman" value="takeaway">
                <input type="hidden" name="metode_pembayaran" id="final_metode_pembayaran" value="cash">
                <input type="hidden" name="alamat_pengiriman" id="final_alamat_pengiriman" value="">
                <input type="hidden" name="uang_dibayar" id="final_uang_dibayar" value="<?= $total_setelah_ppn ?>">
                
                <button type="submit" name="checkout" class="btn-checkout-final" id="checkout-btn">
                    <i class="fas fa-credit-card"></i> Checkout Sekarang - Rp <?= number_format($total_setelah_ppn, 0, ',', '.'); ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle dropdown profile
        const profileDropdown = document.getElementById('profileDropdown');
        
        if (profileDropdown) {
            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                this.classList.toggle('active');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                profileDropdown.classList.remove('active');
            });

            // Prevent dropdown from closing when clicking inside
            const dropdownMenu = document.querySelector('.dropdown-menu');
            if (dropdownMenu) {
                dropdownMenu.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        }

        // Format number to Indonesian currency format
        function formatCurrency(amount) {
            return 'Rp ' + Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Quantity control functions
        function updateQuantity(index, change) {
            const input = document.getElementById('quantity-' + index);
            if (!input) return;
            
            let newQuantity = parseInt(input.value) + change;
            
            // Validate min and max
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > 99) newQuantity = 99;
            
            input.value = newQuantity;
            updateSubtotal(index);
        }

        // Update subtotal when quantity changes
        function updateSubtotal(index) {
            const input = document.getElementById('quantity-' + index);
            if (!input) return;
            
            const quantity = parseInt(input.value);
            const row = document.querySelector(`tr[data-index="${index}"]`);
            if (!row) return;
            
            const price = parseInt(row.getAttribute('data-price'));
            
            // Calculate new subtotal
            const newSubtotal = price * quantity;
            
            // Update subtotal display with animation
            const subtotalElement = document.getElementById('subtotal-' + index);
            if (subtotalElement) {
                subtotalElement.textContent = formatCurrency(newSubtotal);
                subtotalElement.classList.add('price-update');
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    subtotalElement.classList.remove('price-update');
                }, 500);
            }
            
            // Submit form to update quantity in session
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const indexInput = document.createElement('input');
            indexInput.name = 'index';
            indexInput.value = index;
            
            const quantityInput = document.createElement('input');
            quantityInput.name = 'quantity';
            quantityInput.value = quantity;
            
            const submitInput = document.createElement('input');
            submitInput.name = 'update_quantity';
            submitInput.value = '1';
            
            form.appendChild(indexInput);
            form.appendChild(quantityInput);
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        }

        // Metode Pengiriman
        document.querySelectorAll('input[name="metode_pengiriman"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const deliveryAddress = document.getElementById('delivery-address');
                const finalMetodePengiriman = document.getElementById('final_metode_pengiriman');
                const finalAlamatPengiriman = document.getElementById('final_alamat_pengiriman');
                const alamatInput = document.getElementById('alamat_pengiriman');
                
                if (this.value === 'delivery') {
                    if (deliveryAddress) deliveryAddress.style.display = 'block';
                    if (finalMetodePengiriman) finalMetodePengiriman.value = 'delivery';
                    if (finalAlamatPengiriman && alamatInput) finalAlamatPengiriman.value = alamatInput.value;
                } else {
                    if (deliveryAddress) deliveryAddress.style.display = 'none';
                    if (finalMetodePengiriman) finalMetodePengiriman.value = 'takeaway';
                    if (finalAlamatPengiriman) finalAlamatPengiriman.value = '';
                }
                
                updateRadioSelection('metode_pengiriman');
            });
        });

        // Metode Pembayaran
        document.querySelectorAll('input[name="metode_pembayaran"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const finalMetodePembayaran = document.getElementById('final_metode_pembayaran');
                if (finalMetodePembayaran) {
                    finalMetodePembayaran.value = this.value;
                }
                
                // Show/hide payment methods
                document.querySelectorAll('.payment-method').forEach(method => {
                    method.classList.remove('active');
                });
                
                if (this.value === 'cash') {
                    const cashPayment = document.getElementById('cash-payment');
                    if (cashPayment) cashPayment.classList.add('active');
                    const checkoutBtn = document.getElementById('checkout-btn');
                    if (checkoutBtn) checkoutBtn.disabled = false;
                } else if (this.value === 'qris') {
                    const qrisPayment = document.getElementById('qris-payment');
                    if (qrisPayment) qrisPayment.classList.add('active');
                    const checkoutBtn = document.getElementById('checkout-btn');
                    if (checkoutBtn) checkoutBtn.disabled = false;
                } else if (this.value === 'debit') {
                    const debitPayment = document.getElementById('debit-payment');
                    if (debitPayment) debitPayment.classList.add('active');
                    const checkoutBtn = document.getElementById('checkout-btn');
                    if (checkoutBtn) checkoutBtn.disabled = false;
                }
                
                updateRadioSelection('metode_pembayaran');
            });
        });

        // Update radio selection styling
        function updateRadioSelection(name) {
            document.querySelectorAll(`input[name="${name}"]`).forEach(radio => {
                const option = radio.closest('.radio-option');
                if (option) {
                    if (radio.checked) {
                        option.classList.add('selected');
                    } else {
                        option.classList.remove('selected');
                    }
                }
            });
        }

        // Calculate change for cash payment
        function calculateChange() {
            const uangDibayarInput = document.getElementById('uang_dibayar');
            const finalUangDibayar = document.getElementById('final_uang_dibayar');
            const displayTotal = document.getElementById('display-total');
            const displayPaid = document.getElementById('display-paid');
            const displayChange = document.getElementById('display-change');
            const checkoutBtn = document.getElementById('checkout-btn');
            
            if (!uangDibayarInput || !finalUangDibayar) return;
            
            const uangDibayar = parseFloat(uangDibayarInput.value) || 0;
            const totalHarga = <?= $total_setelah_ppn ?>;
            
            finalUangDibayar.value = uangDibayar;
            
            const kembalian = uangDibayar - totalHarga;
            
            if (displayTotal) displayTotal.textContent = formatCurrency(totalHarga);
            if (displayPaid) displayPaid.textContent = formatCurrency(uangDibayar);
            if (displayChange) displayChange.textContent = formatCurrency(Math.max(0, kembalian));
            
            // Enable/disable checkout button for cash
            const currentPaymentMethod = document.getElementById('final_metode_pembayaran');
            if (checkoutBtn && currentPaymentMethod && currentPaymentMethod.value === 'cash') {
                checkoutBtn.disabled = uangDibayar < totalHarga;
            }
        }

        // Update alamat when typing
        const alamatInput = document.getElementById('alamat_pengiriman');
        const finalAlamat = document.getElementById('final_alamat_pengiriman');
        if (alamatInput && finalAlamat) {
            alamatInput.addEventListener('input', function() {
                finalAlamat.value = this.value;
            });
        }

        // Form validation
        const checkoutForm = document.getElementById('checkout-form');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function(e) {
                const metodePembayaran = document.getElementById('final_metode_pembayaran');
                const metodePengiriman = document.getElementById('final_metode_pengiriman');
                const alamatPengiriman = document.getElementById('final_alamat_pengiriman');
                
                if (!metodePembayaran || !metodePengiriman || !alamatPengiriman) {
                    e.preventDefault();
                    alert('Terjadi kesalahan sistem!');
                    return false;
                }
                
                // Validasi untuk delivery
                if (metodePengiriman.value === 'delivery' && !alamatPengiriman.value.trim()) {
                    e.preventDefault();
                    alert('Alamat pengiriman harus diisi untuk metode delivery!');
                    return false;
                }
                
                // Validasi untuk cash
                if (metodePembayaran.value === 'cash') {
                    const uangDibayar = parseFloat(document.getElementById('uang_dibayar').value) || 0;
                    const totalHarga = <?= $total_setelah_ppn ?>;
                    
                    if (uangDibayar < totalHarga) {
                        e.preventDefault();
                        alert('Uang yang dibayarkan kurang dari total harga!');
                        return false;
                    }
                }
                
                return true;
            });
        }

        // Auto hide notification after 5 seconds
        setTimeout(function() {
            const notification = document.querySelector('.message');
            if (notification) {
                notification.style.display = 'none';
            }
        }, 5000);

        // Initialize everything when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateRadioSelection('metode_pengiriman');
            updateRadioSelection('metode_pembayaran');
            calculateChange();
        });
    </script>
</body>
</html>