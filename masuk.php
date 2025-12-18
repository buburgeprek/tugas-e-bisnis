<?php
session_start();
include 'koneksi.php';

$error = '';

// Cek jika ada redirect dari pendaftaran berhasil
if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Pendaftaran Berhasil!',
                text: 'Silakan login untuk melanjutkan.',
                confirmButtonColor: '#d37b2c'
            });
        });
    </script>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($email == '' || $password == '') {
        $error = "Semua field wajib diisi.";
    } else {
        // Cek di tabel pengguna terlebih dahulu (berdasarkan email)
        $query_pengguna = "SELECT * FROM pengguna WHERE email = ?";
        $stmt_pengguna = $conn->prepare($query_pengguna);
        $stmt_pengguna->bind_param('s', $email);
        $stmt_pengguna->execute();
        $result_pengguna = $stmt_pengguna->get_result();

        $user = null;
        $user_type = '';

        if ($result_pengguna->num_rows == 1) {
            $user = $result_pengguna->fetch_assoc();
            $user_type = 'pengguna';
        } else {
            // Jika tidak ditemukan di pengguna, cek di tabel admin (berdasarkan username)
            $query_admin = "SELECT * FROM admin WHERE username = ?";
            $stmt_admin = $conn->prepare($query_admin);
            $stmt_admin->bind_param('s', $email);
            $stmt_admin->execute();
            $result_admin = $stmt_admin->get_result();

            if ($result_admin->num_rows == 1) {
                $user = $result_admin->fetch_assoc();
                $user_type = 'admin';
            }
            $stmt_admin->close();
        }

        if ($user && password_verify($password, $user['password'])) {
            // Set session berdasarkan tipe user
            if ($user_type === 'pengguna') {
                $_SESSION['user_id'] = $user['id_pelanggan'];
                $_SESSION['user_name'] = $user['nama'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_no_hp'] = $user['no_hp'];
                $_SESSION['user_role'] = $user['role'] ?? 'pengguna';
                
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Login Berhasil!',
                            text: 'Selamat datang di Baker Old!',
                            confirmButtonColor: '#d37b2c'
                        }).then(() => {
                            window.location.href = 'beranda.php';
                        });
                    });
                </script>";
                exit;
            } else if ($user_type === 'admin') {
                $_SESSION['admin_id'] = $user['id_admin'];
                $_SESSION['admin_name'] = $user['nama_admin'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Login Admin Berhasil!',
                            text: 'Selamat datang di Dashboard Admin!',
                            confirmButtonColor: '#d37b2c'
                        }).then(() => {
                            window.location.href = 'admin_dashboard.php';
                        });
                    });
                </script>";
                exit;
            }
        } else {
            $error = "Email/Username atau password salah!";
        }
        $stmt_pengguna->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Masuk - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f9f5f0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .form-container { 
            max-width:420px; 
            width: 100%;
            padding:28px; 
            background:#fff; 
            border-radius:12px; 
            box-shadow:0 6px 20px rgba(0,0,0,.06); 
        }
        .form-container h2{ 
            margin:0 0 20px 0; 
            color:#d37b2c; 
            text-align:center; 
            font-size: 24px;
        }
        .form-container input { 
            width:100%; 
            padding:12px 14px; 
            margin:10px 0; 
            border:1px solid #ddd; 
            border-radius:8px; 
            box-sizing: border-box;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-container input:focus {
            outline: none;
            border-color: #d37b2c;
        }
        .form-container .btn { 
            background:#d37b2c; 
            color:#fff; 
            border:none; 
            padding:12px 14px; 
            border-radius:8px; 
            cursor:pointer; 
            width:100%; 
            font-size:16px;
            margin-top: 10px;
            transition: background 0.3s;
        }
        .form-container .btn:hover {
            background:#b36622;
        }
        .error-box { 
            background:#ffecec; 
            color:#b00000; 
            padding:10px; 
            border-radius:8px; 
            margin-bottom:12px; 
            text-align: center;
        }
        .small { 
            font-size:14px; 
            color:#555; 
            margin-top:16px; 
            text-align:center; 
        }
        .small a {
            color: #d37b2c;
            text-decoration: none;
        }
        .small a:hover {
            text-decoration: underline;
        }
        
        .password-container {
            position: relative;
            width: 100%;
            margin: 10px 0;
        }
        .password-container input {
            padding-right: 40px;
            width: 100%;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #777;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .toggle-password:hover {
            color: #d37b2c;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }
        
        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .divider span {
            padding: 0 10px;
            color: #777;
            font-size: 14px;
        }
        
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        .google-btn:hover {
            background: #f5f5f5;
        }
        
        .google-btn img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
        }
    </style>
</head>
<body class="register-page">
    <div class="form-container">
        <h2>Login</h2>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="email" placeholder="Email atau Username" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" class="toggle-password" id="togglePassword">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            
            <button type="submit" class="btn">Log In</button>
        </form>

        <div class="divider">
            <span>atau</span>
        </div>

        <button class="google-btn" onclick="loginWithGoogle()">
            <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google Logo">
            Login with Google
        </button>

        <p class="small">Belum punya akun? <a href="daftar.php">Daftar Disini</a></p>
         <p class="small">Lupa Password? <a href="forgot_password.php">reset disini</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function setupPasswordToggle(passwordId, toggleId) {
            const passwordInput = document.getElementById(passwordId);
            const toggleButton = document.getElementById(toggleId);
            const icon = toggleButton.querySelector('i');
            
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        }
        
        setupPasswordToggle('password', 'togglePassword');
        
        function loginWithGoogle() {
            // Fungsi untuk login dengan Google
            // Anda dapat mengimplementasikan OAuth2 atau metode autentikasi Google lainnya di sini
            alert('Fitur Login dengan Google akan segera tersedia!');
            // window.location.href = 'google-auth.php'; // Arahkan ke halaman autentikasi Google
        }
    </script>
</body>
</html>