<?php
require_once __DIR__ . '/../PHP/config.php';

$id = $_GET['id'] ?? "";

if ($id === "") {
    die("Error: ID struk tidak ditemukan.");
}


$queryHeader = "SELECT t.id_transaksi, t.tanggal_transaksi, t.status_pembayaran, 
                       t.total_belanja, t.total_bayar, 
                       m.nama_metode
                FROM transaksi t
                LEFT JOIN metode_pembayaran m ON t.id_metode = m.id_metode
                WHERE t.id_transaksi = ?";

$stmt = mysqli_prepare($con, $queryHeader);
mysqli_stmt_bind_param($stmt, "s", $id);
mysqli_stmt_execute($stmt);
$resultHeader = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($resultHeader);
mysqli_stmt_close($stmt);

if (!$data) {
    die("Error: Data transaksi tidak ditemukan.");
}


$queryDetail = "SELECT d.qty, d.subtotal, d.harga_satuan_saat_ini, p.nama_produk
                FROM detail_transaksi d
                LEFT JOIN produk p ON d.id_produk = p.id_produk
                WHERE d.id_transaksi = ?";

$stmtDetail = mysqli_prepare($con, $queryDetail);
mysqli_stmt_bind_param($stmtDetail, "s", $id);
mysqli_stmt_execute($stmtDetail);
$resultDetail = mysqli_stmt_get_result($stmtDetail);


$subtotalKotor = $data['total_belanja'];
$grandTotal    = $data['total_bayar'];
$diskon        = $subtotalKotor - $grandTotal; 

$formattedDate = date('d F Y, H:i', strtotime($data['tanggal_transaksi']));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Struk Pembayaran - <?= htmlspecialchars($data['id_transaksi']) ?></title>
    <link rel="stylesheet" href="../CSS/struct.css" />
    <link rel="shortcut icon" href="../ASSET/logo-Url.png" />
    <style>
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.9em;
            color: #555;
        }
        .summary-row.final {
            font-weight: bold;
            color: #000;
            font-size: 1.1em;
            margin-top: 10px;
            border-top: 1px dashed #ccc;
            padding-top: 10px;
        }
    </style>
</head>

<body>

    <div class="receipt-card">
        <p class="receipt-title">Struk Pembayaran</p>
        <p class="receipt-sub">Terima kasih telah berbelanja di FFKS MART.</p>
        <p class="receipt-sub">Jl. Melati Indah No. 45, Kelurahan Sukamaju</p>

        <div class="line"></div>

        <div class="info">
            <div class="row">
                <span>ID Transaksi</span>
                <span><?= htmlspecialchars($data['id_transaksi']) ?></span>
            </div>
            <div class="row">
                <span>Metode Pembayaran</span>
                <span><?= htmlspecialchars($data['nama_metode']) ?></span>
            </div>
            <div class="row">
                <span>Status</span>
                <span>
                    <?php 
                        $status = $data['status_pembayaran'];
                       
                        $color = ($status == 'approved') ? 'green' : (($status == 'unpaid') ? 'red' : 'black');
                        echo "<strong style='color:$color'>" . ucfirst($status) . "</strong>";
                    ?>
                </span>
            </div>
            <div class="row">
                <span>Tanggal</span>
                <span><?= $formattedDate ?></span>
            </div>
        </div>

        <div class="section-title">Detail Pembelian</div>
        <div class="items">

            <?php while ($row = mysqli_fetch_assoc($resultDetail)) : ?>
                <div class="item">
                    <span><?= htmlspecialchars($row['nama_produk']) ?> <small>(x<?= $row['qty'] ?>)</small></span>
                    <span>Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></span>
                </div>
            <?php endwhile; ?>

        </div>

        <div class="line"></div>
        
        <div class="summary-section">
            <div class="summary-row">
                <span>Subtotal</span>
                <span>Rp <?= number_format($subtotalKotor, 0, ',', '.') ?></span>
            </div>
            
            <?php if ($diskon > 0): ?>
            <div class="summary-row" style="color: green;">
                <span>Diskon</span>
                <span>- Rp <?= number_format($diskon, 0, ',', '.') ?></span>
            </div>
            <?php endif; ?>
            
            <div class="summary-row final">
                <span>Total Bayar</span>
                <span>Rp <?= number_format($grandTotal, 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="actions">
            <button class="back-btn" onclick="window.location.href='../HTML/index.php'">Kembali ke Beranda</button>
            <button class="back-btn alt" onclick="window.print()">Cetak Struk</button>
        </div>
    </div>

</body>

</html>