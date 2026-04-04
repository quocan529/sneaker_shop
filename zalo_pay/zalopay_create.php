<?php
// =====================================================
// zalopay_create.php — ĐÃ SỬA redirect dùng APP_URL
// =====================================================
require_once '../includes/db.php';
require_once 'zalopay_config.php';

if (!isLoggedIn()) {
    // BUG FIX: dùng APP_URL để redirect tuyệt đối, không dùng ../login.php
    redirect(APP_URL . APP_PATH . '/../login.php');
}

$order_id = (int)($_GET['order_id'] ?? 0);
$user_id  = $_SESSION['user_id'];

$order = $conn->query("SELECT * FROM orders WHERE id=$order_id AND user_id=$user_id")->fetch_assoc();
if (!$order) {
    redirect(APP_URL . APP_PATH . '/../cart.php');
}

// Tạo app_trans_id: yymmdd_orderid_timestamp
$app_trans_id = date('ymd') . '_' . $order_id . '_' . time();

$app_time   = round(microtime(true) * 1000);
$amount     = (int)$order['total_amount'];
$embed_data = json_encode(['redirecturl' => ZALOPAY_RETURN_URL]);
$items      = json_encode([]);
$desc       = 'SneakerShop - Thanh toan don ' . $order['order_code'];

// Tạo MAC: app_id|app_trans_id|app_user|amount|app_time|embed_data|item
$mac_str = implode('|', [
    ZALOPAY_APP_ID,
    $app_trans_id,
    'user_' . $user_id,
    $amount,
    $app_time,
    $embed_data,
    $items,
]);
$mac = hash_hmac('sha256', $mac_str, ZALOPAY_KEY1);

$payload = [
    'app_id'       => ZALOPAY_APP_ID,
    'app_trans_id' => $app_trans_id,
    'app_user'     => 'user_' . $user_id,
    'app_time'     => $app_time,
    'amount'       => $amount,
    'item'         => $items,
    'description'  => $desc,
    'embed_data'   => $embed_data,
    'bank_code'    => '',
    'callback_url' => ZALOPAY_CALLBACK_URL,
    'mac'          => $mac,
];

// Lưu app_trans_id vào DB
$ats = $conn->real_escape_string($app_trans_id);
$conn->query("UPDATE orders SET app_trans_id='$ats', payment_status='pending' WHERE id=$order_id");

// Gọi API ZaloPay
$ch = curl_init(ZALOPAY_ENDPOINT);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response = curl_exec($ch);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    // BUG FIX: dùng APP_URL tuyệt đối thay vì ../checkout.php
    redirect(APP_URL . APP_PATH . '/../checkout.php?zp_error=' . urlencode('Lỗi kết nối: ' . $curl_err));
}

$result = json_decode($response, true);

if (isset($result['order_url']) && $result['return_code'] == 1) {
    header('Location: ' . $result['order_url']);
    exit;
} else {
    $err = $result['return_message'] ?? 'Không thể tạo đơn ZaloPay';
    redirect(APP_URL . APP_PATH . '/../checkout.php?zp_error=' . urlencode($err));
}