<?php
// zalopay_callback.php — ZaloPay gọi ngầm, trừ tồn kho khi thành công
require_once '../includes/db.php';
require_once 'zalopay_config.php';

$raw    = file_get_contents('php://input');
$cbdata = json_decode($raw, true);
$result = ['return_code' => -1, 'return_message' => 'unknown error'];

try {
    // Xác thực MAC bằng KEY2 (server-to-server dùng KEY2)
    $mac = hash_hmac('sha256', $cbdata['data'], ZALOPAY_KEY2);

    if ($mac !== $cbdata['mac']) {
        $result = ['return_code' => -1, 'return_message' => 'mac not equal'];
    } else {
        $payment_data = json_decode($cbdata['data'], true);
        $app_trans_id = $conn->real_escape_string($payment_data['app_trans_id']);
        $zp_trans_id  = $conn->real_escape_string((string)($payment_data['zp_trans_id'] ?? ''));

        // Tìm đơn hàng
        $order = $conn->query(
            "SELECT * FROM orders WHERE app_trans_id='$app_trans_id' LIMIT 1"
        )->fetch_assoc();

        if ($order && $order['payment_status'] !== 'paid') {
            // Trừ tồn kho (callback về trước return URL)
            $items = $conn->query(
                "SELECT product_id, quantity FROM order_details WHERE order_id={$order['id']}"
            );
            while ($item = $items->fetch_assoc()) {
                $conn->query(
                    "UPDATE products
                     SET stock_quantity = stock_quantity - {$item['quantity']}
                     WHERE id = {$item['product_id']} AND stock_quantity >= {$item['quantity']}"
                );
            }

            // Cập nhật đơn hàng
            $conn->query(
                "UPDATE orders
                 SET payment_status = 'paid',
                     zp_trans_id    = '$zp_trans_id',
                     status         = 'confirmed'
                 WHERE id = {$order['id']} AND payment_status != 'paid'"
            );
        }

        $result = ['return_code' => 1, 'return_message' => 'success'];
    }
} catch (Exception $e) {
    $result = ['return_code' => -1, 'return_message' => $e->getMessage()];
}

header('Content-Type: application/json');
echo json_encode($result);