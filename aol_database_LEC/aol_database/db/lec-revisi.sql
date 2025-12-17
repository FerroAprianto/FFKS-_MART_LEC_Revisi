-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Des 2025 pada 07.43
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lec-revisi`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `auto_generate_transaksi_lengkap` (IN `jumlah_data` INT)   BEGIN
    DECLARE i INT DEFAULT 1;
    DECLARE v_trx_id VARCHAR(20);
    DECLARE v_cust_id VARCHAR(20);
    DECLARE v_admin_id VARCHAR(20);
    DECLARE v_metode_id INT;
    DECLARE v_produk_id VARCHAR(10);
    DECLARE v_qty_acak INT;
    DECLARE x INT;
    
    WHILE i <= jumlah_data DO
        
        SELECT id_customer INTO v_cust_id FROM customer ORDER BY RAND() LIMIT 1;
        SELECT id_admin INTO v_admin_id FROM admin ORDER BY RAND() LIMIT 1;
        SELECT id_metode INTO v_metode_id FROM metode_pembayaran ORDER BY RAND() LIMIT 1;
        
        SET v_trx_id = CONCAT('TRX-AUTO-', UPPER(SUBSTRING(MD5(UUID()), 1, 5)));
        
        INSERT INTO `transaksi` (`id_transaksi`, `id_customer`, `id_admin`, `id_metode`, `status_pembayaran`) 
        VALUES (v_trx_id, v_cust_id, v_admin_id, v_metode_id, 'approved');
        
        SET x = 1;
        WHILE x <= (FLOOR(1 + RAND() * 3)) DO
            
            SELECT id_produk INTO v_produk_id FROM produk ORDER BY RAND() LIMIT 1;
            
            SET v_qty_acak = FLOOR(1 + RAND() * 5);
            
            CALL tambah_item_belanja(v_trx_id, v_produk_id, v_qty_acak);
            
            SET x = x + 1;
        END WHILE;
        
        CALL proses_finalisasi_transaksi(v_trx_id);
        
        SET i = i + 1;
    END WHILE;
    
    SELECT CONCAT(jumlah_data, ' Data Transaksi Berhasil Dibuat Secara Otomatis!') AS Status;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `proses_finalisasi_transaksi` (IN `p_id_transaksi` VARCHAR(20))   BEGIN
    DECLARE v_qty INT;
    DECLARE v_subtotal DECIMAL(10,2);
    DECLARE v_diskon DECIMAL(10,2);
    DECLARE v_total_bayar DECIMAL(10,2);
    
    -- 1. Panggil fungsi-fungsi yang sudah kita buat
    SET v_qty = hitung_total_qty(p_id_transaksi);
    SET v_subtotal = hitung_subtotal_kotor(p_id_transaksi);
    SET v_diskon = hitung_nominal_diskon(p_id_transaksi);
    SET v_total_bayar = hitung_grand_total_bersih(p_id_transaksi);
    
    -- 2. Update tabel transaksi
    -- Kita asumsikan kolom 'total_belanja' adalah subtotal kotor
    -- Dan kolom 'total_bayar' adalah nominal bersih setelah diskon
    UPDATE `transaksi`
    SET 
        `total_belanja` = v_subtotal,
        `total_bayar` = v_total_bayar
    WHERE `id_transaksi` = p_id_transaksi;
    
    -- 3. Tampilkan hasil (Receipt Sederhana)
    SELECT 
        p_id_transaksi AS ID_Transaksi,
        v_qty AS Jumlah_Item_Dibeli,
        FORMAT(v_subtotal, 2) AS Subtotal_Kotor,
        FORMAT(v_diskon, 2) AS Potongan_Diskon,
        FORMAT(v_total_bayar, 2) AS Total_Yang_Harus_Dibayar;
        
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `tambah_item_belanja` (IN `p_id_transaksi` VARCHAR(20), IN `p_id_produk` VARCHAR(10), IN `p_qty` INT)   BEGIN
    DECLARE v_harga DECIMAL(10,2);
    
    SELECT `harga` INTO v_harga FROM `produk` WHERE `id_produk` = p_id_produk;
    
    INSERT INTO `detail_transaksi` (`id_transaksi`, `id_produk`, `qty`, `harga_satuan_saat_ini`, `subtotal`)
    VALUES (p_id_transaksi, p_id_produk, p_qty, v_harga, (v_harga * p_qty));
END$$

--
-- Fungsi
--
CREATE DEFINER=`root`@`localhost` FUNCTION `hitung_grand_total_bersih` (`input_id_transaksi` VARCHAR(20)) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE v_subtotal DECIMAL(10,2);
    DECLARE v_diskon DECIMAL(10,2);
    
    SET v_subtotal = hitung_subtotal_kotor(input_id_transaksi);
    SET v_diskon = hitung_nominal_diskon(input_id_transaksi);
    
    RETURN (v_subtotal - v_diskon);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `hitung_nominal_diskon` (`input_id_transaksi` VARCHAR(20)) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE v_subtotal DECIMAL(10,2);
    DECLARE v_diskon DECIMAL(10,2);
    
    -- Ambil subtotal dulu
    SET v_subtotal = hitung_subtotal_kotor(input_id_transaksi);
    
    -- Logika Diskon (Silakan ubah angka di sini sesuai kebutuhan)
    IF v_subtotal >= 500000 THEN
        SET v_diskon = v_subtotal * 0.10; -- Diskon 10%
    ELSEIF v_subtotal >= 100000 THEN
        SET v_diskon = v_subtotal * 0.05; -- Diskon 5%
    ELSE
        SET v_diskon = 0;
    END IF;
    
    RETURN v_diskon;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `hitung_subtotal_kotor` (`input_id_transaksi` VARCHAR(20)) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE subtotal DECIMAL(10,2);
    
    SELECT SUM(subtotal) INTO subtotal 
    FROM `detail_transaksi` 
    WHERE `id_transaksi` = input_id_transaksi;
    
    RETURN IFNULL(subtotal, 0);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `hitung_total_belanja` (`input_id_transaksi` VARCHAR(20)) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE total DECIMAL(10,2);
    SELECT SUM(`subtotal`) INTO total 
    FROM `detail_transaksi` 
    WHERE `id_transaksi` = input_id_transaksi;
    RETURN IFNULL(total, 0);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `hitung_total_qty` (`input_id_transaksi` VARCHAR(20)) RETURNS INT(11) DETERMINISTIC BEGIN
    DECLARE total_qty INT;
    
    SELECT SUM(qty) INTO total_qty 
    FROM `detail_transaksi` 
    WHERE `id_transaksi` = input_id_transaksi;
    
    RETURN IFNULL(total_qty, 0);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `total_diskon` () RETURNS DECIMAL(12,2) DETERMINISTIC BEGIN
    DECLARE total DECIMAL(12,2);

    SELECT SUM(harga * qty * diskon_persen / 100)
    INTO total
    FROM tabel_transaksi;

    RETURN total;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `username`, `password`) VALUES
('ADM-001', 'admin', '0192023a7bbd73250516f069df18b500');

-- --------------------------------------------------------

--
-- Struktur dari tabel `customer`
--

CREATE TABLE `customer` (
  `id_customer` varchar(20) NOT NULL,
  `Username` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Age` tinyint(4) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `customer`
