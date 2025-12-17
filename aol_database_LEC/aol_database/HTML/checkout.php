<?php
session_start();


if (!isset($_SESSION['valid'])) {
    header("Location: ../HTML/login.php");
    exit;
}

include("../PHP/config.php");


$metodeDB = mysqli_query($con, "SELECT * FROM metode_pembayaran");


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../CSS/checkout.css" />
    <link rel="shortcut icon" href="../ASSET/logo-Url.png" />
    <title>Checkout</title>
    <style>
        select.form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            background: #fff;
            color: #333;
            outline: none;
        }

        select.form-select:focus {
            border: 1px solid #1eb853;
        }
    </style>
</head>

<body>

    <div class="container">
        <form action="../PHP/checkout-process.php" method="POST" id="checkout-form">
            
            <input type="hidden" name="cart" id="cart-hidden">
            <input type="hidden" name="total" id="total-hidden">
            <input type="hidden" name="nama" id="nama-hidden">

            <div class="row">
                <div class="col">
                    <h3 class="title">Pembayaran</h3>

                    <div class="inputBox">
                        <span>Kartu yang diterima :</span>
                        <div class="margin-card">
                            <img src="../ASSET/bca.png" alt="BCA" />
                            <img src="../ASSET/mandiri.png" alt="Mandiri" />
                            <img src="../ASSET/SeaBank-Logo.webp" alt="SeaBank" />
                        </div>
                    </div>

                    <div class="inputBox">
                        <span>Pilih Bank :</span>
                        <select name="bank" id="bank" class="form-select" required>
                            <?php while ($m = mysqli_fetch_assoc($metodeDB)) : ?>
                                <option value="<?= htmlspecialchars($m['nama_metode']) ?>">
                                    <?= htmlspecialchars($m['nama_metode']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="inputBox">
                        <span>Nama pada kartu (Hanya Huruf) :</span>
                        <input type="text" name="card_name" id="card-name-input" placeholder="Mr. John Doe" required />
                    </div>

                    <div class="inputBox">
                        <span>Nomor Kartu (16 Digit) :</span>
                        <input type="text" id="card-number" placeholder="1111-2222-3333-4444" maxlength="16" inputmode="numeric" required />
                    </div>

                    <div class="inputBox">
                        <span>Exp Month :</span>
                        <select id="exp-month" class="form-select" required>
                            <option value="" disabled selected>Pilih Bulan</option>
                            <option value="01">Januari</option>
                            <option value="02">Februari</option>
                            <option value="03">Maret</option>
                            <option value="04">April</option>
                            <option value="05">Mei</option>
                            <option value="06">Juni</option>
                            <option value="07">Juli</option>
                            <option value="08">Agustus</option>
                            <option value="09">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </div>

                    <div class="flex">
                        <div class="inputBox">
                            <span>Exp Year (4 Digit) :</span>
                            <input type="text" id="exp-year" placeholder="2025" maxlength="4" inputmode="numeric" required />
                        </div>
                        <div class="inputBox">
                            <span>CVV (3 Digit) :</span>
                            <input type="text" id="cvv" placeholder="123" maxlength="3" inputmode="numeric" required />
                        </div>
                    </div>
                </div>

                <div class="col">
                    <h3 class="title">Total Belanja</h3>
                    <ul id="checkout-list" style="list-style: none; padding: 0; margin-bottom: 20px;"></ul>

                    <h3 class="cart-total" style="background: #333; color: white; padding: 10px; text-align: center;">
                        Total: <span id="checkout-total-display">Rp 0</span>
                    </h3>
                </div>
            </div>

            <button type="submit" class="submit-btn" style="width: 100%; padding: 12px; background: #1eb853; color: white; border: none; cursor: pointer; font-size: 1.2rem; margin-top: 10px;">Bayar Sekarang</button>
        </form>
    </div>

    <div id="success-popup" class="popup" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); align-items:center; justify-content:center; z-index:999;">
        <div class="popup-box" style="background:#fff; padding:30px; text-align:center; border-radius:10px; width: 300px;">
            <h2 style="color:#1eb853; margin-bottom:10px;">Berhasil!</h2>
            <p>Pembayaran sukses diproses.</p>
            <div style="margin-top:20px; display:flex; flex-direction:column; gap:10px;">
                <button onclick="viewReceipt()" style="padding:10px; background:#1eb853; color:white; border:none; cursor:pointer;">Lihat Struk</button>
                <button onclick="goHome()" style="padding:10px; background:#ddd; border:none; cursor:pointer;">Kembali ke Home</button>
            </div>
        </div>
    </div>

    <script>
        const formatRp = (num) => 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");

        let cartItems = JSON.parse(localStorage.getItem('cartItems')) || [];
        let grandTotal = 0;

        const listElement = document.getElementById('checkout-list');

        if (cartItems.length === 0) {
            listElement.innerHTML = "<li style='text-align:center; padding:20px;'>Keranjang Kosong</li>";
        } else {
            cartItems.forEach(item => {
                let subtotal = item.price * item.quantity;
                grandTotal += subtotal;

                let li = document.createElement('li');
                li.style.cssText = "border-bottom: 1px solid #ddd; padding: 10px 0; display: flex; justify-content: space-between;";
                li.innerHTML = `<span>${item.quantity}x ${item.name}</span> <span style="font-weight:bold;">${formatRp(subtotal)}</span>`;
                listElement.appendChild(li);
            });
        }

        document.getElementById('checkout-total-display').innerText = formatRp(grandTotal);

        function restrictNumber(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        }

        function restrictLetters(e) {
            this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        }

        document.getElementById('card-number').addEventListener('input', restrictNumber);
        document.getElementById('exp-year').addEventListener('input', restrictNumber);
        document.getElementById('cvv').addEventListener('input', restrictNumber);

        document.getElementById('card-name-input').addEventListener('input', restrictLetters);

        const form = document.getElementById('checkout-form');

        form.addEventListener('submit', function(e) {
            if (cartItems.length === 0) {
                e.preventDefault();
                alert("Keranjang kosong!");
                return;
            }

            const cardNum = document.getElementById('card-number').value;
            const expMonth = document.getElementById('exp-month').value;
            const expYear = document.getElementById('exp-year').value;
            const cvv = document.getElementById('cvv').value;

            if (cardNum.length !== 16) {
                e.preventDefault();
                alert("Nomor Kartu harus 16 digit!");
                return;
            }
            if (expMonth === "") {
                e.preventDefault();
                alert("Silakan pilih Bulan Expired!");
                return;
            }
            if (expYear.length !== 4) {
                e.preventDefault();
                alert("Tahun Exp harus 4 digit (misal: 2025)!");
                return;
            }
            if (cvv.length !== 3) {
                e.preventDefault();
                alert("CVV harus 3 digit!");
                return;
            }

            document.getElementById('cart-hidden').value = JSON.stringify(cartItems);
            document.getElementById('total-hidden').value = grandTotal;
            document.getElementById('nama-hidden').value = document.getElementById('card-name-input').value;
        });

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("success") === "1") {
            document.getElementById("success-popup").style.display = "flex";
            if (urlParams.get("id")) {
                localStorage.setItem("lastOrderId", urlParams.get("id"));
            }
            localStorage.removeItem('cartItems');
        }

        function goHome() {
            window.location.href = "../HTML/index.php";
        }

        function viewReceipt() {
            let id = localStorage.getItem("lastOrderId");
            if (id) window.location.href = "../HTML/struct.php?id=" + id;
        }
    </script>

</body>
</html>