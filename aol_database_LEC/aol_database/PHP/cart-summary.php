<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$payload = json_decode(file_get_contents('php://input'), true);
$cartItems = $payload['cart'] ?? [];

if (!is_array($cartItems)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Payload cart tidak valid.', 'total' => 0]);
    exit;
}

function lookupProduct(mysqli $connection, mysqli_stmt $stmt, string $productId): ?array {
    mysqli_stmt_bind_param($stmt, 's', $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? mysqli_fetch_assoc($result) : null;
}

$total = 0;
$prepared = mysqli_prepare($con, 'SELECT nama_produk, harga FROM produk WHERE id_produk = ?');
$items = [];

foreach ($cartItems as $item) {
    if (empty($item['id'])) {
        continue;
    }
    $quantity = max(intval($item['quantity'] ?? 0), 0);
    if ($quantity <= 0) {
        continue;
    }

    $row = $prepared ? lookupProduct($con, $prepared, $item['id']) : null;
    $price = floatval($item['price'] ?? 0);
    $name = $item['name'] ?? 'Produk tidak diketahui';

    if ($row) {
        $price = floatval($row['harga']);
        $name = $row['nama_produk'];
    }

    $subtotal = $price * $quantity;
    if ($subtotal <= 0) {
        continue;
    }

    $total += $subtotal;

    $items[] = [
        'id' => $item['id'],
        'name' => $name,
        'price' => $price,
        'quantity' => $quantity,
        'subtotal' => $subtotal,
    ];
}

mysqli_stmt_close($prepared);

echo json_encode([
    'success' => true,
    'total' => $total,
    'items' => $items,
]);
