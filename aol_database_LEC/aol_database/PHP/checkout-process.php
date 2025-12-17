<?php
session_start();
require_once __DIR__ . '/config.php'; 


if (!isset($_SESSION['valid'])) {
    die("Error: Anda belum login.");
}

$id_customer = $_SESSION['valid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $cart_json = $_POST['cart'] ?? '[]';
    $cart = json_decode($cart_json, true);
    $bank = $_POST['bank'] ?? 'Transfer Bank';

    if (empty($cart)) {
        die("Error: Data keranjang kosong.");
    }

    
    $id_metode = 1; 
    $stmtMetode = mysqli_prepare($con, "SELECT id_metode FROM metode_pembayaran WHERE nama_metode = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtMetode, "s", $bank);
    mysqli_stmt_execute($stmtMetode);
    $resMetode = mysqli_stmt_get_result($stmtMetode);
    if ($row = mysqli_fetch_assoc($resMetode)) {
        $id_metode = $row['id_metode'];
    }

    
    $total_fix = 0;
    $stmtHarga = mysqli_prepare($con, "SELECT harga FROM produk WHERE id_produk = ?");
    $harga_items = []; 

    foreach ($cart as $item) {
        $id_produk = $item['id'];
        $qty = intval($item['quantity']);

        mysqli_stmt_bind_param($stmtHarga, "s", $id_produk);
        mysqli_stmt_execute($stmtHarga);
        $resHarga = mysqli_stmt_get_result($stmtHarga);

        if ($rowHarga = mysqli_fetch_assoc($resHarga)) {
            $harga_satuan = $rowHarga['harga'];
            $subtotal = $harga_satuan * $qty;
            $total_fix += $subtotal;
            $harga_items[$id_produk] = $harga_satuan; 
        }
    }
    
    if ($total_fix <= 0) {
        die("Error: Total belanja 0.");
    }

    $id_transaksi = "TRX-" . date("ym") . "-" . strtoupper(substr(md5(uniqid()), 0, 6));

    mysqli_begin_transaction($con);
    try {
        
        $sql = "INSERT INTO transaksi (id_transaksi, id_customer, id_metode, tanggal_transaksi, total_belanja, total_bayar, status_pembayaran) 
                VALUES (?, ?, ?, NOW(), ?, ?, 'unpaid')";
        
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "ssidd", $id_transaksi, $id_customer, $id_metode, $total_fix, $total_fix);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal insert header transaksi");
        }

      
        $sqlDetail = "INSERT INTO detail_transaksi (id_transaksi, id_produk, qty, subtotal) VALUES (?, ?, ?, ?)";
        $stmtDetail = mysqli_prepare($con, $sqlDetail);

        foreach ($cart as $item) {
            $id_p = $item['id'];
            $qt = intval($item['quantity']);
            $harga_satuan = isset($harga_items[$id_p]) ? $harga_items[$id_p] : 0;
            $subtotal_item = $harga_satuan * $qt;

            mysqli_stmt_bind_param($stmtDetail, "ssid", $id_transaksi, $id_p, $qt, $subtotal_item);
            
            if (!mysqli_stmt_execute($stmtDetail)) {
               
                 throw new Exception("Masih Gagal: " . mysqli_stmt_error($stmtDetail));
            }
        }

        mysqli_commit($con);
        header("Location: ../HTML/checkout.php?success=1&id=$id_transaksi");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($con);
        die("Gagal memproses: " . $e->getMessage());
    }

} else {
    echo "Halaman ini hanya menerima POST data.";
}
?>