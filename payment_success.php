<?php
session_start();
if (!isset($_SESSION['last_order_id'])) {
    header("Location: menu.php");
    exit;
}

$order_id = $_SESSION['last_order_id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Payment Success - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f9f5f0;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .success-container {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
            animation: bounce 1s ease;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }

        h1 {
            color: #28a745;
            margin-bottom: 20px;
            font-size: 32px;
        }

        p {
            color: #666;
            margin-bottom: 15px;
            font-size: 18px;
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

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
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

        .btn-receipt {
            background: #d37b2c;
            color: white;
        }

        .btn-receipt:hover {
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

        .btn-continue {
            background: #28a745;
            color: white;
        }

        .btn-continue:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Pesanan Berhasil!</h1>
        <p>Terima kasih telah berbelanja di Baker Old</p>
        <div class="order-id">
            Order ID: #<?= str_pad($order_id, 6, '0', STR_PAD_LEFT) ?>
        </div>
        <p>Pesanan Anda sedang diproses dan akan segera disiapkan.</p>
        
        <div class="btn-group">
            <a href="bukti_transaksi.php?order_id=<?= $order_id ?>" class="btn btn-receipt">
                <i class="fas fa-receipt"></i> Lihat Bukti Transaksi
            </a>
            <a href="beranda.php" class="btn btn-home">
                <i class="fas fa-home"></i> Ke Beranda
            </a>
            <a href="menu.php" class="btn btn-continue">
                <i class="fas fa-utensils"></i> Pesan Lagi
            </a>
        </div>
    </div>

    <?php
    // Jangan clear session di sini, biarkan untuk bukti_transaksi.php
    // unset($_SESSION['last_order_id']);
    ?>
</body>
</html>