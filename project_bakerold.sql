-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 15 Des 2025 pada 16.34
-- Versi server: 8.0.30
-- Versi PHP: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `project_bakerold`
--
CREATE DATABASE IF NOT EXISTS `project_bakerold` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `project_bakerold`;

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int NOT NULL,
  `nama_admin` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('pengguna','admin') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `nama_admin`, `email`, `username`, `password`, `role`) VALUES
(1, 'Administrator', 'admin@bakerold.com', 'adminku', '$2y$10$cy0ibB3doslICNiElPigSegeh/HUIZirzwWzQtzhLj/64kHuk7qtu', 'admin'),
(2, 'Administrator', 'admin@bakerold.com', 'adminku33', '$2y$10$Yeby.FS6/BUMIu.LsYr/Uu6.uaPbwn3uRyzU9XrK4DgPk0.Q5u.d2', 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori`
--

CREATE TABLE `kategori` (
  `id_kategori` int NOT NULL,
  `nama_kategori` varchar(100) NOT NULL,
  `deskripsi` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `kategori`
--

INSERT INTO `kategori` (`id_kategori`, `nama_kategori`, `deskripsi`, `created_at`, `updated_at`) VALUES
(1, 'Roti Tawar', 'Roti tawar segar untuk sarapan atau camilan', '2025-10-23 11:21:56', '2025-10-23 11:21:56'),
(2, 'Roti Manis', 'Roti dengan rasa manis dan berbagai topping', '2025-10-23 11:21:56', '2025-10-23 11:21:56'),
(3, 'Roti Gandum', 'Roti sehat dari bahan gandum utuh', '2025-10-23 11:21:56', '2025-10-23 11:21:56'),
(4, 'Pastry', 'Berbagai jenis kue pastry dan kue kering', '2025-10-23 11:21:56', '2025-10-23 11:21:56'),
(5, 'Roti Special', 'Roti spesial dengan resep unik Baker Old', '2025-10-23 11:21:56', '2025-10-23 11:21:56'),
(6, 'Roti Vanilla', 'Roti Vanilla', '2025-10-23 11:22:25', '2025-10-23 11:22:25'),
(7, 'Roti Coklat', 'Roti Coklat', '2025-10-23 11:23:54', '2025-10-23 11:23:54'),
(8, 'Keju', 'Roti Keju', '2025-10-23 12:26:28', '2025-10-23 12:26:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi_admin`
--

CREATE TABLE `notifikasi_admin` (
  `id_notifikasi` int NOT NULL,
  `id_pesanan` int DEFAULT NULL,
  `judul` varchar(255) DEFAULT NULL,
  `pesan` text,
  `dibaca` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pelanggan` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_hp` bigint NOT NULL,
  `password` varchar(255) NOT NULL,
  `alamat` text,
  `tipe_pengiriman` enum('kirim','ambil') DEFAULT 'ambil',
  `foto_profil` varchar(255) DEFAULT NULL,
  `role` enum('pengguna','admin') DEFAULT 'pengguna',
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `pengguna`
--

INSERT INTO `pengguna` (`id_pelanggan`, `nama`, `email`, `no_hp`, `password`, `alamat`, `tipe_pengiriman`, `foto_profil`, `role`, `reset_token`, `reset_token_expires`) VALUES
(3, 'StevComp', 'stevcomp58@gmail.com', 89604134028, '$2y$10$/woSarZUfFGYGF18O1PdOe1gcPEkM/sIuO7U/ZpNOSrmi5/b9qVKi', NULL, 'ambil', NULL, 'pengguna', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengiriman`
--

CREATE TABLE `pengiriman` (
  `id_pengiriman` int NOT NULL,
  `id_pesanan` int DEFAULT NULL,
  `metode_pengiriman` enum('gojek','shopeefood','tim_delivery_baker_old') DEFAULT NULL,
  `tanggal_kirim` date DEFAULT NULL,
  `nama_kurir` varchar(100) DEFAULT NULL,
  `jenis_kendaraan` enum('motor','mobil','sepeda') DEFAULT NULL,
  `nomor_kendaraan` varchar(20) DEFAULT NULL,
  `status_pengiriman` enum('diproses','dikirim','dalam_perjalanan','selesai','dibatalkan') DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `waktu_tiba` datetime DEFAULT NULL,
  `estimasi_waktu_tiba` datetime DEFAULT NULL,
  `konfirmasi_diterima` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan_baru`
--

CREATE TABLE `pesanan_baru` (
  `id_pesanan` int NOT NULL,
  `id_pelanggan` int NOT NULL,
  `tanggal_pesanan` datetime NOT NULL,
  `status_pesanan` varchar(50) DEFAULT 'pending',
  `total_harga` decimal(10,2) NOT NULL,
  `metode_pembayaran` varchar(50) NOT NULL,
  `metode_pengiriman` varchar(50) NOT NULL,
  `alamat_pengiriman` text,
  `uang_dibayar` decimal(10,2) DEFAULT NULL,
  `kembalian` decimal(10,2) DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `total_diskon` decimal(10,2) DEFAULT '0.00',
  `ppn` decimal(10,2) DEFAULT '0.00',
  `total_setelah_ppn` decimal(10,2) DEFAULT '0.00',
  `tanggal_dikirim` datetime DEFAULT NULL,
  `tanggal_konfirmasi_user` datetime DEFAULT NULL,
  `dikonfirmasi_oleh` enum('admin','user') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `pesanan_baru`
--

INSERT INTO `pesanan_baru` (`id_pesanan`, `id_pelanggan`, `tanggal_pesanan`, `status_pesanan`, `total_harga`, `metode_pembayaran`, `metode_pengiriman`, `alamat_pengiriman`, `uang_dibayar`, `kembalian`, `bukti_pembayaran`, `created_at`, `updated_at`, `total_diskon`, `ppn`, `total_setelah_ppn`, `tanggal_dikirim`, `tanggal_konfirmasi_user`, `dikonfirmasi_oleh`) VALUES
(27, 3, '2025-12-12 15:30:23', 'selesai', 8164.80, '0', 'takeaway', '', 8164.80, 0.00, 'images/uploads/bukti_pembayaran/admin_27_1765553471.jpeg', '2025-12-12 15:30:23', '2025-12-12 15:41:37', 810.00, 0.00, 0.00, NULL, NULL, 'admin'),
(28, 3, '2025-12-12 15:39:56', 'pending', 18222.40, '0', 'takeaway', '', 18222.40, 0.00, NULL, '2025-12-12 15:39:56', '2025-12-12 15:39:56', 4030.00, 0.00, 0.00, NULL, NULL, 'admin'),
(29, 3, '2025-12-12 15:41:07', 'selesai', 840.00, '0', 'takeaway', '', 840.00, 0.00, NULL, '2025-12-12 15:41:07', '2025-12-12 15:42:15', 750.00, 0.00, 0.00, NULL, NULL, 'admin');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pesanan_items_baru`
--

CREATE TABLE `pesanan_items_baru` (
  `id_item` int NOT NULL,
  `id_pesanan` int NOT NULL,
  `nama_produk` varchar(255) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `jumlah` int NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `diskon` decimal(10,2) DEFAULT '0.00',
  `jenis_diskon` varchar(255) DEFAULT '',
  `subtotal_setelah_diskon` decimal(10,2) DEFAULT '0.00',
  `is_promo_gratis` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `pesanan_items_baru`
--

INSERT INTO `pesanan_items_baru` (`id_item`, `id_pesanan`, `nama_produk`, `harga`, `jumlah`, `subtotal`, `created_at`, `diskon`, `jenis_diskon`, `subtotal_setelah_diskon`, `is_promo_gratis`) VALUES
(29, 27, 'Roti Vanilla', 8100.00, 1, 8100.00, '2025-12-12 15:30:23', 810.00, 'Diskon 10.00% dari produk', 7290.00, 0),
(30, 28, 'Roti Vanilla', 8100.00, 1, 8100.00, '2025-12-12 15:39:56', 810.00, 'Diskon 10.00% dari produk', 7290.00, 0),
(31, 28, 'Roti Coklat', 7200.00, 1, 7200.00, '2025-12-12 15:39:56', 720.00, 'Diskon 10.00% dari produk', 6480.00, 0),
(32, 28, 'Roti Keju', 5000.00, 1, 5000.00, '2025-12-12 15:39:56', 2500.00, 'Diskon 50.00% dari produk', 2500.00, 0),
(33, 29, 'Roti Vanilla new', 1500.00, 1, 1500.00, '2025-12-12 15:41:07', 750.00, 'Diskon 50.00% dari produk', 750.00, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id_produk` int NOT NULL,
  `nama_produk` varchar(100) NOT NULL,
  `harga` decimal(10,2) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `deskripsi_produk` text,
  `id_kategori` int DEFAULT NULL,
  `gambar_produk` varchar(255) DEFAULT NULL,
  `stok` int DEFAULT '0',
  `status_produk` enum('tersedia','habis') DEFAULT 'tersedia',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `diskon` decimal(5,2) DEFAULT '0.00',
  `harga_setelah_diskon` decimal(10,2) DEFAULT '0.00',
  `expired_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id_produk`, `nama_produk`, `harga`, `kategori`, `deskripsi_produk`, `id_kategori`, `gambar_produk`, `stok`, `status_produk`, `created_at`, `updated_at`, `diskon`, `harga_setelah_diskon`, `expired_date`) VALUES
(1, 'Roti Coklat', 8000.00, NULL, 'Dibuat dengan coklat batang pilihan, bukan coklat cair. Hasilnya? Rasa yang lebih dalam dan lumeran yang sempurna di setiap helai roti yang empuk.', 7, 'images/uploads/roti/68fa10790d388_1761218681.jpeg', 92, 'tersedia', '2025-10-23 11:24:41', '2025-12-12 15:39:56', 10.00, 7200.00, '2025-12-31'),
(2, 'Roti Keju', 10000.00, NULL, 'Dengarkan kerenyahan pertama saat Anda menggigitnya. Lihatlah jejak keju yang memanjang. Rasakan lembutnya roti dan gurihnya keju yang berpadu. Inilah simfoni rasa untuk para pencinta keju.', 8, 'images/uploads/roti/68fa1f1d618ae_1761222429.jpeg', 76, 'tersedia', '2025-10-23 12:27:09', '2025-12-12 15:39:56', 50.00, 5000.00, '2025-12-31'),
(3, 'Roti Vanilla', 9000.00, NULL, 'Kelembutan yang polos, namun begitu menggetarkan hati. Aroma vanilla yang hangat dan natural menyelimuti setiap serat roti ini, menciptakan kenyamanan dalam setiap suapan.', 6, 'images/uploads/roti/68fa1febca549_1761222635.jpeg', 82, 'tersedia', '2025-10-23 12:30:25', '2025-12-12 15:39:56', 10.00, 8100.00, '2025-12-30'),
(4, 'Roti Vanilla new', 3000.00, NULL, 'test', 6, 'images/uploads/roti/693c378484fb9_1765554052.jpeg', 99, 'tersedia', '2025-12-12 15:40:52', '2025-12-12 15:41:07', 50.00, 1500.00, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `ulasan_produk`
--

CREATE TABLE `ulasan_produk` (
  `id_ulasan` int NOT NULL,
  `id_pesanan` int NOT NULL,
  `id_produk` int NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `rating` int NOT NULL,
  `ulasan` text,
  `tanggal_ulasan` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status_ulasan` enum('pending','disetujui','ditolak') DEFAULT 'disetujui'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`);

--
-- Indeks untuk tabel `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id_kategori`),
  ADD UNIQUE KEY `nama_kategori` (`nama_kategori`);

--
-- Indeks untuk tabel `notifikasi_admin`
--
ALTER TABLE `notifikasi_admin`
  ADD PRIMARY KEY (`id_notifikasi`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pelanggan`),
  ADD UNIQUE KEY `unique_nama` (`nama`);

--
-- Indeks untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD PRIMARY KEY (`id_pengiriman`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `pesanan_baru`
--
ALTER TABLE `pesanan_baru`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD KEY `id_pelanggan` (`id_pelanggan`);

--
-- Indeks untuk tabel `pesanan_items_baru`
--
ALTER TABLE `pesanan_items_baru`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_pesanan` (`id_pesanan`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD KEY `fk_produk_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  ADD PRIMARY KEY (`id_ulasan`),
  ADD KEY `fk_ulasan_pesanan` (`id_pesanan`),
  ADD KEY `idx_ulasan_produk` (`id_produk`),
  ADD KEY `idx_ulasan_pelanggan` (`nama_pelanggan`),
  ADD KEY `idx_ulasan_tanggal` (`tanggal_ulasan`),
  ADD KEY `idx_ulasan_status` (`status_ulasan`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id_kategori` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `notifikasi_admin`
--
ALTER TABLE `notifikasi_admin`
  MODIFY `id_notifikasi` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id_pelanggan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  MODIFY `id_pengiriman` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pesanan_baru`
--
ALTER TABLE `pesanan_baru`
  MODIFY `id_pesanan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `pesanan_items_baru`
--
ALTER TABLE `pesanan_items_baru`
  MODIFY `id_item` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT untuk tabel `produk`
--
ALTER TABLE `produk`
  MODIFY `id_produk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  MODIFY `id_ulasan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `notifikasi_admin`
--
ALTER TABLE `notifikasi_admin`
  ADD CONSTRAINT `notifikasi_admin_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan_baru` (`id_pesanan`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pengiriman`
--
ALTER TABLE `pengiriman`
  ADD CONSTRAINT `fk_pengiriman_pesanan` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan_baru` (`id_pesanan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pesanan_baru`
--
ALTER TABLE `pesanan_baru`
  ADD CONSTRAINT `pesanan_baru_ibfk_1` FOREIGN KEY (`id_pelanggan`) REFERENCES `pengguna` (`id_pelanggan`);

--
-- Ketidakleluasaan untuk tabel `pesanan_items_baru`
--
ALTER TABLE `pesanan_items_baru`
  ADD CONSTRAINT `pesanan_items_baru_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan_baru` (`id_pesanan`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `fk_produk_kategori` FOREIGN KEY (`id_kategori`) REFERENCES `kategori` (`id_kategori`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ulasan_produk`
--
ALTER TABLE `ulasan_produk`
  ADD CONSTRAINT `ulasan_produk_ibfk_1` FOREIGN KEY (`nama_pelanggan`) REFERENCES `pengguna` (`nama`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `ulasan_produk_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `ulasan_produk_ibfk_3` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan_baru` (`id_pesanan`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;