--

INSERT INTO `customer` (`id_customer`, `Username`, `Email`, `Age`, `Password`, `CreatedAt`) VALUES
('CSTMR-1F06', 'bambang', 'bambangganteng@gmail.com', 20, '$2y$10$lw0QgGODeRBLEBQGyw5bpuL0NpLgYzB76YQg23/Po3adOITVVKQbC', '2025-12-13 08:16:24'),
('CSTMR-3AF68', 'yanto', 'tes5@gmail.com', 34, '$2y$10$LTiMO0k5S3dfEUpxvBayiubpuSeQFXMFpskZNiYw1ZmQQ1ujYYUSC', '2025-12-17 05:11:11'),
('CSTMR-58D8', 'bambang', 'bambang@gmail.com', 17, '$2y$10$eQW0fz/QSQnHtYR4vFwDGuPt7bXJYO5LLxEDv1uVWhN7v9X4QZb.2', '2025-12-14 11:52:11'),
('CSTMR-910A', 'tes3', 'tes3@gmail.com', 16, '$2y$10$52WL4o321wa64t.yptOK9OFbMK4FAdrfkmiMZb5Rg2TbxAv447ju6', '2025-12-13 11:08:48'),
('CSTMR-C949', 'ferro', 'ferroganteng@gmail.com', 19, '$2y$10$c98hU6yL9FMtLChnLwteZO79bui8LKioKD0DhGTQjzZEUguCQLGtS', '2025-12-13 08:20:30'),
('CSTMR-FBE7', 'fredd', 'fredd@gmail.com', 19, '$2y$10$0qK41bynpdf0JNhMZPRbdulT/WyCV9fbejHzDKqDo9jmnN.v/h/MW', '2025-12-16 15:32:12');

