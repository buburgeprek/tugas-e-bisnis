<?php
session_start();
include 'koneksi.php';

$errors = [];
$old = ['nama'=>'', 'email'=>'', 'no_hp'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ambil input dan amankan
    $nama    = trim($_POST['nama'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $no_hp   = trim($_POST['no_hp'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $role    = 'pengguna'; // Default role untuk register public

    $old['nama'] = htmlspecialchars($nama, ENT_QUOTES);
    $old['email'] = htmlspecialchars($email, ENT_QUOTES);
    $old['no_hp'] = htmlspecialchars($no_hp, ENT_QUOTES);

    // validasi
    if ($nama === '') {
        $errors[] = "Nama lengkap wajib diisi.";
    } elseif (strlen($nama) < 3) {
        $errors[] = "Nama terlalu pendek (minimal 3 karakter).";
    }

    if ($email === '') {
        $errors[] = "Email wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format email tidak valid.";
    }

    if ($no_hp === '') {
        $errors[] = "Nomor HP wajib diisi.";
    } elseif (!preg_match('/^[0-9]{10,13}$/', $no_hp)) {
        $errors[] = "Format nomor HP tidak valid (10-13 digit angka).";
    }

    if ($pass === '') {
        $errors[] = "Password wajib diisi.";
    } elseif (strlen($pass) < 6) {
        $errors[] = "Password minimal 6 karakter.";
    }

    if ($pass !== $confirm) {
        $errors[] = "Password dan konfirmasi password tidak sama.";
    }

    // jika tidak ada error, cek duplicate email hanya di tabel pengguna
    if (empty($errors)) {
        $cek_pengguna = $conn->prepare("SELECT id_pelanggan FROM pengguna WHERE email = ?");
        $cek_pengguna->bind_param("s", $email);
        $cek_pengguna->execute();
        $cek_pengguna->store_result();

        // Untuk admin, kita cek berdasarkan username (karena admin tidak punya email)
        $cek_admin = $conn->prepare("SELECT id_admin FROM admin WHERE username = ?");
        $cek_admin->bind_param("s", $email); // menggunakan email sebagai username untuk pengecekan
        $cek_admin->execute();
        $cek_admin->store_result();

        if ($cek_pengguna->num_rows > 0 || $cek_admin->num_rows > 0) {
            $errors[] = "Email/Username sudah terdaftar. Silakan login.";
        }
        $cek_pengguna->close();
        $cek_admin->close();
    }

    // jika valid dan belum terdaftar
    if (empty($errors)) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        
        // Insert ke tabel pengguna
        $stmt = $conn->prepare("INSERT INTO pengguna (nama, email, no_hp, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nama, $email, $no_hp, $hash, $role);

        if ($stmt->execute()) {
            // Set session untuk auto login setelah redirect
            $_SESSION['registration_success'] = true;
            $_SESSION['registered_email'] = $email;
            
            // Redirect ke halaman login dengan parameter success
            header("Location: masuk.php?registered=true");
            exit;
        } else {
            $errors[] = "Terjadi kesalahan saat menyimpan data: " . $conn->error;
        }
        $stmt->close();
    }
}

// Cek jika ada parameter success dari redirect
if (isset($_GET['success']) && $_GET['success'] == 'true') {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Pendaftaran Berhasil!',
                text: 'Silakan login untuk melanjutkan.',
                confirmButtonColor: '#d37b2c'
            }).then(() => {
                window.location.href = 'masuk.php';
            });
        });
    </script>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .form-container {
            max-width:420px;
            margin:40px auto;
            padding:28px;
            background:#fff;
            border-radius:12px;
            box-shadow:0 6px 20px rgba(0,0,0,.06);
        }
        .form-container h2{ color:#d37b2c; text-align:center; margin-bottom:10px; }
        .form-container input { 
            width:100%; 
            padding:10px 12px; 
            margin:8px 0; 
            border:1px solid #ddd; 
            border-radius:8px; 
            box-sizing: border-box;
        }
        .form-container .btn { 
            background:#d37b2c; 
            color:#fff; 
            border:none; 
            padding:10px 14px; 
            border-radius:8px; 
            cursor:pointer; 
            width:100%; 
            font-size:16px;
            margin-top: 10px;
        }
        .form-container .btn:hover {
            background:#b36622;
        }
        .errors { 
            background:#ffecec; 
            color:#b00000; 
            padding:10px; 
            border-radius:8px; 
            margin-bottom:12px; 
        }
        .small { font-size:13px; color:#555; margin-top:8px; text-align:center; }
        
        .password-container {
            position: relative;
            width: 100%;
            margin: 8px 0;
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
    </style>
</head>
<body class="register-page">
    <div class="form-container">
        <h2>Daftar</h2>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul style="margin:0 0 0 18px;padding:0;">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e, ENT_QUOTES); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="text" name="nama" placeholder="Nama Lengkap" required value="<?php echo $old['nama']; ?>">
            <input type="email" name="email" placeholder="Email" required value="<?php echo $old['email']; ?>">
            <input type="text" name="no_hp" placeholder="Nomor HP" required value="<?php echo $old['no_hp']; ?>">
            
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" class="toggle-password" id="togglePassword">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            
            <div class="password-container">
                <input type="password" name="confirm" id="confirmPassword" placeholder="Ketik Ulang Password" required>
                <button type="button" class="toggle-password" id="toggleConfirmPassword">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            
            <button type="submit" class="btn">Sign Up</button>
        </form>

        <p class="small">Sudah punya akun? <a href="masuk.php">login</a></p>
        <p class="small">Lupa Password? <a href="forgot_password.php">Reset Disini</a></p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Fungsi untuk toggle show/hide password
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
        
        // Setup untuk kedua field password
        setupPasswordToggle('password', 'togglePassword');
        setupPasswordToggle('confirmPassword', 'toggleConfirmPassword');

        // SweetAlert untuk success message dari parameter URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('success') === 'true') {
                Swal.fire({
                    icon: 'success',
                    title: 'Pendaftaran Berhasil!',
                    text: 'Silakan login untuk melanjutkan.',
                    confirmButtonColor: '#d37b2c'
                }).then(() => {
                    window.location.href = 'masuk.php';
                });
            }
        });
    </script>
</body>
</html>