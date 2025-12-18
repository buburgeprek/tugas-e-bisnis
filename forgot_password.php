<?php
session_start();
include 'koneksi.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi input
    if ($email == '' || $new_password == '' || $confirm_password == '') {
        $error = "Semua field wajib diisi.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password baru harus minimal 6 karakter.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Password baru dan konfirmasi password tidak cocok.";
    } else {
        // Cek apakah email terdaftar
        $query = "SELECT * FROM pengguna WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Hash password baru
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password di database
            $update_query = "UPDATE pengguna SET password = ? WHERE email = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param('ss', $hashed_password, $email);
            
            if ($update_stmt->execute()) {
                $success = "Password berhasil direset!";
                
                // Tampilkan SweetAlert dan redirect
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Berhasil Direset!',
                            text: 'Silakan login dengan password baru Anda.',
                            confirmButtonColor: '#d37b2c'
                        }).then(() => {
                            window.location.href = 'masuk.php';
                        });
                    });
                </script>";
            } else {
                $error = "Terjadi kesalahan saat mengupdate password. Silakan coba lagi.";
            }
            
            $update_stmt->close();
        } else {
            $error = "Email tidak terdaftar.";
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
            max-width: 420px;
            width: 100%;
            padding: 28px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,.06);
        }
        
        .form-container h2 {
            margin: 0 0 20px 0;
            color: #d37b2c;
            text-align: center;
            font-size: 24px;
        }
        
        .form-container p {
            color: #555;
            text-align: center;
            margin-bottom: 20px;
            line-height: 1.5;
            font-size: 14px;
        }
        
        .form-container input {
            width: 100%;
            padding: 12px 14px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-container input:focus {
            outline: none;
            border-color: #d37b2c;
        }
        
        .form-container .btn {
            background: #d37b2c;
            color: #fff;
            border: none;
            padding: 12px 14px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            margin-top: 10px;
            transition: background 0.3s;
        }
        
        .form-container .btn:hover {
            background: #b36622;
        }
        
        .form-container .btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        
        .error-box {
            background: #ffecec;
            color: #b00000;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .success-box {
            background: #ecf9ec;
            color: #008000;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
            text-align: center;
        }
        
        .small {
            font-size: 14px;
            color: #555;
            margin-top: 16px;
            text-align: center;
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
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #777;
        }
        
        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }
        
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            color: #777;
            position: relative;
            z-index: 2;
        }
        
        .step.active {
            border-color: #d37b2c;
            background: #d37b2c;
            color: #fff;
        }
        
        .step.completed {
            border-color: #27ae60;
            background: #27ae60;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Reset Password</h2>
        
        <p>Masukkan email Anda yang terdaftar dan buat password baru.</p>
        
        <div class="form-steps">
            <div class="step active">1</div>
            <div class="step">2</div>
            <div class="step">3</div>
        </div>
        
        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-box"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <form method="POST" id="resetForm">
            <input type="email" name="email" id="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            
            <div class="password-container">
                <input type="password" name="new_password" id="new_password" placeholder="Password Baru" required>
                <button type="button" class="toggle-password" id="toggleNewPassword">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            <div class="password-strength" id="passwordStrength"></div>
            
            <div class="password-container">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Konfirmasi Password Baru" required>
                <button type="button" class="toggle-password" id="toggleConfirmPassword">
                    <i class="far fa-eye"></i>
                </button>
            </div>
            <div class="password-strength" id="confirmPasswordMatch"></div>
            
            <button type="submit" class="btn" id="submitBtn">Reset Password</button>
        </form>
        
        <p class="small">
            <a href="masuk.php">Kembali ke halaman masuk</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Password toggle functionality
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
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthText = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[!@#$%^&*(),.?":{}|<>]+/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    strengthText.innerHTML = 'Kekuatan password: <span class="strength-weak">Lemah</span>';
                    break;
                case 2:
                case 3:
                    strengthText.innerHTML = 'Kekuatan password: <span class="strength-medium">Sedang</span>';
                    break;
                case 4:
                case 5:
                    strengthText.innerHTML = 'Kekuatan password: <span class="strength-strong">Kuat</span>';
                    break;
            }
        }
        
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchText = document.getElementById('confirmPasswordMatch');
            
            if (confirmPassword === '') {
                matchText.innerHTML = '';
            } else if (password === confirmPassword) {
                matchText.innerHTML = '<span class="strength-strong">✓ Password cocok</span>';
            } else {
                matchText.innerHTML = '<span class="strength-weak">✗ Password tidak cocok</span>';
            }
        }
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!email || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Semua field wajib diisi.');
                return;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Password baru harus minimal 6 karakter.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Password baru dan konfirmasi password tidak cocok.');
                return;
            }
        });
        
        // Update form steps based on progress
        function updateFormSteps() {
            const email = document.getElementById('email').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            const steps = document.querySelectorAll('.step');
            
            // Reset all steps
            steps.forEach(step => {
                step.classList.remove('active', 'completed');
            });
            
            // Step 1: Email filled
            if (email) {
                steps[0].classList.add('completed');
                steps[1].classList.add('active');
            } else {
                steps[0].classList.add('active');
                return;
            }
            
            // Step 2: New password filled
            if (newPassword) {
                steps[1].classList.add('completed');
                steps[2].classList.add('active');
            } else {
                steps[1].classList.add('active');
                return;
            }
            
            // Step 3: Confirm password filled and match
            if (confirmPassword && newPassword === confirmPassword) {
                steps[2].classList.add('completed');
            }
        }
        
        // Initialize
        setupPasswordToggle('new_password', 'toggleNewPassword');
        setupPasswordToggle('confirm_password', 'toggleConfirmPassword');
        
        document.getElementById('new_password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
            updateFormSteps();
        });
        
        document.getElementById('confirm_password').addEventListener('input', function() {
            checkPasswordMatch();
            updateFormSteps();
        });
        
        document.getElementById('email').addEventListener('input', updateFormSteps);
        
        // Initial update
        updateFormSteps();
    </script>
</body>
</html>