--
-- Trigger `customer`
--
DELIMITER $$
CREATE TRIGGER `tg_bi_customer_id` BEFORE INSERT ON `customer` FOR EACH ROW BEGIN
    IF NEW.id_customer IS NULL OR NEW.id_customer = '' THEN
        SET NEW.id_customer = CONCAT('CSTMR-', UPPER(SUBSTRING(MD5(UUID()), 1, 5)));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_transaksi`
--

CREATE TABLE `detail_transaksi` (
  `id_detail` int(11) NOT NULL,
  `id_transaksi` varchar(20) DEFAULT NULL,
  `id_produk` varchar(10) DEFAULT NULL,
  `qty` int(11) DEFAULT NULL,
  `harga_satuan_saat_ini` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `detail_transaksi`
--

INSERT INTO `detail_transaksi` (`id_detail`, `id_transaksi`, `id_produk`, `qty`, `harga_satuan_saat_ini`, `subtotal`) VALUES
(40, 'TRX-2512-98539E', 'PRD-165C', 10, 3190.00, 31900.00),
(42, 'TRX-2512-9BC928', 'PRD-3DC4', 1, 16990.00, 16990.00),
(43, 'TRX-2512-9897BC', 'PRD-5F24', 1, 6090.00, 6090.00),
(44, 'TRX-2512-28EF0F', 'PRD-2A03', 1, 49500.00, 49500.00),
(45, 'TRX-2512-1D8A92', 'PRD-2A03', 2, 49500.00, 99000.00),
(46, 'TRX-2512-1D8A92', 'PRD-165C', 1, 3190.00, 3190.00),
(47, 'TRX-2512-1D8A92', 'PRD-155F', 1, 82990.00, 82990.00),
(48, 'TRX-2512-1D8A92', 'PRD-5F24', 1, 6090.00, 6090.00),
(49, 'TRX-2512-1D8A92', 'PRD-607B', 1, 4190.00, 4190.00),
(50, 'TRX-2512-1D8A92', 'PRD-934E', 1, 7290.00, 7290.00),
(51, 'TRX-2512-1D8A92', 'PRD-9042', 1, 29590.00, 29590.00),
(67, 'TRX-2512-7E3F54', 'PRD-5AEE', 1, 3190.00, 3190.00),
(68, 'TRX-2512-7E3F54', 'PRD-3DC4', 1, 16990.00, 16990.00),
(69, 'TRX-2512-7E3F54', 'PRD-2A03', 1, 49500.00, 49500.00),
(70, 'TRX-2512-1D8A92', 'PRD-155F', 5, 82990.00, 414950.00),
(71, 'TRX-2512-1D8A92', 'PRD-155F', 5, 82990.00, 414950.00),
(73, 'TRX-2512-1D8A92', 'PRD-155F', 3, 82990.00, 248970.00),
(74, 'TRX-2512-1D8A92', 'PRD-155F', 2, 82990.00, 165980.00),
(75, 'TRX-2512-1D8A92', 'PRD-155F', 12, 82990.00, 995880.00),
(76, 'TRX-2512-1D8A92', 'PRD-155F', 9, 82990.00, 746910.00),
(85, 'TRX-2512-47505A', 'PRD-2A03', 5, NULL, 247500.00);

--
-- Trigger `detail_transaksi`
--
DELIMITER $$
CREATE TRIGGER `tg_kurangi_stok` AFTER INSERT ON `detail_transaksi` FOR EACH ROW BEGIN
    UPDATE `stok_produk` 
    SET `jumlah_stok` = `jumlah_stok` - NEW.qty 
    WHERE `id_produk` = NEW.id_produk;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_produk`
--

