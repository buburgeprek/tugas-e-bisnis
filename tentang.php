<?php
session_start();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tentang Kami - Baker Old</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f9f5f0;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, #d37b2c 0%, #b36622 100%);
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            height: 70px;
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
        }

        .navbar ul li a i {
            margin-right: 8px;
        }

        .navbar ul li a:hover,
        .navbar ul li a.active {
            background-color: rgba(255,255,255,0.15);
        }

        /* Auth Buttons */
        .auth-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }

        .btn-login {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-login:hover {
            background: rgba(255,255,255,0.3);
        }

        .btn-register {
            background: white;
            color: #d37b2c;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .btn-register:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }

        /* Profile Dropdown Styles */
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
            color: #333;
            text-decoration: none;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            color: #d37b2c;
        }

        .dropdown-item:hover {
            background: #f9f5f0;
            color: #d37b2c;
            padding-left: 20px;
        }

        .dropdown-item.profile-link,
        .dropdown-item.logout-link {
            color: #333;
        }

        .dropdown-item.profile-link:hover,
        .dropdown-item.logout-link:hover {
            color: #d37b2c;
        }

        .dropdown-divider {
            height: 1px;
            background: #f0f0f0;
            margin: 5px 0;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 100px 20px;
            margin-bottom: 60px;
        }

        .hero h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .hero p {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
        }

        /* About Section */
        .about-section {
            padding: 60px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
            color: #d37b2c;
            font-size: 36px;
            position: relative;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #d37b2c;
            margin: 15px auto;
            border-radius: 2px;
        }

        .about-content {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 40px;
            margin-bottom: 60px;
        }

        .about-text {
            flex: 1;
            min-width: 300px;
        }

        .about-text h3 {
            font-size: 28px;
            margin-bottom: 20px;
            color: #d37b2c;
        }

        .about-text p {
            margin-bottom: 20px;
            font-size: 17px;
        }

        .about-image {
            flex: 1;
            min-width: 300px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .about-image img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s ease;
        }

        .about-image:hover img {
            transform: scale(1.05);
        }

        /* Values Section */
        .values-section {
            padding: 60px 0;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 60px;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .value-card {
            text-align: center;
            padding: 30px 20px;
            background: #f9f5f0;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .value-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .value-card i {
            font-size: 48px;
            color: #d37b2c;
            margin-bottom: 20px;
        }

        .value-card h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 22px;
        }

        .value-card p {
            color: #666;
            line-height: 1.6;
        }

        /* Team Section */
        .team-section {
            padding: 60px 0;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .team-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-10px);
        }

        .team-img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        .team-info {
            padding: 25px 20px;
        }

        .team-info h3 {
            color: #333;
            margin-bottom: 5px;
            font-size: 20px;
        }

        .team-info .position {
            color: #d37b2c;
            font-weight: 600;
            margin-bottom: 15px;
            display: block;
        }

        .team-info p {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Contact Section */
        .contact-section {
            padding: 60px 0;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 60px;
        }

        .contact-info {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-top: 40px;
        }

        .contact-card {
            background: #f9f5f0;
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
            min-width: 250px;
            max-width: 350px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .contact-card i {
            font-size: 40px;
            color: #d37b2c;
            margin-bottom: 20px;
        }

        .contact-card h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .contact-card p {
            color: #666;
        }

        /* Map Section */
        .map-section {
            padding: 60px 0;
            text-align: center;
        }

        .map-container {
            margin-top: 40px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            height: 400px;
        }

        .map-placeholder {
            background: #e9e9e9;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 18px;
        }

        /* Footer */
        footer {
            background-color: #333;
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }

        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 30px;
        }

        .footer-column {
            flex: 1;
            min-width: 250px;
        }

        .footer-column h3 {
            margin-bottom: 20px;
            color: #d37b2c;
            font-size: 20px;
        }

        .footer-column p, .footer-column a {
            color: #ccc;
            margin-bottom: 10px;
            display: block;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-column a:hover {
            color: #d37b2c;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #444;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: #d37b2c;
            transform: translateY(-5px);
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #999;
            font-size: 14px;
        }

        /* Responsive Design */
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
            }

            .auth-buttons {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                justify-content: center;
            }

            .profile-dropdown {
                margin-left: 0;
            }

            .hero h1 {
                font-size: 36px;
            }
            
            .hero p {
                font-size: 18px;
            }
            
            .about-content {
                flex-direction: column;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .social-links {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .navbar ul {
                flex-direction: column;
                gap: 10px;
            }

            .auth-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .hero {
                padding: 60px 20px;
            }

            .hero h1 {
                font-size: 28px;
            }

            .section-title {
                font-size: 28px;
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
            <li><a href="keranjang.php"><i class="fas fa-shopping-cart"></i> Keranjang</a></li>
            <li><a href="tentang.php"><i class="fas fa-info-circle"></i> Tentang</a></li>
             <li><a href="ulasan_public.php"> <i class="fas fa-star"></i> Ulasan Produk</a></li>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Profile Dropdown (muncul hanya jika login) -->
                <li class="profile-dropdown" id="profileDropdown">
                    <div class="profile-toggle">
                        <img src="images/profil.jpeg" alt="User" class="profile-img">
                        <span class="profile-name"><?= htmlspecialchars($_SESSION['user_name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </div>
                    <div class="dropdown-menu">
                        <a href="profile.php" class="dropdown-item profile-link">
                            <i class="fas fa-user"></i>
                            Profil Saya
                        </a>
                        <a href="rating_produk.php" class="dropdown-item profile-link">
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
                        <a href="logout.php" class="dropdown-item logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </li>
            <?php else: ?>
                <!-- Auth Buttons (muncul hanya jika belum login) -->
                <div class="auth-buttons">
                    <a href="masuk.php" class="btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    
                </div>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Tentang Baker Old</h1>
            <p>Menghadirkan roti berkualitas dengan resep tradisional sejak 1985</p>
        </div>
    </section>

    <!-- About Section -->
    <section class="about-section">
        <div class="container">
            <h2 class="section-title">Cerita Kami</h2>
            <div class="about-content">
                <div class="about-text">
                    <h3>Dibuat dengan Cinta, Dihidangkan dengan Kebanggaan</h3>
                    <p>Baker Old didirikan pada tahun 1985 oleh keluarga Santoso dengan misi sederhana: menghadirkan roti terbaik dengan resep warisan leluhur. Dimulai dari toko kecil di sudut jalan, kami telah berkembang menjadi destinasi favorit para pencinta roti autentik.</p>
                    <p>Kami percaya bahwa kualitas terletak pada bahan-bahan terbaik dan proses pembuatan yang penuh perhatian. Setiap produk kami dibuat dengan tangan menggunakan teknik tradisional yang telah disempurnakan selama tiga generasi.</p>
                    <p>Dari roti tawar klasik hingga pastry spesial, setiap gigitan membawa kenangan akan rasa otentik yang sulit ditemukan di tempat lain. Komitmen kami terhadap kualitas dan rasa autentik tetap tidak berubah meskipun waktu terus bergulir.</p>
                </div>
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1558961363-fa8fdf82db35?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Interior Baker Old">
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="values-section">
        <div class="container">
            <h2 class="section-title">Nilai-Nilai Kami</h2>
            <div class="values-grid">
                <div class="value-card">
                    <i class="fas fa-heart"></i>
                    <h3>Kualitas Terbaik</h3>
                    <p>Kami hanya menggunakan bahan-bahan pilihan terbaik untuk memastikan setiap produk memiliki cita rasa yang konsisten dan berkualitas tinggi.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-history"></i>
                    <h3>Resep Tradisional</h3>
                    <p>Resep warisan keluarga yang telah teruji selama puluhan tahun, dipertahankan untuk menjaga keautentikan rasa.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-users"></i>
                    <h3>Pelayanan Ramah</h3>
                    <p>Tim kami siap melayani dengan senyuman dan keramahan, membuat setiap kunjungan Anda berkesan.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-leaf"></i>
                    <h3>Ramah Lingkungan</h3>
                    <p>Kami berkomitmen menggunakan kemasan ramah lingkungan dan mengurangi limbah dalam setiap proses produksi.</p>
                </div>
            </div>
        </div>
    </section>

    

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="container">
            <h2 class="section-title">Hubungi Kami</h2>
            <div class="contact-info">
                <div class="contact-card">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Alamat</h3>
                    <p>Jl. Roti Enak No. 123<br>Kota Baker, 12345<br>Indonesia</p>
                </div>
                <div class="contact-card">
                    <i class="fas fa-phone"></i>
                    <h3>Telepon</h3>
                    <p>(021) 1234-5678<br>+62 857-1155-5527</p>
                </div>
                <div class="contact-card">
                    <i class="fas fa-envelope"></i>
                    <h3>Email</h3>
                    <p>info@bakerold.com<br>order@bakerold.com</p>
                </div>
                <div class="contact-card">
                    <i class="fas fa-clock"></i>
                    <h3>Jam Operasional</h3>
                    <p>Senin - Sabtu<br>07:00 - 20:00 WIB</p>
                    <p>Minggu <br>10:00 - 17:00 WIB</p>

                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <h2 class="section-title">Lokasi Kami</h2>
            <div class="map-container">
                <div class="map-placeholder">
                    <div>
                        <i class="fas fa-map-marked-alt" style="font-size: 48px; margin-bottom: 15px; color: #d37b2c;"></i>
                        <p>Peta Lokasi Baker Old</p>
                        <p style="font-size: 14px; margin-top: 10px;">Jl. Roti Enak No. 123, Kota Baker</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Baker Old</h3>
                    <p>Menghadirkan roti berkualitas dengan resep tradisional sejak 1985. Setiap gigitan adalah kenangan.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Tautan Cepat</h3>
                    <a href="beranda.php">Beranda</a>
                    <a href="menu.php">Menu</a>
                    <a href="tentang.php">Tentang Kami</a>
                    <a href="kontak.html">Kontak</a>
                </div>
                <div class="footer-column">
                    <h3>Kontak</h3>
                    <p><i class="fas fa-map-marker-alt"></i> Jl. Roti Enak No. 123, Kota Baker</p>
                    <p><i class="fas fa-phone"></i> (021) 1234-5678</p>
                    <p><i class="fas fa-envelope"></i> info@bakerold.com</p>
                </div>
                
            </div>
            <div class="copyright">
                <p>&copy; 2025 Baker Old. All Rights Reserved. | Dibuat dengan <i class="fas fa-heart" style="color: #d37b2c;"></i> untuk para pencinta roti</p>
            </div>
        </div>
    </footer>

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

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.value-card, .team-card, .contact-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>