CREATE TABLE `kategori_produk` (
  `id_kategori` int(11) NOT NULL,
  `nama_kategori` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `kategori_produk`
--

INSERT INTO `kategori_produk` (`id_kategori`, `nama_kategori`) VALUES
(1, 'Makanan Ringan'),
(2, 'Buah Segar'),
(3, 'Bahan Masak'),
(4, 'Perawatan Diri'),
(5, 'Minuman'),
(6, 'Makanan Instan');

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `lihat_kategori_dan_produk`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `lihat_kategori_dan_produk` (
`Kategori` varchar(50)
,`ID Produk` varchar(10)
,`Nama Produk` varchar(100)
,`Harga` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Struktur dari tabel `metode_pembayaran`
--

CREATE TABLE `metode_pembayaran` (
  `id_metode` int(11) NOT NULL,
  `nama_metode` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `metode_pembayaran`
--

INSERT INTO `metode_pembayaran` (`id_metode`, `nama_metode`) VALUES
(1, 'seabank'),
(2, 'BCA'),
(3, 'Mandiri');

-- --------------------------------------------------------

--
-- Struktur dari tabel `produk`
--

CREATE TABLE `produk` (
  `id_produk` varchar(10) NOT NULL,
  `id_kategori` int(11) DEFAULT NULL,
  `nama_produk` varchar(100) DEFAULT NULL,
  `harga` decimal(10,2) DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `produk`
--

INSERT INTO `produk` (`id_produk`, `id_kategori`, `nama_produk`, `harga`, `gambar`) VALUES
('PRD-155F', 4, 'LOREAL SHPF', 82990.00, 'loreal.png'),
('PRD-165C', 6, 'INDOMIE GRNG ACEH', 3190.00, 'shop6.png'),
('PRD-2A03', 2, 'LENGKENG BANGKOK', 49500.00, 'lengkeng.png'),
('PRD-3DC4', 4, 'SELECTION KOREA /karton', 16990.00, 'sedap-selection.png'),
('PRD-5AEE', 6, 'SELECTION KOREA', 3190.00, 'sedap-selection.png'),
('PRD-5F24', 3, 'SP BIHUN ', 6090.00, 'sp-bihun.png'),
('PRD-607B', 6, 'INDOMIE GR ', 4190.00, 'shop3.png'),
('PRD-67AC', 5, 'CIMORY ', 6990.00, 'shop5.png'),
('PRD-79A4', 2, 'buahahaha', 12000.00, NULL),
('PRD-7B6C', 5, 'CIMORY COOKIES', 5990.00, 'milk-cokies.png'),
('PRD-7DA1', 6, 'SEDAAP MI WHT CUR', 3190.00, 'sedap-white-curry.png'),
('PRD-9042', 4, 'FORMULA S/G ', 29590.00, 'formula-sg-sp.png'),
('PRD-934E', 5, 'FRISIAN FLAG', 7290.00, 'frisian-flag.png'),
('PRD-AADE', 4, 'COLGATE', 51990.00, 'colagate.png'),
('PRD-CD6E', 5, 'MILO UHT', 6190.00, 'shop4.png'),
('PRD-D5D2', 3, 'PRONAS SPAG P/MIA', 11900.00, 'pronas-spaggeti.jpg'),
('PRD-FA14', 1, 'POP ICE CARAMEL', 16900.00, 'pop-ice-caramel.png');

--
-- Trigger `produk`
--
DELIMITER $$
CREATE TRIGGER `tg_bi_produk_id` BEFORE INSERT ON `produk` FOR EACH ROW BEGIN
    IF NEW.id_produk IS NULL OR NEW.id_produk = '' THEN
        SET NEW.id_produk = CONCAT('PRD-', UPPER(SUBSTRING(MD5(UUID()), 1, 4)));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `stok_produk`
--

CREATE TABLE `stok_produk` (
  `id_stok` varchar(20) NOT NULL,
  `id_produk` varchar(10) DEFAULT NULL,
  `jumlah_stok` int(11) DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `stok_produk`
--

INSERT INTO `stok_produk` (`id_stok`, `id_produk`, `jumlah_stok`, `last_update`) VALUES
('STK-0FF22', 'PRD-9042', 94, '2025-12-03 11:38:52'),
('STK-144D2', 'PRD-AADE', 88, '2025-12-03 11:38:52'),
('STK-1ED20', 'PRD-165C', 24, '2025-12-13 08:57:46'),
('STK-201D9', 'PRD-5F24', 97, '2025-12-03 11:38:52'),
('STK-265B3', 'PRD-155F', 57, '2025-12-03 11:38:52'),
('STK-3FAE0', 'PRD-7B6C', 98, '2025-12-03 11:38:52'),
('STK-459B6', 'PRD-D5D2', 95, '2025-12-03 11:38:52'),
('STK-59585', 'PRD-2A03', 80, '2025-12-03 11:38:52'),
('STK-6FD77', 'PRD-67AC', 94, '2025-12-03 11:38:52'),
('STK-9FB87', 'PRD-7DA1', 91, '2025-12-07 05:52:45'),
('STK-A2876', 'PRD-607B', 97, '2025-12-03 11:38:52'),
('STK-C12D5', 'PRD-5AEE', 96, '2025-12-03 11:38:52'),
('STK-D154F', 'PRD-934E', 94, '2025-12-03 11:38:52'),
('STK-E8FCD', 'PRD-3DC4', 92, '2025-12-03 11:38:52'),
('STK-EA9C9', 'PRD-FA14', 89, '2025-12-03 11:38:52'),
('STK-F1935', 'PRD-165C', 71, '2025-12-03 11:38:52'),
('STK-F2472', 'PRD-CD6E', 99, '2025-12-03 11:38:52');

--
-- Trigger `stok_produk`
--
DELIMITER $$
CREATE TRIGGER `tg_bi_stok_id` BEFORE INSERT ON `stok_produk` FOR EACH ROW BEGIN
    IF NEW.id_stok IS NULL OR NEW.id_stok = '' THEN
        SET NEW.id_stok = CONCAT('STK-', UPPER(SUBSTRING(MD5(UUID()), 1, 5)));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `transaksi`
--

CREATE TABLE `transaksi` (
  `id_transaksi` varchar(20) NOT NULL,
  `id_customer` varchar(20) DEFAULT NULL,
  `id_admin` varchar(20) DEFAULT NULL,
  `id_metode` int(11) DEFAULT NULL,
  `tanggal_transaksi` datetime DEFAULT current_timestamp(),
  `total_belanja` decimal(10,2) DEFAULT 0.00,
  `total_bayar` decimal(10,2) DEFAULT 0.00,
  `status_pembayaran` enum('unpaid','pending','approved','rejected') NOT NULL DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `transaksi`
--

INSERT INTO `transaksi` (`id_transaksi`, `id_customer`, `id_admin`, `id_metode`, `tanggal_transaksi`, `total_belanja`, `total_bayar`, `status_pembayaran`) VALUES
('TRX-2512-1D8A92', 'CSTMR-910A', 'ADM-001', 1, '2025-12-13 22:19:09', 232340.00, 232340.00, 'approved'),
('TRX-2512-22BA56', 'CSTMR-3AF68', 'ADM-001', 1, '2025-12-17 13:03:53', 132490.00, 132490.00, 'unpaid'),
('TRX-2512-28EF0F', 'CSTMR-910A', 'ADM-001', 1, '2025-12-13 22:17:41', 49500.00, 49500.00, 'approved'),
('TRX-2512-47505A', 'CSTMR-3AF68', 'ADM-001', 2, '2025-12-17 13:40:23', 247500.00, 247500.00, 'unpaid'),
('TRX-2512-7E3F54', 'CSTMR-FBE7', 'ADM-001', 1, '2025-12-16 22:34:13', 69680.00, 69680.00, 'approved'),
('TRX-2512-98539E', 'CSTMR-910A', 'ADM-001', 1, '2025-12-13 18:17:40', 31900.00, 31900.00, 'approved'),
('TRX-2512-9897BC', 'CSTMR-910A', 'ADM-001', 1, '2025-12-13 18:56:32', 6090.00, 6090.00, 'approved'),
('TRX-2512-9BC928', 'CSTMR-910A', 'ADM-001', 1, '2025-12-13 18:55:21', 16990.00, 16990.00, 'approved');

--
-- Trigger `transaksi`
--
DELIMITER $$
CREATE TRIGGER `tg_bi_transaksi_defaults` BEFORE INSERT ON `transaksi` FOR EACH ROW BEGIN
    -- 1. Otomatis Generate ID Transaksi (TRX-TahunBulan-KodeUnik)
    IF NEW.id_transaksi IS NULL OR NEW.id_transaksi = '' THEN
        SET NEW.id_transaksi = CONCAT('TRX-', DATE_FORMAT(NOW(), '%y%m'), '-', UPPER(SUBSTRING(MD5(UUID()), 1, 6)));
    END IF;

    -- 2. Otomatis Assign Customer (Jika Kosong -> Set ke CSTMR-UMUM)
    IF NEW.id_customer IS NULL OR NEW.id_customer = '' THEN
        -- Pastikan 'CSTMR-UMUM' sudah dibuat di langkah sebelumnya
        SET NEW.id_customer = 'CSTMR-UMUM'; 
    END IF;

    -- 3. Otomatis Assign Admin (Jika Kosong -> Ambil Admin Pertama yg ditemukan)
    IF NEW.id_admin IS NULL OR NEW.id_admin = '' THEN
        SET NEW.id_admin = (SELECT id_admin FROM admin LIMIT 1);
    END IF;
    
    -- 4. Otomatis Status (Jika unpaid, biarkan. Jika kosong, set unpaid)
    IF NEW.status_pembayaran IS NULL THEN
        SET NEW.status_pembayaran = 'unpaid';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur untuk view `lihat_kategori_dan_produk`
--
DROP TABLE IF EXISTS `lihat_kategori_dan_produk`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `lihat_kategori_dan_produk`  AS SELECT `k`.`nama_kategori` AS `Kategori`, `p`.`id_produk` AS `ID Produk`, `p`.`nama_produk` AS `Nama Produk`, `p`.`harga` AS `Harga` FROM (`kategori_produk` `k` join `produk` `p` on(`k`.`id_kategori` = `p`.`id_kategori`)) ORDER BY `k`.`nama_kategori` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id_customer`),
  ADD UNIQUE KEY `Email` (`Email`);

--
-- Indeks untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_produk` (`id_produk`),
  ADD KEY `detail_transaksi_ibfk_1` (`id_transaksi`);

--
-- Indeks untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  ADD PRIMARY KEY (`id_kategori`);

--
-- Indeks untuk tabel `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  ADD PRIMARY KEY (`id_metode`);

--
-- Indeks untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id_produk`),
  ADD KEY `id_kategori` (`id_kategori`);

--
-- Indeks untuk tabel `stok_produk`
--
ALTER TABLE `stok_produk`
  ADD PRIMARY KEY (`id_stok`),
  ADD KEY `id_produk` (`id_produk`);

--
-- Indeks untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id_transaksi`),
  ADD KEY `id_metode` (`id_metode`),
  ADD KEY `fk_transaksi_users` (`id_customer`),
  ADD KEY `fk_transaksi_admin` (`id_admin`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT untuk tabel `kategori_produk`
--
ALTER TABLE `kategori_produk`
  MODIFY `id_kategori` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `metode_pembayaran`
--
ALTER TABLE `metode_pembayaran`
  MODIFY `id_metode` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_transaksi`
--
ALTER TABLE `detail_transaksi`
  ADD CONSTRAINT `detail_transaksi_ibfk_1` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`),
  ADD CONSTRAINT `detail_transaksi_ibfk_2` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`);

--
-- Ketidakleluasaan untuk tabel `produk`
--
ALTER TABLE `produk`
  ADD CONSTRAINT `produk_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_produk` (`id_kategori`);

--
-- Ketidakleluasaan untuk tabel `stok_produk`
--
ALTER TABLE `stok_produk`
  ADD CONSTRAINT `stok_produk_ibfk_1` FOREIGN KEY (`id_produk`) REFERENCES `produk` (`id_produk`);

--
-- Ketidakleluasaan untuk tabel `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `fk_transaksi_admin` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transaksi_customer` FOREIGN KEY (`id_customer`) REFERENCES `customer` (`id_customer`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `transaksi_ibfk_2` FOREIGN KEY (`id_metode`) REFERENCES `metode_pembayaran` (`id_metode`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
